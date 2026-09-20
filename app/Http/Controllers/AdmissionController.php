<?php

namespace App\Http\Controllers;

use App\Admission;
use App\AdmissionCaretaker;
use App\Bed;
use App\Contact;
use App\User;
use App\Ward;
use App\WardTransfer;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Inpatient admissions (hospital module): admit a patient to a ward/bed,
 * record caretakers, transfer wards, and discharge (freeing the bed).
 */
class AdmissionController extends Controller
{
    protected $commonUtil;

    protected $moduleUtil;

    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
    }

    private function checkHospitalEnabled()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    public function index()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('admission.view') && ! auth()->user()->can('admission.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = Admission::leftjoin('contacts AS c', 'admissions.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->leftjoin('wards AS w', 'admissions.ward_id', '=', 'w.id')
                ->leftjoin('beds AS b', 'admissions.bed_id', '=', 'b.id')
                ->where('admissions.business_id', $business_id)
                ->select([
                    'admissions.id',
                    'admissions.ref_no',
                    'admissions.admission_date',
                    'admissions.discharge_date',
                    'admissions.status',
                    'c.name as patient_name',
                    'pd.file_number',
                    'w.name as ward_name',
                    'b.bed_number',
                ]);

            if (! empty(request()->input('status'))) {
                $query->where('admissions.status', request()->input('status'));
            }
            if (! empty(request()->input('ward_id'))) {
                $query->where('admissions.ward_id', request()->input('ward_id'));
            }

            return DataTables::of($query)
                ->editColumn('admission_date', '{{ @format_datetime($admission_date) }}')
                ->editColumn('discharge_date', function ($row) {
                    return $row->discharge_date ? \Carbon::parse($row->discharge_date)->format(session('business.date_format', 'd-m-Y').' H:i') : '-';
                })
                ->editColumn('file_number', function ($row) {
                    return $row->file_number ?: '-';
                })
                ->editColumn('bed_number', function ($row) {
                    return $row->bed_number ?: '-';
                })
                ->editColumn('status', function ($row) {
                    $map = ['active' => 'label-success', 'discharged' => 'label-default', 'transferred' => 'label-info', 'absconded' => 'label-danger'];

                    return '<span class="label '.($map[$row->status] ?? 'label-default').'">'.__('lang_v1.admission_status_'.$row->status).'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle"
                            data-toggle="dropdown" aria-expanded="false">'.__('messages.actions').'<span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can('admission.view')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\AdmissionController::class, 'show'], [$row->id]).'"><i class="fas fa-eye"></i> '.__('messages.view').'</a></li>';
                    }
                    if ($row->status == 'active' && auth()->user()->can('admission.discharge')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\AdmissionController::class, 'dischargeForm'], [$row->id]).'"><i class="fas fa-sign-out-alt"></i> '.__('lang_v1.discharge').'</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        $wards = Ward::forDropdown($business_id, true);

        return view('admission.index')->with(compact('wards'));
    }

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('admission.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $wards = Ward::forBusiness($business_id)->active()->with(['beds' => function ($q) {
            $q->where('status', 'available');
        }])->orderBy('name')->get();

        $doctors = User::forDropdown($business_id, false);

        //Optional preselected patient (from patient profile / consultation).
        $preselected_patient = null;
        if ($request->filled('contact_id')) {
            $preselected_patient = Contact::where('business_id', $business_id)->onlyPatients()->with('patientDetails')->find($request->input('contact_id'));
        }
        $visit_id = $request->input('visit_id');

        return view('admission.create')->with(compact('wards', 'doctors', 'preselected_patient', 'visit_id'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('admission.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'contact_id' => 'required',
            'ward_id' => 'required',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            $patient = Contact::where('business_id', $business_id)->onlyPatients()->findOrFail($request->input('contact_id'));
            $ward = Ward::forBusiness($business_id)->findOrFail($request->input('ward_id'));

            //Block double-admitting the same patient.
            $already = Admission::forBusiness($business_id)->active()->where('contact_id', $patient->id)->exists();
            if ($already) {
                return redirect()->back()->withInput()->with('status', [
                    'success' => false,
                    'msg' => __('lang_v1.patient_already_admitted'),
                ]);
            }

            DB::beginTransaction();

            $bed = null;
            if ($request->filled('bed_id')) {
                $bed = Bed::where('business_id', $business_id)->where('ward_id', $ward->id)->find($request->input('bed_id'));
                if ($bed && $bed->status !== 'available') {
                    DB::rollBack();

                    return redirect()->back()->withInput()->with('status', [
                        'success' => false,
                        'msg' => __('lang_v1.bed_not_available'),
                    ]);
                }
            }

            $ref_count = $this->commonUtil->setAndGetReferenceCount('admission', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('admission', $ref_count, $business_id, 'ADM-');

            $admission = Admission::create([
                'business_id' => $business_id,
                'visit_id' => $request->input('visit_id') ?: null,
                'contact_id' => $patient->id,
                'ward_id' => $ward->id,
                'bed_id' => $bed->id ?? null,
                'admitted_by' => $request->session()->get('user.id'),
                'admitting_doctor_id' => $request->input('admitting_doctor_id') ?: null,
                'admission_date' => $request->filled('admission_date') ? $request->input('admission_date') : now(),
                'reason_for_admission' => $request->input('reason_for_admission') ?: null,
                'deposit_amount' => $request->filled('deposit_amount') ? $this->commonUtil->num_uf($request->input('deposit_amount')) : 0,
                'status' => 'active',
                'ref_no' => $ref_no,
            ]);

            if ($bed) {
                $bed->status = 'occupied';
                $bed->save();
            }

            //Mark the originating visit as admitted (if linked).
            if ($admission->visit_id) {
                \App\Visit::where('id', $admission->visit_id)->update(['status' => 'admitted', 'type' => 'inpatient']);
            }

            //Primary caretaker (optional).
            if ($request->filled('caretaker_name')) {
                AdmissionCaretaker::create([
                    'admission_id' => $admission->id,
                    'name' => $request->input('caretaker_name'),
                    'phone' => $request->input('caretaker_phone'),
                    'relationship' => $request->input('caretaker_relationship'),
                    'is_primary' => 1,
                ]);
            }

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.admission_created_success')];

            return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$admission->id])->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('admission.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $admission = Admission::forBusiness($business_id)
            ->with(['patient.patientDetails', 'ward', 'bed', 'admittedBy', 'admittingDoctor', 'caretakers', 'transfers.fromWard', 'transfers.toWard'])
            ->findOrFail($id);

        //Running bill: every hospital fee is a sell transaction on the patient.
        //Show all of this patient's charges since admission (consultation, lab,
        //imaging, ward dispense, etc.) — mirrors the customer-due model.
        $charges = \App\Transaction::where('business_id', $business_id)
            ->where('contact_id', $admission->contact_id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->where('transaction_date', '>=', $admission->admission_date)
            ->orderBy('transaction_date')
            ->get();

        //Patient's total outstanding due (= customer due across all their charges).
        $info = app(\App\Utils\ContactUtil::class)->getContactInfo($business_id, $admission->contact_id);
        $patient_total_due = ($info->total_invoice ?? 0) - ($info->invoice_received ?? 0);

        //Nursing layer: vitals series + KPIs, MAR, notes, ordered drugs (Phase 12).
        $nursing = \App\Http\Controllers\NursingController::nursingData($admission);

        return view('admission.show')->with(compact('admission', 'charges', 'patient_total_due', 'nursing'));
    }

    public function addCaretaker(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('admission.update')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['name' => 'required|string|max:191']);

        try {
            $business_id = $request->session()->get('user.business_id');
            $admission = Admission::forBusiness($business_id)->findOrFail($id);

            $is_primary = $request->boolean('is_primary');
            if ($is_primary) {
                AdmissionCaretaker::where('admission_id', $admission->id)->update(['is_primary' => 0]);
            }

            AdmissionCaretaker::create([
                'admission_id' => $admission->id,
                'name' => $request->input('name'),
                'phone' => $request->input('phone'),
                'relationship' => $request->input('relationship'),
                'is_primary' => $is_primary ? 1 : 0,
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.caretaker_added_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$id])->with('status', $output);
    }

    public function transferWard(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('ward_transfer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['to_ward_id' => 'required']);

        try {
            $business_id = $request->session()->get('user.business_id');
            $admission = Admission::forBusiness($business_id)->findOrFail($id);

            if ($admission->status !== 'active') {
                return redirect()->back()->with('status', ['success' => false, 'msg' => __('lang_v1.admission_not_active')]);
            }

            $to_ward = Ward::forBusiness($business_id)->findOrFail($request->input('to_ward_id'));

            DB::beginTransaction();

            $from_ward_id = $admission->ward_id;
            $from_bed_id = $admission->bed_id;

            //Free the old bed.
            if ($from_bed_id) {
                Bed::where('id', $from_bed_id)->update(['status' => 'available']);
            }

            //Occupy the new bed (if selected).
            $to_bed = null;
            if ($request->filled('to_bed_id')) {
                $to_bed = Bed::where('business_id', $business_id)->where('ward_id', $to_ward->id)->find($request->input('to_bed_id'));
                if ($to_bed && $to_bed->status === 'available') {
                    $to_bed->status = 'occupied';
                    $to_bed->save();
                } else {
                    $to_bed = null;
                }
            }

            $admission->ward_id = $to_ward->id;
            $admission->bed_id = $to_bed->id ?? null;
            $admission->save();

            WardTransfer::create([
                'business_id' => $business_id,
                'admission_id' => $admission->id,
                'from_ward_id' => $from_ward_id,
                'to_ward_id' => $to_ward->id,
                'from_bed_id' => $from_bed_id,
                'to_bed_id' => $to_bed->id ?? null,
                'transferred_by' => $request->session()->get('user.id'),
                'transfer_date' => now(),
                'reason' => $request->input('reason') ?: null,
            ]);

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.ward_transfer_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$id])->with('status', $output);
    }

    public function dischargeForm($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('admission.discharge')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $admission = Admission::forBusiness($business_id)->with(['patient.patientDetails', 'ward', 'bed'])->findOrFail($id);

        if ($admission->status !== 'active') {
            return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$id]);
        }

        $info = app(\App\Utils\ContactUtil::class)->getContactInfo($business_id, $admission->contact_id);
        $patient_total_due = ($info->total_invoice ?? 0) - ($info->invoice_received ?? 0);

        return view('admission.discharge')->with(compact('admission', 'patient_total_due'));
    }

    public function discharge(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('admission.discharge')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $admission = Admission::forBusiness($business_id)->findOrFail($id);

            if ($admission->status !== 'active') {
                return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$id]);
            }

            DB::beginTransaction();

            $admission->status = 'discharged';
            $admission->discharge_date = $request->filled('discharge_date') ? $request->input('discharge_date') : now();
            $admission->discharge_authorized_by = $request->session()->get('user.id');
            $admission->discharge_notes = $request->input('discharge_notes') ?: null;
            $admission->save();

            //Free the bed.
            if ($admission->bed_id) {
                Bed::where('id', $admission->bed_id)->update(['status' => 'available']);
            }

            //Close the linked visit.
            if ($admission->visit_id) {
                \App\Visit::where('id', $admission->visit_id)->where('status', '!=', 'closed')
                    ->update(['status' => 'closed', 'closed_by' => $request->session()->get('user.id'), 'closed_at' => now()]);
            }

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.discharge_success')];

            return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$id])->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->with('status', [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }
}
