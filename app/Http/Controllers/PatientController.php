<?php

namespace App\Http\Controllers;

use App\Business;
use App\Contact;
use App\PatientDetail;
use App\Transaction;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ContactCreatedOrModified;

/**
 * Patients (hospital module).
 *
 * A patient is an ordinary customer contact (type='customer', is_patient=1)
 * with a 1:1 patient_details row holding the clinical fields. This controller
 * reuses ContactUtil for the contact side and manages patient_details + the
 * auto-generated file number alongside.
 *
 * Every action is gated by `hospital` being in enabled_modules (the middleware
 * group) plus the granular patient.* permission.
 */
class PatientController extends Controller
{
    protected $commonUtil;

    protected $contactUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    public function __construct(
        Util $commonUtil,
        ModuleUtil $moduleUtil,
        TransactionUtil $transactionUtil,
        ContactUtil $contactUtil
    ) {
        $this->commonUtil = $commonUtil;
        $this->contactUtil = $contactUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Ensure the hospital module is enabled, else 404.
     */
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

        if (! auth()->user()->can('patient.view') && ! auth()->user()->can('patient.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = Contact::leftjoin('patient_details AS pd', 'contacts.id', '=', 'pd.contact_id')
                ->where('contacts.business_id', $business_id)
                ->onlyPatients()
                ->select([
                    'contacts.id',
                    'contacts.name',
                    'contacts.mobile',
                    'contacts.contact_status',
                    'contacts.is_default',
                    'pd.file_number',
                    'pd.sex',
                    'pd.blood_group',
                    'pd.date_of_birth',
                    'pd.insurance_provider',
                ]);

            if (! empty(request()->input('contact_status'))) {
                $query->where('contacts.contact_status', request()->input('contact_status'));
            }

            if (! empty(request()->input('sex'))) {
                $query->where('pd.sex', request()->input('sex'));
            }

            return DataTables::of($query)
                ->addColumn('age', function ($row) {
                    if (empty($row->date_of_birth)) {
                        return '';
                    }

                    return \Carbon::parse($row->date_of_birth)->age;
                })
                ->editColumn('name', function ($row) {
                    $name = e($row->name);
                    if ($row->contact_status == 'inactive') {
                        $name .= ' <small class="label pull-right bg-red no-print">'.__('lang_v1.inactive').'</small>';
                    }

                    return $name;
                })
                ->editColumn('file_number', function ($row) {
                    return $row->file_number ?: '<span class="text-muted">-</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle"
                            data-toggle="dropdown" aria-expanded="false">'.
                            __('messages.actions').
                            '<span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can('patient.view')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PatientController::class, 'show'], [$row->id]).'"><i class="fas fa-eye" aria-hidden="true"></i> '.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('patient.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PatientController::class, 'edit'], [$row->id]).'" class="edit_patient_button"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</a></li>';
                    }
                    if (! $row->is_default && auth()->user()->can('patient.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\PatientController::class, 'destroy'], [$row->id]).'" class="delete_patient_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->removeColumn('id')
                ->removeColumn('is_default')
                ->removeColumn('contact_status')
                ->removeColumn('date_of_birth')
                ->rawColumns(['name', 'file_number', 'action'])
                ->make(true);
        }

        return view('patient.index');
    }

    public function create()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('patient.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\PatientController::class, 'index']));
        }

        $business = Business::find($business_id);
        $next_file_number = $this->previewNextFileNumber($business);

        return view('patient.create')
            ->with(compact('next_file_number'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('patient.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            $input = $request->only([
                'prefix', 'first_name', 'middle_name', 'last_name', 'mobile',
                'landline', 'alternate_number', 'email', 'city', 'state', 'country',
                'address_line_1', 'address_line_2', 'zip_code',
            ]);

            $name_array = array_filter([
                $input['prefix'] ?? null,
                $input['first_name'] ?? null,
                $input['middle_name'] ?? null,
                $input['last_name'] ?? null,
            ]);
            $input['name'] = trim(implode(' ', $name_array));

            $input['type'] = 'customer';
            $input['is_patient'] = 1;
            $input['contact_type'] = 'individual';
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');

            DB::beginTransaction();

            $output = $this->contactUtil->createNewContact($input);
            $contact = $output['data'];

            //Generate file number atomically and create the clinical row.
            $file_number = $this->generateFileNumber($business_id);

            PatientDetail::create([
                'contact_id' => $contact->id,
                'business_id' => $business_id,
                'file_number' => $file_number,
                'date_of_birth' => $request->filled('date_of_birth') ? $request->input('date_of_birth') : null,
                'sex' => $request->input('sex') ?: null,
                'blood_group' => $request->input('blood_group') ?: null,
                'known_allergies' => $request->input('known_allergies') ?: null,
                'next_of_kin_name' => $request->input('next_of_kin_name') ?: null,
                'next_of_kin_phone' => $request->input('next_of_kin_phone') ?: null,
                'next_of_kin_relationship' => $request->input('next_of_kin_relationship') ?: null,
                'insurance_provider' => $request->input('insurance_provider') ?: null,
                'insurance_number' => $request->input('insurance_number') ?: null,
                'insurance_coverage_pct' => $request->filled('insurance_coverage_pct') ? $this->commonUtil->num_uf($request->input('insurance_coverage_pct')) : null,
                'insurance_expiry_date' => $request->filled('insurance_expiry_date') ? $request->input('insurance_expiry_date') : null,
                'notes' => $request->input('notes') ?: null,
            ]);

            event(new ContactCreatedOrModified($input, 'added'));
            $this->contactUtil->activityLog($contact, 'added');

            DB::commit();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.patient_added_success'),
                'id' => $contact->id,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if ($request->ajax()) {
            return $output;
        }

        return redirect()
            ->action([\App\Http\Controllers\PatientController::class, 'index'])
            ->with('status', $output);
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('patient.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $contact = Contact::where('business_id', $business_id)
            ->onlyPatients()
            ->with(['patientDetails', 'patientDetails.mother'])
            ->findOrFail($id);

        $contact_info = $this->contactUtil->getContactInfo($business_id, $id);

        $view_type = request()->get('view', 'info');

        $activities = Activity::forSubject($contact)
            ->with(['causer', 'subject'])
            ->latest()
            ->get();

        //Hospital module data (visits + prescriptions) — only when those tables/relations exist.
        $visits = \App\Visit::forBusiness($business_id)
            ->where('contact_id', $contact->id)
            ->with('location')
            ->orderBy('visit_date', 'desc')
            ->get();

        $prescriptions = \App\ConsultationPrescription::whereHas('consultation', function ($q) use ($business_id, $contact) {
            $q->where('business_id', $business_id)->where('contact_id', $contact->id);
        })
            ->with(['product', 'variation', 'consultation'])
            ->orderBy('created_at', 'desc')
            ->get();

        $lab_requests = \App\LabRequest::forBusiness($business_id)
            ->where('contact_id', $contact->id)
            ->with('lines')
            ->orderBy('requested_at', 'desc')
            ->get();

        $imaging_requests = \App\ImagingRequest::forBusiness($business_id)
            ->where('contact_id', $contact->id)
            ->orderBy('requested_at', 'desc')
            ->get();

        $admissions = \App\Admission::forBusiness($business_id)
            ->where('contact_id', $contact->id)
            ->with(['ward', 'bed'])
            ->orderBy('admission_date', 'desc')
            ->get();

        //Billing: outstanding due (= customer due) + the patient's charge transactions.
        $total_invoice = $contact_info->total_invoice ?? 0;
        $invoice_received = $contact_info->invoice_received ?? 0;
        $total_due = $total_invoice - $invoice_received;

        $charge_transactions = \App\Transaction::where('business_id', $business_id)
            ->where('contact_id', $contact->id)
            ->where('type', 'sell')
            ->where('status', 'final')
            ->orderBy('transaction_date', 'desc')
            ->get();

        //Patient 360° timeline — merge every clinical & financial event into one stream.
        $timeline = collect();
        foreach ($visits as $v) {
            $timeline->push(['t' => $v->visit_date, 'icon' => 'fa-stethoscope', 'cls' => 'sky', 'title' => __('lang_v1.visit_type_' . $v->type), 'sub' => optional($v->location)->name, 'url' => auth()->user()->can('visit.view') ? action([\App\Http\Controllers\VisitController::class, 'show'], [$v->id]) : null]);
        }
        foreach ($prescriptions as $rx) {
            $timeline->push(['t' => optional($rx->consultation)->created_at, 'icon' => 'fa-pills', 'cls' => 'indigo', 'title' => __('lang_v1.prescription') . ': ' . optional($rx->product)->name, 'sub' => trim(($rx->dosage ?? '') . ' ' . ($rx->frequency ?? '')), 'url' => null]);
        }
        foreach ($lab_requests as $lr) {
            $timeline->push(['t' => $lr->requested_at, 'icon' => 'fa-vial', 'cls' => 'amber', 'title' => __('lang_v1.lab_request') . ' · ' . $lr->ref_no, 'sub' => __('lang_v1.lab_status_' . $lr->status), 'url' => auth()->user()->can('lab_request.view') ? action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lr->id]) : null]);
        }
        foreach ($imaging_requests as $ir) {
            $timeline->push(['t' => $ir->requested_at, 'icon' => 'fa-x-ray', 'cls' => 'purple', 'title' => __('lang_v1.imaging') . ' · ' . $ir->ref_no, 'sub' => $ir->status, 'url' => auth()->user()->can('imaging_request.view') ? action([\App\Http\Controllers\ImagingController::class, 'show'], [$ir->id]) : null]);
        }
        foreach ($admissions as $a) {
            $timeline->push(['t' => $a->admission_date, 'icon' => 'fa-procedures', 'cls' => 'green', 'title' => __('lang_v1.admission') . ' · ' . optional($a->ward)->name, 'sub' => $a->discharge_date ? __('lang_v1.admission_status_' . $a->status) : __('lang_v1.currently_admitted'), 'url' => auth()->user()->can('admission.view') ? action([\App\Http\Controllers\AdmissionController::class, 'show'], [$a->id]) : null]);
        }
        foreach ($charge_transactions as $ct) {
            $timeline->push(['t' => $ct->transaction_date, 'icon' => 'fa-file-invoice-dollar', 'cls' => 'rose', 'title' => __('lang_v1.bill') . ' · ' . $ct->invoice_no, 'sub' => $ct->payment_status, 'url' => null]);
        }
        $timeline = $timeline->filter(fn ($e) => ! empty($e['t']))->sortByDesc('t')->values();

        return view('patient.show')
            ->with(compact('contact', 'contact_info', 'view_type', 'activities', 'visits', 'prescriptions', 'lab_requests', 'imaging_requests', 'admissions', 'total_due', 'charge_transactions', 'timeline'));
    }

    public function edit($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('patient.update')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        $contact = Contact::where('business_id', $business_id)
            ->onlyPatients()
            ->with('patientDetails')
            ->findOrFail($id);

        return view('patient.edit')
            ->with(compact('contact'));
    }

    public function update(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('patient.update')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            $contact = Contact::where('business_id', $business_id)
                ->onlyPatients()
                ->findOrFail($id);

            $input = $request->only([
                'prefix', 'first_name', 'middle_name', 'last_name', 'mobile',
                'landline', 'alternate_number', 'email', 'city', 'state', 'country',
                'address_line_1', 'address_line_2', 'zip_code',
            ]);

            $name_array = array_filter([
                $input['prefix'] ?? null,
                $input['first_name'] ?? null,
                $input['middle_name'] ?? null,
                $input['last_name'] ?? null,
            ]);
            $input['name'] = trim(implode(' ', $name_array));

            DB::beginTransaction();

            foreach ($input as $key => $value) {
                $contact->$key = $value;
            }
            $contact->save();

            $clinical = [
                'date_of_birth' => $request->filled('date_of_birth') ? $request->input('date_of_birth') : null,
                'sex' => $request->input('sex') ?: null,
                'blood_group' => $request->input('blood_group') ?: null,
                'known_allergies' => $request->input('known_allergies') ?: null,
                'next_of_kin_name' => $request->input('next_of_kin_name') ?: null,
                'next_of_kin_phone' => $request->input('next_of_kin_phone') ?: null,
                'next_of_kin_relationship' => $request->input('next_of_kin_relationship') ?: null,
                'insurance_provider' => $request->input('insurance_provider') ?: null,
                'insurance_number' => $request->input('insurance_number') ?: null,
                'insurance_coverage_pct' => $request->filled('insurance_coverage_pct') ? $this->commonUtil->num_uf($request->input('insurance_coverage_pct')) : null,
                'insurance_expiry_date' => $request->filled('insurance_expiry_date') ? $request->input('insurance_expiry_date') : null,
                'notes' => $request->input('notes') ?: null,
            ];

            $details = PatientDetail::firstOrNew(['contact_id' => $contact->id]);
            $details->business_id = $business_id;
            //Preserve any previously generated file number; generate if missing.
            if (empty($details->file_number)) {
                $details->file_number = $this->generateFileNumber($business_id);
            }
            foreach ($clinical as $key => $value) {
                $details->$key = $value;
            }
            $details->save();

            $this->contactUtil->activityLog($contact, 'edited');

            DB::commit();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.patient_updated_success'),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if ($request->ajax()) {
            return $output;
        }

        return redirect()
            ->action([\App\Http\Controllers\PatientController::class, 'index'])
            ->with('status', $output);
    }

    public function destroy($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('patient.delete')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->session()->get('user.business_id');

                //Block deletion if the patient has any transactions (sales, visits, etc.)
                $count = Transaction::where('business_id', $business_id)
                    ->where('contact_id', $id)
                    ->count();

                if ($count == 0) {
                    $contact = Contact::where('business_id', $business_id)
                        ->onlyPatients()
                        ->findOrFail($id);

                    if (! $contact->is_default) {
                        $this->contactUtil->activityLog($contact, 'contact_deleted', [
                            'id' => $contact->id,
                            'name' => $contact->name,
                        ]);

                        PatientDetail::where('contact_id', $contact->id)->delete();
                        $contact->delete();
                    }

                    $output = [
                        'success' => true,
                        'msg' => __('lang_v1.patient_deleted_success'),
                    ];
                } else {
                    $output = [
                        'success' => false,
                        'msg' => __('lang_v1.you_cannot_delete_this_patient'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = [
                    'success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Select2 AJAX search for patients (used by the visit/consultation pickers).
     */
    public function search()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('patient.view') && ! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $term = request()->input('q', '');
        $business_id = request()->session()->get('user.business_id');

        $patients = Contact::leftjoin('patient_details AS pd', 'contacts.id', '=', 'pd.contact_id')
            ->where('contacts.business_id', $business_id)
            ->onlyPatients()
            ->where(function ($q) use ($term) {
                $q->where('contacts.name', 'like', "%{$term}%")
                    ->orWhere('contacts.mobile', 'like', "%{$term}%")
                    ->orWhere('pd.file_number', 'like', "%{$term}%");
            })
            ->select([
                'contacts.id',
                'contacts.name',
                'contacts.mobile',
                'pd.file_number',
            ])
            ->limit(20)
            ->get();

        $results = $patients->map(function ($p) {
            $label = $p->name;
            $meta = array_filter([$p->file_number, $p->mobile]);
            if (! empty($meta)) {
                $label .= ' ('.implode(' · ', $meta).')';
            }

            return ['id' => $p->id, 'text' => $label];
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Atomically increment the business file counter and return the formatted
     * file number, e.g. PMI-0001.
     */
    private function generateFileNumber($business_id)
    {
        return PatientDetail::generateFileNumber($business_id);
    }

    /**
     * Non-mutating preview of what the next file number would look like.
     */
    private function previewNextFileNumber($business)
    {
        $prefix = $business->hospital_file_prefix ?: 'PMI';
        $next = ($business->hospital_file_counter ?? 0) + 1;

        return $prefix.'-'.str_pad($next, 4, '0', STR_PAD_LEFT);
    }
}
