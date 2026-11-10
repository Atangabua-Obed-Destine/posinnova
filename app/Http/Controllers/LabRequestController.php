<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Consultation;
use App\LabRequest;
use App\LabRequestLine;
use App\LabTest;
use App\Visit;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Laboratory requests (hospital module). A doctor raises a request for one or
 * more tests; the lab collects the sample, enters results and validates them.
 */
class LabRequestController extends Controller
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

        if (! auth()->user()->can('lab_request.view') && ! auth()->user()->can('lab_request.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = LabRequest::leftjoin('contacts AS c', 'lab_requests.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->leftjoin('users AS u', 'lab_requests.requested_by', '=', 'u.id')
                ->where('lab_requests.business_id', $business_id)
                ->select([
                    'lab_requests.id',
                    'lab_requests.ref_no',
                    'lab_requests.status',
                    'lab_requests.requested_at',
                    'c.name as patient_name',
                    'pd.file_number',
                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as requested_by_name"),
                    DB::raw('(SELECT COUNT(*) FROM lab_request_lines WHERE lab_request_lines.lab_request_id = lab_requests.id) as tests_count'),
                ]);

            if (! empty(request()->input('status'))) {
                $query->where('lab_requests.status', request()->input('status'));
            }
            if (request()->boolean('today_only')) {
                $query->whereDate('lab_requests.requested_at', \Carbon::today()->toDateString());
            }

            return DataTables::of($query)
                ->editColumn('requested_at', '{{ @format_datetime($requested_at) }}')
                ->editColumn('file_number', function ($row) {
                    return $row->file_number ?: '-';
                })
                ->editColumn('status', function ($row) {
                    $map = [
                        'pending' => 'label-default',
                        'sample_collected' => 'label-info',
                        'processing' => 'label-warning',
                        'resulted' => 'label-primary',
                        'validated' => 'label-success',
                    ];
                    $cls = $map[$row->status] ?? 'label-default';

                    return '<span class="label '.$cls.'">'.__('lang_v1.lab_status_'.$row->status).'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle"
                            data-toggle="dropdown" aria-expanded="false">'.__('messages.actions').'<span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can('lab_request.view')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\LabRequestController::class, 'show'], [$row->id]).'"><i class="fas fa-eye"></i> '.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('lab_result.enter') && in_array($row->status, ['pending', 'sample_collected', 'processing', 'resulted'])) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\LabRequestController::class, 'enterResult'], [$row->id]).'"><i class="fas fa-vial"></i> '.__('lang_v1.enter_results').'</a></li>';
                    }
                    if (in_array($row->status, ['resulted', 'validated'])) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\LabRequestController::class, 'print'], [$row->id]).'" target="_blank"><i class="fas fa-print"></i> '.__('lang_v1.print_result').'</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('lab.request_index');
    }

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_request.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $lab_tests = LabTest::forBusiness($business_id)->active()->orderBy('name')->get();
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        //Optional preselected visit/patient/consultation (when launched from a consultation).
        $visit = null;
        $consultation = null;
        if ($request->filled('visit_id')) {
            $visit = Visit::forBusiness($business_id)->with('patient.patientDetails')->find($request->input('visit_id'));
        }
        if ($request->filled('consultation_id')) {
            $consultation = Consultation::forBusiness($business_id)->find($request->input('consultation_id'));
        }

        return view('lab.request_create')->with(compact('lab_tests', 'business_locations', 'visit', 'consultation'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_request.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'contact_id' => 'required',
            'lab_test_ids' => 'required|array|min:1',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            $patient = Contact::where('business_id', $business_id)->onlyPatients()->findOrFail($request->input('contact_id'));

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('lab_request', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('lab_request', $ref_count, $business_id, 'LAB-');

            $lab_request = LabRequest::create([
                'business_id' => $business_id,
                'visit_id' => $request->input('visit_id') ?: null,
                'consultation_id' => $request->input('consultation_id') ?: null,
                'contact_id' => $patient->id,
                'requested_by' => $request->session()->get('user.id'),
                'location_id' => $request->input('location_id') ?: null,
                'ref_no' => $ref_no,
                'status' => 'pending',
                'clinical_notes' => $request->input('clinical_notes') ?: null,
                'requested_at' => now(),
            ]);

            $test_ids = $request->input('lab_test_ids', []);
            $tests = LabTest::forBusiness($business_id)->whereIn('id', $test_ids)->get()->keyBy('id');

            foreach ($test_ids as $tid) {
                $test = $tests->get($tid);
                if (! $test) {
                    continue;
                }
                LabRequestLine::create([
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

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.lab_request_created_success')];

            return redirect()
                ->action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lab_request->id])
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

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_request.view') && ! auth()->user()->can('lab_result.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $lab_request = LabRequest::forBusiness($business_id)
            ->with(['patient.patientDetails', 'requestedBy', 'validatedBy', 'lines.labTest', 'location'])
            ->findOrFail($id);

        $amendments = $this->amendmentsFor($lab_request->id);

        return view('lab.request_show')->with(compact('lab_request', 'amendments'));
    }

    /**
     * PosInnova: correct a VALIDATED result. The line takes the new value and
     * flag; lab_result_amendments keeps the old ones, the reason, who and when.
     */
    public function amendResult(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_result.amend')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $lab_request = LabRequest::forBusiness($business_id)->with('lines')->findOrFail($id);
        $back = redirect()->action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lab_request->id]);

        $line = $lab_request->lines->firstWhere('id', (int) $request->input('line_id'));
        $new_value = trim((string) $request->input('new_value'));
        $reason = trim((string) $request->input('reason'));
        $new_flag = $request->input('new_flag');
        $new_flag = in_array($new_flag, ['normal', 'high', 'low', 'critical'], true) ? $new_flag : null;

        if ($lab_request->status !== 'validated' || ! $line || $new_value === '' || $reason === '') {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.amend_required')]);
        }

        try {
            DB::beginTransaction();
            DB::table('lab_result_amendments')->insert([
                'business_id' => $business_id,
                'lab_request_id' => $lab_request->id,
                'lab_request_line_id' => $line->id,
                'old_value' => $line->result_value,
                'old_flag' => $line->result_flag,
                'new_value' => mb_substr($new_value, 0, 191),
                'new_flag' => $new_flag,
                'reason' => $reason,
                'amended_by' => $request->session()->get('user.id'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $line->result_value = mb_substr($new_value, 0, 191);
            $line->result_flag = $new_flag;
            $line->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $back->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return $back->with('status', ['success' => true, 'msg' => __('clinic.amend_saved')]);
    }

    /**
     * PosInnova: the amendment history of one lab request, newest first.
     */
    private function amendmentsFor($lab_request_id)
    {
        return DB::table('lab_result_amendments as a')
            ->join('lab_request_lines as l', 'a.lab_request_line_id', '=', 'l.id')
            ->leftJoin('users as u', 'a.amended_by', '=', 'u.id')
            ->where('a.lab_request_id', $lab_request_id)
            ->orderBy('a.id', 'desc')
            ->select('a.*', 'l.test_name', DB::raw("TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) as amended_by_name"))
            ->get();
    }

    public function enterResult($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_result.enter')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $lab_request = LabRequest::forBusiness($business_id)
            ->with(['patient.patientDetails', 'lines.labTest'])
            ->findOrFail($id);

        //PosInnova: a validated result is locked; corrections are amendments.
        if ($lab_request->status === 'validated') {
            return redirect()->action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lab_request->id])
                ->with('status', ['success' => false, 'msg' => __('clinic.result_locked')]);
        }

        return view('lab.enter_result')->with(compact('lab_request'));
    }

    public function saveResult(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_result.enter')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            $lab_request = LabRequest::forBusiness($business_id)->with('lines')->findOrFail($id);

            //PosInnova: a validated result is locked. Before this, saving here
            //overwrote a validated value silently and dropped the status back.
            if ($lab_request->status === 'validated') {
                return redirect()->action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lab_request->id])
                    ->with('status', ['success' => false, 'msg' => __('clinic.result_locked')]);
            }

            DB::beginTransaction();

            $results = $request->input('results', []);
            foreach ($lab_request->lines as $line) {
                if (! isset($results[$line->id])) {
                    continue;
                }
                $r = $results[$line->id];
                $line->result_value = $r['value'] ?? null;
                $line->result_flag = ! empty($r['flag']) ? $r['flag'] : null;
                $line->result_notes = $r['notes'] ?? null;
                if (! empty($line->result_value)) {
                    $line->resulted_by = $request->session()->get('user.id');
                    $line->resulted_at = now();
                }
                $line->save();
            }

            //Advance status: resulted when every line has a value, else processing.
            $lab_request->refresh();
            $total = $lab_request->lines->count();
            $resulted = $lab_request->lines->filter(fn ($l) => ! empty($l->result_value))->count();

            if ($resulted == 0) {
                $lab_request->status = 'sample_collected';
            } elseif ($resulted < $total) {
                $lab_request->status = 'processing';
            } else {
                $lab_request->status = 'resulted';
                $lab_request->resulted_at = now();
            }

            //Optional validation in the same action (lab scientist).
            if ($request->input('action') === 'validate' && auth()->user()->can('lab_result.validate') && $resulted == $total && $total > 0) {
                $lab_request->status = 'validated';
                $lab_request->validated_by = $request->session()->get('user.id');
            }

            $lab_request->save();

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.lab_result_saved_success')];

            return redirect()
                ->action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lab_request->id])
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

    public function validateResult($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_result.validate')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $lab_request = LabRequest::forBusiness($business_id)->with('lines')->findOrFail($id);

            $total = $lab_request->lines->count();
            $resulted = $lab_request->lines->filter(fn ($l) => ! empty($l->result_value))->count();

            if ($total > 0 && $resulted == $total) {
                $lab_request->status = 'validated';
                $lab_request->validated_by = request()->session()->get('user.id');
                $lab_request->save();
                $output = ['success' => true, 'msg' => __('lang_v1.lab_result_validated_success')];
            } else {
                $output = ['success' => false, 'msg' => __('lang_v1.lab_result_incomplete')];
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        if (request()->ajax()) {
            return $output;
        }

        return redirect()->action([\App\Http\Controllers\LabRequestController::class, 'show'], [$id])->with('status', $output);
    }

    public function print($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('lab_request.view') && ! auth()->user()->can('lab_result.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $lab_request = LabRequest::forBusiness($business_id)
            ->with(['patient.patientDetails', 'requestedBy', 'validatedBy', 'lines', 'location'])
            ->findOrFail($id);

        $business = \App\Business::find($business_id);

        $amendments = $this->amendmentsFor($lab_request->id);

        return view('lab.result_print')->with(compact('lab_request', 'business', 'amendments'));
    }
}
