<?php

namespace App\Http\Controllers;

use App\ClinicInsurer;
use App\ClinicInsuranceShare;
use App\PatientDetail;
use App\Utils\ClinicInsurance;
use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Insurers (clinic phase 5): the companies whose share of a patient's charges
 * is billed to them. Each has its own customer account, so what it owes shows
 * on the usual customer screens too. Needs clinic_insurance.manage.
 */
class ClinicInsurerController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    public static function authorizeInsurance()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        if (! auth()->user()->can('clinic_insurance.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        self::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');

        $insurers = ClinicInsurer::forBusiness($business_id)->orderByDesc('is_active')->orderBy('name')->get();
        $patients = PatientDetail::where('business_id', $business_id)->whereNotNull('insurer_id')
            ->groupBy('insurer_id')->select('insurer_id', DB::raw('COUNT(*) as n'))->pluck('n', 'insurer_id');
        $balances = ClinicInsurance::balances($business_id);

        return view('clinic.insurance.insurers', compact('insurers', 'patients', 'balances'));
    }

    public function create()
    {
        self::authorizeInsurance();

        return view('clinic.insurance.insurer_form', ['insurer' => new ClinicInsurer(['is_active' => 1])]);
    }

    public function store(Request $request)
    {
        self::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $data = $this->validated($request, $business_id);

        try {
            DB::beginTransaction();
            $insurer = ClinicInsurance::createInsurer($business_id, $data, $request->session()->get('user.id'));
            $this->commonUtil->activityLog($insurer, 'added', null, ['name' => $insurer->name]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'index'])->with('status', ['success' => true, 'msg' => __('clinic.ins_insurer_saved')]);
    }

    public function edit(Request $request, $id)
    {
        self::authorizeInsurance();
        $insurer = ClinicInsurer::forBusiness($request->session()->get('user.business_id'))->findOrFail($id);

        return view('clinic.insurance.insurer_form', compact('insurer'));
    }

    public function update(Request $request, $id)
    {
        self::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $insurer = ClinicInsurer::forBusiness($business_id)->findOrFail($id);
        $data = $this->validated($request, $business_id, $insurer->id);

        try {
            DB::beginTransaction();
            ClinicInsurance::updateInsurer($insurer, $data);
            $this->commonUtil->activityLog($insurer, 'edited', null, ['name' => $insurer->name]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'index'])->with('status', ['success' => true, 'msg' => __('clinic.ins_insurer_saved')]);
    }

    /**
     * Removes an insurer nobody uses yet; one with charges or patients can
     * only be switched off (its history stays).
     */
    public function destroy(Request $request, $id)
    {
        self::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $insurer = ClinicInsurer::forBusiness($business_id)->findOrFail($id);
        $back = redirect()->action([self::class, 'index']);

        if (ClinicInsuranceShare::where('insurer_id', $insurer->id)->exists()
            || PatientDetail::where('business_id', $business_id)->where('insurer_id', $insurer->id)->exists()) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_insurer_in_use')]);
        }
        $insurer->delete();

        return $back->with('status', ['success' => true, 'msg' => __('clinic.ins_insurer_deleted')]);
    }

    private function validated(Request $request, $business_id, $ignore_id = null)
    {
        $request->validate([
            'name' => 'required|max:191',
            'phone' => 'nullable|max:60',
            'email' => 'nullable|email|max:191',
            'address' => 'nullable|max:1000',
            'notes' => 'nullable|max:2000',
        ]);
        $name = trim((string) $request->input('name'));
        $dup = ClinicInsurer::forBusiness($business_id)->where('name', $name)
            ->when($ignore_id, fn ($q) => $q->where('id', '!=', $ignore_id))->exists();
        if ($dup) {
            throw \Illuminate\Validation\ValidationException::withMessages(['name' => __('clinic.ins_insurer_exists')]);
        }
        $pct = $request->filled('default_pct') ? (float) $this->commonUtil->num_uf($request->input('default_pct')) : null;
        if (! is_null($pct) && ($pct < 0 || $pct > 100)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['default_pct' => __('clinic.ins_pct_range')]);
        }

        return [
            'name' => $name,
            'default_pct' => $pct,
            'phone' => trim((string) $request->input('phone')) ?: null,
            'email' => trim((string) $request->input('email')) ?: null,
            'address' => trim((string) $request->input('address')) ?: null,
            'notes' => trim((string) $request->input('notes')) ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
