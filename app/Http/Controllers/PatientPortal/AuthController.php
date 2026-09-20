<?php

namespace App\Http\Controllers\PatientPortal;

use App\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Patient Portal authentication (hospital module).
 *
 * Patients sign in with their FILE NUMBER + MOBILE NUMBER (no password / no OTP
 * for now). On a unique match the contact is logged into the dedicated
 * `patient` guard — fully separate from the staff `web` guard. The flow is
 * structured so an OTP verification step can be inserted later between the
 * match and the guard login without reworking the rest of the portal.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        // Already signed in? Go straight to the portal.
        if (Auth::guard('patient')->check()) {
            return redirect()->route('portal.dashboard');
        }

        return view('patient_portal.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'file_number' => 'required|string',
            'mobile' => 'required|string',
        ]);

        // Simple throttle: 5 attempts / minute per IP+file_number.
        $key = 'portal-login:'.$request->ip().':'.$request->input('file_number');
        if (\RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withInput()->with('login_error', __('lang_v1.portal_too_many_attempts'));
        }
        \RateLimiter::hit($key, 60);

        $file_number = trim($request->input('file_number'));
        $mobile = trim($request->input('mobile'));

        // Match a patient by file_number + mobile. File numbers are unique per
        // business, so require exactly ONE matching patient overall to avoid any
        // cross-tenant ambiguity.
        $matches = Contact::join('patient_details AS pd', 'contacts.id', '=', 'pd.contact_id')
            ->where('contacts.is_patient', 1)
            ->where('contacts.contact_status', '!=', 'inactive')
            ->where('pd.file_number', $file_number)
            ->where('contacts.mobile', $mobile)
            ->select('contacts.*')
            ->get();

        if ($matches->count() !== 1) {
            return back()->withInput()->with('login_error', __('lang_v1.portal_login_failed'));
        }

        $patient = $matches->first();

        \RateLimiter::clear($key);

        // (OTP hook point: send + verify a one-time code here before login.)
        Auth::guard('patient')->login($patient);
        $request->session()->regenerate();

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
}
