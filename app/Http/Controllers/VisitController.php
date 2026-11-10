<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\ClinicPackage;
use App\Contact;
use App\Consultation;
use App\Transaction;
use App\Visit;
use App\VisitCharge;
use App\Utils\ClinicPlans;
use App\Utils\ClinicQueue;
use App\Utils\ClinicSettings;
use App\Utils\HospitalBillingUtil;
use App\Utils\ModuleUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use DB;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

/**
 * Visits (hospital module). Reception checks a patient in: picks the doctor and
 * the consultation package, the patient gets the day's queue number and the
 * package price goes on their account as an invoice with a real line (clinic
 * phase 2). Consultations / lab requests / dispenses then attach to the visit.
 * The index doubles as the reception queue (today's open visits).
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
                ->leftjoin('users AS doc', 'visits.doctor_id', '=', 'doc.id')
                ->leftjoin('clinic_packages AS pkg', 'visits.package_id', '=', 'pkg.id')
                ->leftjoin('transactions AS fee', 'visits.fee_transaction_id', '=', 'fee.id')
                ->where('visits.business_id', $business_id)
                ->select([
                    'visits.id',
                    'visits.ref_no',
                    'visits.visit_date',
                    'visits.type',
                    'visits.status',
                    'visits.queue_no',
                    'visits.queue_status',
                    'visits.fee_transaction_id',
                    'fee.payment_status as fee_payment_status',
                    'fee.final_total as fee_total',
                    'pkg.name as package_name',
                    'c.name as patient_name',
                    'pd.file_number',
                    'bl.name as location_name',
                    DB::raw("TRIM(CONCAT(COALESCE(doc.surname, ''), ' ', COALESCE(doc.first_name, ''), ' ', COALESCE(doc.last_name, ''))) as doctor_name"),
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
            if (! empty(request()->input('doctor_id'))) {
                $query->where('visits.doctor_id', request()->input('doctor_id'));
            }
            if (request()->boolean('today_only')) {
                $query->whereDate('visits.visit_date', \Carbon::today()->toDateString());
            }

            $can_pay = auth()->user()->can('sell.payments');

            return DataTables::of($query)
                ->editColumn('visit_date', '{{ @format_date($visit_date) }}')
                ->editColumn('type', function ($row) {
                    return __('lang_v1.visit_type_'.$row->type);
                })
                ->editColumn('queue_no', function ($row) {
                    if (empty($row->queue_no)) {
                        return '-';
                    }
                    $html = '<strong style="font-size:15px;">'.$row->queue_no.'</strong>';
                    if (! empty($row->queue_status)) {
                        $cls = ['waiting' => 'label-info', 'with_doctor' => 'label-warning', 'seen' => 'label-success', 'no_show' => 'label-default', 'cancelled' => 'label-danger'][$row->queue_status] ?? 'label-default';
                        $html .= ' <span class="label '.$cls.'">'.__('clinic.queue_'.$row->queue_status).'</span>';
                    }

                    return $html;
                })
                ->editColumn('doctor_name', function ($row) {
                    $html = e($row->doctor_name ?: '-');
                    if (! empty($row->package_name)) {
                        $html .= '<br><small class="text-muted">'.e($row->package_name).'</small>';
                    }

                    return $html;
                })
                ->addColumn('payment', function ($row) {
                    if (empty($row->fee_transaction_id) || $row->fee_total <= 0) {
                        return '<span class="text-muted">-</span>';
                    }
                    $state = in_array($row->fee_payment_status, ['paid', 'partial']) ? $row->fee_payment_status : 'due';
                    $cls = ['paid' => 'label-success', 'partial' => 'label-warning', 'due' => 'label-danger'][$state];

                    return '<span class="label '.$cls.'">'.__('lang_v1.'.$state).'</span>';
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
                ->addColumn('action', function ($row) use ($can_pay) {
                    $html = '<div class="btn-group">
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-info tw-w-max dropdown-toggle"
                            data-toggle="dropdown" aria-expanded="false">'.__('messages.actions').'<span class="caret"></span></button>
                        <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    if (auth()->user()->can('visit.view')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\VisitController::class, 'show'], [$row->id]).'"><i class="fas fa-eye"></i> '.__('messages.view').'</a></li>';
                    }
                    if ($can_pay && ! empty($row->fee_transaction_id) && $row->fee_total > 0 && $row->fee_payment_status != 'paid') {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$row->fee_transaction_id]).'?post_to_register=1" class="add_payment_modal"><i class="fas fa-money-bill-alt"></i> '.__('clinic.take_payment').'</a></li>';
                    }
                    if ($row->status == 'open' && empty($row->queue_status) && auth()->user()->can('consultation.create')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ConsultationController::class, 'create']).'?visit_id='.$row->id.'"><i class="fas fa-stethoscope"></i> '.__('lang_v1.new_consultation').'</a></li>';
                    }
                    if ($row->status == 'open' && in_array($row->queue_status, ['waiting', 'no_show']) && auth()->user()->can('visit.create')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\VisitController::class, 'cancel'], [$row->id]).'" class="cancel_checkin_button"><i class="fas fa-ban"></i> '.__('clinic.cancel_checkin').'</a></li>';
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
                ->rawColumns(['queue_no', 'doctor_name', 'payment', 'status', 'action'])
                ->make(true);
        }

        $business_locations = BusinessLocation::forDropdown($business_id, true);
        $doctors = ClinicQueue::doctors($business_id);

        return view('visit.index')->with(compact('business_locations', 'doctors'));
    }

    /** Check-in screen: patient, doctor, package. */
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

        $doctors = ClinicQueue::doctors($business_id);
        $packages = ClinicPackage::forBusiness($business_id)->active()->perVisit()->ordered()->get();
        $default_fee = (float) (\App\Business::where('id', $business_id)->value('consultation_fee') ?: 0);
        $wards_on = ClinicSettings::sectionOn($business_id, 'wards');
        $pay_first = (bool) ClinicSettings::get($business_id, 'pay_first');

        $waiting = Visit::where('business_id', $business_id)
            ->whereDate('visit_date', \Carbon::today()->toDateString())
            ->where('status', 'open')
            ->whereIn('queue_status', ['waiting', 'with_doctor'])
            ->groupBy('doctor_id')
            ->select('doctor_id', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'doctor_id');

        // monthly plans: the screen asks what the chosen patient's plan covers
        $has_plans = \App\ClinicSubscription::forBusiness($business_id)->exists();

        // checking in from the appointments diary
        $appointment = null;
        if (request()->filled('appointment_id')) {
            $appointment = \App\ClinicAppointment::forBusiness($business_id)->open()->with('patient.patientDetails')
                ->whereDate('date', \Carbon::today()->toDateString())->find(request()->input('appointment_id'));
            if ($appointment) {
                $preselected_patient = $appointment->patient;
            }
        }

        return view('visit.create')->with(compact('business_locations', 'preselected_patient', 'doctors', 'packages',
            'default_fee', 'wards_on', 'pay_first', 'waiting', 'has_plans', 'appointment'));
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
            'doctor_id' => 'nullable|integer',
            'package_id' => ['nullable', 'regex:/^(plan|\d{1,10})$/'], // a package id, or 'plan'
        ]);

        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        $type = $request->input('type');
        if ($type == 'inpatient' && ! ClinicSettings::sectionOn($business_id, 'wards')) {
            $type = 'opd';
        }
        $visit_date = $request->filled('visit_date') ? $request->input('visit_date') : \Carbon::today()->toDateString();

        //Ensure the contact is a patient of this business.
        $patient = Contact::where('business_id', $business_id)
            ->onlyPatients()
            ->findOrFail($request->input('contact_id'));

        $doctor_id = null;
        $package = null;
        $plan_period = null;
        if ($type == 'opd') {
            $doctors = ClinicQueue::doctors($business_id);
            if (! empty($doctors)) {
                $doctor_id = (int) $request->input('doctor_id');
                if (! isset($doctors[$doctor_id])) {
                    return $this->fail(__('clinic.choose_doctor'));
                }
            }

            // Monthly plan: an unpaid plan may block check-in (Clinic
            // settings); a plan that covers today bills nothing at check-in.
            $coverage = ClinicPlans::coverage($business_id, $patient->id, $visit_date);
            if ($coverage['blocks']) {
                return $this->fail($coverage['message']);
            }
            $packages = ClinicPackage::forBusiness($business_id)->active()->perVisit()->get()->keyBy('id');
            if ($request->input('package_id') === 'plan') {
                if (! $coverage['covers']) {
                    return $this->fail($coverage['message'] ?: __('clinic.plan_none'));
                }
                $package = $coverage['subscription']->package;
                $plan_period = $coverage['period'];
            } elseif ($packages->isNotEmpty()) {
                $package = $packages->get((int) $request->input('package_id'));
                if (! $package) {
                    return $this->fail(__('clinic.choose_package'));
                }
            }

            $already = ClinicQueue::activeCheckIn($business_id, $patient->id, $visit_date);
            if ($already) {
                return $this->fail(__('clinic.already_checked_in', ['no' => $already->queue_no]));
            }
        }

        // appointments: checking in a booked patient marks the booking as arrived
        $appointment = null;
        if ($type == 'opd' && $request->filled('appointment_id')) {
            $appointment = \App\ClinicAppointment::forBusiness($business_id)->open()
                ->where('contact_id', $patient->id)->whereDate('date', $visit_date)
                ->find($request->input('appointment_id'));
            if (! $appointment) {
                return $this->fail(__('clinic.appt_not_for_checkin'));
            }
        }

        $location_id = $request->input('location_id') ?: array_key_first(BusinessLocation::forDropdown($business_id, false)->toArray());

        try {
            DB::beginTransaction();

            $ref_count = $this->commonUtil->setAndGetReferenceCount('visit', $business_id);
            $ref_no = $this->commonUtil->generateReferenceNumber('visit', $ref_count, $business_id, 'VIS-');

            $visit = Visit::create([
                'business_id' => $business_id,
                'contact_id' => $patient->id,
                'location_id' => $location_id ?: null,
                'doctor_id' => $doctor_id,
                'package_id' => optional($package)->id,
                'subscription_period_id' => optional($plan_period)->id,
                'appointment_id' => optional($appointment)->id,
                'queue_no' => $type == 'opd' ? ClinicQueue::nextQueueNo($business_id, $visit_date) : null,
                'queue_status' => $type == 'opd' ? 'waiting' : null,
                'checked_in_at' => $type == 'opd' ? now() : null,
                'type' => $type,
                'status' => 'open',
                'visit_date' => $visit_date,
                'ref_no' => $ref_no,
                'opened_by' => $user_id,
                'notes' => $request->input('notes') ?: null,
            ]);

            //Bill the consultation now: the package price, or (no packages set
            //up) the default consultation fee. Consultations on a visit billed
            //here are never charged again.
            $fee_transaction = null;
            if ($type == 'opd' && ! empty($location_id) && ! $plan_period) { // a plan visit is already paid for by the month
                $billing = new HospitalBillingUtil();
                if ($package && $package->price > 0) {
                    $service = \App\Utils\ClinicServices::forPackage($package, $user_id);
                    $fee_transaction = $billing->chargePatientFee([
                        'business_id' => $business_id,
                        'contact_id' => $patient->id,
                        'lines' => [['variation_id' => $service->id, 'product_id' => $service->product_id,
                            'quantity' => 1, 'unit_price' => (float) $package->price,
                            'note' => $visit->ref_no]],
                        'charge_type' => 'consultation',
                        'description' => __('clinic.package_product_name', ['name' => $package->name]).' · '.$visit->ref_no,
                        'location_id' => $location_id,
                        'visit_id' => $visit->id,
                        'created_by' => $user_id,
                        'reference_type' => 'visit',
                    ]);
                } elseif (! $package) {
                    $default_fee = (float) (\App\Business::where('id', $business_id)->value('consultation_fee') ?: 0);
                    if ($default_fee > 0) {
                        $fee_transaction = $billing->chargePatientFee([
                            'business_id' => $business_id,
                            'contact_id' => $patient->id,
                            'amount' => $default_fee,
                            'charge_type' => 'consultation',
                            'description' => __('lang_v1.consultation_fee').' · '.$visit->ref_no,
                            'location_id' => $location_id,
                            'visit_id' => $visit->id,
                            'created_by' => $user_id,
                            'reference_type' => 'visit',
                        ]);
                    }
                }
            }
            if ($fee_transaction) {
                $visit->fee_transaction_id = $fee_transaction->id;
                $visit->save();
            }
            if ($appointment) {
                $appointment->status = 'checked_in';
                $appointment->visit_id = $visit->id;
                $appointment->confirmed_at = $appointment->confirmed_at ?? now();
                $appointment->confirmed_by = $appointment->confirmed_by ?? $user_id;
                $appointment->save();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->fail(__('messages.something_went_wrong'));
        }

        $msg = $visit->queue_no
            ? __('clinic.checked_in_success', ['no' => $visit->queue_no, 'doctor' => optional($visit->doctor)->user_full_name ?: '-'])
            : __('lang_v1.visit_opened_success');

        return redirect()
            ->action([\App\Http\Controllers\VisitController::class, 'show'], [$visit->id])
            ->with('status', ['success' => true, 'msg' => $msg]);
    }

    private function fail($msg)
    {
        return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => $msg]);
    }

    public function show($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $visit = Visit::forBusiness($business_id)
            ->with(['patient.patientDetails', 'location', 'openedBy', 'doctor', 'package', 'feeTransaction'])
            ->findOrFail($id);

        $consultations = Consultation::forBusiness($business_id)
            ->where('visit_id', $visit->id)
            ->with(['consultedBy', 'prescriptions'])
            ->orderBy('created_at', 'desc')
            ->get();

        $pay_state = ClinicQueue::payState($visit);
        $pay_blocks = ClinicQueue::payGateBlocks($visit);

        return view('visit.show')->with(compact('visit', 'consultations', 'pay_state', 'pay_blocks'));
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
                if (in_array($visit->queue_status, ['waiting', 'with_doctor'])) {
                    $visit->queue_status = 'seen';
                    $visit->seen_at = $visit->seen_at ?? now();
                }
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

    /**
     * Cancel a check-in before the doctor has seen the patient (the patient
     * left, or was checked in by mistake). An unpaid check-in invoice is voided,
     * which needs hospital_charge.void; once the patient has paid anything the
     * money must go back through a sell return, so the check-in stays.
     */
    public function cancel($id)
    {
        $this->checkHospitalEnabled();

        if (! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $visit = Visit::forBusiness($business_id)->findOrFail($id);

        $output = $this->cancelCheckIn($visit, $business_id);

        if (request()->ajax()) {
            return $output;
        }

        return redirect()->action([self::class, 'show'], [$visit->id])->with('status', $output);
    }

    private function cancelCheckIn(Visit $visit, $business_id)
    {
        if ($visit->status != 'open' || ! in_array($visit->queue_status, ['waiting', 'no_show'])) {
            return ['success' => false, 'msg' => __('clinic.cannot_cancel_checkin')];
        }

        $transaction = $visit->fee_transaction_id
            ? Transaction::where('business_id', $business_id)->find($visit->fee_transaction_id)
            : null;

        if ($transaction) {
            if (! auth()->user()->can('hospital_charge.void')) {
                return ['success' => false, 'msg' => __('clinic.void_needs_permission')];
            }
            $paid = (float) $transaction->payment_lines()->sum('amount');
            if ($paid - \App\Utils\ClinicInsurance::legacyInsurerPaid($transaction->id) > 0.0001) {
                return ['success' => false, 'msg' => __('clinic.paid_cannot_void')];
            }
            // the insurer's part goes too, unless it was already claimed or paid
            if ($blocker = \App\Utils\ClinicInsurance::voidBlocker($transaction->id)) {
                return ['success' => false, 'msg' => $blocker];
            }
        }

        try {
            DB::beginTransaction();

            if ($transaction) {
                $result = \App\Utils\ClinicInsurance::voidFor($business_id, $transaction->id);
                if ($result !== true) {
                    DB::rollBack();

                    return $result;
                }
                $result = (new TransactionUtil())->deleteSale($business_id, $transaction->id);
                if (isset($result['success']) && ! $result['success']) {
                    DB::rollBack();

                    return $result;
                }
                VisitCharge::where('business_id', $business_id)->where('reference_id', $transaction->id)
                    ->where('reference_type', 'visit')->delete();
                $visit->fee_transaction_id = null;
            }

            if ($visit->appointment_id) {
                \App\ClinicAppointment::where('id', $visit->appointment_id)->where('status', 'checked_in')
                    ->update(['status' => 'confirmed', 'visit_id' => null]);
            }

            $visit->status = 'cancelled';
            $visit->queue_status = 'cancelled';
            $visit->closed_by = request()->session()->get('user.id');
            $visit->closed_at = now();
            $visit->save();

            $this->commonUtil->activityLog($visit, 'checkin_cancelled', null, [
                'ref_no' => $visit->ref_no,
                'voided_invoice' => optional($transaction)->invoice_no,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return ['success' => false, 'msg' => __('messages.something_went_wrong')];
        }

        return ['success' => true, 'msg' => __('clinic.checkin_cancelled')];
    }
}
