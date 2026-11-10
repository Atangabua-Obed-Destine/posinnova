<?php

namespace App\Http\Controllers;

use App\Business;
use App\ClinicClaim;
use App\ClinicInsurer;
use App\ClinicInsuranceShare;
use App\TransactionPayment;
use App\Utils\ClinicCashier;
use App\Utils\ClinicInsurance;
use App\Utils\TransactionUtil;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Insurance claims (clinic phase 5).
 *
 * A claim gathers an insurer's charges for a period. While it is a draft it
 * can be refreshed and items taken out; once submitted (sent to the insurer)
 * its items are fixed. The insurer's payment is recorded against the claim as
 * an ordinary payment on its invoices; what it refuses is billed to the
 * patient (ClinicInsurance::rebill). Needs clinic_insurance.manage.
 */
class ClinicClaimController extends Controller
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    public function index(Request $request)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');

        $insurers = ClinicInsurer::forBusiness($business_id)->orderBy('name')->get();
        $balances = ClinicInsurance::balances($business_id);

        $claims = ClinicClaim::forBusiness($business_id)
            ->when($request->filled('insurer_id'), fn ($q) => $q->where('insurer_id', $request->input('insurer_id')))
            ->with('insurer')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();
        foreach ($claims as $c) {
            $c->summary = ClinicInsurance::claimSummary($c);
        }

        return view('clinic.insurance.claims', compact('insurers', 'balances', 'claims'));
    }

    public function create(Request $request)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $insurers = ClinicInsurer::forBusiness($business_id)->orderBy('name')->pluck('name', 'id');
        $from = Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = $from->copy()->endOfMonth();

        return view('clinic.insurance.claim_create', [
            'insurers' => $insurers,
            'insurer_id' => $request->input('insurer_id'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function store(Request $request)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $insurer = ClinicInsurer::forBusiness($business_id)->find($request->input('insurer_id'));
        [$from, $to] = $this->range($request);
        if (! $insurer || ! $from) {
            return back()->withInput()->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_choose')]);
        }

        $shares = ClinicInsurance::unclaimed($business_id, $insurer->id, $from, $to);
        if ($shares->isEmpty()) {
            return back()->withInput()->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_nothing')]);
        }

        try {
            DB::beginTransaction();
            $claim = ClinicClaim::create([
                'business_id' => $business_id,
                'insurer_id' => $insurer->id,
                'ref_no' => ClinicInsurance::nextClaimRef($business_id),
                'date_from' => $from,
                'date_to' => $to,
                'status' => 'draft',
                'notes' => mb_substr(trim((string) $request->input('notes')), 0, 2000) ?: null,
                'created_by' => $request->session()->get('user.id'),
            ]);
            ClinicInsuranceShare::whereIn('id', $shares->pluck('id'))->whereNull('claim_id')->update(['claim_id' => $claim->id]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return back()->withInput()->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'show'], [$claim->id])
            ->with('status', ['success' => true, 'msg' => __('clinic.ins_claim_created', ['n' => $shares->count()])]);
    }

    public function show(Request $request, $id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        [$claim, $items, $summary] = $this->load($business_id, $id);
        $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);
        unset($payment_types['advance']);
        $unclaimed = $claim->status == 'draft'
            ? ClinicInsurance::unclaimed($business_id, $claim->insurer_id, $claim->date_from->toDateString(), $claim->date_to->toDateString())->count()
            : 0;

        return view('clinic.insurance.claim_show', compact('claim', 'items', 'summary', 'payment_types', 'unclaimed'));
    }

    public function printClaim(Request $request, $id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        [$claim, $items, $summary] = $this->load($business_id, $id);
        $business = Business::find($business_id);

        return view('clinic.insurance.claim_print', compact('claim', 'items', 'summary', 'business'));
    }

    /**
     * Draft: add the insurer's charges of the period that came in since.
     */
    public function refresh(Request $request, $id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $claim = ClinicClaim::forBusiness($business_id)->findOrFail($id);
        $back = redirect()->action([self::class, 'show'], [$claim->id]);
        if ($claim->status != 'draft') {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_not_draft')]);
        }
        $shares = ClinicInsurance::unclaimed($business_id, $claim->insurer_id, $claim->date_from->toDateString(), $claim->date_to->toDateString());
        ClinicInsuranceShare::whereIn('id', $shares->pluck('id'))->whereNull('claim_id')->update(['claim_id' => $claim->id]);

        return $back->with('status', ['success' => true, 'msg' => __('clinic.ins_claim_refreshed', ['n' => $shares->count()])]);
    }

    public function removeItem(Request $request, $id, $share_id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $claim = ClinicClaim::forBusiness($business_id)->findOrFail($id);
        $back = redirect()->action([self::class, 'show'], [$claim->id]);
        if ($claim->status != 'draft') {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_not_draft')]);
        }
        ClinicInsuranceShare::where('claim_id', $claim->id)->where('id', $share_id)->update(['claim_id' => null]);

        return $back->with('status', ['success' => true, 'msg' => __('clinic.ins_item_removed')]);
    }

    public function submit(Request $request, $id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $claim = ClinicClaim::forBusiness($business_id)->findOrFail($id);
        $back = redirect()->action([self::class, 'show'], [$claim->id]);
        if ($claim->status != 'draft') {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_not_draft')]);
        }
        if (! $claim->items()->exists()) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_empty')]);
        }
        $claim->status = 'submitted';
        $claim->submitted_at = now();
        $claim->submitted_by = $request->session()->get('user.id');
        $claim->save();
        $this->transactionUtil->activityLog($claim, 'claim_submitted', null, ['ref_no' => $claim->ref_no]);

        return $back->with('status', ['success' => true, 'msg' => __('clinic.ins_claim_submitted')]);
    }

    /**
     * Submitted by mistake: back to draft while nothing was paid or refused.
     */
    public function reopen(Request $request, $id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        [$claim, $items, $summary] = $this->load($business_id, $id);
        $back = redirect()->action([self::class, 'show'], [$claim->id]);
        if ($claim->status != 'submitted' || $summary['paid'] > 0 || $summary['rebilled'] > 0) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_cannot_reopen')]);
        }
        $claim->status = 'draft';
        $claim->submitted_at = null;
        $claim->submitted_by = null;
        $claim->save();

        return $back->with('status', ['success' => true, 'msg' => __('clinic.ins_claim_reopened')]);
    }

    public function destroy(Request $request, $id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $claim = ClinicClaim::forBusiness($business_id)->findOrFail($id);
        if ($claim->status != 'draft') {
            return redirect()->action([self::class, 'show'], [$claim->id])->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_not_draft')]);
        }
        DB::transaction(function () use ($claim) {
            ClinicInsuranceShare::where('claim_id', $claim->id)->update(['claim_id' => null]);
            $claim->delete();
        });

        return redirect()->action([self::class, 'index'])->with('status', ['success' => true, 'msg' => __('clinic.ins_claim_deleted')]);
    }

    /**
     * The insurer's answer: what it paid on each item, and which unpaid rest
     * goes to the patient.
     */
    public function settle(Request $request, $id)
    {
        ClinicInsurerController::authorizeInsurance();
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');
        [$claim, $items, $summary] = $this->load($business_id, $id);
        $back = redirect()->action([self::class, 'show'], [$claim->id]);
        if ($claim->status != 'submitted') {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_claim_not_submitted')]);
        }

        $items = $items->keyBy('id');
        $allocation = [];
        $rebills = [];
        foreach ((array) $request->input('items', []) as $sid => $row) {
            if (! isset($items[$sid]) || $items[$sid]->due <= 0.0001) {
                continue;
            }
            $item = $items[$sid];
            $paid = round((float) $this->transactionUtil->num_uf($row['paid'] ?? 0), 4);
            if ($paid < 0 || $paid > $item->due + 0.004) {
                return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_paid_too_much', ['patient' => optional($item->patient)->name])]);
            }
            if ($paid > 0) {
                $allocation[$item->insurer_transaction_id] = min($paid, $item->due);
            }
            if (! empty($row['rebill']) && $paid < $item->due - 0.0001) {
                $rebills[] = $item->id;
            }
        }
        if (empty($allocation) && empty($rebills)) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.ins_nothing_entered')]);
        }

        $method = $request->input('method');
        $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);
        if (! empty($allocation) && (! array_key_exists($method, $payment_types) || $method == 'advance')) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.acc_method_required')]);
        }
        try {
            $paid_on = $request->filled('paid_on') ? Carbon::parse($request->input('paid_on')) : Carbon::today();
        } catch (\Exception $e) {
            $paid_on = Carbon::today();
        }
        if ($paid_on->isFuture()) {
            $paid_on = Carbon::today();
        }
        $paid_on = $paid_on->setTimeFrom(now());
        $reason = mb_substr(trim((string) $request->input('reason')), 0, 150);

        try {
            DB::beginTransaction();

            $total = 0;
            if (! empty($allocation)) {
                $pay_request = new Request([
                    'contact_id' => $claim->insurer->contact_id,
                    'method' => $method,
                    'note' => trim($claim->ref_no.' '.mb_substr(trim((string) $request->input('reference')), 0, 200)),
                    'paid_on' => $paid_on->toDateTimeString(),
                    'cheque_number' => $request->input('reference'),
                    'bank_account_number' => $request->input('bank_account_number'),
                ]);
                for ($i = 1; $i < 8; $i++) {
                    $pay_request->merge(["transaction_no_{$i}" => $request->input('reference')]);
                }
                $parent = $this->transactionUtil->payContactByInvoices($pay_request, $allocation, false);
                foreach (TransactionPayment::where('parent_id', $parent->id)->get() as $child) {
                    ClinicCashier::postToRegister(auth()->user()->id, $child->transaction_id, $child->method, $child->amount);
                }
                $total = array_sum($allocation);
            }

            $rebilled = 0;
            foreach ($rebills as $sid) {
                $share = ClinicInsuranceShare::with(['insurer', 'claim'])->find($sid);
                $before = ClinicInsurance::invoiceDue($share->insurer_transaction_id);
                if (ClinicInsurance::rebill($share, $reason, $user_id)) {
                    $rebilled += $before;
                }
            }

            $this->transactionUtil->activityLog($claim, 'claim_settled', null, ['ref_no' => $claim->ref_no, 'paid' => $total, 'rebilled' => $rebilled]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $back->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        return $back->with('status', ['success' => true, 'msg' => __('clinic.ins_settled', [
            'paid' => $this->transactionUtil->num_f($total, true),
            'rebilled' => $this->transactionUtil->num_f($rebilled, true),
        ])]);
    }

    private function load($business_id, $id)
    {
        $claim = ClinicClaim::forBusiness($business_id)->with(['insurer', 'createdBy', 'submittedBy'])->findOrFail($id);
        $items = ClinicInsurance::attach(ClinicInsuranceShare::where('claim_id', $claim->id)
            ->with(['patient.patientDetails', 'patientInvoice', 'rebillInvoice'])
            ->join('transactions as pt', 'pt.id', '=', 'clinic_insurance_shares.patient_transaction_id')
            ->orderBy('pt.transaction_date')->orderBy('clinic_insurance_shares.id')
            ->select('clinic_insurance_shares.*', 'pt.transaction_date as charged_on', 'pt.invoice_no as patient_invoice_no')
            ->get());

        return [$claim, $items, ClinicInsurance::claimSummary($claim, $items)];
    }

    private function range(Request $request)
    {
        try {
            $from = Carbon::parse($request->input('date_from'))->toDateString();
            $to = Carbon::parse($request->input('date_to'))->toDateString();
        } catch (\Exception $e) {
            return [null, null];
        }
        if (! $request->filled('date_from') || ! $request->filled('date_to') || $from > $to) {
            return [null, null];
        }

        return [$from, $to];
    }
}
