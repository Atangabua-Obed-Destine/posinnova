<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Contact;
use App\Consultation;
use App\ImagingCatalogue;
use App\ImagingRequest;
use App\Visit;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Imaging / Radiology (hospital module). One controller handling both the
 * imaging catalogue (modal CRUD) and imaging requests with radiologist reports.
 */
class ImagingController extends Controller
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
        //PosInnova: section switched off in Clinic settings
        if (! \App\Utils\ClinicSettings::sectionOn(request()->session()->get('user.business_id'), 'imaging')) {
            abort(404);
        }
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    /* ---------------- Catalogue ---------------- */

    public function catalogue()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('imaging.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');

            $items = ImagingCatalogue::where('business_id', $business_id)
                ->select(['id', 'name', 'code', 'modality', 'fee', 'is_active']);

            return DataTables::of($items)
                ->editColumn('fee', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true">'.$row->fee.'</span>';
                })
                ->editColumn('is_active', function ($row) {
                    return $row->is_active
                        ? '<span class="label label-success">'.__('lang_v1.active').'</span>'
                        : '<span class="label label-danger">'.__('lang_v1.inactive').'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<button data-href="'.action([\App\Http\Controllers\ImagingController::class, 'editCatalogue'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_imaging_button"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</button>';
                    $html .= ' <button data-href="'.action([\App\Http\Controllers\ImagingController::class, 'destroyCatalogue'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_imaging_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button>';

                    return $html;
                })
                ->rawColumns(['fee', 'is_active', 'action'])
                ->make(true);
        }

        return view('imaging.catalogue');
    }

    public function createCatalogue()
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('imaging.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        return view('imaging.catalogue_create');
    }

    public function storeCatalogue(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('imaging.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            $validated = $request->validate([
                'name' => 'required|string|max:191',
                'code' => 'nullable|string|max:50',
                'modality' => 'nullable|string|max:50',
                'fee' => 'nullable|numeric|min:0',
            ]);

            $validated['business_id'] = $business_id;
            $validated['created_by'] = $request->session()->get('user.id');
            $validated['fee'] = $request->filled('fee') ? $request->input('fee') : 0;
            $validated['is_active'] = 1;

            ImagingCatalogue::create($validated);

            $output = ['success' => true, 'msg' => __('lang_v1.imaging_added_success')];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'msg' => collect($e->errors())->flatten()->first()]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    public function editCatalogue($id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('imaging.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $imaging = ImagingCatalogue::where('business_id', $business_id)->findOrFail($id);

            return view('imaging.catalogue_edit')->with(compact('imaging'));
        }
    }

    public function updateCatalogue(Request $request, $id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('imaging.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $imaging = ImagingCatalogue::where('business_id', $business_id)->findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:191',
                'code' => 'nullable|string|max:50',
                'modality' => 'nullable|string|max:50',
                'fee' => 'nullable|numeric|min:0',
            ]);

            $imaging->fill([
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'modality' => $validated['modality'] ?? null,
                'fee' => $request->filled('fee') ? $request->input('fee') : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);
            $imaging->save();

            $output = ['success' => true, 'msg' => __('lang_v1.imaging_updated_success')];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'msg' => collect($e->errors())->flatten()->first()]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    public function destroyCatalogue($id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('imaging.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $imaging = ImagingCatalogue::where('business_id', $business_id)->findOrFail($id);

            $in_use = ImagingRequest::where('imaging_catalogue_id', $id)->exists();
            if ($in_use) {
                return ['success' => false, 'msg' => __('lang_v1.imaging_in_use_cannot_delete')];
            }

            $imaging->delete();

            $output = ['success' => true, 'msg' => __('lang_v1.imaging_deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /* ---------------- Requests ---------------- */

    public function index()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('imaging_request.view') && ! auth()->user()->can('imaging_request.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = ImagingRequest::leftjoin('contacts AS c', 'imaging_requests.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->leftjoin('users AS u', 'imaging_requests.requested_by', '=', 'u.id')
                ->where('imaging_requests.business_id', $business_id)
                ->select([
                    'imaging_requests.id',
                    'imaging_requests.ref_no',
                    'imaging_requests.study_name',
                    'imaging_requests.status',
                    'imaging_requests.requested_at',
                    'c.name as patient_name',
                    'pd.file_number',
                    DB::raw("CONCAT(COALESCE(u.surname, ''), ' ', COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as requested_by_name"),
                ]);

            if (! empty(request()->input('status'))) {
                $query->where('imaging_requests.status', request()->input('status'));
            }

            return DataTables::of($query)
                ->editColumn('requested_at', '{{ @format_datetime($requested_at) }}')
                ->editColumn('file_number', function ($row) {
                    return $row->file_number ?: '-';
                })
                ->editColumn('status', function ($row) {
                    return $row->status == 'done'
                        ? '<span class="label label-success">'.__('lang_v1.imaging_status_done').'</span>'
                        : '<span class="label label-default">'.__('lang_v1.imaging_status_pending').'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<div class="btn-group">
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle"
                            data-toggle="dropdown" aria-expanded="false">'.__('messages.actions').'<span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can('imaging_request.view')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ImagingController::class, 'show'], [$row->id]).'"><i class="fas fa-eye"></i> '.__('messages.view').'</a></li>';
                    }
                    if ($row->status == 'pending' && auth()->user()->can('imaging_result.enter')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ImagingController::class, 'enterReport'], [$row->id]).'"><i class="fas fa-x-ray"></i> '.__('lang_v1.enter_report').'</a></li>';
                    }

                    $html .= '</ul></div>';

                    return $html;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('imaging.request_index');
    }

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('imaging_request.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $catalogue = ImagingCatalogue::forBusiness($business_id)->active()->orderBy('name')->get();
        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $visit = null;
        $consultation = null;
        if ($request->filled('visit_id')) {
            $visit = Visit::forBusiness($business_id)->with('patient.patientDetails')->find($request->input('visit_id'));
        }
        if ($request->filled('consultation_id')) {
            $consultation = Consultation::forBusiness($business_id)->find($request->input('consultation_id'));
        }

        return view('imaging.request_create')->with(compact('catalogue', 'business_locations', 'visit', 'consultation'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('imaging_request.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'contact_id' => 'required',
            'imaging_catalogue_id' => 'required',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            $patient = Contact::where('business_id', $business_id)->onlyPatients()->findOrFail($request->input('contact_id'));
            $study = ImagingCatalogue::forBusiness($business_id)->findOrFail($request->input('imaging_catalogue_id'));

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('imaging_request', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('imaging_request', $ref_count, $business_id, 'IMG-');

            $imaging_request = ImagingRequest::create([
                'business_id' => $business_id,
                'visit_id' => $request->input('visit_id') ?: null,
                'consultation_id' => $request->input('consultation_id') ?: null,
                'contact_id' => $patient->id,
                'imaging_catalogue_id' => $study->id,
                'study_name' => $study->name,
                'requested_by' => $request->session()->get('user.id'),
                'location_id' => $request->input('location_id') ?: null,
                'ref_no' => $ref_no,
                'status' => 'pending',
                'clinical_notes' => $request->input('clinical_notes') ?: null,
                'fee' => $study->fee,
                'requested_at' => now(),
            ]);

            //Bill the imaging fee to the patient (Phase 8).
            (new \App\Utils\HospitalBillingUtil())->chargeImagingRequest($imaging_request, $request->session()->get('user.id'));

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.imaging_request_created_success')];

            return redirect()
                ->action([\App\Http\Controllers\ImagingController::class, 'show'], [$imaging_request->id])
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

        if (! auth()->user()->can('imaging_request.view') && ! auth()->user()->can('imaging_result.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $imaging_request = ImagingRequest::forBusiness($business_id)
            ->with(['patient.patientDetails', 'requestedBy', 'reportedBy', 'location', 'catalogue'])
            ->findOrFail($id);

        return view('imaging.request_show')->with(compact('imaging_request'));
    }

    public function enterReport($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('imaging_result.enter')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $imaging_request = ImagingRequest::forBusiness($business_id)
            ->with(['patient.patientDetails'])
            ->findOrFail($id);

        return view('imaging.enter_report')->with(compact('imaging_request'));
    }

    public function saveReport(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('imaging_result.enter')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['report' => 'required|string']);

        try {
            $business_id = $request->session()->get('user.business_id');

            $imaging_request = ImagingRequest::forBusiness($business_id)->findOrFail($id);

            $imaging_request->report = $request->input('report');
            $imaging_request->status = 'done';
            $imaging_request->reported_by = $request->session()->get('user.id');
            $imaging_request->reported_at = now();
            $imaging_request->save();

            $output = ['success' => true, 'msg' => __('lang_v1.imaging_report_saved_success')];

            return redirect()
                ->action([\App\Http\Controllers\ImagingController::class, 'show'], [$imaging_request->id])
                ->with('status', $output);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', [
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }
}
