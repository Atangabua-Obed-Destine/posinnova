<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sales saved by the old payment guard: it capped the cash tendered at the invoice
 * total but kept the "change return" line, so the change was subtracted twice and a
 * fully paid sale showed as "partial" (invoice 2750: total 27,500, payment 27,500,
 * change 4,800 -> only 22,700 "paid").
 *
 * Detection is exact: a change line only exists when more than the total was handed
 * over, so "payments equal the total AND there is change" is always this bug. The
 * repair puts the tendered amount back (payment = total + change), which is what the
 * cash register already recorded, and recomputes the payment status.
 *
 * Used by `php artisan pos:repair-change-payments` and Settings > Repair payments.
 */
class ChangePaymentRepair
{
    /** Affected final sales, oldest first; all businesses when $business_id is null. */
    public static function find($business_id = null)
    {
        $paid = DB::table('transaction_payments as tp')
            ->join('transactions as tx', 'tx.id', '=', 'tp.transaction_id')
            ->where('tx.type', 'sell')
            ->when($business_id, fn ($q) => $q->where('tx.business_id', $business_id))
            ->select('tp.transaction_id',
                DB::raw('SUM(IF(tp.is_return = 0, tp.amount, 0)) AS paid'),
                DB::raw('SUM(IF(tp.is_return = 1, tp.amount, 0)) AS change_given'))
            ->groupBy('tp.transaction_id');

        return DB::table('transactions as t')
            ->joinSub($paid, 'p', 'p.transaction_id', '=', 't.id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->when($business_id, fn ($q) => $q->where('t.business_id', $business_id))
            ->where('p.change_given', '>', 0)
            ->whereRaw('ABS(p.paid - t.final_total) < 0.01')
            ->select('t.id', 't.business_id', 't.invoice_no', 't.transaction_date', 't.final_total', 't.payment_status',
                'p.paid', 'p.change_given', 'c.name as customer')
            ->orderBy('t.transaction_date')
            ->get();
    }

    /**
     * Repairs the given rows (from find()). Returns the number repaired.
     */
    public static function repair($rows, TransactionUtil $transactionUtil, $by = 'unknown')
    {
        $fixed = 0;
        foreach ($rows as $r) {
            DB::transaction(function () use ($r, $transactionUtil, $by, &$fixed) {
                // re-check under lock: the row may have been repaired meanwhile
                $lines = DB::table('transaction_payments')->where('transaction_id', $r->id)->lockForUpdate()->get();
                $paid = (float) $lines->where('is_return', 0)->sum('amount');
                $change = (float) $lines->where('is_return', 1)->sum('amount');
                if ($change <= 0 || abs($paid - (float) $r->final_total) >= 0.01) {
                    return;
                }
                // the tendered line: the largest cash payment (else the largest payment)
                $line = $lines->where('is_return', 0)->sortByDesc(fn ($l) => ($l->method == 'cash' ? 1e12 : 0) + (float) $l->amount)->first();
                if (! $line) {
                    return;
                }
                $new_amount = (float) $line->amount + $change;
                DB::table('transaction_payments')->where('id', $line->id)->update(['amount' => $new_amount, 'updated_at' => now()]);
                DB::table('account_transactions')->where('transaction_payment_id', $line->id)->update(['amount' => $new_amount]);
                $status = $transactionUtil->updatePaymentStatus($r->id, $r->final_total);
                Log::info("change-payment repair by {$by}: sale {$r->id} (invoice {$r->invoice_no}) payment {$line->id} {$line->amount} -> {$new_amount}, status {$r->payment_status} -> {$status}");
                $fixed++;
            });
        }

        return $fixed;
    }
}
