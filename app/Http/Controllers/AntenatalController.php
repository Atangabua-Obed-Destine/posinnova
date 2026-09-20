<?php

namespace App\Http\Controllers;

use App\AntenatalRecord;
use App\AntenatalVisit;
use App\Contact;
use App\User;
use App\Utils\HospitalBillingUtil;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Antenatal care (hospital module, maternity). Tracks a pregnancy and its ANC
 * visits; each ANC visit fee is billed to the mother (Phase 8 billing reuse).
 */
class AntenatalController extends Controller
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

        if (! auth()->user()->can('antenatal.view') && ! auth()->user()->can('antenatal.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = AntenatalRecord::leftjoin('contacts AS c', 'antenatal_records.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->where('antenatal_records.business_id', $business_id)
                ->select([
                    'antenatal_records.id',
                    'antenatal_records.ref_no',
                    'antenatal_records.lmp',
                    'antenatal_records.edd',
                    'antenatal_records.status',
                    'c.name as mother_name',
                    'pd.file_number',
                    DB::raw('(SELECT COUNT(*) FROM antenatal_visits WHERE antenatal_visits.antenatal_record_id = antenatal_records.id) as visits_count'),
                ]);

            if (! empty(request()->input('status'))) {
                $query->where('antenatal_records.status', request()->input('status'));
            }

            return DataTables::of($query)
                ->editColumn('lmp', function ($row) {
                    return $row->lmp ? \Carbon::parse($row->lmp)->format(session('business.date_format', 'd-m-Y')) : '-';
                })
                ->editColumn('edd', function ($row) {
                    return $row->edd ? \Carbon::parse($row->edd)->format(session('business.date_format', 'd-m-Y')) : '-';
                })
                ->editColumn('file_number', function ($row) {
                    return $row->file_number ?: '-';
                })
                ->editColumn('status', function ($row) {
                    $map = ['active' => 'label-success', 'delivered' => 'label-info', 'terminated' => 'label-default'];

                    return '<span class="label '.($map[$row->status] ?? 'label-default').'">'.__('lang_v1.anc_status_'.$row->status).'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<a href="'.action([\App\Http\Controllers\AntenatalController::class, 'show'], [$row->id]).'" class="btn btn-xs btn-info"><i class="fas fa-eye"></i> '.__('messages.view').'</a>';

                    return $html;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('maternity.antenatal_index');
    }

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('antenatal.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\AntenatalController::class, 'index']));
        }

        $preselected_patient = null;
        if ($request->filled('contact_id')) {
            $preselected_patient = Contact::where('business_id', $business_id)->onlyPatients()->with('patientDetails')->find($request->input('contact_id'));
        }

        return view('maternity.antenatal_create')->with(compact('preselected_patient'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('antenatal.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['contact_id' => 'required']);

        try {
            $business_id = $request->session()->get('user.business_id');

            $mother = Contact::where('business_id', $business_id)->onlyPatients()->findOrFail($request->input('contact_id'));

            //Block duplicate active pregnancy.
            $active = AntenatalRecord::forBusiness($business_id)->where('contact_id', $mother->id)->where('status', 'active')->exists();
            if ($active) {
                return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('lang_v1.active_pregnancy_exists')]);
            }

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('antenatal', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('antenatal', $ref_count, $business_id, 'ANC-');

            $lmp = $request->filled('lmp') ? $request->input('lmp') : null;

            AntenatalRecord::create([
                'business_id' => $business_id,
                'contact_id' => $mother->id,
                'ref_no' => $ref_no,
                'lmp' => $lmp,
                'edd' => $lmp ? AntenatalRecord::eddFromLmp($lmp) : null,
                'gravida' => $request->input('gravida') ?: null,
                'para' => $request->input('para') ?: null,
                'blood_group_mother' => $request->input('blood_group_mother') ?: null,
                'blood_group_father' => $request->input('blood_group_father') ?: null,
                'hiv_status' => $request->input('hiv_status') ?: null,
                'status' => 'active',
                'notes' => $request->input('notes') ?: null,
                'created_by' => $request->session()->get('user.id'),
            ]);

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.antenatal_created_success')];

            return redirect()->action([\App\Http\Controllers\AntenatalController::class, 'index'])->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('antenatal.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $record = AntenatalRecord::forBusiness($business_id)
            ->with(['mother.patientDetails', 'antenatalVisits.attendedBy', 'delivery'])
            ->findOrFail($id);

        return view('maternity.antenatal_show')->with(compact('record'));
    }

    public function recordVisit(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('antenatal.update')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['visit_date' => 'required']);

        try {
            $business_id = $request->session()->get('user.business_id');
            $record = AntenatalRecord::forBusiness($business_id)->findOrFail($id);

            DB::beginTransaction();

            $next_number = (int) AntenatalVisit::where('antenatal_record_id', $record->id)->max('visit_number') + 1;

            $visit = AntenatalVisit::create([
                'antenatal_record_id' => $record->id,
                'visit_number' => $next_number,
                'visit_date' => $request->input('visit_date'),
                'attended_by' => $request->session()->get('user.id'),
                'gestational_age_weeks' => $request->input('gestational_age_weeks') ?: null,
                'weight' => $request->filled('weight') ? $request->input('weight') : null,
                'bp_systolic' => $request->input('bp_systolic') ?: null,
                'bp_diastolic' => $request->input('bp_diastolic') ?: null,
                'fundal_height' => $request->filled('fundal_height') ? $request->input('fundal_height') : null,
                'fetal_heart_rate' => $request->input('fetal_heart_rate') ?: null,
                'presentation' => $request->input('presentation') ?: null,
                'complications' => $request->input('complications') ?: null,
                'next_visit_date' => $request->filled('next_visit_date') ? $request->input('next_visit_date') : null,
                'notes' => $request->input('notes') ?: null,
                'fee' => $request->filled('fee') ? $this->commonUtil->num_uf($request->input('fee')) : 0,
            ]);

            //Bill the ANC visit fee to the mother (reuse Phase 8 billing).
            if ($visit->fee > 0) {
                $location_id = \App\BusinessLocation::where('business_id', $business_id)->value('id');
                if ($location_id) {
                    (new HospitalBillingUtil())->chargePatientFee([
                        'business_id' => $business_id,
                        'contact_id' => $record->contact_id,
                        'amount' => (float) $visit->fee,
                        'charge_type' => 'anc',
                        'description' => __('lang_v1.anc_visit').' #'.$next_number.' · '.$record->ref_no,
                        'location_id' => $location_id,
                        'visit_id' => null,
                        'admission_id' => null,
                        'created_by' => $request->session()->get('user.id'),
                        'reference_type' => 'antenatal_visit',
                    ]);
                }
            }

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.anc_visit_recorded_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\AntenatalController::class, 'show'], [$id])->with('status', $output);
    }
}
