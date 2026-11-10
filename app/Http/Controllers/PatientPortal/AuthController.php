<?php

namespace App\Http\Controllers\PatientPortal;

use App\Contact;
use App\Http\Controllers\Controller;
use App\Utils\ClinicSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Patient Portal authentication (hospital module).
 *
 * Patients sign in with their FILE NUMBER + a PIN that reception sets (4 to 6
 * digits, stored hashed in patient_details.portal_pin). The portal faces the
 * internet through the clinic's tunnel, so:
 *  - 5 wrong PINs in a row lock that file's sign-in for 15 minutes;
 *  - one address is throttled to 20 attempts a minute across all files;
 *  - every attempt, good or bad, is written to patient_portal_logins;
 *  - the business can switch the portal off in Clinic settings.
 * On success the contact is logged into the dedicated `patient` guard, fully
 * separate from the staff `web` guard.
 */
class AuthController extends Controller
{
    const MAX_FAILED = 5;

    const LOCK_MINUTES = 15;

    public function showLogin()
    {
        if (Auth::guard('patient')->check()) {
            return redirect()->route('portal.dashboard');
        }

        return view('patient_portal.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'file_number' => 'required|string|max:50',
            'pin' => 'required|string|max:12',
        ]);

        $file_number = trim($request->input('file_number'));
        $pin = trim($request->input('pin'));

        // Per-address throttle, whatever file number is tried.
        $ip_key = 'portal-login-ip:'.$request->ip();
        if (\RateLimiter::tooManyAttempts($ip_key, 20)) {
            $this->log(null, null, $file_number, false, 'ip_throttled');

            return back()->withInput($request->only('file_number'))->with('login_error', __('lang_v1.portal_too_many_attempts'));
        }
        \RateLimiter::hit($ip_key, 60);

        // File numbers are unique per business: require exactly ONE active
        // patient with this file number to avoid any cross-tenant ambiguity.
        $matches = Contact::join('patient_details AS pd', 'contacts.id', '=', 'pd.contact_id')
            ->where('contacts.is_patient', 1)
            ->where('contacts.contact_status', '!=', 'inactive')
            ->whereNull('contacts.deleted_at')
            ->where('pd.file_number', $file_number)
            ->select('contacts.*', 'pd.id as pd_id', 'pd.portal_pin', 'pd.portal_failed_attempts', 'pd.portal_locked_until')
            ->get();

        if ($matches->count() !== 1) {
            $this->log(null, null, $file_number, false, 'no_match');

            return $this->fail($request, __('clinic.portal_pin_login_failed'));
        }

        $patient = $matches->first();

        if (! ClinicSettings::get($patient->business_id, 'portal_enabled')) {
            $this->log($patient->business_id, $patient->id, $file_number, false, 'portal_disabled');

            return $this->fail($request, __('clinic.portal_disabled'));
        }

        if (! empty($patient->portal_locked_until) && \Carbon::parse($patient->portal_locked_until)->isFuture()) {
            $this->log($patient->business_id, $patient->id, $file_number, false, 'locked');

            return $this->fail($request, __('clinic.portal_locked', ['time' => \Carbon::parse($patient->portal_locked_until)->format('H:i')]));
        }

        if (empty($patient->portal_pin)) {
            $this->log($patient->business_id, $patient->id, $file_number, false, 'no_pin');

            return $this->fail($request, __('clinic.portal_no_pin'));
        }

        if (! Hash::check($pin, $patient->portal_pin)) {
            $failed = (int) $patient->portal_failed_attempts + 1;
            $update = ['portal_failed_attempts' => $failed];
            $msg = __('clinic.portal_pin_login_failed');
            if ($failed >= self::MAX_FAILED) {
                $until = now()->addMinutes(self::LOCK_MINUTES);
                $update = ['portal_failed_attempts' => 0, 'portal_locked_until' => $until];
                $msg = __('clinic.portal_locked', ['time' => $until->format('H:i')]);
            } else {
                $msg .= ' '.__('clinic.portal_tries_left', ['count' => self::MAX_FAILED - $failed]);
            }
            DB::table('patient_details')->where('id', $patient->pd_id)->update($update);
            $this->log($patient->business_id, $patient->id, $file_number, false, $failed >= self::MAX_FAILED ? 'wrong_pin_locked' : 'wrong_pin');

            return $this->fail($request, $msg);
        }

        DB::table('patient_details')->where('id', $patient->pd_id)->update(['portal_failed_attempts' => 0, 'portal_locked_until' => null]);
        $this->log($patient->business_id, $patient->id, $file_number, true, null);

        Auth::guard('patient')->login(Contact::find($patient->id));
        $request->session()->regenerate();
        $request->session()->put('portal_last_activity', time());

        return redirect()->route('portal.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::guard('patient')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }

    /**
     * Set the portal locale. Writes the same session key the Language
     * middleware reads (`user.language`), so the choice persists per session
     * without touching the contacts table.
     */
    public function setLocale(Request $request, $locale)
    {
        if (in_array($locale, ['en', 'fr'], true)) {
            $request->session()->put('user.language', $locale);
        }

        return redirect()->back();
    }

    private function fail(Request $request, $message)
    {
        return back()->withInput($request->only('file_number'))->with('login_error', $message);
    }

    private function log($business_id, $contact_id, $file_number, $success, $reason)
    {
        try {
            DB::table('patient_portal_logins')->insert([
                'business_id' => $business_id,
                'contact_id' => $contact_id,
                'file_number' => mb_substr((string) $file_number, 0, 50),
                'ip' => request()->ip(),
                'success' => $success ? 1 : 0,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('portal login log failed: '.$e->getMessage());
        }
    }
}
