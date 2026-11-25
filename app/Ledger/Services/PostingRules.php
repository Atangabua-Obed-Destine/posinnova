<?php

namespace App\Ledger\Services;

use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use Illuminate\Support\Facades\DB;

/**
 * What a POS document puts in the ledger (SYSCOHADA, inventory counted at
 * year end: purchases go to 601, sales to 701/706, the stock change is a
 * year-end entry). Each rule returns the entry a document should have right
 * now, or null when it has none (a draft, an order not received, zero).
 * LedgerSync compares that with what is posted and corrects the difference.
 *
 *   sale             Dr 411 customer           Cr 701 goods / 706 services / 707 delivery, Cr 4431 VAT
 *   sale return      Dr 701/706/707, Dr 4431   Cr 411 customer
 *   purchase         Dr 6011 goods, 6015 costs, 4452 VAT        Cr 401 supplier
 *   purchase return  Dr 401 supplier           Cr 6011, 4452
 *   expense          Dr 6xx (by category), 4454 VAT             Cr 401 (supplier) / 4718
 *   opening balance  customer: Dr 411 Cr 4711; supplier: Dr 4711 Cr 401
 *   ledger discount  customer: Dr 673 Cr 411; supplier: Dr 401 Cr 773
 *   payment on a sale / opening balance     Dr cash   Cr 411    (change given back: the opposite)
 *   payment on a sale return                Dr 411    Cr cash
 *   payment on a purchase / expense         Dr 401    Cr cash
 *   payment on a purchase return            Dr cash   Cr 401
 *   advance received (no invoice)           Dr cash   Cr 4191   (supplier: Dr 409 Cr cash)
 *   advance used (method "advance", or the  Dr 4191   Cr 411    (supplier: Dr 401 Cr 409)
 *     share of an advance given to an invoice)
 *   advance given back to the customer      Dr 4191   Cr cash
 */
class PostingRules
{
    /** Document types that post. */
    const TYPES = ['sell', 'sell_return', 'purchase', 'purchase_return', 'expense', 'opening_balance', 'ledger_discount'];

    private $acc = [];   // mapping cache: type|key => account id

    private $codes = []; // code => id

    public function __construct()
    {
        $this->codes = ChartOfAccount::pluck('id', 'account_code')->all();
    }

    // ---------------------------------------------------------------- documents

    /** @return array|null ['date', 'description', 'reference', 'journal_type', 'lines' => [...]] */
    public function forTransaction(object $t): ?array
    {
        switch ($t->type) {
            case 'sell': return $t->status === 'final' ? $this->sale($t, false) : null;
            case 'sell_return': return $this->sale($t, true);
            case 'purchase': return $t->status === 'received' ? $this->purchase($t, false) : null;
            case 'purchase_return': return $this->purchase($t, true);
            case 'expense': return $this->expense($t);
            case 'opening_balance': return $this->openingBalance($t);
            case 'ledger_discount': return $this->ledgerDiscount($t);
        }

        return null;
    }

    private function sale(object $t, bool $return): ?array
    {
        $total = round((float) $t->final_total, 2);
        if ($total <= 0) {
            return null;
        }
        $lines = DB::table('transaction_sell_lines as l')
            ->leftJoin('products as p', 'p.id', '=', 'l.product_id')
            ->where('l.transaction_id', $return && $t->return_parent_id ? $t->return_parent_id : $t->id)
            ->whereNull('l.parent_sell_line_id')
            ->get(['l.quantity', 'l.quantity_returned', 'l.unit_price_inc_tax', 'l.item_tax', 'p.enable_stock', 'p.type as ptype']);

        $goods = 0.0;
        $services = 0.0;
        $lineVat = 0.0;
        foreach ($lines as $l) {
            // a return made from a sale counts the sale's returned quantities; a return on its own has its own lines
            $q = ($return && $t->return_parent_id) ? (float) $l->quantity_returned : (float) $l->quantity;
            $net = ((float) $l->unit_price_inc_tax - (float) $l->item_tax) * $q;
            $lineVat += (float) $l->item_tax * $q;
            if ((int) $l->enable_stock === 1) {
                $goods += $net;
            } else {
                $services += $net;
            }
        }
        // VAT = the lines' VAT plus the order tax. Delivery and the other charges added to
        // the bill go to 707; packing and rounding stay in the sales figure.
        $vat = round($lineVat + (float) $t->tax_amount, 2);
        $other = $return ? 0 : round((float) $t->shipping_charges + (float) $t->additional_expense_value_1
            + (float) $t->additional_expense_value_2 + (float) $t->additional_expense_value_3
            + (float) $t->additional_expense_value_4, 2);
        $sales = round($total - $vat - $other, 2);
        if ($sales < 0) {
            $other = max(0, round($other + $sales, 2));
            $sales = 0;
        }
        // goods / services split of the sales figure (after discounts), by the lines' weight
        $base = $goods + $services;
        $sGoods = $base > 0 ? round($sales * $goods / $base, 2) : $sales;
        $sServices = round($sales - $sGoods, 2);

        $customer = $this->contactAccount($t->contact_id, 'customers');
        $out = [];
        $out[] = $this->line($customer, $return ? 0 : $total, $return ? $total : 0, $this->label($t), $t->contact_id);
        $out[] = $this->line($this->map('sale_goods'), $return ? $sGoods : 0, $return ? 0 : $sGoods, __('ledger.l_sales_goods'));
        $out[] = $this->line($this->map('sale_services'), $return ? $sServices : 0, $return ? 0 : $sServices, __('ledger.l_sales_services'));
        $out[] = $this->line($this->map('sale_other'), $return ? $other : 0, $return ? 0 : $other, __('ledger.l_sales_other'));
        $out[] = $this->line($this->map('vat_collected'), $return ? $vat : 0, $return ? 0 : $vat, __('ledger.l_vat_collected'));

        return $this->entry($t, $return ? 'sales_return' : 'sales', $out);
    }

    private function purchase(object $t, bool $return): ?array
    {
        $total = round((float) $t->final_total, 2);
        if ($total <= 0) {
            return null;
        }
        $lines = DB::table('purchase_lines')->where('transaction_id', $return && $t->return_parent_id ? $t->return_parent_id : $t->id)
            ->get(['quantity', 'quantity_returned', 'item_tax']);
        $lineVat = 0.0;
        foreach ($lines as $l) {
            $lineVat += (float) $l->item_tax * (($return && $t->return_parent_id) ? (float) $l->quantity_returned : (float) $l->quantity);
        }
        $vat = round($lineVat + ($return ? 0 : (float) $t->tax_amount), 2);
        $costs = $return ? 0 : round((float) $t->shipping_charges + (float) $t->additional_expense_value_1 + (float) $t->additional_expense_value_2
            + (float) $t->additional_expense_value_3 + (float) $t->additional_expense_value_4, 2);
        $goods = round($total - $vat - $costs, 2);
        if ($goods < 0) {
            $costs = max(0, round($costs + $goods, 2));
            $goods = 0;
        }
        $supplier = $this->contactAccount($t->contact_id, 'suppliers');
        $out = [];
        $out[] = $this->line($this->map('purchases'), $return ? 0 : $goods, $return ? $goods : 0, __('ledger.l_purchases'));
        $out[] = $this->line($this->map('purchase_costs'), $costs, 0, __('ledger.l_purchase_costs'));
        $out[] = $this->line($this->map('vat_deductible'), $return ? 0 : $vat, $return ? $vat : 0, __('ledger.l_vat_deductible'));
        $out[] = $this->line($supplier, $return ? $total : 0, $return ? 0 : $total, $this->label($t), $t->contact_id);

        return $this->entry($t, $return ? 'purchase_return' : 'purchases', $out);
    }

    private function expense(object $t): ?array
    {
        $total = round((float) $t->final_total, 2);
        if ($total <= 0) {
            return null;
        }
        $refund = ! empty($t->is_refund ?? null);
        $vat = round((float) $t->tax_amount, 2);
        $net = round($total - $vat, 2);
        $cat = (string) ($t->expense_category_id ?? '');
        $expenseAcc = $this->map('expense_category', $cat);
        $payable = $t->contact_id ? $this->contactAccount($t->contact_id, 'suppliers') : $this->map('expense_payable');
        $out = [];
        $out[] = $this->line($expenseAcc, $refund ? 0 : $net, $refund ? $net : 0, $this->label($t));
        $out[] = $this->line($this->map('vat_deductible'), $refund ? 0 : $vat, $refund ? $vat : 0, __('ledger.l_vat_deductible'));
        $out[] = $this->line($payable, $refund ? $total : 0, $refund ? 0 : $total, $this->label($t), $t->contact_id);

        return $this->entry($t, 'expenses', $out);
    }

    private function openingBalance(object $t): ?array
    {
        $total = round((float) $t->final_total, 2);
        if ($total == 0 || ! $t->contact_id) {
            return null;
        }
        $type = DB::table('contacts')->where('id', $t->contact_id)->value('type');
        $offset = $this->map('opening_balance');
        if ($type === 'supplier') {
            $out = [$this->line($offset, $total, 0, __('ledger.l_opening_balance')),
                $this->line($this->map('suppliers'), 0, $total, $this->label($t), $t->contact_id)];
        } else {
            $out = [$this->line($this->map('customers'), $total, 0, $this->label($t), $t->contact_id),
                $this->line($offset, 0, $total, __('ledger.l_opening_balance'))];
        }

        return $this->entry($t, 'opening', $out);
    }

    private function ledgerDiscount(object $t): ?array
    {
        $total = round((float) $t->final_total, 2);
        if ($total <= 0 || ! $t->contact_id) {
            return null;
        }
        $type = DB::table('contacts')->where('id', $t->contact_id)->value('type');
        if ($type === 'supplier' || ($t->sub_type ?? '') === 'purchase_discount') {
            $out = [$this->line($this->map('suppliers'), $total, 0, $this->label($t), $t->contact_id),
                $this->line($this->map('purchase_discount'), 0, $total, __('ledger.l_discount_received'))];
        } else {
            $out = [$this->line($this->map('sale_discount'), $total, 0, __('ledger.l_discount_allowed')),
                $this->line($this->map('customers'), 0, $total, $this->label($t), $t->contact_id)];
        }

        return $this->entry($t, 'general', $out);
    }

    // ---------------------------------------------------------------- payments

    /**
     * @param  object  $p  transaction_payments row
     * @param  object|null  $t  its transaction (null for an advance)
     */
    public function forPayment(object $p, ?object $t): ?array
    {
        $amount = round((float) $p->amount, 2);
        if ($amount <= 0) {
            return null;
        }
        $contactId = $p->payment_for ?: ($t->contact_id ?? null);
        $isSupplier = $t ? in_array($t->type, ['purchase', 'purchase_return', 'expense'])
            : (DB::table('contacts')->where('id', $contactId)->value('type') === 'supplier');

        // money that moves inside the books (an advance used), not cash
        $fromAdvance = $p->method === 'advance' || ! empty($p->parent_id);

        if (! $t) {
            // no invoice: an advance received, or an advance given back
            if (! $contactId) {
                return null;
            }
            $cash = $this->cashAccount($p);
            if ($isSupplier) {
                $adv = $this->map('supplier_advances');
                $out = $p->payment_type === 'credit' && ! $p->is_advance
                    ? [$this->line($cash, $amount, 0, $this->payLabel($p)), $this->line($adv, 0, $amount, __('ledger.l_supplier_advance'), $contactId)]
                    : [$this->line($adv, $amount, 0, __('ledger.l_supplier_advance'), $contactId), $this->line($cash, 0, $amount, $this->payLabel($p))];
            } else {
                $adv = $this->map('customer_advances');
                $out = $p->payment_type === 'debit' && ! $p->is_advance
                    ? [$this->line($adv, $amount, 0, __('ledger.l_customer_advance'), $contactId), $this->line($cash, 0, $amount, $this->payLabel($p))]
                    : [$this->line($cash, $amount, 0, $this->payLabel($p)), $this->line($adv, 0, $amount, __('ledger.l_customer_advance'), $contactId)];
            }

            return $this->paymentEntry($p, $out);
        }

        if (! in_array($t->type, ['sell', 'sell_return', 'purchase', 'purchase_return', 'expense', 'opening_balance'])) {
            return null;
        }

        // A payment on a sale that is not final (a draft, a quotation) or a purchase not received
        // yet: the invoice is not on 411 / 401, so the money is an advance until it is.
        $invoiceOn = ! (($t->type === 'sell' && $t->status !== 'final') || ($t->type === 'purchase' && $t->status !== 'received'));
        if (! $invoiceOn) {
            if ($fromAdvance) {
                return null; // an advance put on a draft stays an advance
            }
            $cash = $this->cashAccount($p);
            $adv = $isSupplier ? $this->map('supplier_advances') : $this->map('customer_advances');
            $in = ! $isSupplier;
            if (! empty($p->is_return)) {
                $in = ! $in;
            }
            $out = $in
                ? [$this->line($cash, $amount, 0, $this->payLabel($p)), $this->line($adv, 0, $amount, $isSupplier ? __('ledger.l_supplier_advance') : __('ledger.l_customer_advance'), $t->contact_id)]
                : [$this->line($adv, $amount, 0, $isSupplier ? __('ledger.l_supplier_advance') : __('ledger.l_customer_advance'), $t->contact_id), $this->line($cash, 0, $amount, $this->payLabel($p))];

            return $this->paymentEntry($p, $out);
        }

        // the counterpart: customer / supplier account of the document
        if ($t->type === 'expense') {
            $party = $t->contact_id ? $this->map('suppliers') : $this->map('expense_payable');
        } elseif ($t->type === 'opening_balance') {
            $party = DB::table('contacts')->where('id', $t->contact_id)->value('type') === 'supplier' ? $this->map('suppliers') : $this->map('customers');
            $isSupplier = $party === $this->map('suppliers');
        } else {
            $party = $isSupplier ? $this->map('suppliers') : $this->map('customers');
        }
        $partyContact = $t->contact_id;

        if ($fromAdvance) {
            $adv = $isSupplier ? $this->map('supplier_advances') : $this->map('customer_advances');
            $side = $isSupplier ? [$party, $adv] : [$adv, $party]; // [debit, credit]
            $out = [$this->line($side[0], $amount, 0, $this->payLabel($p), $partyContact),
                $this->line($side[1], 0, $amount, $this->payLabel($p), $partyContact)];

            return $this->paymentEntry($p, $out);
        }

        $cash = $this->cashAccount($p);
        // money in: sale / opening balance of a customer / purchase return (supplier refunds);
        // change given back on a sale (is_return) is money out.
        $moneyIn = in_array($t->type, ['sell', 'purchase_return']) || ($t->type === 'opening_balance' && ! $isSupplier);
        if (! empty($p->is_return)) {
            $moneyIn = ! $moneyIn;
        }
        $out = $moneyIn
            ? [$this->line($cash, $amount, 0, $this->payLabel($p)), $this->line($party, 0, $amount, $this->payLabel($p), $partyContact)]
            : [$this->line($party, $amount, 0, $this->payLabel($p), $partyContact), $this->line($cash, 0, $amount, $this->payLabel($p))];

        return $this->paymentEntry($p, $out);
    }

    // ---------------------------------------------------------------- payment accounts

    /**
     * A movement on a Payment Account (account_transactions) that is not the cash side of a
     * payment (payments post themselves): a fund transfer, a deposit, an account's opening balance.
     * UltimatePOS writes a transfer as a pair: a debit row on the account the money leaves and a
     * credit row on the account it reaches, pointing at each other (transfer_transaction_id).
     * The credit row posts the pair: Dr the receiving account's ledger account, Cr the sending
     * one's. With no sending account (a deposit from outside, an opening balance) the other side
     * is the opening-balance suspense account (4711), for the accountant to reclassify.
     * A debit row posts only when it has no partner (money taken out to nowhere): Dr 4711, Cr the account.
     */
    public function forAccountTransaction(object $a): ?array
    {
        if (! empty($a->deleted_at) || ! empty($a->transaction_id) || ! empty($a->transaction_payment_id)) {
            return null;
        }
        // money paid out by the payroll (a salary, a staff loan, tax paid over): the entry is the payroll's own
        if (in_array($a->sub_type ?? null, ['payroll', 'staff_loan', 'tax_remittance'], true)) {
            return null;
        }
        $amount = round((float) $a->amount, 2);
        if ($amount <= 0) {
            return null;
        }
        $partner = $a->transfer_transaction_id
            ? DB::table('account_transactions')->where('id', $a->transfer_transaction_id)->whereNull('deleted_at')->first()
            : null;
        if ($a->type === 'debit') {
            // a fund transfer's debit row is pointed at by its credit row (not the other way round)
            $partner = $partner ?: DB::table('account_transactions')->where('transfer_transaction_id', $a->id)
                ->where('type', 'credit')->whereNull('deleted_at')->first();
            if ($partner) {
                return null; // the credit row of the pair posts it
            }
        }
        $here = $this->paymentAccountLedger((int) $a->account_id);
        $there = $partner ? $this->paymentAccountLedger((int) $partner->account_id) : $this->map('opening_balance');
        if ($here === $there) {
            return null;
        }
        $label = trim(__('ledger.doc_account_'.($a->sub_type ?: 'deposit')).' '.($a->reff_no ?: '#'.$a->id));
        $lines = $a->type === 'credit'
            ? [$this->line($here, $amount, 0, $label), $this->line($there, 0, $amount, $label)]
            : [$this->line($there, $amount, 0, $label), $this->line($here, 0, $amount, $label)];

        return [
            'date' => substr((string) $a->operation_date, 0, 10),
            'journal_type' => 'cash',
            'description' => $label.($a->note ? ' · '.mb_substr($a->note, 0, 120) : ''),
            'reference' => $a->reff_no,
            'lines' => $lines,
        ];
    }

    /** The ledger account a Payment Account is linked to (Cash and bank in the ledger). */
    private function paymentAccountLedger(int $accountId): int
    {
        $row = DB::table('accounts')->where('id', $accountId)->first(['name', 'chart_of_account_id']);
        if (! $row || ! $row->chart_of_account_id) {
            throw new \RuntimeException(__('ledger.err_account_not_linked', ['name' => $row->name ?? ('#'.$accountId)]));
        }

        return (int) $row->chart_of_account_id;
    }

    // ---------------------------------------------------------------- helpers

    /** The cash side of a payment: its payment account's ledger account, else its method's. */
    public function cashAccount(object $p): int
    {
        if (! empty($p->account_id)) {
            $coa = DB::table('accounts')->where('id', $p->account_id)->value('chart_of_account_id');
            if ($coa) {
                return (int) $coa;
            }
        }
        $id = $this->map('payment_method', (string) ($p->method ?: 'cash'));

        return $id;
    }

    private function contactAccount($contactId, string $default): int
    {
        return $this->map($default);
    }

    private function map(string $type, string $key = ''): int
    {
        $k = $type.'|'.$key;
        if (! array_key_exists($k, $this->acc)) {
            $this->acc[$k] = DefaultAccountMapping::accountFor($type, $key);
        }
        if (! $this->acc[$k]) {
            throw new \RuntimeException(__('ledger.err_no_mapping', ['type' => __('ledger.map_'.$type), 'key' => $key]));
        }

        return $this->acc[$k];
    }

    private function code(string $code): ?int
    {
        return $this->codes[$code] ?? null;
    }

    private function line($account, $debit, $credit, $desc = null, $contactId = null): array
    {
        return ['account_id' => $account, 'debit' => round((float) $debit, 2), 'credit' => round((float) $credit, 2),
            'description' => $desc, 'contact_id' => $contactId];
    }

    private function label(object $t): string
    {
        $no = $t->invoice_no ?: $t->ref_no;
        $name = $t->contact_id ? DB::table('contacts')->where('id', $t->contact_id)->value(DB::raw("COALESCE(NULLIF(supplier_business_name,''), name)")) : null;

        return trim(__('ledger.doc_'.$t->type).' '.$no.($name ? ' · '.$name : ''));
    }

    private function payLabel(object $p): string
    {
        return trim(__('ledger.doc_payment').' '.($p->payment_ref_no ?: '#'.$p->id));
    }

    private function entry(object $t, string $journal, array $lines): ?array
    {
        $lines = array_values(array_filter($lines, fn ($l) => $l['debit'] > 0 || $l['credit'] > 0));
        if (count($lines) < 2) {
            return null;
        }

        return [
            'date' => substr((string) $t->transaction_date, 0, 10),
            'journal_type' => $journal,
            'description' => $this->label($t),
            'reference' => $t->invoice_no ?: $t->ref_no,
            'lines' => $lines,
        ];
    }

    private function paymentEntry(object $p, array $lines): ?array
    {
        return [
            'date' => substr((string) $p->paid_on, 0, 10),
            'journal_type' => 'cash',
            'description' => $this->payLabel($p),
            'reference' => $p->payment_ref_no,
            'lines' => $lines,
        ];
    }
}
