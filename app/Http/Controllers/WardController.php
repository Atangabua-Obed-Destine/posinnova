<?php

namespace App\Http\Controllers;

use App\Admission;
use App\Bed;
use App\BusinessLocation;
use App\Department;
use App\Ward;
use App\Utils\ModuleUtil;
use DB;
use Illuminate\Http\Request;

/**
 * Wards & beds (hospital module). Provides ward CRUD, automatic bed generation,
 * and a visual bed-occupancy overview.
 */
class WardController extends Controller
{
    protected $moduleUtil;

    public function __construct(ModuleUtil $moduleUtil)
    {
        $this->moduleUtil = $moduleUtil;
    }

    private function checkHospitalEnabled()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        //PosInnova: section switched off in Clinic settings
        if (! \App\Utils\ClinicSettings::sectionOn(request()->session()->get('user.business_id'), 'wards')) {
            abort(404);
        }
    }

    /**
     * The department_type enum values, labelled for dropdowns. Shared by the
     * department forms (Phase 6 location mapping).
     */
    public static function departmentTypes()
    {
        $types = ['pharmacy', 'opd', 'lab', 'radiology', 'maternity', 'surgery', 'paediatrics', 'ward', 'morgue', 'physiotherapy', 'dental', 'admin', 'other'];
        $list = [];
        foreach ($types as $t) {
            $list[$t] = __('lang_v1.dept_type_'.$t);
        }

        return $list;
    }

    public function index()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('ward.manage') && ! auth()->user()->can('admission.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $wards = Ward::forBusiness($business_id)
            ->with(['location', 'beds'])
            ->withCount(['activeAdmissions'])
            ->orderBy('name')
            ->get();

        //Active admissions keyed by bed for occupant labels in the grid.
        $occupants = Admission::forBusiness($business_id)
            ->active()
            ->with('patient')
            ->get()
            ->keyBy('bed_id');

        return view('ward.index')->with(compact('wards', 'occupants'));
    }

    public function create()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('ward.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $business_locations = BusinessLocation::forDropdown($business_id, true);
        $departments = Department::forDropdown($business_id, true);

        return view('ward.create')->with(compact('business_locations', 'departments'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('ward.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:191',
            'total_beds' => 'nullable|integer|min:0|max:500',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            DB::beginTransaction();

            $ward = Ward::create([
                'business_id' => $business_id,
                'name' => $request->input('name'),
                'location_id' => $request->input('location_id') ?: null,
                'department_id' => $request->input('department_id') ?: null,
                'total_beds' => (int) $request->input('total_beds', 0),
                'description' => $request->input('description') ?: null,
                'is_active' => 1,
                'created_by' => $request->session()->get('user.id'),
            ]);

            //Auto-generate beds numbered <prefix>-01.. when a count is given.
            $this->syncBeds($ward, (int) $request->input('total_beds', 0), $request->input('bed_prefix'));

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.ward_added_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\WardController::class, 'index'])->with('status', $output);
    }

    public function edit($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('ward.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $ward = Ward::forBusiness($business_id)->with('beds')->findOrFail($id);
        $business_locations = BusinessLocation::forDropdown($business_id, true);
        $departments = Department::forDropdown($business_id, true);

        return view('ward.edit')->with(compact('ward', 'business_locations', 'departments'));
    }

    public function update(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('ward.manage')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'name' => 'required|string|max:191',
            'total_beds' => 'nullable|integer|min:0|max:500',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');
            $ward = Ward::forBusiness($business_id)->findOrFail($id);

            DB::beginTransaction();

            $ward->fill([
                'name' => $request->input('name'),
                'location_id' => $request->input('location_id') ?: null,
                'department_id' => $request->input('department_id') ?: null,
                'total_beds' => (int) $request->input('total_beds', 0),
                'description' => $request->input('description') ?: null,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);
            $ward->save();

            $this->syncBeds($ward, (int) $request->input('total_beds', 0), $request->input('bed_prefix'));

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.ward_updated_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\WardController::class, 'index'])->with('status', $output);
    }

    public function destroy($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('ward.manage')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $ward = Ward::forBusiness($business_id)->findOrFail($id);

            //Block delete if there are (ever) admissions referencing this ward.
            $in_use = Admission::forBusiness($business_id)->where('ward_id', $id)->exists();
            if ($in_use) {
                return ['success' => false, 'msg' => __('lang_v1.ward_in_use_cannot_delete')];
            }

            Bed::where('ward_id', $ward->id)->delete();
            $ward->delete();

            $output = ['success' => true, 'msg' => __('lang_v1.ward_deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /**
     * Reconcile the beds of a ward to the requested count. Grows by appending
     * new numbered beds; shrinking only removes trailing *available* beds so an
     * occupied bed is never silently deleted.
     */
    private function syncBeds(Ward $ward, $target, $prefix = null)
    {
        $prefix = trim((string) $prefix);
        $existing = Bed::where('ward_id', $ward->id)->orderBy('id')->get();
        $current = $existing->count();

        if ($target > $current) {
            for ($i = $current + 1; $i <= $target; $i++) {
                $number = ($prefix !== '' ? $prefix.'-' : '').str_pad($i, 2, '0', STR_PAD_LEFT);
                //Guard against unique collisions if a prefix change overlaps.
                $exists = Bed::where('ward_id', $ward->id)->where('bed_number', $number)->exists();
                if ($exists) {
                    $number = $number.'-'.$i;
                }
                Bed::create([
                    'business_id' => $ward->business_id,
                    'ward_id' => $ward->id,
                    'bed_number' => $number,
                    'status' => 'available',
                ]);
            }
        } elseif ($target < $current) {
            //Remove only trailing available beds.
            $removable = $existing->where('status', 'available')->reverse()->values();
            $to_remove = $current - $target;
            foreach ($removable as $bed) {
                if ($to_remove <= 0) {
                    break;
                }
                $bed->delete();
                $to_remove--;
            }
        }
    }
}
