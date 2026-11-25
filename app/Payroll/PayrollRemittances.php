<?php

namespace App\Payroll;

use App\Ledger\Ledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: what the business holds on somebody else's behalf, and paying it over
 * (paxhitestbed TaxRemittanceService). Paying salaries credits 447 (the State: IRPP, CAC, CFC, RAV,
 * TDL) and 431 (CNPS, FNE) month after month; this says what is owed, to whom, for which salary
 * month, and records the payment when it is made: Dr the liability, Cr the cash or bank it left.
 * Neither side is a cost (the salary became one when it was paid), so the Daybook does not move.
 *
 * The unit is the salary MONTH, the unit a declaration is made in. What is owed comes from the tax
 * lines of the month's PAID payrolls: each line is rounded once and the payroll's totals are their
 * sum, so the lines and the entry posted from them agree (reversing a salary payment takes its
 * lines back out). With the books kept here, the balance of each liability account is shown beside
 * it, and a difference is pointed out rather than hidden.
 */
class PayrollRemittances
{
    /** Every authority-and-month with money owed or already paid, oldest first. */
    public static function months($business_id)
    {
        $owed = DB::table('payroll_tax_lines as l')->join('payrolls as p', 'p.id', '=', 'l.payroll_id')
            ->where('p.business_id', $business_id)->where('p.status', 1)
            ->groupBy('l.liability_code', 'p.salary_month')
            ->selectRaw('l.liability_code as code, p.salary_month as month, SUM(l.employee_amount) as employee, SUM(l.employer_amount) as employer, COUNT(DISTINCT l.payroll_id) as staff')
            ->get()->keyBy(fn ($r) => ($r->code ?: '447').'|'.substr((string) $r->month, 0, 10));
        $paid = PayrollRemittance::live()->where('business_id', $business_id)
            ->groupBy('liability_code', 'salary_month')
            ->selectRaw('liability_code as code, salary_month as month, SUM(amount) as paid')
            ->get()->keyBy(fn ($r) => $r->code.'|'.substr((string) $r->month, 0, 10));

        $rows = [];
        foreach ($owed->keys()->merge($paid->keys())->unique() as $key) {
            [$code, $month] = explode('|', $key);
            $o = $owed->get($key);
            $due = round((float) ($o->employee ?? 0) + (float) ($o->employer ?? 0), 2);
            $settled = round((float) ($paid->get($key)->paid ?? 0), 2);
            $rows[] = [
                'code' => $code, 'month' => $month, 'label' => self::authority($business_id, $code),
                'employee' => round((float) ($o->employee ?? 0), 2), 'employer' => round((float) ($o->employer ?? 0), 2),
                'staff' => (int) ($o->staff ?? 0), 'due' => $due, 'paid' => $settled, 'outstanding' => round($due - $settled, 2),
                'status' => self::status($due, $settled),
            ];
        }
        usort($rows, fn ($a, $b) => [$a['month'], $a['code']] <=> [$b['month'], $b['code']]);

        return $rows;
    }

    private static function status($due, $paid)
    {
        if ($paid <= 0) {
            return 'owed';
        }
        if (abs($due - $paid) < 0.01) {
            return 'settled';
        }

        return $paid > $due ? 'overpaid' : 'part_paid';
    }

    /** Who a liability code is owed to: the chart's name when the books are kept here. */
    public static function authority($business_id, $code)
    {
        static $names = [];
        $k = $business_id.'|'.$code;
        if (! isset($names[$k])) {
            $name = PayrollCash::books($business_id)
                ? Ledger::forBusiness($business_id, fn () => \App\Ledger\Models\ChartOfAccount::where('account_code', $code)->value('account_name'))
                : null;
            $names[$k] = $code.' · '.($name ?: __('payroll.authority_'.(in_array($code, ['431', '447', '442']) ? $code : 'other')));
        }

        return $names[$k];
    }

    /** The individual taxes behind one authority-and-month, for the declaration. */
    public static function breakdown($business_id, $code, $month)
    {
        return DB::table('payroll_tax_lines as l')->join('payrolls as p', 'p.id', '=', 'l.payroll_id')
            ->where('p.business_id', $business_id)->where('p.status', 1)->where('l.liability_code', $code)->where('p.salary_month', $month)
            ->groupBy('l.label')
            ->selectRaw('l.label, SUM(l.employee_amount) as employee, SUM(l.employer_amount) as employer, COUNT(DISTINCT l.user_id) as staff')
            ->orderByRaw('SUM(l.employee_amount) + SUM(l.employer_amount) DESC')->get();
    }

    /** With the books kept here: does each liability account carry what the months say is still owed? */
    public static function reconciliation($business_id, array $months)
    {
        if (! PayrollCash::books($business_id)) {
            return [];
        }
        $perCode = [];
        foreach ($months as $m) {
            $perCode[$m['code']] = round(($perCode[$m['code']] ?? 0) + $m['outstanding'], 2);
        }
        $rows = [];
        foreach ($perCode as $code => $outstanding) {
            $ledger = Ledger::forBusiness($business_id, function () use ($business_id, $code) {
                $id = \App\Ledger\Models\ChartOfAccount::where('account_code', $code)->value('id');

                return $id ? round((float) DB::table('journal_entry_lines as l')->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
                    ->where('e.business_id', $business_id)->where('e.is_posted', 1)->whereNull('e.deleted_at')->where('l.account_id', $id)
                    ->sum(DB::raw('l.credit - l.debit')), 2) : null;
            });
            if ($ledger === null) {
                continue;
            }
            $rows[] = ['code' => $code, 'label' => self::authority($business_id, $code), 'outstanding' => $outstanding, 'ledger' => $ledger,
                'difference' => round($ledger - $outstanding, 2), 'agrees' => abs($ledger - $outstanding) < 0.51];
        }

        return $rows;
    }

    /** @param array $in code, month (Y-m-d), amount, payment_date, payment_account_id, reference, note, allow_additional, allow_overpay */
    public static function record($business_id, array $in, $actor_id)
    {
        $code = preg_match('/^\d{2,10}$/', (string) ($in['code'] ?? '')) ? $in['code'] : null;
        $month = isset($in['month']) && strtotime($in['month']) ? Carbon::parse($in['month'])->startOfMonth()->toDateString() : null;
        $amount = round((float) str_replace([',', ' '], '', (string) ($in['amount'] ?? 0)), 2);
        if (! $code || ! $month) {
            throw new PayrollException(__('payroll.err_remit_month'));
        }
        if ($amount <= 0) {
            throw new PayrollException(__('payroll.err_remit_amount'));
        }
        $date = PayrollCash::date($in['payment_date'] ?? null);
        $account = PayrollCash::account($business_id, (int) ($in['payment_account_id'] ?? 0));
        $row = collect(self::months($business_id))->first(fn ($r) => $r['code'] === $code && $r['month'] === $month);
        $due = $row['due'] ?? 0.0;
        $already = $row['paid'] ?? 0.0;
        if ($already > 0 && empty($in['allow_additional'])) {
            throw new PayrollException(__('payroll.err_remit_already'));
        }
        if ($amount > round($due - $already, 2) + 0.01 && empty($in['allow_overpay'])) {
            throw new PayrollException(__('payroll.err_remit_over'));
        }
        $reference = mb_substr(trim((string) ($in['reference'] ?? '')), 0, 100) ?: null;
        $note = mb_substr(trim((string) ($in['note'] ?? '')), 0, 1000) ?: null;

        return DB::transaction(function () use ($business_id, $code, $month, $amount, $date, $account, $reference, $note, $actor_id) {
            $r = PayrollRemittance::create(['business_id' => $business_id, 'liability_code' => $code, 'salary_month' => $month, 'amount' => $amount,
                'payment_date' => $date->toDateString(), 'payment_account_id' => $account->id, 'reference' => $reference, 'note' => $note, 'created_by' => $actor_id]);
            $what = __('payroll.remit_je', ['who' => self::authority($business_id, $code), 'month' => PayrollBook::monthLabel($month)]);
            [$atId, $entryId] = PayrollCash::out($business_id, $account, $amount, 'tax_remittance', 'REM-'.$r->id,
                $what.($reference ? ' · '.$reference : ''), $date, $code, $what, $what, 'payroll_remittance', $r->id, $actor_id);
            $r->fill(['account_transaction_id' => $atId, 'journal_entry_id' => $entryId])->save();

            return $r;
        });
    }

    /** Void a payment: never a delete — the row stays, the entry is reversed, the money comes back. */
    public static function void(PayrollRemittance $r, $reason, $actor_id)
    {
        if ($r->isVoided()) {
            throw new PayrollException(__('payroll.err_remit_voided'));
        }

        return DB::transaction(function () use ($r, $reason, $actor_id) {
            PayrollCash::undo($r->business_id, $r->account_transaction_id, $r->journal_entry_id, 'payroll_remittance_reversal', $r->id, __('payroll.why_remit_voided'), $actor_id);
            $r->fill(['voided_at' => now(), 'voided_by' => $actor_id, 'void_reason' => mb_substr(trim((string) $reason), 0, 255) ?: null, 'account_transaction_id' => null])->save();

            return $r;
        });
    }
}
