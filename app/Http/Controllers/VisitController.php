<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Consultation;
use App\Visit;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Visits (hospital module). A visit is opened at reception for a patient, then
 * consultations / lab requests / pharmacy dispenses attach to it. The index
 * doubles as the OPD queue (today's open visits).
 */
class VisitController extends Controller
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

        if (! auth()->user()->can('visit.view') && ! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = Visit::leftjoin('contacts AS c', 'visits.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->leftjoin('business_locations AS bl', 'visits.location_id', '=', 'bl.id')
                ->leftjoin('users AS u', 'visits.opened_by', '=', 'u.id')
                ->where('visits.business_id', $business_id)
                ->select([
                    'visits.id',
                    'visits.ref_no',
                    'visits.visit_date',
                    'visits.type',
                    'visits.status',
                    'c.name as patient_name',
                    'pd.file_number',
                    'bl.name as location_name',
                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as opened_by_name"),
                ]);

            if (! empty(request()->input('status'))) {
                $query->where('visits.status', request()->input('status'));
            }
            if (! empty(request()->input('visit_type'))) {
                $query->where('visits.type', request()->input('visit_type'));
            }
            if (! empty(request()->input('location_id'))) {
                $query->where('visits.location_id', request()->input('location_id'));
            }
            if (request()->boolean('today_only')) {
                $query->whereDate('visits.visit_date', \Carbon::today()->toDateString());
            }

            return DataTables::of($query)
                ->editColumn('visit_date', '{{ @format_date($visit_date) }}')
                ->editColumn('type', function ($row) {
                    return __('lang_v1.visit_type_'.$row->type);
                })
                ->editColumn('status', function ($row) {
                    $map = [
                        'open' => 'label-success',
                        'admitted' => 'label-warning',
                        'closed' => 'label-default',
                        'cancelled' => 'label-danger',
                    ];
                    $cls = $map[$row->status] ?? 'label-default';

                    return '<span class="label '.$cls.'">'.__('lang_v1.visit_status_'.$row->status).'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle"
                            data-toggle="dropdown" aria-expanded="false">'.__('messages.actions').'<span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can('visit.view')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\VisitController::class, 'show'], [$row->id]).'"><i class="fas fa-eye"></i> '.__('messages.view').'</a></li>';
                    }
                    if ($row->status == 'open' && auth()->user()->can('consultation.create')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ConsultationController::class, 'create']).'?visit_id='.$row->id.'"><i class="fas fa-stethoscope"></i> '.__('lang_v1.new_consultation').'</a></li>';
                    }
                    if ($row->status == 'open' && auth()->user()->can('visit.close')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\VisitController::class, 'close'], [$row->id]).'" class="close_visit_button"><i class="fas fa-check-circle"></i> '.__('lang_v1.close_visit').'</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->editColumn('file_number', function ($row) {
                    return $row->file_number ?: '-';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        return view('visit.index')->with(compact('business_locations'));
    }

    public function create()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse(action([\App\Http\Controllers\VisitController::class, 'index']));
        }

        $business_locations = BusinessLocation::forDropdown($business_id, false);

        //If a patient is preselected (e.g. from the patient profile), pass it through.
        $preselected_patient = null;
        if (request()->filled('contact_id')) {
            $preselected_patient = Contact::where('business_id', $business_id)
                ->onlyPatients()
                ->with('patientDetails')
                ->find(request()->input('contact_id'));
        }

        return view('visit.create')->with(compact('business_locations', 'preselected_patient'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'contact_id' => 'required',
            'type' => 'required|in:opd,inpatient',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            if (! $this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }

            //Ensure the contact is a patient of this business.
            $patient = Contact::where('business_id', $business_id)
                ->onlyPatients()
                ->findOrFail($request->input('contact_id'));

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('visit', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('visit', $ref_count, $business_id, 'VIS-');

            $visit = Visit::create([
                'business_id' => $business_id,
                'contact_id' => $patient->id,
                'location_id' => $request->input('location_id') ?: null,
                'type' => $request->input('type'),
                'status' => 'open',
                'visit_date' => $request->filled('visit_date') ? $request->input('visit_date') : \Carbon::today()->toDateString(),
                'ref_no' => $ref_no,
                'opened_by' => $request->session()->get('user.id'),
                'notes' => $request->input('notes') ?: null,
            ]);

            DB::commit();

            $output = [
                'success' => true,
                'msg' => __('lang_v1.visit_opened_success'),
            ];

            //Go straight to a new consultation for the visit when permitted (smooth OPD flow).
            if (auth()->user()->can('consultation.create')) {
                return redirect()
                    ->action([\App\Http\Controllers\ConsultationController::class, 'create'], ['visit_id' => $visit->id])
                    ->with('status', $output);
            }

            return redirect()
                ->action([\App\Http\Controllers\VisitController::class, 'show'], [$visit->id])
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

        if (! auth()->user()->can('visit.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $visit = Visit::forBusiness($business_id)
            ->with(['patient.patientDetails', 'location', 'openedBy'])
            ->findOrFail($id);

        $consultations = Consultation::forBusiness($business_id)
            ->where('visit_id', $visit->id)
            ->with(['consultedBy', 'prescriptions'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('visit.show')->with(compact('visit', 'consultations'));
    }

    public function close($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.close')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = request()->session()->get('user.business_id');

            $visit = Visit::forBusiness($business_id)->findOrFail($id);

            if ($visit->status == 'open') {
                $visit->status = 'closed';
                $visit->closed_by = request()->session()->get('user.id');
                $visit->closed_at = now();
                $visit->save();
            }

            $output = [
                'success' => true,
                'msg' => __('lang_v1.visit_closed_success'),
            ];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ];
        }

        if (request()->ajax()) {
            return $output;
        }

        return redirect()->action([\App\Http\Controllers\VisitController::class, 'index'])->with('status', $output);
    }
}
