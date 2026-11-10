<?php

namespace App\Http\Controllers;

use App\Contact;
use App\MorgueFamilyVisit;
use App\MorgueRecord;
use App\MorgueSlot;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Morgue / mortuary (hospital module). Body intake (hospital + external),
 * storage-slot tracking, family-visit logging, and release with storage fees.
 */
class MorgueController extends Controller
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
        if (! \App\Utils\ClinicSettings::sectionOn(request()->session()->get('user.business_id'), 'morgue')) {
            abort(404);
        }
    }

    public function index()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('morgue.view') && ! auth()->user()->can('morgue.receive')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = MorgueRecord::where('morgue_records.business_id', $business_id)
                ->leftjoin('morgue_slots AS s', 'morgue_records.morgue_slot_id', '=', 's.id')
                ->select([
                    'morgue_records.id',
                    'morgue_records.ref_no',
                    'morgue_records.deceased_name',
                    'morgue_records.date_of_death',
                    'morgue_records.received_at',
                    'morgue_records.released_at',
                    'morgue_records.status',
                    'morgue_records.legal_hold',
                    'morgue_records.source',
                    'morgue_records.family_contact_name',
                    's.slot_number',
                ]);

            if (! empty(request()->input('status'))) {
                $query->where('morgue_records.status', request()->input('status'));
            }
            if (! empty(request()->input('source'))) {
                $query->where('morgue_records.source', request()->input('source'));
            }

            return DataTables::of($query)
                ->editColumn('received_at', '{{ @format_datetime($received_at) }}')
                ->addColumn('days_stored', function ($row) {
                    $end = $row->released_at ? \Carbon::parse($row->released_at) : now();

                    return max(1, \Carbon::parse($row->received_at)->diffInDays($end));
                })
                ->editColumn('status', function ($row) {
                    $map = ['in_storage' => 'label-warning', 'released' => 'label-success', 'transferred' => 'label-default'];
                    $html = '<span class="label '.($map[$row->status] ?? 'label-default').'">'.__('lang_v1.morgue_status_'.$row->status).'</span>';
                    if ($row->legal_hold) {
                        $html .= ' <span class="label label-danger">'.__('lang_v1.legal_hold').'</span>';
                    }

                    return $html;
                })
                ->editColumn('slot_number', function ($row) {
                    return $row->slot_number ?: '-';
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.action([\App\Http\Controllers\MorgueController::class, 'show'], [$row->id]).'" class="btn btn-xs btn-info"><i class="fas fa-eye"></i> '.__('messages.view').'</a>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        //Slot grid for the dashboard view.
        $slots = MorgueSlot::forBusiness($business_id)
            ->with(['currentRecord'])
            ->orderBy('slot_number')
            ->get();

        return view('morgue.index')->with(compact('slots'));
    }

    /* ---------------- Slots ---------------- */

    public function slots(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('morgue.manage_slots')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        if ($request->isMethod('post')) {
            $request->validate(['slot_number' => 'required|string|max:50']);

            $exists = MorgueSlot::forBusiness($business_id)->where('slot_number', $request->input('slot_number'))->exists();
            if ($exists) {
                return redirect()->back()->with('status', ['success' => false, 'msg' => __('lang_v1.slot_exists')]);
            }

            MorgueSlot::create([
                'business_id' => $business_id,
                'slot_number' => $request->input('slot_number'),
                'description' => $request->input('description') ?: null,
                'status' => 'available',
            ]);

            return redirect()->back()->with('status', ['success' => true, 'msg' => __('lang_v1.slot_added_success')]);
        }

        $slots = MorgueSlot::forBusiness($business_id)->with('currentRecord')->orderBy('slot_number')->get();

        return view('morgue.slots')->with(compact('slots'));
    }

    /* ---------------- Intake ---------------- */

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('morgue.receive')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        $slots = MorgueSlot::forBusiness($business_id)->where('status', 'available')->orderBy('slot_number')->get();
        $default_daily_fee = \App\Business::where('id', $business_id)->value('morgue_daily_fee') ?: 0;

        //Optional preselected deceased patient.
        $preselected_patient = null;
        if ($request->filled('contact_id')) {
            $preselected_patient = Contact::where('business_id', $business_id)->onlyPatients()->with('patientDetails')->find($request->input('contact_id'));
        }

        return view('morgue.create')->with(compact('slots', 'default_daily_fee', 'preselected_patient'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('morgue.receive')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'deceased_name' => 'required|string|max:191',
            'date_of_death' => 'required',
            'source' => 'required|in:hospital,external',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('morgue', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('morgue', $ref_count, $business_id, 'MRG-');

            //Assign and occupy a slot if chosen.
            $slot = null;
            if ($request->filled('morgue_slot_id')) {
                $slot = MorgueSlot::forBusiness($business_id)->find($request->input('morgue_slot_id'));
                if ($slot && $slot->status !== 'available') {
                    DB::rollBack();

                    return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('lang_v1.slot_not_available')]);
                }
            }

            $record = MorgueRecord::create([
                'business_id' => $business_id,
                'morgue_slot_id' => $slot->id ?? null,
                'contact_id' => $request->input('contact_id') ?: null,
                'deceased_name' => $request->input('deceased_name'),
                'deceased_age' => $request->input('deceased_age') ?: null,
                'deceased_sex' => $request->input('deceased_sex') ?: null,
                'date_of_death' => $request->input('date_of_death'),
                'cause_of_death' => $request->input('cause_of_death') ?: null,
                'certified_by' => $request->input('certified_by') ?: ($request->boolean('certify_self') ? $request->session()->get('user.id') : null),
                'source' => $request->input('source'),
                'family_contact_name' => $request->input('family_contact_name') ?: null,
                'family_contact_phone' => $request->input('family_contact_phone') ?: null,
                'family_relationship' => $request->input('family_relationship') ?: null,
                'legal_hold' => $request->boolean('legal_hold') ? 1 : 0,
                'legal_hold_reason' => $request->input('legal_hold_reason') ?: null,
                'received_at' => $request->filled('received_at') ? $request->input('received_at') : now(),
                'status' => 'in_storage',
                'daily_storage_fee' => $request->filled('daily_storage_fee') ? $this->commonUtil->num_uf($request->input('daily_storage_fee')) : 0,
                'preparation_fee' => $request->filled('preparation_fee') ? $this->commonUtil->num_uf($request->input('preparation_fee')) : 0,
                'ref_no' => $ref_no,
                'notes' => $request->input('notes') ?: null,
                'created_by' => $request->session()->get('user.id'),
            ]);

            if ($slot) {
                $slot->status = 'occupied';
                $slot->save();
            }

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.morgue_intake_success')];

            return redirect()->action([\App\Http\Controllers\MorgueController::class, 'show'], [$record->id])->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('morgue.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $record = MorgueRecord::forBusiness($business_id)
            ->with(['slot', 'contact', 'certifiedBy', 'familyVisits.recordedBy'])
            ->findOrFail($id);

        return view('morgue.show')->with(compact('record'));
    }

    public function recordVisit(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('morgue.record_visit')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate(['visitor_name' => 'required|string|max:191']);

        try {
            $business_id = $request->session()->get('user.business_id');
            $record = MorgueRecord::forBusiness($business_id)->findOrFail($id);

            MorgueFamilyVisit::create([
                'morgue_record_id' => $record->id,
                'visitor_name' => $request->input('visitor_name'),
                'visitor_phone' => $request->input('visitor_phone'),
                'relationship' => $request->input('relationship'),
                'visited_at' => $request->filled('visited_at') ? $request->input('visited_at') : now(),
                'recorded_by' => $request->session()->get('user.id'),
                'notes' => $request->input('notes') ?: null,
            ]);

            $output = ['success' => true, 'msg' => __('lang_v1.family_visit_recorded_success')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());
            $output = ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return redirect()->action([\App\Http\Controllers\MorgueController::class, 'show'], [$id])->with('status', $output);
    }

    public function release(Request $request, $id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('morgue.release')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'released_to' => 'required|string|max:191',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');
            $record = MorgueRecord::forBusiness($business_id)->with('slot')->findOrFail($id);

            if ($record->status !== 'in_storage') {
                return redirect()->action([\App\Http\Controllers\MorgueController::class, 'show'], [$id]);
            }

            if ($record->legal_hold) {
                return redirect()->back()->with('status', ['success' => false, 'msg' => __('lang_v1.legal_hold_blocks_release')]);
            }

            DB::beginTransaction();

            $released_at = $request->filled('released_at') ? \Carbon::parse($request->input('released_at')) : now();
            $days = max(1, $record->received_at->diffInDays($released_at));
            $total_fee = ($days * $record->daily_storage_fee) + $record->preparation_fee;

            $record->status = 'released';
            $record->released_at = $released_at;
            $record->released_to = $request->input('released_to');
            $record->release_authorized_by = $request->input('release_authorized_by') ?: optional(auth()->user())->user_full_name;
            $record->total_fee_charged = $total_fee;
            $record->save();

            //Free the slot.
            if ($record->morgue_slot_id) {
                MorgueSlot::where('id', $record->morgue_slot_id)->update(['status' => 'available']);
            }

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.body_released_success')];

            return redirect()->action([\App\Http\Controllers\MorgueController::class, 'show'], [$id])->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }
}
