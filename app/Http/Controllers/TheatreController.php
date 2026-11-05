<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Procedure;
use App\TheatreBooking;
use App\User;
use App\Utils\HospitalBillingUtil;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Theatre / operating room (hospital module). One controller for the procedure
 * catalogue (modal CRUD) and theatre bookings (schedule → record → bill).
 */
class TheatreController extends Controller
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
        if (! \App\Utils\ClinicSettings::sectionOn(request()->session()->get('user.business_id'), 'theatre')) {
            abort(404);
        }
    }

    /* ---------------- Procedure catalogue ---------------- */

    public function catalogue()
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('theatre.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $items = Procedure::where('business_id', $business_id)
                ->select(['id', 'name', 'category', 'fee', 'anaesthesia_fee', 'is_active']);

            return DataTables::of($items)
                ->editColumn('category', function ($row) {
                    return __('lang_v1.procedure_cat_'.$row->category);
                })
                ->editColumn('fee', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true">'.$row->fee.'</span>';
                })
                ->editColumn('anaesthesia_fee', function ($row) {
                    return '<span class="display_currency" data-currency_symbol="true">'.$row->anaesthesia_fee.'</span>';
                })
                ->editColumn('is_active', function ($row) {
                    return $row->is_active
                        ? '<span class="label label-success">'.__('lang_v1.active').'</span>'
                        : '<span class="label label-danger">'.__('lang_v1.inactive').'</span>';
                })
                ->addColumn('action', function ($row) {
                    $html = '<button data-href="'.action([\App\Http\Controllers\TheatreController::class, 'editProcedure'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary edit_procedure_button"><i class="glyphicon glyphicon-edit"></i> '.__('messages.edit').'</button>';
                    $html .= ' <button data-href="'.action([\App\Http\Controllers\TheatreController::class, 'destroyProcedure'], [$row->id]).'" class="tw-dw-btn tw-dw-btn-outline tw-dw-btn-xs tw-dw-btn-error delete_procedure_button"><i class="glyphicon glyphicon-trash"></i> '.__('messages.delete').'</button>';

                    return $html;
                })
                ->rawColumns(['fee', 'anaesthesia_fee', 'is_active', 'action'])
                ->make(true);
        }

        return view('theatre.catalogue');
    }

    public function createProcedure()
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('theatre.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        return view('theatre.procedure_create');
    }

    public function storeProcedure(Request $request)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('theatre.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');

            $validated = $request->validate([
                'name' => 'required|string|max:191',
                'category' => 'required|in:surgery,obstetric,dental,other',
                'fee' => 'nullable|numeric|min:0',
                'anaesthesia_fee' => 'nullable|numeric|min:0',
            ]);

            Procedure::create([
                'business_id' => $business_id,
                'name' => $validated['name'],
                'category' => $validated['category'],
                'fee' => $request->filled('fee') ? $request->input('fee') : 0,
                'anaesthesia_fee' => $request->filled('anaesthesia_fee') ? $request->input('anaesthesia_fee') : 0,
                'is_active' => 1,
                'created_by' => $request->session()->get('user.id'),
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.procedure_added_success')];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'msg' => collect($e->errors())->flatten()->first()]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    public function editProcedure($id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('theatre.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $procedure = Procedure::where('business_id', $business_id)->findOrFail($id);

            return view('theatre.procedure_edit')->with(compact('procedure'));
        }
    }

    public function updateProcedure(Request $request, $id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('theatre.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $procedure = Procedure::where('business_id', $business_id)->findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:191',
                'category' => 'required|in:surgery,obstetric,dental,other',
                'fee' => 'nullable|numeric|min:0',
                'anaesthesia_fee' => 'nullable|numeric|min:0',
            ]);

            $procedure->fill([
                'name' => $validated['name'],
                'category' => $validated['category'],
                'fee' => $request->filled('fee') ? $request->input('fee') : 0,
                'anaesthesia_fee' => $request->filled('anaesthesia_fee') ? $request->input('anaesthesia_fee') : 0,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);
            $procedure->save();

            $output = ['success' => true, 'msg' => __('lang_v1.procedure_updated_success')];
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'msg' => collect($e->errors())->flatten()->first()]);
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    public function destroyProcedure($id)
    {
        $this->checkHospitalEnabled();
        if (! auth()->user()->can('theatre.manage_catalogue')) {
            abort(403, 'Unauthorized action.');
        }

        if (! request()->ajax()) {
            return;
        }

        try {
            $business_id = request()->session()->get('user.business_id');
            $procedure = Procedure::where('business_id', $business_id)->findOrFail($id);

            $in_use = TheatreBooking::where('procedure_id', $id)->exists();
            if ($in_use) {
                return ['success' => false, 'msg' => __('lang_v1.procedure_in_use_cannot_delete')];
            }

            $procedure->delete();

            $output = ['success' => true, 'msg' => __('lang_v1.procedure_deleted_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return $output;
    }

    /* ---------------- Bookings ---------------- */

    public function index()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('theatre.view') && ! auth()->user()->can('theatre.book')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = TheatreBooking::leftjoin('contacts AS c', 'theatre_bookings.contact_id', '=', 'c.id')
                ->leftjoin('patient_details AS pd', 'c.id', '=', 'pd.contact_id')
                ->leftjoin('users AS s', 'theatre_bookings.surgeon_id', '=', 's.id')
                ->where('theatre_bookings.business_id', $business_id)
                ->select([
                    'theatre_bookings.id',
                    'theatre_bookings.ref_no',
                    'theatre_bookings.procedure_name',
                    'theatre_bookings.scheduled_at',
                    'theatre_bookings.status',
                    'c.name as patient_name',
                    'pd.file_number',
                    DB::raw("CONCAT(COALESCE(s.surname, ''), ' ', COALESCE(s.first_name, ''), ' ', COALESCE(s.last_name, '')) as surgeon_name"),
                ]);

            if (! empty(request()->input('status'))) {
                $query->where('theatre_bookings.status', request()->input('status'));
            }

            return DataTables::of($query)
                ->editColumn('scheduled_at', '{{ @format_datetime($scheduled_at) }}')
                ->editColumn('file_number', function ($row) {
                    return $row->file_number ?: '-';
                })
                ->editColumn('status', function ($row) {
                    $map = ['scheduled' => 'label-info', 'in_progress' => 'label-warning', 'completed' => 'label-success', 'cancelled' => 'label-danger'];

                    return '<span class="label '.($map[$row->status] ?? 'label-default').'">'.__('lang_v1.theatre_status_'.$row->status).'</span>';
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.action([\App\Http\Controllers\TheatreController::class, 'show'], [$row->id]).'" class="btn btn-xs btn-info"><i class="fas fa-eye"></i> '.__('messages.view').'</a>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('theatre.index');
    }

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('theatre.book')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $procedures = Procedure::forBusiness($business_id)->active()->orderBy('name')->get();
        $doctors = User::forDropdown($business_id, false);

        $preselected_patient = null;
        if ($request->filled('contact_id')) {
            $preselected_patient = Contact::where('business_id', $business_id)->onlyPatients()->with('patientDetails')->find($request->input('contact_id'));
        }

        return view('theatre.create')->with(compact('procedures', 'doctors', 'preselected_patient'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('theatre.book')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'contact_id' => 'required',
            'procedure_id' => 'required',
            'scheduled_at' => 'required',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            $patient = Contact::where('business_id', $business_id)->onlyPatients()->findOrFail($request->input('contact_id'));
            $procedure = Procedure::forBusiness($business_id)->findOrFail($request->input('procedure_id'));

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('theatre', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('theatre', $ref_count, $business_id, 'THT-');

            $booking = TheatreBooking::create([
                'business_id' => $business_id,
                'visit_id' => $request->input('visit_id') ?: null,
                'admission_id' => $request->input('admission_id') ?: null,
                'contact_id' => $patient->id,
                'procedure_id' => $procedure->id,
                'procedure_name' => $procedure->name,
                'surgeon_id' => $request->input('surgeon_id') ?: null,
                'anaesthetist_id' => $request->input('anaesthetist_id') ?: null,
                'scheduled_at' => $request->input('scheduled_at'),
                'status' => 'scheduled',
                'preop_notes' => $request->input('preop_notes') ?: null,
                'fee' => $procedure->fee,
                'anaesthesia_fee' => $procedure->anaesthesia_fee,
                'ref_no' => $ref_no,
                'created_by' => $request->session()->get('user.id'),
            ]);

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.theatre_booked_success')];

            return redirect()->action([\App\Http\Controllers\TheatreController::class, 'show'], [$booking->id])->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('theatre.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $booking = TheatreBooking::forBusiness($business_id)
            ->with(['patient.patientDetails', 'procedure', 'surgeon', 'anaesthetist'])
            ->findOrFail($id);

        return view('theatre.show')->with(compact('booking'));
    }

    /**
     * Record/advance the procedure: update notes + status. On completion, bill
     * the procedure + anaesthesia fee to the patient (once).
     */
    public function record(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('theatre.record_procedure')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $business_id = $request->session()->get('user.business_id');
            $booking = TheatreBooking::forBusiness($business_id)->findOrFail($id);

            DB::beginTransaction();

            $new_status = $request->input('status', $booking->status);

            $booking->preop_notes = $request->input('preop_notes') ?: $booking->preop_notes;
            $booking->intraop_notes = $request->input('intraop_notes') ?: $booking->intraop_notes;
            $booking->postop_notes = $request->input('postop_notes') ?: $booking->postop_notes;

            if ($new_status === 'in_progress' && empty($booking->started_at)) {
                $booking->started_at = now();
            }
            if ($new_status === 'completed' && empty($booking->ended_at)) {
                $booking->ended_at = now();
            }
            $booking->status = $new_status;
            $booking->save();

            //Bill on completion (once), reusing Phase 8 billing.
            if ($new_status === 'completed' && ! $booking->fee_charged) {
                $total = (float) $booking->total_fee;
                if ($total > 0) {
                    $location_id = \App\BusinessLocation::where('business_id', $business_id)->value('id');
                    if ($location_id) {
                        (new HospitalBillingUtil())->chargePatientFee([
                            'business_id' => $business_id,
                            'contact_id' => $booking->contact_id,
                            'amount' => $total,
                            'charge_type' => 'procedure',
                            'description' => $booking->procedure_name.' · '.$booking->ref_no,
                            'location_id' => $location_id,
                            'visit_id' => $booking->visit_id,
                            'admission_id' => $booking->admission_id,
                            'created_by' => $request->session()->get('user.id'),
                            'reference_type' => 'theatre_booking',
                        ]);
                    }
                }
                $booking->fee_charged = 1;
                $booking->save();
            }

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.theatre_updated_success')];
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\TheatreController::class, 'show'], [$id])->with('status', $output);
    }
}
