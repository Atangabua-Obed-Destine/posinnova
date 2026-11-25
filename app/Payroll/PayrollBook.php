<?php

namespace App\Payroll;

use App\AccountTransaction;
use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\JournalEntry;
use App\Ledger\Services\LedgerPoster;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: saving, approving, paying and reversing payrolls (paxhitestbed PayrollWriter,
 * PayrollPayer, PayrollAccountingService).
 *
 * Nothing is posted when a payroll is generated or approved. Paying writes ONE journal entry, dated
 * the pay date, through the ledger's only way in (LedgerPoster), when the business keeps its books
 * in PosInnova:
 *
 *   Dr 661 salaries          gross salary (+ loan repayments taken from it)
 *   Dr 664 employer charges  employer contributions
 *       Cr 421 staff advances the loan and advance repayments withheld
 *       Cr 431 / 447 …       each tax and contribution, both shares, on the account it is owed on
 *       Cr 5xx               the net salary, from the payment account it was paid from
 *
 * The money also leaves that payment account (Payment Accounts › its book), so the cash book and
 * "Cash and bank in the ledger" keep agreeing; that movement is not posted again by the sync.
 * Reversing a payment reverses the entry (today) and removes the movement.
 */
class PayrollBook
{
    // ------------------------------------------------------------------ generate

    /**
     * The lines a new payroll starts from: last month's (when the settings say so), without last
     * month's loan repayments, then each open loan's instalment for this month, worked out afresh so
     * a loan stops being deducted once it is repaid.
     */
    public static function startingLines($business_id, $user_id, Carbon $month)
    {
        $out = ['allowances' => [], 'deductions' => [], 'from' => null];
        if (! empty(PayrollRules::settings($business_id)['carry_over_lines'])) {
            $prev = Payroll::where('business_id', $business_id)->where('user_id', $user_id)->where('salary_month', '<', $month->toDateString())
                ->orderByDesc('salary_month')->first();
            if ($prev) {
                $d = $prev->details()->get();
                $out = [
                    'allowances' => $d->where('status', 1)->map(fn ($x) => ['title' => $x->title, 'amount' => (float) $x->amount])->values()->all(),
                    'deductions' => $d->where('status', 0)->whereNull('staff_loan_id')->map(fn ($x) => ['title' => $x->title, 'amount' => (float) $x->amount])->values()->all(),
                    'from' => $prev->salary_month,
                ];
            }
        }
        foreach (PayrollLoan::dueLines($business_id, $user_id, $month) as $l) {
            $out['deductions'][] = $l;
        }

        return $out;
    }

    /** Generate (or change) a staff member's payroll for a month. Clears its approval. */
    public static function save($business_id, $user_id, Carbon $month, array $allowances, array $deductions, $actor_id, $hours = null)
    {
        $month = $month->copy()->startOfMonth();
        $staff = PayrollStaff::where('business_id', $business_id)->where('user_id', $user_id)->first();
        if (! $staff) {
            throw new PayrollException(__('payroll.err_no_salary'));
        }
        $existing = Payroll::where('business_id', $business_id)->where('user_id', $user_id)->where('salary_month', $month->toDateString())->first();
        if ($existing && $existing->isPaid()) {
            throw new PayrollException(__('payroll.err_paid_locked'));
        }
        $c = PayrollCalculator::calculate($business_id, $staff, $month, $allowances, $deductions, $existing, $hours);
        if ($c['problems']) {
            throw new PayrollException(__('payroll.err_not_saved', ['why' => implode(' ', $c['problems'])]));
        }

        return DB::transaction(function () use ($business_id, $user_id, $month, $staff, $c, $actor_id, $existing) {
            $p = Payroll::updateOrCreate(['business_id' => $business_id, 'user_id' => $user_id, 'salary_month' => $month->toDateString()], [
                'basic_salary' => $c['pay_type'] === 'hourly' ? $c['hourly_rate'] : $staff->basic_salary,
                'salary_type' => $c['pay_type'] === 'hourly' ? 2 : 1, 'hours_worked' => $c['hours_worked'], 'total_earning' => $c['earning'],
                'paid_days' => $c['paid_days'], 'period_days' => $c['period_days'],
                'total_allowance' => $c['total_allowance'], 'total_deduction' => $c['total_deduction'],
                'gross_salary' => $c['gross'], 'taxable_salary' => $c['taxable'], 'tax' => $c['tax'], 'employer_tax' => $c['employer_tax'],
                'net_salary' => $c['net'], 'total_cost' => $c['total_cost'], 'status' => 0,
                'approved_by' => null, 'approved_at' => null,
                'created_by' => optional($existing)->created_by ?: $actor_id, 'updated_by' => $actor_id,
            ]);
            PayrollDetail::where('payroll_id', $p->id)->delete();
            foreach ($c['allowances'] as $l) {
                PayrollDetail::create(['payroll_id' => $p->id, 'title' => $l['title'], 'amount' => $l['amount'], 'is_taxable' => $l['is_taxable'], 'status' => 1]);
            }
            foreach ($c['deductions'] as $l) {
                PayrollDetail::create(['payroll_id' => $p->id, 'staff_loan_id' => $l['loan_id'] ?? null, 'title' => $l['title'], 'amount' => $l['amount'], 'status' => 0]);
            }
            self::recordTaxLines($p, $c['tax_lines']);

            return $p->fresh();
        });
    }

    private static function recordTaxLines(Payroll $p, array $lines)
    {
        PayrollTaxLine::where('payroll_id', $p->id)->delete();
        foreach ($lines as $l) {
            PayrollTaxLine::create(['business_id' => $p->business_id, 'payroll_id' => $p->id, 'user_id' => $p->user_id, 'salary_month' => $p->salary_month,
                'tax_group_id' => $l['tax_group_id'], 'tax_setting_id' => $l['tax_setting_id'], 'label' => $l['label'],
                'employee_amount' => $l['employee'], 'employer_amount' => $l['employer'], 'liability_code' => $l['liability_code']]);
        }
    }

    public static function approve(Payroll $p, $actor_id)
    {
        if ($p->isPaid()) {
            throw new PayrollException(__('payroll.err_already_paid'));
        }
        if ($p->isApproved()) {
            throw new PayrollException(__('payroll.err_already_approved'));
        }
        $p->approved_by = $actor_id;
        $p->approved_at = now();
        $p->save();

        return $p;
    }

    // ------------------------------------------------------------------ pay

    /** Pay a payroll from a payment account (cash box, bank, mobile money), dated $pay_date. */
    public static function pay(Payroll $p, $pay_date, $payment_account_id, $note, $actor_id)
    {
        $date = Carbon::parse($pay_date)->startOfDay();
        if ($date->gt(today())) {
            throw new PayrollException(__('payroll.err_future_date'));
        }
        $account = DB::table('accounts')->where('business_id', $p->business_id)->where('id', $payment_account_id)->where('is_closed', 0)->whereNull('deleted_at')->first();
        if (! $account) {
            throw new PayrollException(__('payroll.err_account'));
        }

        return DB::transaction(function () use ($p, $date, $account, $note, $actor_id) {
            $p = Payroll::lockForUpdate()->find($p->id);
            if ($p->isPaid()) {
                throw new PayrollException(__('payroll.err_already_paid'));
            }
            if (! empty(PayrollRules::settings($p->business_id)['require_approval']) && ! $p->isApproved()) {
                throw new PayrollException(__('payroll.err_not_approved'));
            }
            $name = self::name($p);
            $label = __('payroll.movement_note', ['name' => $name, 'month' => self::monthLabel($p->salary_month)]);
            $at = AccountTransaction::createAccountTransaction(['account_id' => $account->id, 'type' => 'debit', 'sub_type' => 'payroll',
                'amount' => $p->net_salary, 'reff_no' => 'PAY-'.$p->id, 'operation_date' => $date->copy()->setTimeFrom(now())->toDateTimeString(),
                'created_by' => $actor_id, 'note' => mb_substr($label.($note ? ' · '.$note : ''), 0, 250)]);
            $at->reff_no = 'PAY-'.$p->id;   // createAccountTransaction does not take it
            $at->save();
            $p->fill(['status' => 1, 'pay_date' => $date->toDateString(), 'payment_account_id' => $account->id, 'payment_method' => $account->name,
                'note' => $note ? mb_substr($note, 0, 1000) : null, 'account_transaction_id' => $at->id, 'updated_by' => $actor_id])->save();

            if (Ledger::enabledFor($p->business_id) && Ledger::isSetUp($p->business_id)) {
                $entry = Ledger::forBusiness($p->business_id, fn () => LedgerPoster::post([
                    'entry_date' => $date->toDateString(), 'journal_type' => 'payroll',
                    'description' => __('payroll.je_description', ['name' => $name, 'month' => self::monthLabel($p->salary_month)]),
                    'reference_type' => 'payroll', 'reference_id' => $p->id, 'reference_number' => 'PAY-'.$p->id, 'is_system_generated' => true,
                ], self::lines($p, $account), $actor_id));
                $p->journal_entry_id = $entry->id;
                $p->save();
            }

            return $p->fresh();
        });
    }

    /** The lines of the salary entry (see the class note). */
    public static function lines(Payroll $p, $account)
    {
        $name = self::name($p);
        $acc = fn ($code) => ChartOfAccount::where('account_code', $code)->where('is_active', 1)->value('id');
        $salary = $acc('661') ?: $acc('66');
        $charges = $acc('664') ?: $salary;
        $cash = $account->chart_of_account_id ? (int) $account->chart_of_account_id : null;
        if (! $salary) {
            throw new PayrollException(__('payroll.err_no_account', ['code' => '661']));
        }
        if (! $cash) {
            throw new PayrollException(__('payroll.err_account_not_linked', ['name' => $account->name]));
        }
        // loan and advance repayments came off the gross: the salary cost is the gross before them,
        // and what was withheld settles what the staff member owes on staff advances
        $repaid = round((float) $p->details()->where('status', 0)->whereNotNull('staff_loan_id')->sum('amount'), 2);
        $lines = [['account_id' => $salary, 'debit' => round((float) $p->gross_salary + $repaid, 2), 'credit' => 0, 'description' => __('payroll.l_gross', ['name' => $name])]];
        if ($repaid > 0) {
            $code = PayrollRules::settings($p->business_id)['advance_account_code'] ?: '421';
            $adv = $acc($code) ?: $acc(substr($code, 0, 3));
            if (! $adv) {
                throw new PayrollException(__('payroll.err_no_account', ['code' => $code]));
            }
            $lines[] = ['account_id' => $adv, 'debit' => 0, 'credit' => $repaid, 'description' => __('payroll.l_repaid', ['name' => $name])];
        }
        if ((float) $p->employer_tax > 0) {
            $lines[] = ['account_id' => $charges, 'debit' => round((float) $p->employer_tax, 2), 'credit' => 0, 'description' => __('payroll.l_employer', ['name' => $name])];
        }
        $byCode = [];
        foreach ($p->taxLines()->get() as $t) {
            $code = $t->liability_code ?: '447';
            $byCode[$code] = round(($byCode[$code] ?? 0) + (float) $t->employee_amount + (float) $t->employer_amount, 2);
        }
        // what the lines say must be what the payroll says (rounding goes to the largest account)
        $want = round((float) $p->tax + (float) $p->employer_tax, 2);
        if ($byCode && abs(array_sum($byCode) - $want) > 0.004) {
            $big = array_search(max($byCode), $byCode);
            $byCode[$big] = round($byCode[$big] + $want - array_sum($byCode), 2);
        } elseif (! $byCode && $want > 0) {
            $byCode['447'] = $want;
        }
        foreach ($byCode as $code => $amount) {
            if ($amount <= 0) {
                continue;
            }
            $id = $acc($code) ?: $acc(substr($code, 0, 2));
            if (! $id) {
                throw new PayrollException(__('payroll.err_no_account', ['code' => $code]));
            }
            $lines[] = ['account_id' => $id, 'debit' => 0, 'credit' => $amount,
                'description' => __('payroll.l_withheld', ['account' => (string) ChartOfAccount::where('id', $id)->value('account_name'), 'name' => $name])];
        }
        $lines[] = ['account_id' => $cash, 'debit' => 0, 'credit' => round((float) $p->net_salary, 2), 'description' => __('payroll.l_net', ['name' => $name])];

        return $lines;
    }

    /** Undo a payment: the entry is reversed (today) and the money comes back to the account. */
    public static function unpay(Payroll $p, $actor_id)
    {
        return DB::transaction(function () use ($p, $actor_id) {
            $p = Payroll::lockForUpdate()->find($p->id);
            if (! $p->isPaid()) {
                throw new PayrollException(__('payroll.err_already_unpaid'));
            }
            if ($p->journal_entry_id) {
                $entry = Ledger::forBusiness($p->business_id, fn () => JournalEntry::find($p->journal_entry_id));
                if ($entry && $entry->is_posted && ! $entry->is_reversed) {
                    Ledger::forBusiness($p->business_id, fn () => LedgerPoster::reverse($entry, 'payroll_reversal', $p->id, $actor_id, __('payroll.why_unpaid')));
                }
            }
            if ($p->account_transaction_id && ($at = AccountTransaction::find($p->account_transaction_id))) {
                $at->delete();
            }
            $p->fill(['status' => 0, 'pay_date' => null, 'payment_account_id' => null, 'payment_method' => null,
                'journal_entry_id' => null, 'account_transaction_id' => null, 'updated_by' => $actor_id])->save();

            return $p;
        });
    }

    public static function name(Payroll $p)
    {
        $u = $p->user;

        return trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->username ?? '#'.$p->user_id);
    }

    public static function monthLabel($month)
    {
        return Carbon::parse($month)->locale(app()->getLocale())->isoFormat('MMMM YYYY');
    }
}
