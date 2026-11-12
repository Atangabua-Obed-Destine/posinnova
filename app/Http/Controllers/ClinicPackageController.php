<?php

namespace App\Http\Controllers;

use App\ClinicPackage;
use App\Utils\ClinicServices;
use App\Visit;
use DB;
use Illuminate\Http\Request;

/**
 * Consultation packages (clinic phase 2): Standard, VIP, ... with a price and
 * what each one covers. Reception picks one at check-in and the patient is
 * billed its price on a real invoice line. Managed by anyone whose role has
 * clinic_package.manage. No discounts: the price is the price.
 */
class ClinicPackageController extends Controller
{
    private function authorizeAccess()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        if (! auth()->user()->can('clinic_package.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index()
    {
        $this->authorizeAccess();

        $business_id = request()->session()->get('user.business_id');

        $packages = ClinicPackage::forBusiness($business_id)->ordered()->get();
        $usage = Visit::where('business_id', $business_id)
            ->whereNotNull('package_id')
            ->groupBy('package_id')
            ->select('package_id', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'package_id');

        return view('clinic.packages.index', compact('packages', 'usage'));
    }

    public function create()
    {
        $this->authorizeAccess();
        $package = new ClinicPackage(['billing' => 'per_visit', 'is_active' => 1, 'sort_order' => 0]);

        return view('clinic.packages.form', compact('package'));
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();
        $data = $this->validated($request);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        DB::beginTransaction();
        try {
            $package = ClinicPackage::create($data + ['business_id' => $business_id, 'created_by' => $user_id]);
            ClinicServices::forPackage($package, $user_id);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'index'])
            ->with('status', ['success' => true, 'msg' => __('clinic.package_saved')]);
    }

    public function edit($id)
    {
        $this->authorizeAccess();
        $package = ClinicPackage::forBusiness(request()->session()->get('user.business_id'))->findOrFail($id);

        return view('clinic.packages.form', compact('package'));
    }

    public function update(Request $request, $id)
    {
        $this->authorizeAccess();
        $data = $this->validated($request);

        $business_id = $request->session()->get('user.business_id');
        $package = ClinicPackage::forBusiness($business_id)->findOrFail($id);

        // a monthly plan patients are on cannot become a per-visit package
        if ($package->billing == 'monthly' && $data['billing'] != 'monthly' && $this->activePlans($package) > 0) {
            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('clinic.package_has_plans', ['n' => $this->activePlans($package)])]);
        }

        DB::beginTransaction();
        try {
            $package->update($data);
            ClinicServices::forPackage($package, $request->session()->get('user.id'));
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'index'])
            ->with('status', ['success' => true, 'msg' => __('clinic.package_saved')]);
    }

    /** Remove a package. Past visits and invoices keep pointing at it. */
    public function destroy($id)
    {
        $this->authorizeAccess();

        $package = ClinicPackage::forBusiness(request()->session()->get('user.business_id'))->findOrFail($id);
        if ($this->activePlans($package) > 0) {
            return redirect()->action([self::class, 'index'])
                ->with('status', ['success' => false, 'msg' => __('clinic.package_has_plans', ['n' => $this->activePlans($package)])]);
        }
        ClinicServices::retirePackage($package);
        $package->delete();

        return redirect()->action([self::class, 'index'])
            ->with('status', ['success' => true, 'msg' => __('clinic.package_deleted')]);
    }

    /** Patients currently on this package as a monthly plan. */
    private function activePlans(ClinicPackage $package)
    {
        return \App\ClinicSubscription::where('package_id', $package->id)->where('status', 'active')->count();
    }

    private function validated(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'price' => 'required|string|max:30',
            'billing' => 'required|in:per_visit,monthly',
            'visits_per_month' => 'nullable|integer|min:1|max:999',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'cover_mode' => 'nullable|in:none,days,month,calendar_month',
            'cover_days' => 'nullable|required_if:cover_mode,days|integer|min:1|max:366',
            'cover_max_visits' => 'nullable|integer|min:1|max:99',
        ], [], ['cover_days' => __('clinic.cover_days')]);
        // return visits covered by the fee: only for a package paid per visit
        $cover = $request->input('billing') == 'per_visit' ? ($request->input('cover_mode') ?: 'none') : 'none';

        $price = app(\App\Utils\Util::class)->num_uf($request->input('price'));
        if ($price < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['price' => __('clinic.price_not_negative')]);
        }

        return [
            'name' => trim($request->input('name')),
            'description' => $request->input('description') ?: null,
            'price' => $price,
            'billing' => $request->input('billing'),
            'visits_per_month' => $request->input('billing') == 'monthly' && $request->filled('visits_per_month')
                ? (int) $request->input('visits_per_month') : null,
            'is_active' => $request->boolean('is_active') ? 1 : 0,
            'sort_order' => (int) $request->input('sort_order', 0),
            'cover_mode' => $cover,
            'cover_days' => $cover == 'days' ? (int) $request->input('cover_days') : null,
            'cover_max_visits' => $cover != 'none' && $request->filled('cover_max_visits') ? (int) $request->input('cover_max_visits') : null,
            'cover_same_doctor' => $cover != 'none' && $request->boolean('cover_same_doctor') ? 1 : 0,
        ];
    }
}
