<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Consultation;
use App\ConsultationPrescription;
use App\Department;
use App\Visit;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;

/**
 * Consultations (hospital module). A doctor/nurse records vitals, complaint,
 * examination, diagnosis and writes a prescription within a visit.
 */
class ConsultationController extends Controller
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

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('consultation.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\VisitController::class, 'index']));
        }

        $visit = Visit::forBusiness($business_id)
            ->with(['patient.patientDetails', 'package'])
            ->findOrFail($request->input('visit_id'));

        //PosInnova clinic: pay-first gate and queue status.
        if ($blocked = $this->payGateResponse($visit)) {
            return $blocked;
        }
        if ($visit->status == 'open' && $visit->queue_status == 'waiting') {
            $visit->queue_status = 'with_doctor';
            $visit->called_at = now();
            $visit->save();
        }
        //The consultation was billed at check-in (package or default fee).
        $fee_billed = $visit->billedAtCheckin();

        $departments = Department::forDropdown($business_id, true);
        // the fee proposed when nothing was billed at check-in: the visit's doctor's own (or this doctor's), else the clinic's
        $default_fee = \App\Utils\ClinicFees::price($business_id, $visit->doctor_id ?: auth()->id());

        //Lab tests available to request from within the consultation (Phase 4).
        $lab_tests = auth()->user()->can('lab_request.create')
            ? \App\LabTest::forBusiness($business_id)->active()->orderBy('name')->get()
            : collect();

        //Imaging studies available to request from within the consultation (Phase 5).
        $imaging_catalogue = auth()->user()->can('imaging_request.create')
            ? \App\ImagingCatalogue::forBusiness($business_id)->active()->orderBy('name')->get()
            : collect();

        //PosInnova: the patient's history beside the form - last consultations and lab results.
        $history = Consultation::forBusiness($business_id)->where('contact_id', $visit->contact_id)
            ->where('visit_id', '!=', $visit->id)->where('status', 'final')
            ->with(['consultedBy', 'prescriptions.product', 'prescriptions.variation'])
            ->orderByDesc('created_at')->limit(5)->get();
        $history_labs = \App\LabRequest::forBusiness($business_id)->where('contact_id', $visit->contact_id)
            ->whereIn('status', ['resulted', 'validated'])->with('lines')
            ->orderByDesc('requested_at')->limit(3)->get();

        return view('consultation.create')
            ->with(compact('visit', 'departments', 'default_fee', 'lab_tests', 'imaging_catalogue', 'fee_billed', 'history', 'history_labs'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('consultation.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'visit_id' => 'required',
            'consultation_type' => 'required|in:general,specialist',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            $visit = Visit::forBusiness($business_id)->findOrFail($request->input('visit_id'));

            if ($blocked = $this->payGateResponse($visit)) {
                return $blocked;
            }

            $is_final = $request->input('action') === 'final';
            if ($is_final && ! auth()->user()->can('consultation.finalize')) {
                $is_final = false;
            }

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('consultation', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('consultation', $ref_count, $business_id, 'CONS-');

            $consultation = Consultation::create([
                'business_id' => $business_id,
                'visit_id' => $visit->id,
                'contact_id' => $visit->contact_id,
                'consulted_by' => $request->session()->get('user.id'),
                'consultation_type' => $request->input('consultation_type'),
                'specialty' => $request->input('specialty') ?: null,
                'location_id' => $visit->location_id,
                'ref_no' => $ref_no,
                'chief_complaint' => $request->input('chief_complaint') ?: null,
                'vitals_weight' => $request->filled('vitals_weight') ? $request->input('vitals_weight') : null,
                'vitals_height' => $request->filled('vitals_height') ? $request->input('vitals_height') : null,
                'vitals_bp_systolic' => $request->filled('vitals_bp_systolic') ? $request->input('vitals_bp_systolic') : null,
                'vitals_bp_diastolic' => $request->filled('vitals_bp_diastolic') ? $request->input('vitals_bp_diastolic') : null,
                'vitals_temperature' => $request->filled('vitals_temperature') ? $request->input('vitals_temperature') : null,
                'vitals_pulse' => $request->filled('vitals_pulse') ? $request->input('vitals_pulse') : null,
                'vitals_spo2' => $request->filled('vitals_spo2') ? $request->input('vitals_spo2') : null,
                'examination_notes' => $request->input('examination_notes') ?: null,
                'diagnosis' => $request->input('diagnosis') ?: null,
                'referral_to' => $request->input('referral_to') ?: null,
                'admission_requested' => $request->boolean('admission_requested') ? 1 : 0,
                'status' => $is_final ? 'final' : 'draft',
                'consultation_fee' => $visit->billedAtCheckin() ? 0
                    : ($request->filled('consultation_fee') ? $this->commonUtil->num_uf($request->input('consultation_fee')) : 0),
            ]);

            $this->savePrescriptions($request, $consultation);
            $this->saveLabRequest($request, $consultation, $visit);
            $this->saveImagingRequests($request, $consultation, $visit);

            //Bill the consultation fee to the patient (only once it is final).
            if ($is_final) {
                $this->chargeConsultationFee($consultation, $visit);
                $this->markSeen($visit);
            }

            DB::commit();

            $output = [
                'success' => true,
                'msg' => $is_final ? __('lang_v1.consultation_finalized_success') : __('lang_v1.consultation_saved_draft'),
            ];

            return redirect()
                ->action([\App\Http\Controllers\ConsultationController::class, 'show'], [$consultation->id])
                ->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    /**
     * PosInnova: the list of consultations (the menu's "Consultations"). A doctor with
     * consultation.view_own sees their own; consultation.view_all sees everyone's.
     */
    public function index(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('consultation.view_own') && ! auth()->user()->can('consultation.view_all')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = $request->session()->get('user.business_id');
        $search = trim((string) $request->input('q'));
        $query = Consultation::forBusiness($business_id)
            ->join('contacts as c', 'c.id', '=', 'consultations.contact_id')
            ->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'consultations.contact_id')
            ->with('consultedBy')
            ->select('consultations.*', 'c.name as patient_name', 'pd.file_number');
        if (! auth()->user()->can('consultation.view_all')) {
            $query->where('consultations.consulted_by', auth()->user()->id);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('c.name', 'like', '%'.$search.'%')->orWhere('pd.file_number', 'like', '%'.$search.'%')
                    ->orWhere('consultations.diagnosis', 'like', '%'.$search.'%')->orWhere('consultations.ref_no', 'like', '%'.$search.'%');
            });
        }
        $consultations = $query->orderByDesc('consultations.created_at')->paginate(30)->withQueryString();

        return view('consultation.index', compact('consultations', 'search'));
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('consultation.view_own') && ! auth()->user()->can('consultation.view_all')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $consultation = Consultation::forBusiness($business_id)
            ->with(['visit', 'patient.patientDetails', 'consultedBy', 'referralDepartment', 'prescriptions.variation', 'prescriptions.product'])
            ->findOrFail($id);
        \App\Utils\Dispensing::attach($consultation->prescriptions);

        //If user can only view own, enforce it.
        if (! auth()->user()->can('consultation.view_all') && auth()->user()->can('consultation.view_own')) {
            if ($consultation->consulted_by != auth()->user()->id) {
                abort(403, 'Unauthorized action.');
            }
        }

        return view('consultation.show')->with(compact('consultation'));
    }

    public function finalize($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('consultation.finalize')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $consultation = Consultation::forBusiness($business_id)->findOrFail($id);

            if ($consultation->status == 'draft') {
                $consultation->status = 'final';
                $consultation->save();
                //Bill the consultation fee now that it is finalized.
                $this->chargeConsultationFee($consultation, $consultation->visit);
                $this->markSeen($consultation->visit);
            }

            $output = ['success' => true, 'msg' => __('lang_v1.consultation_finalized_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        if (request()->ajax()) {
            return $output;
        }

        return redirect()->action([\App\Http\Controllers\ConsultationController::class, 'show'], [$id])->with('status', $output);
    }

    public function prescription($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('consultation.view_own') && ! auth()->user()->can('consultation.view_all')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $consultation = Consultation::forBusiness($business_id)
            ->with(['visit', 'patient.patientDetails', 'consultedBy', 'prescriptions.variation', 'prescriptions.product'])
            ->findOrFail($id);

        $business = \App\Business::find($business_id);

        return view('consultation.prescription_print')->with(compact('consultation', 'business'));
    }

    /**
     * Persist prescription rows from the dynamic table. Each row carries a
     * variation_id (required), product_id and the free-text dosage fields.
     */
    private function savePrescriptions(Request $request, Consultation $consultation)
    {
        $rows = $request->input('prescriptions', []);
        if (empty($rows) || ! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (empty($row['variation_id']) || empty($row['product_id'])) {
                continue;
            }

            ConsultationPrescription::create([
                'consultation_id' => $consultation->id,
                'product_id' => $row['product_id'],
                'variation_id' => $row['variation_id'],
                'dosage' => $row['dosage'] ?? null,
                'frequency' => $row['frequency'] ?? null,
                'duration' => $row['duration'] ?? null,
                'quantity' => ! empty($row['quantity']) ? $this->commonUtil->num_uf($row['quantity']) : 1,
                'notes' => $row['notes'] ?? null,
            ]);
        }
    }

    /**
     * If the doctor selected lab tests on the consultation form, raise a lab
     * request linked to this consultation/visit. No-op when none selected or
     * the user lacks lab_request.create.
     */
    private function saveLabRequest(Request $request, Consultation $consultation, $visit)
    {
        $test_ids = $request->input('lab_test_ids', []);
        if (empty($test_ids) || ! is_array($test_ids) || ! auth()->user()->can('lab_request.create')) {
            return;
        }

        $business_id = $consultation->business_id;

        $tests = \App\LabTest::forBusiness($business_id)->whereIn('id', $test_ids)->get();
        if ($tests->isEmpty()) {
            return;
        }

        $ref_count = $this->commonUtil->setAndGetReferenceCount('lab_request', $business_id);
        $ref_no = $this->commonUtil->generateReferenceNumber('lab_request', $ref_count, $business_id, 'LAB-');

        $lab_request = \App\LabRequest::create([
            'business_id' => $business_id,
            'visit_id' => $consultation->visit_id,
            'consultation_id' => $consultation->id,
            'contact_id' => $consultation->contact_id,
            'requested_by' => $request->session()->get('user.id'),
            'location_id' => $visit->location_id ?? null,
            'ref_no' => $ref_no,
            'status' => 'pending',
            'clinical_notes' => $request->input('lab_clinical_notes') ?: null,
            'requested_at' => now(),
        ]);

        foreach ($tests as $test) {
            \App\LabRequestLine::create([
                'lab_request_id' => $lab_request->id,
                'lab_test_id' => $test->id,
                'test_name' => $test->name,
                'normal_range' => $test->normal_range,
                'unit' => $test->unit,
                'fee' => $test->fee,
            ]);
        }

        //Bill the lab fee to the patient (Phase 8).
        (new \App\Utils\HospitalBillingUtil())->chargeLabRequest($lab_request->load('lines'), $request->session()->get('user.id'));
    }

    /**
     * If the doctor selected imaging studies on the consultation form, raise an
     * imaging request per study linked to this consultation/visit. No-op
     * otherwise.
     */
    private function saveImagingRequests(Request $request, Consultation $consultation, $visit)
    {
        $study_ids = $request->input('imaging_catalogue_ids', []);
        if (empty($study_ids) || ! is_array($study_ids) || ! auth()->user()->can('imaging_request.create')) {
            return;
        }

        $business_id = $consultation->business_id;

        $studies = \App\ImagingCatalogue::forBusiness($business_id)->whereIn('id', $study_ids)->get();

        foreach ($studies as $study) {
            $ref_count = $this->commonUtil->setAndGetReferenceCount('imaging_request', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('imaging_request', $ref_count, $business_id, 'IMG-');

            $imaging_request = \App\ImagingRequest::create([
                'business_id' => $business_id,
                'visit_id' => $consultation->visit_id,
                'consultation_id' => $consultation->id,
                'contact_id' => $consultation->contact_id,
                'imaging_catalogue_id' => $study->id,
                'study_name' => $study->name,
                'requested_by' => $request->session()->get('user.id'),
                'location_id' => $visit->location_id ?? null,
                'ref_no' => $ref_no,
                'status' => 'pending',
                'clinical_notes' => $request->input('lab_clinical_notes') ?: null,
                'fee' => $study->fee,
                'requested_at' => now(),
            ]);

            //Bill the imaging fee to the patient (Phase 8).
            (new \App\Utils\HospitalBillingUtil())->chargeImagingRequest($imaging_request, $request->session()->get('user.id'));
        }
    }

    /**
     * PosInnova clinic: when the clinic takes payment before the doctor, an
     * unpaid check-in keeps the consultation closed (see App\Utils\ClinicQueue).
     */
    private function payGateResponse($visit)
    {
        if (! \App\Utils\ClinicQueue::payGateBlocks($visit)) {
            return null;
        }

        return redirect()
            ->action([\App\Http\Controllers\VisitController::class, 'show'], [$visit->id])
            ->with('status', ['success' => false, 'msg' => __('clinic.pay_first_blocked')]);
    }

    /** The doctor has finished with the patient: out of the queue. */
    private function markSeen($visit)
    {
        if ($visit && in_array($visit->queue_status, ['waiting', 'with_doctor'])) {
            $visit->queue_status = 'seen';
            $visit->seen_at = now();
            $visit->save();
        }
    }

    /**
     * Post the consultation fee to the patient's account as a sell/due (Phase 8
     * billing). Idempotent via the consultations.fee_charged flag, so finalizing
     * the same consultation twice never double-charges.
     */
    private function chargeConsultationFee(Consultation $consultation, $visit)
    {
        if ($consultation->fee_charged || $consultation->consultation_fee <= 0) {
            return;
        }
        //Billed at check-in already (clinic phase 2): never charge twice.
        if ($visit && $visit->billedAtCheckin()) {
            return;
        }

        $location_id = optional($visit)->location_id
            ?? \App\BusinessLocation::where('business_id', $consultation->business_id)->value('id');

        if (empty($location_id)) {
            return; // cannot invoice without a location
        }

        $billing = new \App\Utils\HospitalBillingUtil();
        $billing->chargePatientFee([
            'business_id' => $consultation->business_id,
            'contact_id' => $consultation->contact_id,
            'amount' => (float) $consultation->consultation_fee,
            'charge_type' => 'consultation',
            'description' => __('lang_v1.consultation_fee').' · '.$consultation->ref_no,
            'location_id' => $location_id,
            'visit_id' => $consultation->visit_id,
            'admission_id' => null,
            'created_by' => auth()->id() ?? $consultation->consulted_by,
            'reference_type' => 'consultation',
        ]);

        $consultation->fee_charged = 1;
        $consultation->save();
    }
}
