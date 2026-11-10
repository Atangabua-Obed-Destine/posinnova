<?php

namespace App\Utils;

use App\CashRegister;
use App\CashRegisterTransaction;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: the cashier's view of a patient's account, and posting
 * payments taken outside the POS sale screen to the cashier's open cash
 * register so the drawer total at close matches the cash in hand.
 *
 * The POS only writes register rows for payments made while ringing up a
 * sale; a payment added later against a credit invoice never reached the
 * register. Clinic screens ask for it explicitly, so the pharmacy's other
 * payment screens keep their current behaviour.
 */
class ClinicCashier
{
    public static function openRegister($user_id)
    {
        return CashRegister::where('user_id', $user_id)->where('status', 'open')->first();
    }

    /**
     * Adds a received payment to the user's open register. Returns false when
     * no register is open (nothing is written).
     */
    public static function postToRegister($user_id, $transaction_id, $method, $amount)
    {
        $register = self::openRegister($user_id);
        if (! $register || (float) $amount == 0.0) {
            return false;
        }

        CashRegisterTransaction::create([
            'cash_register_id' => $register->id,
            'amount' => $amount,
            'pay_method' => $method,
            'type' => 'credit',
            'transaction_type' => 'sell',
            'transaction_id' => $transaction_id,
        ]);

        return true;
    }

    /**
     * The patient's finished invoices that are not fully paid, oldest first,
     * with paid, due and a short description of what was charged.
     */
    public static function dueInvoices($business_id, $contact_id)
    {
        return self::invoiceQuery($business_id, $contact_id)
            ->where('t.payment_status', '!=', 'paid')
            ->orderBy('t.transaction_date')
            ->orderBy('t.id')
            ->get()
            ->map(function ($row) {
                $row->due = max(0, round((float) $row->final_total - (float) $row->paid, 4));

                return $row;
            })
            ->filter(fn ($row) => $row->due > 0)
            ->values();
    }

    public static function recentInvoices($business_id, $contact_id, $limit = 15)
    {
        return self::invoiceQuery($business_id, $contact_id)
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('t.id')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $row->due = max(0, round((float) $row->final_total - (float) $row->paid, 4));

                return $row;
            });
    }

    private static function invoiceQuery($business_id, $contact_id)
    {
        return DB::table('transactions as t')
            ->where('t.business_id', $business_id)
            ->where('t.contact_id', $contact_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->select(
                't.id', 't.invoice_no', 't.transaction_date', 't.final_total', 't.payment_status', 't.consultation_id', 't.visit_id',
                DB::raw('(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp WHERE tp.transaction_id = t.id) as paid'),
                DB::raw('(SELECT vc.description FROM visit_charges vc WHERE vc.reference_id = t.id ORDER BY vc.id LIMIT 1) as charge_description'),
                DB::raw("(SELECT GROUP_CONCAT(p.name ORDER BY tsl.id SEPARATOR ', ') FROM transaction_sell_lines tsl JOIN products p ON p.id = tsl.product_id WHERE tsl.transaction_id = t.id AND tsl.parent_sell_line_id IS NULL) as items")
            );
    }

    /**
     * What a row is for, in a few words.
     */
    public static function describe($row)
    {
        if (! empty($row->charge_description)) {
            return $row->charge_description;
        }
        $items = (string) $row->items;
        $prefix = ! empty($row->consultation_id) ? __('clinic.acc_pharmacy').': ' : '';

        return $prefix.(mb_strlen($items) > 90 ? mb_substr($items, 0, 87).'...' : $items);
    }

    /**
     * Splits an amount across invoices oldest first.
     *
     * @return array [transaction_id => amount]
     */
    public static function allocateOldestFirst($invoices, $amount)
    {
        $out = [];
        foreach ($invoices as $inv) {
            if ($amount <= 0) {
                break;
            }
            $take = min($amount, $inv->due);
            if ($take > 0) {
                $out[$inv->id] = round($take, 4);
                $amount = round($amount - $take, 4);
            }
        }

        return $out;
    }
}
