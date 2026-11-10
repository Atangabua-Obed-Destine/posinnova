<?php

namespace App\Http\Controllers;

use App\Business;
use App\Utils\BusinessUtil;
use Illuminate\Http\Request;

/**
 * PosInnova hospital shifts — admin settings.
 *
 * Enable/disable the feature and configure the morning window. The night
 * shift is always the contiguous complement, so it is not stored.
 */
class ShiftSettingController extends Controller
{
    protected $businessUtil;

    public function __construct(BusinessUtil $businessUtil)
    {
        $this->businessUtil = $businessUtil;
    }

    public function index()
    {
        if (! auth()->user()->can('shift_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $settings = $this->businessUtil->getShiftSettings($business_id);

        return view('shift_settings.index')->with(compact('settings'));
    }

    public function update(Request $request)
    {
        if (! auth()->user()->can('shift_settings.access')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $enable = $request->has('enable_shift') ? 1 : 0;
            $start = (string) $request->input('shift_morning_start');
            $end = (string) $request->input('shift_morning_end');

            // HH:MM 24-hour format
            $time_re = '/^([01]\d|2[0-3]):[0-5]\d$/';
            if (! preg_match($time_re, $start) || ! preg_match($time_re, $end)) {
                return redirect()->back()->withInput()
                    ->with('status', ['success' => 0, 'msg' => __('lang_v1.invalid_shift_times')]);
            }

            // Morning must be a single intra-day block so night is well-defined.
            if ($start >= $end) {
                return redirect()->back()->withInput()
                    ->with('status', ['success' => 0, 'msg' => __('lang_v1.morning_end_after_start')]);
            }

            $business_id = $request->session()->get('user.business_id');
            $business = Business::findOrFail($business_id);
            $business->enable_shift = $enable;
            $business->shift_morning_start = $start . ':00';
            $business->shift_morning_end = $end . ':00';
            $business->save();

            // Refresh the cached business so the change takes effect right away.
            $request->session()->put('business', $business);

            $output = ['success' => 1, 'msg' => __('lang_v1.shift_settings_updated')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => 0, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\ShiftSettingController::class, 'index'])
            ->with('status', $output);
    }
}
