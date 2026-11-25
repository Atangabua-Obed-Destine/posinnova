<?php

namespace App\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: staff loans and salary advances (paxhitestbed StaffLoanController and
 * StaffLoanAccounting). Giving one pays the money out of a payment account (Dr 421 staff advances,
 * Cr the cash or bank); each payroll from the start month then deducts the instalment until the loan
 * is repaid (PayrollBook::startingLines), and paying that payroll credits 421 back.
 */
class PayrollLoans
{
    /** @param array $in user_id, kind, title, amount, instalment, start_month (Y-m), given_on, payment_account_id, note */
    public static function give($business_id, array $in, $actor_id)
    {
        $staff = PayrollStaff::where('business_id', $business_id)->where('user_id', (int) ($in['user_id'] ?? 0))->with('user')->first();
        if (! $staff || ! $staff->user) {
            throw new PayrollException(__('payroll.err_loan_staff'));
        }
        $kind = in_array($in['kind'] ?? '', PayrollLoan::KINDS, true) ? $in['kind'] : 'loan';
        $num = fn ($v) => is_numeric($x = str_replace([',', ' '], '', (string) $v)) ? round((float) $x, 2) : 0.0;
        $amount = $num($in['amount'] ?? 0);
        $instalment = $num($in['instalment'] ?? 0);
        if ($amount <= 0) {
            throw new PayrollException(__('payroll.err_loan_amount'));
        }
        if ($instalment <= 0 || $instalment - $amount > 0.005) {
            throw new PayrollException(__('payroll.err_loan_instalment'));
        }
        if (! preg_match('/^\d{4}-\d{2}$/', (string) ($in['start_month'] ?? ''))) {
            throw new PayrollException(__('payroll.err_loan_start'));
        }
        $start = Carbon::createFromFormat('Y-m-d', $in['start_month'].'-01')->startOfDay();
        $given = PayrollCash::date($in['given_on'] ?? null);
        $account = PayrollCash::account($business_id, (int) ($in['payment_account_id'] ?? 0));
        $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 100) ?: __('payroll.kind_'.$kind);
        $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 500) ?: null;

        return DB::transaction(function () use ($business_id, $staff, $kind, $title, $amount, $instalment, $start, $given, $account, $note, $actor_id) {
            $loan = PayrollLoan::create(['business_id' => $business_id, 'user_id' => $staff->user_id, 'kind' => $kind, 'title' => $title,
                'amount' => $amount, 'instalment' => $instalment, 'start_month' => $start->toDateString(), 'given_on' => $given->toDateString(),
                'status' => 'active', 'note' => $note, 'payment_account_id' => $account->id, 'created_by' => $actor_id]);
            $who = trim($staff->user->first_name.' '.$staff->user->last_name);
            [$atId, $entryId] = PayrollCash::out($business_id, $account, $amount, 'staff_loan', 'LOAN-'.$loan->id,
                __('payroll.loan_movement', ['title' => $title, 'name' => $who]), $given,
                PayrollRules::settings($business_id)['advance_account_code'] ?: '421',
                __('payroll.loan_je', ['title' => $title, 'name' => $who]), __('payroll.loan_je', ['title' => $title, 'name' => $who]),
                'payroll_loan', $loan->id, $actor_id);
            $loan->fill(['account_transaction_id' => $atId, 'journal_entry_id' => $entryId])->save();

            return $loan;
        });
    }

    /**
     * Cancel a loan entered by mistake: only before anything has been put on a payroll for it. A
     * loan partly repaid is a real debt, not a mistake, and its history must stay.
     */
    public static function cancel(PayrollLoan $loan, $actor_id)
    {
        if ($loan->status !== 'active') {
            throw new PayrollException(__('payroll.err_loan_cancelled'));
        }
        if ($loan->lines()->exists()) {
            throw new PayrollException(__('payroll.err_loan_on_payroll'));
        }

        return DB::transaction(function () use ($loan, $actor_id) {
            PayrollCash::undo($loan->business_id, $loan->account_transaction_id, $loan->journal_entry_id, 'payroll_loan_reversal', $loan->id, __('payroll.why_loan_cancelled'), $actor_id);
            $loan->fill(['status' => 'cancelled', 'account_transaction_id' => null, 'updated_by' => $actor_id])->save();

            return $loan;
        });
    }
}
