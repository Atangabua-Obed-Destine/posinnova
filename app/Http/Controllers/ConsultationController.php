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
        // PosInnova: a consultation saved to go on later (e.g. while the lab works) is continued, not
        // started again: the doctor lands back in it, with the results that came in meanwhile.
        if (! $request->boolean('new')) {
            $draft = Consultation::forBusiness($business_id)->where('visit_id', $visit->id)->where('status', 'draft')->orderByDesc('id')->first();
            if ($draft && $this->mayContinue($draft)) {
                return redirect()->action([self::class, 'edit'], [$draft->id]);
            }
        }

        return $this->formView($visit, null);
    }

    /**
     * PosInnova: continue a consultation saved as a draft (the same form, filled in, with the lab and
     * imaging results of the tests asked for in it).
     */
    public function edit($id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('consultation.create')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = request()->session()->get('user.business_id');
        $consultation = Consultation::forBusiness($business_id)->findOrFail($id);
        if ($consultation->status != 'draft' || ! $this->mayContinue($consultation)) {
            return redirect()->action([self::class, 'show'], [$consultation->id])
                ->with('status', ['success' => false, 'msg' => __('clinic.cons_not_editable')]);
        }
        $visit = Visit::forBusiness($business_id)->with(['patient.patientDetails', 'package'])->findOrFail($consultation->visit_id);

        return $this->formView($visit, $consultation);
    }

    /** The doctor who wrote it, or someone who sees every consultation. */
    private function mayContinue(Consultation $consultation)
    {
        return $consultation->consulted_by == auth()->id() || auth()->user()->can('consultation.view_all');
    }

    /**
     * The consultation form, empty (new) or filled in (continuing a draft).
     */
    private function formView($visit, $consultation)
    {
        $business_id = $visit->business_id;
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

        // PosInnova: the lists doctors pick from (specialty, complaint, findings, diagnosis, dosage...)
        $doctor_lists = \App\Utils\ClinicOptions::all($business_id);

        // continuing: the prescription lines as they are, and what the lab / imaging sent back
        $rx_rows = $consultation ? $this->rxRowsForForm($consultation, $visit) : [];
        [$lab_requests, $imaging_requests] = $consultation ? $this->requestsOf($consultation) : [collect(), collect()];

        return view('consultation.create')
            ->with(compact('visit', 'departments', 'default_fee', 'lab_tests', 'imaging_catalogue', 'fee_billed', 'history', 'history_labs',
                'consultation', 'doctor_lists', 'rx_rows', 'lab_requests', 'imaging_requests'));
    }

    /** Lab and imaging requests made from a consultation, with results and files. */
    private function requestsOf(Consultation $consultation)
    {
        $lab = \App\LabRequest::forBusiness($consultation->business_id)->where('consultation_id', $consultation->id)
            ->with(['lines', 'attachments.uploadedBy'])->orderBy('id')->get();
        $img = \App\ImagingRequest::forBusiness($consultation->business_id)->where('consultation_id', $consultation->id)
            ->with(['attachments.uploadedBy'])->orderBy('id')->get();

        return [$lab, $img];
    }

    /**
     * PosInnova: the results panel alone, to refresh it while the doctor waits for the lab.
     */
    public function results($id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('consultation.view_own') && ! auth()->user()->can('consultation.view_all') && ! auth()->user()->can('consultation.create')) {
            abort(403, 'Unauthorized action.');
        }
        $consultation = Consultation::forBusiness(request()->session()->get('user.business_id'))->findOrFail($id);
        if (! auth()->user()->can('consultation.view_all') && $consultation->consulted_by != auth()->id()) {
            abort(403, 'Unauthorized action.');
        }
        [$lab_requests, $imaging_requests] = $this->requestsOf($consultation);

        return view('consultation.partials.results', compact('lab_requests', 'imaging_requests'));
    }

    /**
     * Prescription lines of a draft, for the form: values, availability, and whether they can still be
     * changed (a line already given, or closed by the dispensary, stays as it is).
     */
    private function rxRowsForForm(Consultation $consultation, $visit)
    {
        $rxs = \App\Utils\Dispensing::attach(ConsultationPrescription::where('consultation_id', $consultation->id)
            ->with(['product', 'variation'])->orderBy('id')->get());
        $avail = \App\Utils\DrugAvailability::forVariations($consultation->business_id, $rxs->pluck('variation_id')->filter()->all(), $visit->location_id);
        $units = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->whereIn('v.id', $rxs->pluck('variation_id')->filter()->all())->pluck('u.short_name', 'v.id');

        return $rxs->map(function ($rx) use ($avail, $units) {
            $row = [
                'id' => $rx->id,
                'variation_id' => $rx->variation_id,
                'free_text' => $rx->isFreeText(),
                'name' => $rx->displayName(),
                'dosage' => $rx->dosage, 'frequency' => $rx->frequency, 'duration' => $rx->duration,
                'quantity' => (float) $rx->quantity, 'notes' => $rx->notes,
                'outside' => $rx->closeReasonKey() == 'prescribed_outside',
                'locked' => $this->rxLocked($rx),
                'state' => $rx->dispense_state,
                'state_label' => __('clinic.rx_state_'.$rx->dispense_state).' · '.__('clinic.rx_given_of', ['given' => $rx->given_qty + 0, 'qty' => $rx->quantity + 0]),
                'unit' => $units[$rx->variation_id] ?? null,
                'can_give' => true,
            ];

            return array_merge($row, $avail[$rx->variation_id] ?? []);
        })->values()->all();
    }

    /** A line the doctor can no longer change: something was given, or the dispensary closed it. */
    private function rxLocked(ConsultationPrescription $rx)
    {
        return $rx->given_qty > 0 || ($rx->closed_at && ! in_array($rx->closeReasonKey(), ['prescribed_outside', 'not_stocked']));
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

            $consultation = Consultation::create(array_merge([
                'business_id' => $business_id,
                'visit_id' => $visit->id,
                'contact_id' => $visit->contact_id,
                'consulted_by' => $request->session()->get('user.id'),
                'location_id' => $visit->location_id,
                'ref_no' => $ref_no,
                'status' => $is_final ? 'final' : 'draft',
            ], $this->consultationFields($request, $visit)));

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
     * PosInnova: save a consultation being continued (still a draft): the notes, the prescription
     * (lines changed, added or removed, except lines already given), more lab / imaging requests,
     * and finish it when asked.
     */
    public function update(Request $request, $id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('consultation.create')) {
            abort(403, 'Unauthorized action.');
        }
        $request->validate(['consultation_type' => 'required|in:general,specialist']);
        $business_id = $request->session()->get('user.business_id');
        $consultation = Consultation::forBusiness($business_id)->findOrFail($id);
        if ($consultation->status != 'draft' || ! $this->mayContinue($consultation)) {
            return redirect()->action([self::class, 'show'], [$consultation->id])
                ->with('status', ['success' => false, 'msg' => __('clinic.cons_not_editable')]);
        }

        try {
            $visit = Visit::forBusiness($business_id)->findOrFail($consultation->visit_id);
            $is_final = $request->input('action') === 'final' && auth()->user()->can('consultation.finalize');

            DB::beginTransaction();
            // re-read under lock: two saves at once must not both finish it
            $consultation = Consultation::where('id', $consultation->id)->lockForUpdate()->first();
            if ($consultation->status != 'draft') {
                DB::rollBack();

                return redirect()->action([self::class, 'show'], [$consultation->id])
                    ->with('status', ['success' => false, 'msg' => __('clinic.cons_not_editable')]);
            }
            $consultation->fill($this->consultationFields($request, $visit));
            if ($is_final) {
                $consultation->status = 'final';
            }
            $consultation->save();

            $this->savePrescriptions($request, $consultation, true);
            $this->saveLabRequest($request, $consultation, $visit);
            $this->saveImagingRequests($request, $consultation, $visit);

            if ($is_final) {
                $this->chargeConsultationFee($consultation, $visit);
                $this->markSeen($visit);
            }
            DB::commit();

            return redirect()->action([self::class, 'show'], [$consultation->id])->with('status', ['success' => true,
                'msg' => $is_final ? __('lang_v1.consultation_finalized_success') : __('lang_v1.consultation_saved_draft')]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    /** The fields of the consultation form (new or continued). */
    private function consultationFields(Request $request, $visit)
    {
        $num = fn ($f) => $request->filled($f) ? $request->input($f) : null;

        return [
            'consultation_type' => $request->input('consultation_type'),
            'specialty' => $request->input('consultation_type') == 'specialist' ? (mb_substr(trim((string) $request->input('specialty')), 0, 191) ?: null) : null,
            'chief_complaint' => $request->input('chief_complaint') ?: null,
            'vitals_weight' => $num('vitals_weight'),
            'vitals_height' => $num('vitals_height'),
            'vitals_bp_systolic' => $num('vitals_bp_systolic'),
            'vitals_bp_diastolic' => $num('vitals_bp_diastolic'),
            'vitals_temperature' => $num('vitals_temperature'),
            'vitals_pulse' => $num('vitals_pulse'),
            'vitals_spo2' => $num('vitals_spo2'),
            'examination_notes' => $request->input('examination_notes') ?: null,
            'diagnosis' => $request->input('diagnosis') ?: null,
            'referral_to' => $request->input('referral_to') ?: null,
            'admission_requested' => $request->boolean('admission_requested') ? 1 : 0,
            'consultation_fee' => $visit->billedAtCheckin() ? 0
                : ($request->filled('consultation_fee') ? $this->commonUtil->num_uf($request->input('consultation_fee')) : 0),
        ];
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

        [$lab_requests, $imaging_requests] = $this->requestsOf($consultation);
        $can_continue = $consultation->status == 'draft' && auth()->user()->can('consultation.create') && $this->mayContinue($consultation);

        return view('consultation.show')->with(compact('consultation', 'lab_requests', 'imaging_requests', 'can_continue'));
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
        \App\Utils\Dispensing::attach($consultation->prescriptions);

        $business = \App\Business::find($business_id);

        return view('consultation.prescription_print')->with(compact('consultation', 'business'));
    }

    /**
     * PosInnova: the doctor's medicine search, with what the clinic can give at the visit's location
     * (stock, expired stock, nearest expiry, other locations). See App\Utils\DrugAvailability.
     */
    public function drugSearch(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('consultation.prescribe')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = $request->session()->get('user.business_id');

        $location_id = $request->input('location_id');
        if ($request->filled('visit_id')) {
            $location_id = optional(Visit::forBusiness($business_id)->find($request->input('visit_id')))->location_id ?: $location_id;
        }
        $location_id = BusinessLocation::where('business_id', $business_id)->where('id', $location_id)->value('id');

        return response()->json(\App\Utils\DrugAvailability::search($business_id, $request->input('term'), $location_id));
    }

    /**
     * Persist prescription rows from the dynamic table. A row is either a product of the clinic
     * (variation_id; product_id is taken from the variation, never from the form) or a medicine the
     * doctor typed (drug_name). A typed medicine is closed as "not_stocked" and a ticked "buys
     * outside" row as "prescribed_outside": both are to buy at another pharmacy and never wait at
     * the dispensary.
     *
     * Continuing a draft ($existing = true): a row carrying the id of one of the consultation's lines
     * updates it, a line no longer in the form is removed, the rest are added. A line already given
     * (or closed by the dispensary) is left as it is, and is never removed.
     */
    private function savePrescriptions(Request $request, Consultation $consultation, $existing = false)
    {
        $rows = $request->input('prescriptions', []);
        $rows = is_array($rows) ? $rows : [];
        $user_id = auth()->user()->id;
        $current = $existing
            ? \App\Utils\Dispensing::attach(ConsultationPrescription::where('consultation_id', $consultation->id)->get())->keyBy('id')
            : collect();
        if (empty($rows) && $current->isEmpty()) {
            return;
        }

        $variation_ids = collect($rows)->pluck('variation_id')->filter()->map(fn ($v) => (int) $v)->unique()->all();
        $products = empty($variation_ids) ? collect() : DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $consultation->business_id)->whereIn('v.id', $variation_ids)
            ->pluck('p.id', 'v.id');

        $kept = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $line = [
                'dosage' => isset($row['dosage']) ? (mb_substr(trim((string) $row['dosage']), 0, 100) ?: null) : null,
                'frequency' => isset($row['frequency']) ? (mb_substr(trim((string) $row['frequency']), 0, 100) ?: null) : null,
                'duration' => isset($row['duration']) ? (mb_substr(trim((string) $row['duration']), 0, 100) ?: null) : null,
                'quantity' => ! empty($row['quantity']) ? max(0, $this->commonUtil->num_uf($row['quantity'])) : 1,
                'notes' => isset($row['notes']) ? (trim((string) $row['notes']) ?: null) : null,
            ];

            // a line of this consultation, changed
            if (! empty($row['id'])) {
                $rx = $current->get((int) $row['id']);
                if (! $rx) {
                    continue; // not a line of this consultation
                }
                $kept[] = $rx->id;
                if ($this->rxLocked($rx)) {
                    continue;
                }
                if ($rx->isFreeText()) {
                    $name = trim(preg_replace('/\s+/u', ' ', (string) ($row['drug_name'] ?? '')));
                    if ($name !== '') {
                        $line['drug_name'] = mb_substr($name, 0, 191);
                    }
                } elseif (! empty($row['outside']) && ! $rx->closed_at) {
                    $line += ['closed_at' => now(), 'closed_by' => $user_id, 'close_reason' => 'prescribed_outside'];
                } elseif (empty($row['outside']) && $rx->closeReasonKey() == 'prescribed_outside') {
                    $line += ['closed_at' => null, 'closed_by' => null, 'close_reason' => null];
                }
                $rx->fill($line)->save();

                continue;
            }

            $line['consultation_id'] = $consultation->id;
            if (! empty($row['variation_id'])) {
                $product_id = $products[(int) $row['variation_id']] ?? null;
                if (! $product_id) {
                    continue; // not a product of this business
                }
                $line += ['product_id' => $product_id, 'variation_id' => (int) $row['variation_id']];
                if (! empty($row['outside'])) {
                    $line += ['closed_at' => now(), 'closed_by' => $user_id, 'close_reason' => 'prescribed_outside'];
                }
            } else {
                $name = trim(preg_replace('/\s+/u', ' ', (string) ($row['drug_name'] ?? '')));
                if ($name === '') {
                    continue;
                }
                $line += ['product_id' => null, 'variation_id' => null, 'drug_name' => mb_substr($name, 0, 191),
                    'closed_at' => now(), 'closed_by' => $user_id, 'close_reason' => 'not_stocked'];
            }

            ConsultationPrescription::create($line);
        }

        // lines taken off the prescription (never one already given, sold, or recorded on a ward chart)
        foreach ($current as $rx) {
            if (in_array($rx->id, $kept) || $this->rxLocked($rx)) {
                continue;
            }
            $linked = DB::table('transaction_sell_lines')->where('consultation_prescription_id', $rx->id)->exists()
                || DB::table('medication_administrations')->where('consultation_prescription_id', $rx->id)->exists();
            if (! $linked) {
                $rx->delete();
            }
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
