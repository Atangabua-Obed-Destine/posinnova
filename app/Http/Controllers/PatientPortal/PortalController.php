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

        $active_rx = ConsultationPrescription::whereHas('consultation', function ($q) use ($bid, $patient) {
            $q->where('business_id', $bid)->where('contact_id', $patient->id);
        })->where('is_dispensed', 0)->count();

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

        return view('patient_portal.dashboard')->with(compact(
            'patient', 'due', 'visits_count', 'active_rx', 'pending_labs',
            'active_admission', 'recent_consultations', 'recent_labs',
            'weight_trend', 'antenatal', 'next_appointment'
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
            ->with(['consultations.consultedBy', 'consultations.prescriptions.product', 'location'])
            ->findOrFail($id);

        return view('patient_portal.visit_show')->with(compact('patient', 'visit'));
    }

    public function prescriptions()
    {
        $patient = $this->patient();
        $bid = $patient->business_id;

        $prescriptions = ConsultationPrescription::whereHas('consultation', function ($q) use ($bid, $patient) {
            $q->where('business_id', $bid)->where('contact_id', $patient->id);
        })->with(['product', 'consultation'])->orderBy('created_at', 'desc')->paginate(15);

        return view('patient_portal.prescriptions')->with(compact('patient', 'prescriptions'));
    }

    public function labResults()
    {
        $patient = $this->patient();
        $lab_requests = LabRequest::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with('lines')->orderBy('requested_at', 'desc')->paginate(15);

        return view('patient_portal.lab_results')->with(compact('patient', 'lab_requests'));
    }

    public function labShow($id)
    {
        $patient = $this->patient();
        $lab_request = LabRequest::forBusiness($patient->business_id)
            ->where('contact_id', $patient->id) // ownership guard
            ->with(['lines', 'requestedBy', 'validatedBy'])
            ->findOrFail($id);

        return view('patient_portal.lab_show')->with(compact('patient', 'lab_request'));
    }

    public function imaging()
    {
        $patient = $this->patient();
        $imaging_requests = ImagingRequest::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with('reportedBy')->orderBy('requested_at', 'desc')->paginate(15);

        return view('patient_portal.imaging')->with(compact('patient', 'imaging_requests'));
    }

    public function admissions()
    {
        $patient = $this->patient();
        $admissions = Admission::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with(['ward', 'bed'])->orderBy('admission_date', 'desc')->paginate(15);

        return view('patient_portal.admissions')->with(compact('patient', 'admissions'));
    }

    public function maternity()
    {
        $patient = $this->patient();
        $records = AntenatalRecord::forBusiness($patient->business_id)->where('contact_id', $patient->id)
            ->with(['antenatalVisits.attendedBy', 'delivery.baby'])->orderBy('created_at', 'desc')->get();

        if ($records->isEmpty()) {
            return redirect()->route('portal.dashboard');
        }

        return view('patient_portal.maternity')->with(compact('patient', 'records'));
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

        return view('patient_portal.bills')->with(compact('patient', 'due', 'charges', 'total_billed', 'total_paid'));
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
        ]);

        if (! empty($data['mobile'])) {
            $patient->mobile = $data['mobile'];
            $patient->save();
        }

        \App\PatientDetail::updateOrCreate(
            ['contact_id' => $patient->id],
            [
                'business_id' => $patient->business_id,
                'next_of_kin_name' => $data['next_of_kin_name'] ?? null,
                'next_of_kin_phone' => $data['next_of_kin_phone'] ?? null,
                'next_of_kin_relationship' => $data['next_of_kin_relationship'] ?? null,
                'known_allergies' => $data['known_allergies'] ?? null,
            ]
        );

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
