<?php

namespace App\Http\Controllers;

use App\Business;
use App\Utils\ClinicSettings;
use Illuminate\Http\Request;

/**
 * Clinic settings page (hospital module): how this clinic runs. Values live in
 * business.hospital_settings through App\Utils\ClinicSettings; the file number
 * prefix and the default consultation / morgue fees keep their own business
 * columns.
 */
class ClinicSettingsController extends Controller
{
    private function authorizeAccess()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        if (! auth()->user()->can('clinic_settings.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index()
    {
        $this->authorizeAccess();

        $business_id = request()->session()->get('user.business_id');
        $business = Business::findOrFail($business_id, ['id', 'hospital_file_prefix', 'hospital_file_counter', 'consultation_fee', 'morgue_daily_fee']);
        $settings = ClinicSettings::all($business_id);

        $prefix = $business->hospital_file_prefix ?: 'PMI';
        $next_file_number = $prefix.'-'.str_pad(($business->hospital_file_counter ?? 0) + 1, 4, '0', STR_PAD_LEFT);

        $admins = \App\Utils\ClinicQueue::admins($business_id);

        return view('clinic.settings', compact('business', 'settings', 'next_file_number', 'admins'));
    }

    public function update(Request $request)
    {
        $this->authorizeAccess();

        $request->validate([
            'hospital_file_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'consultation_fee' => 'nullable|string|max:30',
            'morgue_daily_fee' => 'nullable|string|max:30',
            'plan_fixed_day' => 'nullable|integer|min:1|max:28',
            'plan_grace_days' => 'nullable|integer|min:0|max:60',
            'portal_idle_minutes' => 'nullable|integer|min:5|max:240',
        ]);

        $business_id = $request->session()->get('user.business_id');

        $update = ['hospital_file_prefix' => strtoupper($request->input('hospital_file_prefix'))];
        foreach (['consultation_fee', 'morgue_daily_fee'] as $fee) {
            if ($request->has($fee)) {
                $update[$fee] = $this->num_uf($request->input($fee));
            }
        }
        Business::where('id', $business_id)->update($update);

        $input = $request->only([
            'pay_first', 'plan_billing_anchor', 'plan_fixed_day', 'plan_grace_days', 'plan_unpaid_rule', 'insurance_covers_plans',
            'sections', 'portal_enabled', 'portal_idle_minutes', 'portal_consultation_detail',
        ]);
        // only real Admins of this business can be ticked as also seeing patients
        $input['admin_doctors'] = array_values(array_intersect(
            array_map('intval', (array) $request->input('admin_doctors', [])),
            array_keys(\App\Utils\ClinicQueue::admins($business_id))
        ));
        ClinicSettings::save($business_id, $input);

        return redirect()->action([self::class, 'index'])
            ->with('status', ['success' => 1, 'msg' => __('clinic.settings_saved')]);
    }

    private function num_uf($value)
    {
        return app(\App\Utils\Util::class)->num_uf($value ?? 0);
    }
}
