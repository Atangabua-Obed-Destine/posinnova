<?php

namespace App\Http\Controllers;

use App\Admission;
use App\BusinessLocation;
use App\Consultation;
use App\LabRequest;
use App\MorgueRecord;
use App\Ward;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Hospital reporting (hospital module). A single controller exposing the
 * hospital dashboard plus a set of operational/clinical/financial reports.
 * Each report mirrors the system's existing filter-panel + DataTables pattern;
 * AJAX requests return the DataTables JSON (with server-side summary extras),
 * non-AJAX requests render the report's filter view.
 */
class HospitalReportController extends Controller
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

    /**
     * Parse start/end date filters (Y-m-d) into a [start, end] day range or null.
     */
    private function dateRange(Request $request)
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [$request->input('start_date').' 00:00:00', $request->input('end_date').' 23:59:59'];
        }

        return null;
    }

    /* ====================== DASHBOARD ====================== */

    public function dashboard()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('hospital_report.dashboard')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $today = \Carbon::today();
        $monthStart = \Carbon::now()->startOfMonth();

        //OPD today = consultations created today.
        $opd_today = Consultation::forBusiness($business_id)->whereDate('created_at', $today)->count();

        //Current inpatients.
        $current_inpatients = Admission::forBusiness($business_id)->active()->count();

        //Bed availability.
        $total_beds = DB::table('beds')->where('business_id', $business_id)->count();
        $occupied_beds = DB::table('beds')->where('business_id', $business_id)->where('status', 'occupied')->count();
        $available_beds = $total_beds - $occupied_beds;
        $occupancy_pct = $total_beds > 0 ? round(($occupied_beds / $total_beds) * 100) : 0;

        //Bodies in morgue.
        $morgue_in_storage = MorgueRecord::forBusiness($business_id)->where('status', 'in_storage')->count();

        //Pending lab results.
        $pending_labs = LabRequest::forBusiness($business_id)->whereIn('status', ['pending', 'sample_collected', 'processing'])->count();

        //Today's & month's revenue: what was billed, from the real invoices (PosInnova clinic
        //reports; the visit_charges index left out medicines and counted insurer shares twice).
        $billed = function ($from) use ($business_id, $today) {
            $r = \App\Utils\ClinicReports::income($business_id, ['from' => $from->copy()->startOfDay(), 'to' => $today->copy(), 'doctor_id' => null, 'q' => '']);

            return $r['tiles'][0]['value'];
        };
        $revenue_today = $billed($today);
        $revenue_month = $billed($monthStart);

        //Deliveries this month.
        $deliveries_month = DB::table('delivery_records')->where('business_id', $business_id)->whereNull('deleted_at')->where('delivery_date', '>=', $monthStart)->count();

        //Theatre scheduled (upcoming).
        $theatre_scheduled = DB::table('theatre_bookings')->where('business_id', $business_id)->whereNull('deleted_at')->where('status', 'scheduled')->count();

        //Patients registered this month.
        $patients_month = DB::table('contacts')->where('business_id', $business_id)->where('is_patient', 1)->whereNull('deleted_at')->where('created_at', '>=', $monthStart)->count();

        //14-day OPD trend (for a sparkline).
        $opd_trend = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = \Carbon::today()->subDays($i);
            $opd_trend[] = [
                't' => $day->format('d/m'),
                'y' => Consultation::forBusiness($business_id)->whereDate('created_at', $day)->count(),
            ];
        }

        //Per-ward occupancy bars.
        $wards = Ward::forBusiness($business_id)->active()->withCount(['beds', 'activeAdmissions'])->orderBy('name')->get();

        //Critical/abnormal lab values resulted in the last 48h — clinical safety alert.
        $critical_labs = DB::table('lab_request_lines as l')
            ->join('lab_requests as lr', 'l.lab_request_id', '=', 'lr.id')
            ->leftjoin('contacts as c', 'lr.contact_id', '=', 'c.id')
            ->leftjoin('users as u', 'lr.requested_by', '=', 'u.id')
            ->where('lr.business_id', $business_id)
            ->whereIn('l.result_flag', ['high', 'low', 'critical'])
            ->where('l.resulted_at', '>=', \Carbon::now()->subHours(48))
            ->orderByRaw("FIELD(l.result_flag,'critical','high','low')")
            ->orderByDesc('l.resulted_at')
            ->select(['lr.id as lab_request_id', 'lr.ref_no', 'l.test_name', 'l.result_value', 'l.result_flag',
                'c.name as patient_name', DB::raw("CONCAT(COALESCE(u.surname,''),' ',COALESCE(u.first_name,'')) as doctor")])
            ->limit(15)->get();

        $kpis = compact(
            'opd_today', 'current_inpatients', 'total_beds', 'occupied_beds', 'available_beds',
            'occupancy_pct', 'morgue_in_storage', 'pending_labs', 'revenue_today', 'revenue_month',
            'deliveries_month', 'theatre_scheduled', 'patients_month'
        );

        return view('report.hospital.dashboard')->with(compact('kpis', 'opd_trend', 'wards', 'critical_labs'));
    }

    /* ====================== OPD ====================== */

    public function opd(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('hospital_report.opd')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = Consultation::leftjoin('contacts AS c', 'consultations.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->leftjoin('users AS u', 'consultations.consulted_by', '=', 'u.id')
                ->where('consultations.business_id', $business_id);

            if ($range) {
                $base->whereBetween('consultations.created_at', $range);
            }
            if ($request->filled('consultation_type')) {
                $base->where('consultations.consultation_type', $request->input('consultation_type'));
            }
            if ($request->filled('doctor_id')) {
                $base->where('consultations.consulted_by', $request->input('doctor_id'));
            }

            $query = (clone $base)->select([
                'consultations.id',
                'consultations.created_at',
                'consultations.ref_no',
                'consultations.consultation_type',
                'consultations.specialty',
                'consultations.diagnosis',
                'consultations.consultation_fee',
                'c.name as patient_name',
                'pd.file_number',
                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, '')) as doctor_name"),
            ]);

            $totals = (clone $base)->selectRaw('COUNT(*) as cnt, COALESCE(SUM(consultations.consultation_fee),0) as fees, COUNT(DISTINCT consultations.contact_id) as patients')->first();

            return DataTables::of($query)
                ->editColumn('created_at', '{{ @format_date($created_at) }}')
                ->editColumn('file_number', fn ($r) => $r->file_number ?: '-')
                ->editColumn('consultation_type', fn ($r) => __('lang_v1.consultation_type_'.$r->consultation_type).($r->specialty ? ' · '.$r->specialty : ''))
                ->editColumn('diagnosis', fn ($r) => \Illuminate\Support\Str::limit($r->diagnosis, 60) ?: '-')
                ->editColumn('consultation_fee', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->consultation_fee.'</span>')
                ->removeColumn('id')->removeColumn('specialty')
                ->rawColumns(['consultation_fee'])
                ->with([
                    'total_consultations' => (int) $totals->cnt,
                    'total_fees' => (float) $totals->fees,
                    'unique_patients' => (int) $totals->patients,
                ])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);
        $doctors = \App\User::forDropdown($business_id, false)->prepend(__('lang_v1.all'), '');

        return view('report.hospital.opd')->with(compact('business_locations', 'doctors'));
    }

    /* ====================== ADMISSIONS ====================== */

    public function admissions(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! \App\Utils\ClinicSettings::sectionOn($request->session()->get('user.business_id'), 'wards')) {
            abort(404);
        }
        if (! auth()->user()->can('hospital_report.admissions')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = Admission::leftjoin('contacts AS c', 'admissions.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->leftjoin('wards AS w', 'admissions.ward_id', '=', 'w.id')
                ->leftjoin('beds AS b', 'admissions.bed_id', '=', 'b.id')
                ->where('admissions.business_id', $business_id);

            if ($range) {
                $base->whereBetween('admissions.admission_date', $range);
            }
            if ($request->filled('ward_id')) {
                $base->where('admissions.ward_id', $request->input('ward_id'));
            }
            if ($request->filled('status')) {
                $base->where('admissions.status', $request->input('status'));
            }

            $query = (clone $base)->select([
                'admissions.id', 'admissions.ref_no', 'admissions.admission_date', 'admissions.discharge_date',
                'admissions.status', 'c.name as patient_name', 'pd.file_number', 'w.name as ward_name', 'b.bed_number',
                DB::raw('GREATEST(1, DATEDIFF(COALESCE(admissions.discharge_date, NOW()), admissions.admission_date)) as days_stayed'),
            ]);

            $stats = (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN admissions.status='active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN admissions.status='discharged' AND DATE(admissions.discharge_date)=CURDATE() THEN 1 ELSE 0 END) as discharged_today,
                AVG(GREATEST(1, DATEDIFF(COALESCE(admissions.discharge_date, NOW()), admissions.admission_date))) as avg_los
            ")->first();

            return DataTables::of($query)
                ->editColumn('admission_date', '{{ @format_datetime($admission_date) }}')
                ->editColumn('discharge_date', fn ($r) => $r->discharge_date ? \Carbon::parse($r->discharge_date)->format(session('business.date_format', 'd-m-Y').' H:i') : '-')
                ->editColumn('file_number', fn ($r) => $r->file_number ?: '-')
                ->editColumn('bed_number', fn ($r) => $r->bed_number ?: '-')
                ->editColumn('status', fn ($r) => '<span class="label '.(['active' => 'label-success', 'discharged' => 'label-default', 'transferred' => 'label-info'][$r->status] ?? 'label-default').'">'.__('lang_v1.admission_status_'.$r->status).'</span>')
                ->removeColumn('id')
                ->rawColumns(['status'])
                ->with([
                    'total_admissions' => (int) $stats->total,
                    'current_inpatients' => (int) $stats->active_count,
                    'discharged_today' => (int) $stats->discharged_today,
                    'avg_los' => round((float) $stats->avg_los, 1),
                ])
                ->make(true);
        }

        $wards = Ward::forDropdown($business_id, true);

        return view('report.hospital.admissions')->with(compact('wards'));
    }

    /* ====================== LAB ====================== */

    public function lab(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('hospital_report.lab')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = LabRequest::leftjoin('contacts AS c', 'lab_requests.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->where('lab_requests.business_id', $business_id);

            if ($range) {
                $base->whereBetween('lab_requests.requested_at', $range);
            }
            if ($request->filled('status')) {
                $base->where('lab_requests.status', $request->input('status'));
            }

            $query = (clone $base)->select([
                'lab_requests.id', 'lab_requests.ref_no', 'lab_requests.status',
                'lab_requests.requested_at', 'lab_requests.resulted_at',
                'c.name as patient_name', 'pd.file_number',
                DB::raw('(SELECT COUNT(*) FROM lab_request_lines WHERE lab_request_lines.lab_request_id = lab_requests.id) as tests_count'),
                DB::raw('TIMESTAMPDIFF(HOUR, lab_requests.requested_at, lab_requests.resulted_at) as turnaround_hours'),
            ]);

            $stats = (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN lab_requests.status IN ('pending','sample_collected','processing') THEN 1 ELSE 0 END) as pending,
                AVG(CASE WHEN lab_requests.resulted_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, lab_requests.requested_at, lab_requests.resulted_at) END) as avg_tat
            ")->first();

            return DataTables::of($query)
                ->editColumn('requested_at', '{{ @format_datetime($requested_at) }}')
                ->editColumn('resulted_at', fn ($r) => $r->resulted_at ? \Carbon::parse($r->resulted_at)->format(session('business.date_format', 'd-m-Y').' H:i') : '-')
                ->editColumn('file_number', fn ($r) => $r->file_number ?: '-')
                ->editColumn('turnaround_hours', fn ($r) => ! is_null($r->turnaround_hours) ? $r->turnaround_hours.'h' : '-')
                ->editColumn('status', fn ($r) => '<span class="label '.(['validated' => 'label-success', 'resulted' => 'label-primary', 'processing' => 'label-warning'][$r->status] ?? 'label-default').'">'.__('lang_v1.lab_status_'.$r->status).'</span>')
                ->removeColumn('id')
                ->rawColumns(['status'])
                ->with([
                    'total_requests' => (int) $stats->total,
                    'pending_requests' => (int) $stats->pending,
                    'avg_turnaround' => ! is_null($stats->avg_tat) ? round((float) $stats->avg_tat, 1) : 0,
                ])
                ->make(true);
        }

        return view('report.hospital.lab');
    }

    /* ====================== WARD CONSUMPTION ====================== */

    public function ward(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! \App\Utils\ClinicSettings::sectionOn($request->session()->get('user.business_id'), 'wards')) {
            abort(404);
        }
        if (! auth()->user()->can('hospital_report.ward')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            //Ward dispenses are visit_charges of type ward_dispense, grouped by ward
            //via the admission they belong to.
            $base = DB::table('visit_charges as vc')
                ->leftjoin('admissions as a', 'vc.admission_id', '=', 'a.id')
                ->leftjoin('wards as w', 'a.ward_id', '=', 'w.id')
                ->where('vc.business_id', $business_id)
                ->where('vc.charge_type', 'ward_dispense');

            if ($range) {
                $base->whereBetween('vc.created_at', $range);
            }
            if ($request->filled('ward_id')) {
                $base->where('a.ward_id', $request->input('ward_id'));
            }

            $query = (clone $base)
                ->select([
                    DB::raw('COALESCE(w.name, "—") as ward_name'),
                    DB::raw('COUNT(vc.id) as dispense_count'),
                    DB::raw('COALESCE(SUM(vc.amount),0) as total_amount'),
                ])
                ->groupBy('w.name');

            $grand = (clone $base)->selectRaw('COALESCE(SUM(vc.amount),0) as total, COUNT(vc.id) as cnt')->first();

            return DataTables::of($query)
                ->editColumn('total_amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->total_amount.'</span>')
                ->rawColumns(['total_amount'])
                ->with([
                    'grand_total' => (float) $grand->total,
                    'grand_count' => (int) $grand->cnt,
                ])
                ->make(true);
        }

        $wards = Ward::forDropdown($business_id, true);

        return view('report.hospital.ward')->with(compact('wards'));
    }

    /* ====================== REVENUE ====================== */

    public function revenue(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('hospital_report.revenue')) {
            abort(403, 'Unauthorized action.');
        }

        //PosInnova: replaced by Clinic reports > Income by doctor and service, which reads the real
        //invoices (this one read visit_charges: every charge "due", medicines missing).
        if (! $request->ajax()) {
            return redirect()->action([\App\Http\Controllers\ClinicReportController::class, 'show'], ['income']);
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = DB::table('visit_charges as vc')
                ->leftjoin('contacts as c', 'vc.contact_id', '=', 'c.id')
                ->where('vc.business_id', $business_id);

            if ($range) {
                $base->whereBetween('vc.created_at', $range);
            }
            if ($request->filled('charge_type')) {
                $base->where('vc.charge_type', $request->input('charge_type'));
            }

            $query = (clone $base)->select([
                'vc.id', 'vc.created_at', 'vc.charge_type', 'vc.description', 'vc.amount', 'vc.is_paid',
                'c.name as patient_name',
            ]);

            //By-type breakdown for the summary block.
            $by_type = (clone $base)->select('vc.charge_type', DB::raw('COALESCE(SUM(vc.amount),0) as total'))
                ->groupBy('vc.charge_type')->get()
                ->map(fn ($r) => ['charge_type' => __('lang_v1.charge_type_'.$r->charge_type), 'total' => (float) $r->total]);

            $grand = (clone $base)->selectRaw('COALESCE(SUM(vc.amount),0) as total')->first();

            return DataTables::of($query)
                ->editColumn('created_at', '{{ @format_date($created_at) }}')
                ->editColumn('charge_type', fn ($r) => __('lang_v1.charge_type_'.$r->charge_type))
                ->editColumn('amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->amount.'</span>')
                ->editColumn('is_paid', fn ($r) => $r->is_paid ? '<span class="label label-success">'.__('lang_v1.paid').'</span>' : '<span class="label label-warning">'.__('lang_v1.due').'</span>')
                ->removeColumn('id')
                ->rawColumns(['amount', 'is_paid'])
                ->with([
                    'grand_total' => (float) $grand->total,
                    'by_type' => $by_type,
                ])
                ->make(true);
        }

        return view('report.hospital.revenue');
    }

    /* ====================== MORTALITY REGISTER ====================== */

    public function mortality(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! \App\Utils\ClinicSettings::sectionOn($request->session()->get('user.business_id'), 'morgue')) {
            abort(404);
        }
        if (! auth()->user()->can('hospital_report.mortality')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = MorgueRecord::leftjoin('users AS u', 'morgue_records.certified_by', '=', 'u.id')
                ->where('morgue_records.business_id', $business_id);

            if ($range) {
                $base->whereBetween('morgue_records.date_of_death', $range);
            }
            if ($request->filled('source')) {
                $base->where('morgue_records.source', $request->input('source'));
            }

            $query = (clone $base)->select([
                'morgue_records.id', 'morgue_records.ref_no', 'morgue_records.deceased_name',
                'morgue_records.deceased_age', 'morgue_records.deceased_sex', 'morgue_records.date_of_death',
                'morgue_records.cause_of_death', 'morgue_records.source',
                DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, '')) as certified_by_name"),
            ]);

            $stats = (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN morgue_records.deceased_sex='M' THEN 1 ELSE 0 END) as males,
                SUM(CASE WHEN morgue_records.deceased_sex='F' THEN 1 ELSE 0 END) as females
            ")->first();

            return DataTables::of($query)
                ->editColumn('date_of_death', '{{ @format_datetime($date_of_death) }}')
                ->editColumn('deceased_age', fn ($r) => $r->deceased_age ?? '-')
                ->editColumn('deceased_sex', fn ($r) => $r->deceased_sex ?: '-')
                ->editColumn('cause_of_death', fn ($r) => $r->cause_of_death ?: '-')
                ->editColumn('source', fn ($r) => __('lang_v1.source_'.$r->source))
                ->removeColumn('id')
                ->with([
                    'total_deaths' => (int) $stats->total,
                    'males' => (int) $stats->males,
                    'females' => (int) $stats->females,
                ])
                ->make(true);
        }

        return view('report.hospital.mortality');
    }

    /* ====================== ANTENATAL ====================== */

    public function antenatal(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! \App\Utils\ClinicSettings::sectionOn($request->session()->get('user.business_id'), 'maternity')) {
            abort(404);
        }
        if (! auth()->user()->can('hospital_report.antenatal')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = DB::table('antenatal_records as ar')
                ->leftjoin('contacts as c', 'ar.contact_id', '=', 'c.id')
                ->leftjoin('patient_details as pd', 'c.id', '=', 'pd.contact_id')
                ->where('ar.business_id', $business_id)
                ->whereNull('ar.deleted_at');

            if ($range) {
                $base->whereBetween('ar.created_at', $range);
            }
            if ($request->filled('status')) {
                $base->where('ar.status', $request->input('status'));
            }

            $query = (clone $base)->select([
                'ar.id', 'ar.ref_no', 'ar.lmp', 'ar.edd', 'ar.status',
                'c.name as mother_name', 'pd.file_number',
                DB::raw('(SELECT COUNT(*) FROM antenatal_visits WHERE antenatal_visits.antenatal_record_id = ar.id) as anc_count'),
            ]);

            $stats = (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN ar.status='active' THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN ar.status='delivered' THEN 1 ELSE 0 END) as delivered_count
            ")->first();

            //Deliveries within the range (separate table).
            $deliv = DB::table('delivery_records')->where('business_id', $business_id)->whereNull('deleted_at');
            if ($range) {
                $deliv->whereBetween('delivery_date', $range);
            }
            $cs_count = (clone $deliv)->where('delivery_type', 'cs')->count();
            $deliveries_total = (clone $deliv)->count();

            return DataTables::of($query)
                ->editColumn('lmp', fn ($r) => $r->lmp ? \Carbon::parse($r->lmp)->format(session('business.date_format', 'd-m-Y')) : '-')
                ->editColumn('edd', fn ($r) => $r->edd ? \Carbon::parse($r->edd)->format(session('business.date_format', 'd-m-Y')) : '-')
                ->editColumn('file_number', fn ($r) => $r->file_number ?: '-')
                ->editColumn('status', fn ($r) => '<span class="label '.(['active' => 'label-success', 'delivered' => 'label-info'][$r->status] ?? 'label-default').'">'.__('lang_v1.anc_status_'.$r->status).'</span>')
                ->removeColumn('id')
                ->rawColumns(['status'])
                ->with([
                    'total_pregnancies' => (int) $stats->total,
                    'active_pregnancies' => (int) $stats->active_count,
                    'deliveries' => (int) $deliveries_total,
                    'cs_rate' => $deliveries_total > 0 ? round(($cs_count / $deliveries_total) * 100) : 0,
                ])
                ->make(true);
        }

        return view('report.hospital.antenatal');
    }

    /* ====================== MORGUE ====================== */

    public function morgue(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! \App\Utils\ClinicSettings::sectionOn($request->session()->get('user.business_id'), 'morgue')) {
            abort(404);
        }
        if (! auth()->user()->can('hospital_report.morgue')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = MorgueRecord::where('morgue_records.business_id', $business_id);

            if ($range) {
                $base->whereBetween('morgue_records.received_at', $range);
            }
            if ($request->filled('status')) {
                $base->where('morgue_records.status', $request->input('status'));
            }

            $query = (clone $base)->select([
                'morgue_records.id', 'morgue_records.ref_no', 'morgue_records.deceased_name',
                'morgue_records.received_at', 'morgue_records.released_at', 'morgue_records.status',
                'morgue_records.legal_hold', 'morgue_records.total_fee_charged', 'morgue_records.family_contact_name',
                DB::raw('GREATEST(1, DATEDIFF(COALESCE(morgue_records.released_at, NOW()), morgue_records.received_at)) as days_stored'),
            ]);

            $stats = (clone $base)->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN morgue_records.status='in_storage' THEN 1 ELSE 0 END) as in_storage,
                COALESCE(SUM(morgue_records.total_fee_charged),0) as fees
            ")->first();

            return DataTables::of($query)
                ->editColumn('received_at', '{{ @format_datetime($received_at) }}')
                ->editColumn('released_at', fn ($r) => $r->released_at ? \Carbon::parse($r->released_at)->format(session('business.date_format', 'd-m-Y').' H:i') : '-')
                ->editColumn('total_fee_charged', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->total_fee_charged.'</span>')
                ->editColumn('status', function ($r) {
                    $html = '<span class="label '.($r->status == 'released' ? 'label-success' : 'label-warning').'">'.__('lang_v1.morgue_status_'.$r->status).'</span>';
                    if ($r->legal_hold) {
                        $html .= ' <span class="label label-danger">'.__('lang_v1.legal_hold').'</span>';
                    }

                    return $html;
                })
                ->removeColumn('id')->removeColumn('legal_hold')
                ->rawColumns(['status', 'total_fee_charged'])
                ->with([
                    'total_bodies' => (int) $stats->total,
                    'in_storage' => (int) $stats->in_storage,
                    'total_fees' => (float) $stats->fees,
                ])
                ->make(true);
        }

        return view('report.hospital.morgue');
    }

    /* ====================== INSURANCE CLAIMS ====================== */

    public function mutualClaims(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('hospital_report.revenue')) {
            abort(403, 'Unauthorized action.');
        }

        //PosInnova: insurance now works through insurers and claims (Billing > Insurance claims,
        //and Clinic reports > Insurers); this report read the old per-patient insurance fields.
        if (! $request->ajax()) {
            return redirect()->action([\App\Http\Controllers\ClinicReportController::class, 'show'], ['insurers']);
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->ajax()) {
            $range = $this->dateRange($request);

            $base = DB::table('visit_charges as vc')
                ->leftjoin('contacts as c', 'vc.contact_id', '=', 'c.id')
                ->leftjoin('patient_details as pd', 'c.id', '=', 'pd.contact_id')
                ->where('vc.business_id', $business_id)
                ->where('vc.insurance_covered', '>', 0);

            if ($range) {
                $base->whereBetween('vc.created_at', $range);
            }
            if ($request->filled('provider')) {
                $base->where('pd.insurance_provider', $request->input('provider'));
            }

            $query = (clone $base)->select([
                'vc.id', 'vc.created_at', 'vc.description', 'vc.amount', 'vc.insurance_covered', 'vc.patient_owes',
                'c.name as patient_name', 'pd.insurance_provider', 'pd.insurance_number',
            ]);

            $by_provider = (clone $base)->select('pd.insurance_provider',
                DB::raw('COUNT(*) as cnt'), DB::raw('COALESCE(SUM(vc.insurance_covered),0) as total'))
                ->groupBy('pd.insurance_provider')->get()
                ->map(fn ($r) => ['provider' => $r->insurance_provider ?: '—', 'cnt' => (int) $r->cnt, 'total' => (float) $r->total]);

            $grand = (clone $base)->selectRaw('COALESCE(SUM(vc.insurance_covered),0) as total, COUNT(*) as cnt')->first();

            return DataTables::of($query)
                ->editColumn('created_at', '{{ @format_date($created_at) }}')
                ->editColumn('insurance_provider', fn ($r) => $r->insurance_provider ?: '—')
                ->editColumn('amount', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->amount.'</span>')
                ->editColumn('insurance_covered', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->insurance_covered.'</span>')
                ->editColumn('patient_owes', fn ($r) => '<span class="display_currency" data-currency_symbol="true">'.$r->patient_owes.'</span>')
                ->removeColumn('id')
                ->rawColumns(['amount', 'insurance_covered', 'patient_owes'])
                ->with([
                    'grand_total' => (float) $grand->total,
                    'claim_count' => (int) $grand->cnt,
                    'by_provider' => $by_provider,
                ])
                ->make(true);
        }

        return view('report.hospital.mutual_claims');
    }
}
