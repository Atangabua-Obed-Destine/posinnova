<?php

namespace App\Http\Controllers\PatientPortal;

use App\Admission;
use App\AntenatalRecord;
use App\Consultation;
use App\ConsultationPrescription;
use App\Http\Controllers\Controller;
use App\ImagingRequest;
use App\LabRequest;
use App\Transaction;
use App\Visit;
use App\Utils\ContactUtil;
use DB;
use Illuminate\Support\Facades\Auth;

/**
 * Patient Portal screens (hospital module). Every method reads ONLY the
 * logged-in patient's own data. Detail screens additionally re-check that the
 * requested record's contact_id belongs to the patient (never trust the URL).
 */
class PortalController extends Controller
{
    protected $contactUtil;

    public function __construct(ContactUtil $contactUtil)
    {
        $this->contactUtil = $contactUtil;
    }

    /** The authenticated patient contact. */
    private function patient()
    {
        return Auth::guard('patient')->user();
    }

    /** Outstanding due for the patient (= customer due). */
    private function outstandingDue($patient)
    {
        $info = $this->contactUtil->getContactInfo($patient->business_id, $patient->id);

        return ($info->total_invoice ?? 0) - ($info->invoice_received ?? 0);
    }

    public function dashboard()
    {
        $patient = $this->patient();
        $patient->load('patientDetails');
        $bid = $patient->business_id;

        $due = $this->outstandingDue($patient);

        $visits_count = Visit::forBusiness($bid)->where('contact_id', $patient->id)->count();

        // lines still waiting to be given (not closed, given less than prescribed)
        $active_rx = \App\Utils\Dispensing::attach(ConsultationPrescription::whereHas('consultation', function ($q) use ($bid, $patient) {
            $q->where('business_id', $bid)->where('contact_id', $patient->id);
        })->whereNull('closed_at')->get(['id', 'quantity', 'legacy_given', 'closed_at']))
            ->filter(fn ($rx) => $rx->outstanding_qty > 0)->count();

        $pending_labs = LabRequest::forBusiness($bid)->where('contact_id', $patient->id)
            ->whereIn('status', ['pending', 'sample_collected', 'processing'])->count();

        $active_admission = Admission::forBusiness($bid)->where('contact_id', $patient->id)->active()
            ->with(['ward', 'bed'])->first();

        // Latest activity feed (most recent few clinical events).
        $recent_consultations = Consultation::forBusiness($bid)->where('contact_id', $patient->id)
            ->orderBy('created_at', 'desc')->limit(3)->get();
        $recent_labs = LabRequest::forBusiness($bid)->where('contact_id', $patient->id)
            ->orderBy('requested_at', 'desc')->limit(3)->get();

        // Weight trend (last ~8 nursing vitals across all admissions, oldest→newest).
        $weight_trend = \App\NursingVital::whereHas('admission', function ($q) use ($bid, $patient) {
            $q->where('business_id', $bid)->where('contact_id', $patient->id);
        })->whereNotNull('weight')->orderBy('observed_at', 'desc')->limit(8)->get()
            ->reverse()->values()->map(fn ($v) => ['t' => optional($v->observed_at)->toDateString(), 'v' => (float) $v->weight]);

        // Active antenatal record (for the maternity countdown).
        $antenatal = AntenatalRecord::forBusiness($bid)->where('contact_id', $patient->id)
            ->whereDoesntHave('delivery')->orderBy('created_at', 'desc')->first();

        // Next appointment = soonest future ANC follow-up.
        $next_appointment = \App\AntenatalVisit::whereHas('record', function ($q) use ($bid, $patient) {
            $q->where('business_id', $bid)->where('contact_id', $patient->id);
        })->whereNotNull('next_visit_date')->whereDate('next_visit_date', '>=', now())
            ->orderBy('next_visit_date', 'asc')->first();

        // clinic appointments: the next booking
        $next_booking = \App\Utils\ClinicAppointments::enabled($bid)
            ? \App\ClinicAppointment::forBusiness($bid)->where('contact_id', $patient->id)->open()
                ->whereDate('date', '>=', now()->toDateString())->with('doctor')->orderBy('date')->orderBy('start_time')->first()
            : null;

        return view('patient_portal.dashboard')->with(compact(
            'patient', 'due', 'visits_count', 'active_rx', 'pending_labs',
            'active_admission', 'recent_consultations', 'recent_labs',
            'weight_trend', 'antenatal', 'next_appointment', 'next_booking'
        ));
    }

    public function visits()
    {
        $patient = $this->patient();
        $visits = Visit::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->withCount('consultations')->orderBy('visit_date', 'desc')->paginate(15);

        return view('patient_portal.visits')->with(compact('patient', 'visits'));
    }

    public function visitShow($id)
    {
        $patient = $this->patient();
        $visit = Visit::forBusiness($patient->business_id)
            ->where('contact_id', $patient->id) // ownership guard
            ->with(['consultations.consultedBy', 'consultations.prescriptions.product', 'consultations.prescriptions.variation', 'location'])
            ->findOrFail($id);
        \App\Utils\Dispensing::attach($visit->consultations->flatMap->prescriptions);

        return view('patient_portal.visit_show')->with(compact('patient', 'visit'));
    }

    public function prescriptions()
    {
        $patient = $this->patient();
        $bid = $patient->business_id;

        $prescriptions = ConsultationPrescription::whereHas('consultation', function ($q) use ($bid, $patient) {
            $q->where('business_id', $bid)->where('contact_id', $patient->id);
        })->with(['product', 'variation', 'consultation'])->orderBy('created_at', 'desc')->paginate(15);
        \App\Utils\Dispensing::attach($prescriptions->getCollection());

        return view('patient_portal.prescriptions')->with(compact('patient', 'prescriptions'));
    }

    public function labResults()
    {
        $patient = $this->patient();
        $lab_requests = LabRequest::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with('lines')->orderBy('requested_at', 'desc')->paginate(15);
        // PosInnova: a result is shown once the patient's part is paid
        $pays = $lab_requests->getCollection()->mapWithKeys(fn ($lr) => [$lr->id => \App\Utils\ClinicRequestPayment::of($lr)]);

        return view('patient_portal.lab_results')->with(compact('patient', 'lab_requests', 'pays'));
    }

    public function labShow($id)
    {
        $patient = $this->patient();
        $lab_request = LabRequest::forBusiness($patient->business_id)
            ->where('contact_id', $patient->id) // ownership guard
            ->with(['lines', 'requestedBy', 'validatedBy', 'attachments'])
            ->findOrFail($id);
        // PosInnova: not paid yet: the values are not sent at all, only that the result waits for payment
        $pay = \App\Utils\ClinicRequestPayment::of($lab_request);
        $locked = ! \App\Utils\ClinicRequestPayment::releasedToPatient($pay);
        if ($locked) {
            $lab_request->setRelation('lines', $lab_request->lines->map(function ($l) {
                $l->result_value = null;
                $l->result_flag = null;
                $l->result_notes = null;

                return $l;
            }));
        }

        return view('patient_portal.lab_show')->with(compact('patient', 'lab_request', 'pay', 'locked'));
    }

    public function imaging()
    {
        abort_unless(\App\Utils\ClinicSettings::sectionOn($this->patient()->business_id, 'imaging'), 404);
        $patient = $this->patient();
        $imaging_requests = ImagingRequest::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with(['reportedBy', 'attachments'])->orderBy('requested_at', 'desc')->paginate(15);
        // PosInnova: a report is shown once the patient's part is paid
        $pays = $imaging_requests->getCollection()->mapWithKeys(fn ($ir) => [$ir->id => \App\Utils\ClinicRequestPayment::of($ir)]);

        return view('patient_portal.imaging')->with(compact('patient', 'imaging_requests', 'pays'));
    }

    public function admissions()
    {
        abort_unless(\App\Utils\ClinicSettings::sectionOn($this->patient()->business_id, 'wards'), 404);
        $patient = $this->patient();
        $admissions = Admission::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with(['ward', 'bed'])->orderBy('admission_date', 'desc')->paginate(15);

        return view('patient_portal.admissions')->with(compact('patient', 'admissions'));
    }

    public function maternity()
    {
        abort_unless(\App\Utils\ClinicSettings::sectionOn($this->patient()->business_id, 'maternity'), 404);
        $patient = $this->patient();
        $records = AntenatalRecord::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with(['antenatalVisits.attendedBy', 'delivery.baby'])->orderBy('created_at', 'desc')->get();

        if ($records->isEmpty()) {
            return redirect()->route('portal.dashboard');
        }

        return view('patient_portal.maternity')->with(compact('patient', 'records'));
    }

    /**
     * The patient's monthly plan: this month (paid? visits used), next
     * billing date, and every month billed.
     */
    public function plan()
    {
        $patient = $this->patient();
        $bid = $patient->business_id;

        $subscription = \App\ClinicSubscription::forBusiness($bid)->where('contact_id', $patient->id)
            ->with('package')->orderByRaw("status = 'active' DESC")->orderByDesc('id')->first();
        $coverage = \App\Utils\ClinicPlans::coverage($bid, $patient->id);
        $periods = $subscription
            ? \App\ClinicSubscriptionPeriod::where('subscription_id', $subscription->id)->with('invoice')->orderByDesc('period_start')->limit(12)->get()
            : collect();
        foreach ($periods as $p) {
            $p->used = $p->visitsUsed();
            $p->paid = \App\Utils\ClinicPlans::periodPaid($p);
        }

        return view('patient_portal.plan')->with(compact('patient', 'subscription', 'coverage', 'periods'));
    }

    public function bills()
    {
        $patient = $this->patient();
        $bid = $patient->business_id;

        $due = $this->outstandingDue($patient);

        // The patient's charge transactions (each hospital fee is a sell on the contact).
        $charges_base = Transaction::where('business_id', $bid)
            ->where('contact_id', $patient->id)
            ->where('type', 'sell')
            ->where('status', 'final');

        $total_billed = (clone $charges_base)->sum('final_total');
        $total_paid = DB::table('transaction_payments')
            ->whereIn('transaction_id', (clone $charges_base)->pluck('id'))
            ->sum('amount');

        $charges = $charges_base->orderBy('transaction_date', 'desc')->paginate(15);

        // clinic phase 5: what the insurer pays on each invoice, and still owes
        $insurer_parts = \App\ClinicInsuranceShare::where('business_id', $bid)->where('contact_id', $patient->id)
            ->whereIn('patient_transaction_id', $charges->pluck('id'))
            ->with('insurer')->get()->keyBy('patient_transaction_id');
        $insurer_owes = \App\Utils\ClinicInsurance::openSharesFor($bid, $patient->id)->sum('due');

        return view('patient_portal.bills')->with(compact('patient', 'due', 'charges', 'total_billed', 'total_paid', 'insurer_parts', 'insurer_owes'));
    }

    public function profile()
    {
        $patient = $this->patient();
        $patient->load('patientDetails');

        return view('patient_portal.profile')->with(compact('patient'));
    }

    /** Self-service: edit a few safe contact fields. */
    public function profileEdit()
    {
        $patient = $this->patient();
        $patient->load('patientDetails');

        return view('patient_portal.profile_edit')->with(compact('patient'));
    }

    public function profileUpdate(\Illuminate\Http\Request $request)
    {
        $patient = $this->patient();

        $data = $request->validate([
            'mobile' => 'nullable|string|max:30',
            'next_of_kin_name' => 'nullable|string|max:191',
            'next_of_kin_phone' => 'nullable|string|max:30',
            'next_of_kin_relationship' => 'nullable|string|max:50',
            'known_allergies' => 'nullable|string|max:500',
            'whatsapp_language' => 'nullable|in:en,fr',
        ]);

        if (! empty($data['mobile'])) {
            $patient->mobile = $data['mobile'];
            $patient->save();
        }

        $details = \App\PatientDetail::updateOrCreate(
            ['contact_id' => $patient->id],
            [
                'business_id' => $patient->business_id,
                'next_of_kin_name' => $data['next_of_kin_name'] ?? null,
                'next_of_kin_phone' => $data['next_of_kin_phone'] ?? null,
                'next_of_kin_relationship' => $data['next_of_kin_relationship'] ?? null,
                'known_allergies' => $data['known_allergies'] ?? null,
            ]
        );
        // the patient's own choice about appointment messages on WhatsApp
        if ($request->has('whatsapp_opt_in')) {
            $details->setWhatsappConsent($request->boolean('whatsapp_opt_in'));
            $details->whatsapp_language = $data['whatsapp_language'] ?? null;
            $details->save();
        }

        return redirect()->route('portal.profile')->with('status', __('lang_v1.portal_profile_saved'));
    }

    /** A self-contained printable receipt for one of the patient's charges. */
    public function billReceipt($id)
    {
        $patient = $this->patient();
        $charge = Transaction::where('business_id', $patient->business_id)
            ->where('contact_id', $patient->id) // ownership guard
            ->where('type', 'sell')
            ->with(['sell_lines.product', 'payment_lines', 'location'])
            ->findOrFail($id);

        return view('patient_portal.receipt')->with(compact('patient', 'charge'));
    }
}
