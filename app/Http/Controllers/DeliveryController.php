<?php

namespace App\Http\Controllers;

use App\AntenatalRecord;
use App\Contact;
use App\DeliveryRecord;
use App\PatientDetail;
use App\Utils\ContactUtil;
use App\Utils\HospitalBillingUtil;
use App\Utils\ModuleUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Deliveries (hospital module, maternity). Records the birth, auto-registers
 * the baby as a patient linked to the mother, and bills the delivery fee.
 */
class DeliveryController extends Controller
{
    protected $commonUtil;

    protected $moduleUtil;

    protected $contactUtil;

    public function __construct(Util $commonUtil, ModuleUtil $moduleUtil, ContactUtil $contactUtil)
    {
        $this->commonUtil = $commonUtil;
        $this->moduleUtil = $moduleUtil;
        $this->contactUtil = $contactUtil;
    }

    private function checkHospitalEnabled()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        //PosInnova: section switched off in Clinic settings
        if (! \App\Utils\ClinicSettings::sectionOn(request()->session()->get('user.business_id'), 'maternity')) {
            abort(404);
        }
    }

    public function index()
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('delivery.record') && ! auth()->user()->can('antenatal.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        if (request()->ajax()) {
            $query = DeliveryRecord::leftjoin('contacts AS m', 'delivery_records.mother_contact_id', '=', 'm.id')
                ->leftjoin('contacts AS baby', 'delivery_records.baby_contact_id', '=', 'baby.id')
                ->where('delivery_records.business_id', $business_id)
                ->select([
                    'delivery_records.id',
                    'delivery_records.ref_no',
                    'delivery_records.delivery_date',
                    'delivery_records.delivery_type',
                    'delivery_records.baby_sex',
                    'delivery_records.baby_weight',
                    'm.name as mother_name',
                    'baby.name as baby_name',
                ]);

            return DataTables::of($query)
                ->editColumn('delivery_date', '{{ @format_datetime($delivery_date) }}')
                ->editColumn('delivery_type', function ($row) {
                    return __('lang_v1.delivery_type_'.$row->delivery_type);
                })
                ->editColumn('baby_weight', function ($row) {
                    return $row->baby_weight ? $row->baby_weight.' kg' : '-';
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.action([\App\Http\Controllers\DeliveryController::class, 'show'], [$row->id]).'" class="btn btn-xs btn-info"><i class="fas fa-eye"></i> '.__('messages.view').'</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('maternity.delivery_index');
    }

    public function create(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('delivery.record')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');

        //Optional preselected mother (from antenatal record).
        $mother = null;
        $antenatal_record = null;
        if ($request->filled('antenatal_record_id')) {
            $antenatal_record = AntenatalRecord::forBusiness($business_id)->with('mother.patientDetails')->find($request->input('antenatal_record_id'));
            $mother = optional($antenatal_record)->mother;
        } elseif ($request->filled('contact_id')) {
            $mother = Contact::where('business_id', $business_id)->onlyPatients()->with('patientDetails')->find($request->input('contact_id'));
        }

        $default_fee = \App\Business::where('id', $business_id)->value('consultation_fee') ?: 0;

        return view('maternity.delivery_create')->with(compact('mother', 'antenatal_record', 'default_fee'));
    }

    public function store(Request $request)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('delivery.record')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'mother_contact_id' => 'required',
            'delivery_date' => 'required',
            'delivery_type' => 'required',
        ]);

        try {
            $business_id = $request->session()->get('user.business_id');

            $mother = Contact::where('business_id', $business_id)->onlyPatients()->findOrFail($request->input('mother_contact_id'));

            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('delivery', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('delivery', $ref_count, $business_id, 'DEL-');

            //Auto-register the baby as a patient linked to the mother (per design).
            $baby_contact_id = null;
            if ($request->boolean('register_baby')) {
                $baby_contact_id = $this->registerBaby($business_id, $mother, $request);
            }

            $delivery = DeliveryRecord::create([
                'business_id' => $business_id,
                'antenatal_record_id' => $request->input('antenatal_record_id') ?: null,
                'mother_contact_id' => $mother->id,
                'admission_id' => $request->input('admission_id') ?: null,
                'ref_no' => $ref_no,
                'delivery_date' => $request->input('delivery_date'),
                'delivery_type' => $request->input('delivery_type'),
                'attended_by' => $request->session()->get('user.id'),
                'baby_sex' => $request->input('baby_sex') ?: 'Unknown',
                'baby_weight' => $request->filled('baby_weight') ? $request->input('baby_weight') : null,
                'apgar_1min' => $request->input('apgar_1min') !== null && $request->input('apgar_1min') !== '' ? $request->input('apgar_1min') : null,
                'apgar_5min' => $request->input('apgar_5min') !== null && $request->input('apgar_5min') !== '' ? $request->input('apgar_5min') : null,
                'complications' => $request->input('complications') ?: null,
                'baby_contact_id' => $baby_contact_id,
                'notes' => $request->input('notes') ?: null,
                'fee' => $request->filled('fee') ? $this->commonUtil->num_uf($request->input('fee')) : 0,
                'created_by' => $request->session()->get('user.id'),
            ]);

            //Mark the pregnancy as delivered.
            if ($delivery->antenatal_record_id) {
                AntenatalRecord::where('id', $delivery->antenatal_record_id)->update(['status' => 'delivered']);
            }

            //Bill the delivery fee to the mother (reuse Phase 8 billing).
            if ($delivery->fee > 0) {
                $location_id = \App\BusinessLocation::where('business_id', $business_id)->value('id');
                if ($location_id) {
                    (new HospitalBillingUtil())->chargePatientFee([
                        'business_id' => $business_id,
                        'contact_id' => $mother->id,
                        'amount' => (float) $delivery->fee,
                        'charge_type' => 'procedure',
                        'description' => __('lang_v1.delivery').' · '.$delivery->ref_no,
                        'location_id' => $location_id,
                        'visit_id' => null,
                        'admission_id' => $delivery->admission_id,
                        'created_by' => $request->session()->get('user.id'),
                        'reference_type' => 'delivery',
                    ]);
                }
            }

            DB::commit();

            $output = ['success' => true, 'msg' => __('lang_v1.delivery_recorded_success')];

            return redirect()->action([\App\Http\Controllers\DeliveryController::class, 'show'], [$delivery->id])->with('status', $output);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('delivery.record') && ! auth()->user()->can('antenatal.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $delivery = DeliveryRecord::forBusiness($business_id)
            ->with(['mother.patientDetails', 'baby.patientDetails', 'attendedBy', 'antenatalRecord'])
            ->findOrFail($id);

        return view('maternity.delivery_show')->with(compact('delivery'));
    }

    /**
     * Create the newborn as a patient contact linked to the mother. Returns the
     * baby's contact id.
     */
    private function registerBaby($business_id, Contact $mother, Request $request)
    {
        $baby_name = $request->filled('baby_name')
            ? $request->input('baby_name')
            : __('lang_v1.baby_of').' '.$mother->name;

        $out = $this->contactUtil->createNewContact([
            'type' => 'customer',
            'is_patient' => 1,
            'contact_type' => 'individual',
            'business_id' => $business_id,
            'created_by' => $request->session()->get('user.id'),
            'first_name' => $baby_name,
            'name' => $baby_name,
            'mobile' => $mother->mobile, // reachable via mother
        ]);
        $baby = $out['data'];

        PatientDetail::create([
            'contact_id' => $baby->id,
            'business_id' => $business_id,
            'file_number' => PatientDetail::generateFileNumber($business_id),
            'date_of_birth' => $request->input('delivery_date') ? \Carbon::parse($request->input('delivery_date'))->toDateString() : null,
            'sex' => in_array($request->input('baby_sex'), ['M', 'F']) ? $request->input('baby_sex') : null,
            'mother_contact_id' => $mother->id,
        ]);

        return $baby->id;
    }
}
