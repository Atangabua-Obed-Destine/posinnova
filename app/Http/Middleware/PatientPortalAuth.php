<?php

namespace App\Http\Middleware;

use App\Business;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Guards the external Patient Portal. Requires an authenticated `patient`
 * guard session whose contact is still flagged as a patient. Keeps portal
 * sessions fully separate from the staff `web` guard.
 */
class PatientPortalAuth
{
    public function handle($request, Closure $next)
    {
        $patient = Auth::guard('patient')->user();

        if (! $patient || ! $patient->is_patient) {
            Auth::guard('patient')->logout();

            return redirect()->route('portal.login');
        }

        // Load the patient's business context (name, date/currency format) so
        // shared helpers like @format_date and display_currency work on the
        // portal, which doesn't run the staff SetSessionData middleware.
        if (! $request->session()->has('business') || (int) optional(session('business'))->id !== (int) $patient->business_id) {
            $business = Business::find($patient->business_id);
            if ($business) {
                $request->session()->put('business', $business);
                $currency = $business->currency;
                if ($currency) {
                    $request->session()->put('currency', [
                        'id' => $currency->id,
                        'code' => $currency->code,
                        'symbol' => $currency->symbol,
                        'thousand_separator' => $currency->thousand_separator,
                        'decimal_separator' => $currency->decimal_separator,
                    ]);
                }
            }
        }

        // Make the logged-in patient available to all portal views.
        view()->share('portal_patient', $patient);

        return $next($request);
    }
}
