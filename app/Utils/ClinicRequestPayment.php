<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;

/**
 * PosInnova: is the fee of a lab or imaging request paid? One answer for the request page, the
 * result/report entry, the lists and the server-side check before results are saved.
 *
 * States (only the patient's own part counts; an insurer's part is settled through its claims):
 *   paid        the patient's invoice is fully paid
 *   partial     part of it is paid
 *   due         nothing paid yet
 *   none        nothing for the patient to pay (free test, or the insurer covers all of it)
 *   not_billed  no invoice found for it (older requests, or billing was not possible)
 * Only "partial" and "due" ask for a decision before results are entered.
 */
class ClinicRequestPayment
{
    const NEEDS_DECISION = ['partial', 'due'];

    /**
     * The payment state of one request (LabRequest or ImagingRequest).
     *
     * @return array state, total, paid, due, invoice_no, transaction_id, contact_id
     */
    public static function of($req)
    {
        $out = ['state' => 'not_billed', 'total' => 0.0, 'paid' => 0.0, 'due' => 0.0, 'invoice_no' => null,
            'transaction_id' => $req->transaction_id ?? null, 'contact_id' => $req->contact_id];

        if (! empty($req->transaction_id)) {
            $t = DB::table('transactions')->where('id', $req->transaction_id)->where('business_id', $req->business_id)
                ->select('id', 'invoice_no', 'final_total')->first();
            if ($t) {
                $paid = (float) DB::table('transaction_payments')->where('transaction_id', $t->id)
                    ->sum(DB::raw('IF(is_return = 1, -1 * amount, amount)'));

                return array_merge($out, self::state((float) $t->final_total, $paid), ['invoice_no' => $t->invoice_no]);
            }

            return $out;
        }

        // nothing to pay (a free test), or no invoice found
        if (self::requestFee($req) <= 0) {
            $out['state'] = 'none';
        }

        return $out;
    }

    /**
     * The state from an invoice total and what was paid on it.
     */
    public static function state($total, $paid)
    {
        $due = round($total - $paid, 4);
        if ($total <= 0.004) {
            $state = 'none';
        } elseif ($due <= 0.004) {
            $state = 'paid';
        } elseif ($paid > 0.004) {
            $state = 'partial';
        } else {
            $state = 'due';
        }

        return ['state' => $state, 'total' => $total, 'paid' => $paid, 'due' => max(0, $due)];
    }

    /**
     * May the patient see this request's result on the portal? Not while their part is unpaid
     * (paid, nothing to pay, or no invoice found: yes).
     */
    public static function releasedToPatient(array $p)
    {
        return ! self::needsDecision($p);
    }

    /** True when results should not go in without the user choosing to go on. */
    public static function needsDecision(array $p)
    {
        return in_array($p['state'], self::NEEDS_DECISION, true);
    }

    /**
     * SQL to add to a list query (requests aliased as $alias): the invoice total and what is paid on it.
     */
    public static function listSelects($alias, $fee_sql)
    {
        return [
            DB::raw("$fee_sql as pay_fee"),
            DB::raw("(SELECT t.final_total FROM transactions t WHERE t.id = $alias.transaction_id) as pay_total"),
            DB::raw("(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp WHERE tp.transaction_id = $alias.transaction_id) as pay_paid"),
        ];
    }

    /**
     * The state of a list row that has pay_total / pay_paid (see listSelects).
     */
    public static function ofRow($row)
    {
        if (empty($row->transaction_id) || $row->pay_total === null) {
            // the list gives the request's fee as pay_fee: a free request has nothing to pay
            $free = isset($row->pay_fee) && (float) $row->pay_fee <= 0;

            return ['state' => $free ? 'none' : 'not_billed', 'due' => 0.0];
        }

        return self::state((float) $row->pay_total, (float) $row->pay_paid);
    }

    /**
     * A small coloured label for the state (lists and headers).
     */
    public static function badge(array $p, $util = null)
    {
        $util = $util ?: new Util();
        switch ($p['state']) {
            case 'paid':
                return '<span class="label label-success"><i class="fas fa-check"></i> '.e(__('clinic.pay_state_paid')).'</span>';
            case 'partial':
                return '<span class="label label-warning"><i class="fas fa-exclamation-triangle"></i> '.e(__('clinic.pay_state_partial', ['due' => $util->num_f($p['due'], true)])).'</span>';
            case 'due':
                return '<span class="label label-danger"><i class="fas fa-exclamation-circle"></i> '.e(__('clinic.pay_state_due', ['due' => $util->num_f($p['due'], true)])).'</span>';
            case 'none':
                return '<span class="label label-default">'.e(__('clinic.pay_state_none')).'</span>';
            default:
                return '<span class="label label-default" title="'.e(__('clinic.pay_state_not_billed_help')).'">'.e(__('clinic.pay_state_not_billed')).'</span>';
        }
    }

    private static function requestFee($req)
    {
        if (isset($req->fee) && $req->fee !== null) {
            return (float) $req->fee;
        }
        if (method_exists($req, 'lines')) {
            return (float) $req->lines()->sum('fee');
        }

        return 0.0;
    }
}
