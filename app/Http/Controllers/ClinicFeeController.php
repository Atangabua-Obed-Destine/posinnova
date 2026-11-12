<?php

namespace App\Http\Controllers;

use App\ClinicPackage;
use App\Utils\ClinicFees;
use App\Utils\ClinicQueue;
use Illuminate\Http\Request;

/**
 * Doctors' consultation fees (clinic). A table: one row per doctor, one column
 * per consultation package (or one "consultation fee" column for a clinic
 * without packages). An empty box = the clinic's price.
 *
 * clinic_package.manage: every doctor. clinic_fee.manage_own: a doctor, their
 * own row only.
 */
class ClinicFeeController extends Controller
{
    /** [business_id, doctors this user may edit [id => name], may edit all?] */
    private function scope()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        $business_id = request()->session()->get('user.business_id');
        $doctors = ClinicQueue::doctors($business_id);
        $user = auth()->user();
        if ($user->can('clinic_package.manage')) {
            return [$business_id, $doctors, true];
        }
        if ($user->can('clinic_fee.manage_own') && isset($doctors[$user->id])) {
            return [$business_id, [$user->id => $doctors[$user->id]], false];
        }
        abort(403, 'Unauthorized action.');
    }

    /** The columns: [package_key => [label, clinic price]] */
    private function columns($business_id)
    {
        $packages = ClinicPackage::forBusiness($business_id)->active()->perVisit()->ordered()->get();
        if ($packages->isEmpty()) {
            return [0 => [__('clinic.fee_consultation'), ClinicFees::clinicPrice($business_id)]];
        }

        return $packages->mapWithKeys(fn ($p) => [$p->id => [$p->name, (float) $p->price]])->all();
    }

    public function index()
    {
        [$business_id, $doctors, $all] = $this->scope();
        $columns = $this->columns($business_id);
        $fees = ClinicFees::overrides($business_id);

        return view('clinic.fees.index', compact('doctors', 'all', 'columns', 'fees'));
    }

    public function save(Request $request)
    {
        [$business_id, $doctors, $all] = $this->scope();
        $columns = $this->columns($business_id);
        $util = app(\App\Utils\Util::class);
        $input = (array) $request->input('fee', []);

        // read everything first: one bad value and nothing is saved
        $values = [];
        foreach ($doctors as $doctor_id => $name) {
            foreach ($columns as $key => [$label]) {
                $raw = trim((string) ($input[$doctor_id][$key] ?? ''));
                if ($raw === '') {
                    $values[$doctor_id][$key] = null;
                    continue;
                }
                // digits and separators only: num_uf() would quietly turn "abc" into 0
                $price = preg_match('/^\d[\d\s.,\']*$/', $raw) ? $util->num_uf($raw) : null;
                if (! is_numeric($price) || $price < 0 || $price > 999999999) {
                    return redirect()->back()->withInput()->with('status', ['success' => false,
                        'msg' => __('clinic.fee_invalid', ['doctor' => $name, 'column' => $label])]);
                }
                $values[$doctor_id][$key] = (float) $price;
            }
        }
        $changed = 0;
        foreach ($values as $doctor_id => $row) {
            foreach ($row as $key => $price) {
                if (ClinicFees::set($business_id, $doctor_id, $key, $price, auth()->id())) {
                    $changed++;
                }
            }
        }
        if ($changed) {
            $util->activityLog(\App\Business::find($business_id), 'doctor_fees_changed', null, ['changes' => $changed]);
        }

        return redirect()->action([self::class, 'index'])->with('status', ['success' => true,
            'msg' => $changed ? trans_choice('clinic.fee_saved', $changed, ['n' => $changed]) : __('clinic.fee_nothing_changed')]);
    }
}
