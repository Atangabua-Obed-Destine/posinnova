<?php

namespace App\Http\Controllers;

use App\Contact;
use App\TransactionPayment;
use App\Utils\ClinicCashier;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The cashier's patient accounts (clinic phase 3). Every clinic charge is a
 * credit invoice on the patient (check-in package, lab, pharmacy...). Here the
 * cashier sees who owes what, opens one patient's account, and takes one
 * payment that settles invoices oldest first (or as the cashier splits it).
 * The payment gets a receipt and goes into the cashier's open cash register.
 *
 * Viewing needs patient.view_bill; taking money also needs sell.payments.
 */
class ClinicAccountController extends Controller
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    private function authorizeView()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
        if (! auth()->user()->can('patient.view_bill')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeView();
        $business_id = $request->session()->get('user.business_id');
        $search = trim((string) $request->input('q'));

        $sells = "t.contact_id = c.id AND t.type = 'sell' AND t.status = 'final'";
        $query = DB::table('contacts as c')
            ->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'c.id')
            ->where('c.business_id', $business_id)
            ->where('c.is_patient', 1)
            ->whereNull('c.deleted_at')
            ->select(
                'c.id', 'c.name', 'c.mobile', 'pd.file_number',
                DB::raw("(SELECT COALESCE(SUM(t.final_total), 0) FROM transactions t WHERE $sells) as invoiced"),
                DB::raw("(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp JOIN transactions t ON t.id = tp.transaction_id WHERE $sells) as paid"),
                DB::raw("(SELECT MIN(t.transaction_date) FROM transactions t WHERE $sells AND t.payment_status != 'paid') as oldest_due")
            );

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('c.name', 'like', '%'.$search.'%')
                    ->orWhere('c.mobile', 'like', '%'.$search.'%')
                    ->orWhere('pd.file_number', 'like', '%'.$search.'%');
            })->orderBy('c.name')->limit(50);
        } else {
            $query->havingRaw('invoiced - paid > 0.004')->orderByRaw('invoiced - paid DESC')->limit(300);
        }

        $patients = $query->get()->map(function ($p) {
            $p->balance = max(0, round((float) $p->invoiced - (float) $p->paid, 4));

            return $p;
        });
        $total_outstanding = $search === '' ? $patients->sum('balance') : null;

        return view('clinic.accounts.index', compact('patients', 'search', 'total_outstanding'));
    }

    public function show($contact_id)
    {
        $this->authorizeView();
        $business_id = request()->session()->get('user.business_id');
        $patient = $this->findPatient($business_id, $contact_id);

        $due_invoices = ClinicCashier::dueInvoices($business_id, $patient->id);
        $recent_invoices = ClinicCashier::recentInvoices($business_id, $patient->id);
        $balance = $due_invoices->sum('due');

        // payments received from this patient: one row per receipt
        $payments = TransactionPayment::where('business_id', $business_id)
            ->where('payment_for', $patient->id)
            ->whereNull('parent_id')
            ->with('created_user')
            ->orderByDesc('paid_on')
            ->orderByDesc('id')
            ->limit(15)
            ->get();

        $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);
        $register_open = (bool) ClinicCashier::openRegister(auth()->user()->id);
        $can_pay = auth()->user()->can('sell.payments');

        $plan = \App\ClinicSubscription::forBusiness($business_id)->where('contact_id', $patient->id)->exists()
            ? \App\Utils\ClinicPlans::coverage($business_id, $patient->id) : null;

        // clinic phase 5: charges the insurer has not settled yet (not on the patient's balance)
        $insurer_open = \App\Utils\ClinicInsurance::openSharesFor($business_id, $patient->id);

        return view('clinic.accounts.show', compact('patient', 'due_invoices', 'recent_invoices', 'balance', 'payments', 'payment_types', 'register_open', 'can_pay', 'plan', 'insurer_open'));
    }

    public function pay(Request $request, $contact_id)
    {
        $this->authorizeView();
        if (! auth()->user()->can('sell.payments')) {
            abort(403, 'Unauthorized action.');
        }
        $business_id = $request->session()->get('user.business_id');
        $patient = $this->findPatient($business_id, $contact_id);
        $back = redirect()->action([self::class, 'show'], [$patient->id]);

        $amount = round((float) $this->transactionUtil->num_uf($request->input('amount')), 4);
        $method = $request->input('method');
        $payment_types = $this->transactionUtil->payment_types(null, false, $business_id);

        if ($amount <= 0) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.acc_amount_required')]);
        }
        if (! array_key_exists($method, $payment_types)) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.acc_method_required')]);
        }

        $due_invoices = ClinicCashier::dueInvoices($business_id, $patient->id)->keyBy('id');
        $balance = round($due_invoices->sum('due'), 4);
        if ($amount > $balance + 0.004) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.acc_more_than_balance')]);
        }

        // the cashier's split, or oldest invoice first
        $allocation = [];
        foreach ((array) $request->input('allocation', []) as $tid => $amt) {
            $amt = round((float) $amt, 4); // plain numbers written by the page script
            if ($amt <= 0) {
                continue;
            }
            if (! isset($due_invoices[$tid]) || $amt > $due_invoices[$tid]->due + 0.004) {
                return $back->with('status', ['success' => false, 'msg' => __('clinic.acc_split_invalid')]);
            }
            $allocation[(int) $tid] = min($amt, $due_invoices[$tid]->due);
        }
        if (empty($allocation)) {
            $allocation = ClinicCashier::allocateOldestFirst($due_invoices->values(), $amount);
        } elseif (abs(array_sum($allocation) - $amount) > 0.004) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.acc_split_mismatch')]);
        }

        try {
            DB::beginTransaction();

            $pay_request = new Request([
                'contact_id' => $patient->id,
                'method' => $method,
                'note' => mb_substr(trim((string) $request->input('note')), 0, 255) ?: null,
                'paid_on' => now()->toDateTimeString(),
                'card_number' => $request->input('card_number'),
                'card_transaction_number' => $request->input('card_transaction_number'),
                'cheque_number' => $request->input('cheque_number'),
                'bank_account_number' => $request->input('bank_account_number'),
            ]);
            for ($i = 1; $i < 8; $i++) {
                $pay_request->merge(["transaction_no_{$i}" => $request->input('transaction_no')]);
            }
            $parent = $this->transactionUtil->payContactByInvoices($pay_request, $allocation, false);

            $posted = false;
            if ($method != 'advance') {
                foreach (TransactionPayment::where('parent_id', $parent->id)->get() as $child) {
                    $posted = ClinicCashier::postToRegister(auth()->user()->id, $child->transaction_id, $child->method, $child->amount) || $posted;
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $back->with('status', ['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }

        $msg = __('clinic.acc_paid', ['ref' => $parent->payment_ref_no]);
        if (! $posted && $method != 'advance') {
            $msg .= ' '.__('clinic.acc_no_register');
        }

        return $back->with('status', ['success' => true, 'msg' => $msg])->with('print_payment_id', $parent->id);
    }

    private function findPatient($business_id, $contact_id)
    {
        return Contact::where('business_id', $business_id)->where('is_patient', 1)
            ->with('patientDetails')->findOrFail($contact_id);
    }
}
