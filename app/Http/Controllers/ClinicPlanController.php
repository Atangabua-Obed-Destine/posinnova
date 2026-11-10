<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\ClinicPackage;
use App\ClinicSubscription;
use App\ClinicSubscriptionPeriod;
use App\Contact;
use App\Transaction;
use App\Utils\ClinicInsurance;
use App\Utils\ClinicPlans;
use App\Utils\ClinicSettings;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\VisitCharge;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Monthly plans (clinic phase 4): put a patient on a monthly package, see who
 * is on which plan and whether this month is paid, bill due plans by hand
 * (the daily scheduled run does it automatically), and cancel a plan.
 *
 * Everything here needs clinic_plan.manage. Coverage at check-in is in
 * VisitController via App\Utils\ClinicPlans::coverage().
 */
class ClinicPlanController extends Controller
{
    protected $commonUtil;

    public function __construct(Util $commonUtil)
    {
        $this->commonUtil = $commonUtil;
    }

    private function authorizePlans()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        if (! auth()->user()->can('clinic_plan.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizePlans();
        $business_id = $request->session()->get('user.business_id');
        $show = $request->input('show') == 'cancelled' ? 'cancelled' : 'active';

        $subs = ClinicSubscription::forBusiness($business_id)
            ->where('status', $show)
            ->with(['patient.patientDetails', 'package'])
            ->orderBy('next_bill_date')
            ->get();

        // the current period of each plan, with paid state and visits used
        $today = Carbon::today();
        foreach ($subs as $s) {
            $s->current_period = ClinicSubscriptionPeriod::where('subscription_id', $s->id)
                ->whereDate('period_start', '<=', $today)->whereDate('period_end', '>=', $today)->first();
            if ($s->current_period) {
                $s->current_paid = ClinicPlans::periodPaid($s->current_period);
                $s->current_used = $s->current_period->visitsUsed();
            }
        }

        $due_now = ClinicSubscription::forBusiness($business_id)->active()->whereDate('next_bill_date', '<=', $today)->count();
        $settings = ClinicSettings::all($business_id);
        $has_plans = ClinicPackage::forBusiness($business_id)->monthly()->active()->exists();

        return view('clinic.plans.index', compact('subs', 'show', 'due_now', 'settings', 'has_plans'));
    }

    public function create(Request $request)
    {
        $this->authorizePlans();
        $business_id = $request->session()->get('user.business_id');

        $plans = ClinicPackage::forBusiness($business_id)->monthly()->active()->ordered()->get();
        $locations = BusinessLocation::forDropdown($business_id, false);
        $settings = ClinicSettings::all($business_id);
        $preselected = $request->filled('contact_id')
            ? Contact::where('business_id', $business_id)->onlyPatients()->with('patientDetails')->find($request->input('contact_id'))
            : null;

        return view('clinic.plans.create', compact('plans', 'locations', 'settings', 'preselected'));
    }

    public function store(Request $request)
    {
        $this->authorizePlans();
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        $patient = Contact::where('business_id', $business_id)->onlyPatients()->find($request->input('contact_id'));
        $package = ClinicPackage::forBusiness($business_id)->monthly()->active()->find($request->input('package_id'));
        if (! $patient || ! $package) {
            return $this->fail(__('clinic.plan_choose_patient_and_plan'));
        }
        if (ClinicPlans::activeFor($business_id, $patient->id)) {
            return $this->fail(__('clinic.plan_already_active'));
        }

        try {
            $start = Carbon::parse($request->input('start_date') ?: Carbon::today()->toDateString())->startOfDay();
        } catch (\Exception $e) {
            return $this->fail(__('clinic.plan_bad_start'));
        }
        if ($start->lt(Carbon::today()->subDays(31)) || $start->gt(Carbon::today()->addDays(90))) {
            return $this->fail(__('clinic.plan_bad_start'));
        }

        $location_id = $request->input('location_id');
        if (! array_key_exists($location_id, BusinessLocation::forDropdown($business_id, false)->toArray())) {
            $location_id = BusinessLocation::where('business_id', $business_id)->value('id');
        }

        try {
            DB::beginTransaction();
            $sub = ClinicSubscription::create([
                'business_id' => $business_id,
                'contact_id' => $patient->id,
                'package_id' => $package->id,
                'location_id' => $location_id,
                'status' => 'active',
                'start_date' => $start->toDateString(),
                'next_bill_date' => $start->toDateString(),
                'notes' => mb_substr(trim((string) $request->input('notes')), 0, 1000) ?: null,
                'created_by' => $user_id,
            ]);
            // a plan starting today (or earlier) is billed at once
            $billed = ClinicPlans::billSubscription($sub->id, Carbon::today(), $user_id);
            $this->commonUtil->activityLog($sub, 'plan_subscribed', null, ['patient' => $patient->name, 'plan' => $package->name, 'start' => $start->toDateString()]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->fail(__('messages.something_went_wrong'));
        }

        $msg = empty($billed) ? __('clinic.plan_subscribed_later', ['date' => $this->commonUtil->format_date($start->toDateString())])
            : __('clinic.plan_subscribed', ['n' => count($billed)]);

        return redirect()->action([self::class, 'show'], [$sub->id])->with('status', ['success' => true, 'msg' => $msg]);
    }

    public function show($id)
    {
        $this->authorizePlans();
        $business_id = request()->session()->get('user.business_id');
        $sub = ClinicSubscription::forBusiness($business_id)->with(['patient.patientDetails', 'package', 'createdBy', 'cancelledBy'])->findOrFail($id);

        $periods = ClinicSubscriptionPeriod::where('subscription_id', $sub->id)->with('invoice')->orderByDesc('period_start')->get();
        foreach ($periods as $p) {
            $p->used = $p->visitsUsed();
            $p->paid = ClinicPlans::periodPaid($p);
        }
        $coverage = ClinicPlans::coverage($business_id, $sub->contact_id);
        $can_void = auth()->user()->can('hospital_charge.void');

        return view('clinic.plans.show', compact('sub', 'periods', 'coverage', 'can_void'));
    }

    /**
     * Stop billing. The months already billed stay on the account and still
     * cover visits; optionally the current month's invoice is cancelled when
     * nothing was paid and no visit used it.
     */
    public function cancel(Request $request, $id)
    {
        $this->authorizePlans();
        $business_id = $request->session()->get('user.business_id');
        $sub = ClinicSubscription::forBusiness($business_id)->findOrFail($id);
        $back = redirect()->action([self::class, 'show'], [$sub->id]);

        if ($sub->status != 'active') {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.plan_not_active')]);
        }

        $void = null;
        if ($request->boolean('void_current')) {
            if (! auth()->user()->can('hospital_charge.void')) {
                return $back->with('status', ['success' => false, 'msg' => __('clinic.void_needs_permission')]);
            }
            $void = ClinicSubscriptionPeriod::where('subscription_id', $sub->id)->orderByDesc('period_start')->first();
            if (! $void || $void->visitsUsed() > 0) {
                return $back->with('status', ['success' => false, 'msg' => __('clinic.plan_cannot_void_used')]);
            }
            if ($void->transaction_id) {
                $paid = (float) DB::table('transaction_payments')->where('transaction_id', $void->transaction_id)->sum('amount');
                if ($paid - ClinicInsurance::legacyInsurerPaid($void->transaction_id) > 0.0001) {
                    return $back->with('status', ['success' => false, 'msg' => __('clinic.paid_cannot_void')]);
                }
                if ($blocker = ClinicInsurance::voidBlocker($void->transaction_id)) {
                    return $back->with('status', ['success' => false, 'msg' => $blocker]);
                }
            }
        }

        try {
            DB::beginTransaction();
            $voided_invoice = null;
            if ($void) {
                if ($void->transaction_id) {
                    $voided_invoice = Transaction::where('id', $void->transaction_id)->value('invoice_no');
                    $result = ClinicInsurance::voidFor($business_id, $void->transaction_id);
                    if ($result !== true) {
                        DB::rollBack();

                        return $back->with('status', $result);
                    }
                    $result = (new TransactionUtil())->deleteSale($business_id, $void->transaction_id);
                    if (isset($result['success']) && ! $result['success']) {
                        DB::rollBack();

                        return $back->with('status', $result);
                    }
                    VisitCharge::where('business_id', $business_id)->where('reference_id', $void->transaction_id)->delete();
                }
                $void->delete();
            }

            $sub->status = 'cancelled';
            $sub->cancelled_at = now();
            $sub->cancelled_by = $request->session()->get('user.id');
            $sub->cancel_reason = mb_substr(trim((string) $request->input('reason')), 0, 191) ?: null;
            $sub->save();

            $this->commonUtil->activityLog($sub, 'plan_cancelled', null, ['voided_invoice' => $voided_invoice, 'reason' => $sub->cancel_reason]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $back->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return $back->with('status', ['success' => true, 'msg' => $void ? __('clinic.plan_cancelled_voided') : __('clinic.plan_cancelled')]);
    }

    /**
     * Bill every plan due today now (the scheduled run does this each night).
     */
    public function billNow(Request $request)
    {
        $this->authorizePlans();
        $business_id = $request->session()->get('user.business_id');
        $n = ClinicPlans::billDue($business_id, Carbon::today(), $request->session()->get('user.id'));

        return redirect()->action([self::class, 'index'])->with('status', ['success' => true, 'msg' => trans_choice('clinic.plan_billed_now', $n, ['n' => $n])]);
    }

    /**
     * Check-in screen: what the patient's plan covers today (JSON).
     */
    public function coverage(Request $request)
    {
        $enabled_modules = $request->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules) || ! auth()->user()->can('visit.create')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = $request->session()->get('user.business_id');
        $patient = Contact::where('business_id', $business_id)->onlyPatients()->find($request->input('contact_id'));
        if (! $patient) {
            return response()->json(['state' => 'none']);
        }
        $c = ClinicPlans::coverage($business_id, $patient->id, $request->input('date'));

        return response()->json([
            'state' => $c['state'],
            'covers' => $c['covers'],
            'blocks' => $c['blocks'],
            'message' => $c['message'],
            'plan' => optional(optional($c['subscription'])->package)->name,
            'used' => $c['used'],
            'allowed' => $c['allowed'],
        ]);
    }

    private function fail($msg)
    {
        return redirect()->back()->withInput()->with('status', ['success' => false, 'msg' => $msg]);
    }
}
