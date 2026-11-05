<?php

namespace App\Http\Controllers;

use App\Admission;
use App\ConsultationPrescription;
use App\MedicationAdministration;
use App\NursingNote;
use App\NursingVital;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;

/**
 * Nursing (hospital module): bedside vitals charting, the Medication
 * Administration Record (MAR), and nursing/shift notes for an admitted patient.
 * All actions are scoped to one admission and gated by the relevant
 * nursing.* permission.
 */
class NursingController extends Controller
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
        //PosInnova: section switched off in Clinic settings
        if (! \App\Utils\ClinicSettings::sectionOn(request()->session()->get('user.business_id'), 'wards')) {
            abort(404);
        }
    }

    private function admissionOrFail($admission_id)
    {
        $business_id = request()->session()->get('user.business_id');

        return Admission::forBusiness($business_id)->findOrFail($admission_id);
    }

    /* ---------------- Vitals ---------------- */

    public function storeVitals(Request $request, $admission_id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('nursing.chart_vitals')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $admission = $this->admissionOrFail($admission_id);

            NursingVital::create([
                'business_id' => $admission->business_id,
                'admission_id' => $admission->id,
                'contact_id' => $admission->contact_id,
                'observed_at' => $request->filled('observed_at') ? $request->input('observed_at') : now(),
                'temperature' => $request->filled('temperature') ? $request->input('temperature') : null,
                'pulse' => $request->input('pulse') ?: null,
                'respiratory_rate' => $request->input('respiratory_rate') ?: null,
                'bp_systolic' => $request->input('bp_systolic') ?: null,
                'bp_diastolic' => $request->input('bp_diastolic') ?: null,
                'spo2' => $request->input('spo2') ?: null,
                'pain_score' => $request->input('pain_score') !== null && $request->input('pain_score') !== '' ? $request->input('pain_score') : null,
                'weight' => $request->filled('weight') ? $request->input('weight') : null,
                'blood_sugar' => $request->input('blood_sugar') ?: null,
                'notes' => $request->input('notes') ?: null,
                'recorded_by' => $request->session()->get('user.id'),
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.vitals_recorded_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$admission_id])
            ->with('status', $output)
            ->with('open_tab', 'adm_vitals');
    }

    /* ---------------- MAR ---------------- */

    public function recordMedication(Request $request, $admission_id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('nursing.record_medication')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'drug_name' => 'required|string|max:191',
            'status' => 'required|in:given,refused,held,missed',
        ]);

        try {
            $admission = $this->admissionOrFail($admission_id);

            MedicationAdministration::create([
                'business_id' => $admission->business_id,
                'admission_id' => $admission->id,
                'contact_id' => $admission->contact_id,
                'consultation_prescription_id' => $request->input('consultation_prescription_id') ?: null,
                'product_id' => $request->input('product_id') ?: null,
                'drug_name' => $request->input('drug_name'),
                'dose' => $request->input('dose') ?: null,
                'route' => $request->input('route') ?: null,
                'administered_at' => $request->filled('administered_at') ? $request->input('administered_at') : now(),
                'status' => $request->input('status'),
                'notes' => $request->input('notes') ?: null,
                'administered_by' => $request->session()->get('user.id'),
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.medication_recorded_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$admission_id])
            ->with('status', $output)
            ->with('open_tab', 'adm_mar');
    }

    /* ---------------- Notes ---------------- */

    public function storeNote(Request $request, $admission_id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('nursing.add_notes')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['note' => 'required|string']);

        try {
            $admission = $this->admissionOrFail($admission_id);

            NursingNote::create([
                'business_id' => $admission->business_id,
                'admission_id' => $admission->id,
                'contact_id' => $admission->contact_id,
                'shift' => $request->input('shift') ?: null,
                'note_type' => $request->input('note_type') ?: 'general',
                'note' => $request->input('note'),
                'noted_at' => $request->filled('noted_at') ? $request->input('noted_at') : now(),
                'recorded_by' => $request->session()->get('user.id'),
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.nursing_note_added_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\AdmissionController::class, 'show'], [$admission_id])
            ->with('status', $output)
            ->with('open_tab', 'adm_notes');
    }

    /* ---------------- Data assembly (used by AdmissionController::show) ---------------- */

    /**
     * Assemble all nursing data + derived KPIs/series for an admission, so the
     * admission view can render the vitals chart, MAR and notes in one pass.
     */
    public static function nursingData(Admission $admission)
    {
        $business_id = $admission->business_id;

        $vitals = NursingVital::where('admission_id', $admission->id)
            ->orderBy('observed_at')
            ->get();

        $mar = MedicationAdministration::where('admission_id', $admission->id)
            ->with('administeredBy')
            ->orderBy('administered_at', 'desc')
            ->get();

        $notes = NursingNote::where('admission_id', $admission->id)
            ->with('recordedBy')
            ->orderBy('noted_at', 'desc')
            ->get();

        //Active prescriptions for this patient (the MAR's "ordered drugs").
        $prescriptions = ConsultationPrescription::whereHas('consultation', function ($q) use ($business_id, $admission) {
            $q->where('business_id', $business_id)->where('contact_id', $admission->contact_id);
        })
            ->with('product')
            ->orderBy('created_at', 'desc')
            ->get();

        //Latest vitals + KPI snapshot.
        $latest = $vitals->last();

        //Trend series for the SVG sparklines (chronological numeric arrays).
        $series = [
            'temperature' => $vitals->map(fn ($v) => ['t' => $v->observed_at->format('d/m H:i'), 'y' => $v->temperature !== null ? (float) $v->temperature : null]),
            'pulse' => $vitals->map(fn ($v) => ['t' => $v->observed_at->format('d/m H:i'), 'y' => $v->pulse]),
            'spo2' => $vitals->map(fn ($v) => ['t' => $v->observed_at->format('d/m H:i'), 'y' => $v->spo2]),
            'bp_systolic' => $vitals->map(fn ($v) => ['t' => $v->observed_at->format('d/m H:i'), 'y' => $v->bp_systolic]),
        ];

        return [
            'vitals' => $vitals,
            'mar' => $mar,
            'notes' => $notes,
            'prescriptions' => $prescriptions,
            'latest_vital' => $latest,
            'vital_series' => $series,
            'kpis' => [
                'meds_given_today' => $mar->where('status', 'given')->filter(fn ($m) => $m->administered_at->isToday())->count(),
                'vitals_today' => $vitals->filter(fn ($v) => $v->observed_at->isToday())->count(),
                'abnormal_latest' => $latest ? $latest->is_abnormal : false,
                'notes_count' => $notes->count(),
            ],
        ];
    }
}
