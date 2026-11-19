<?php

namespace App\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova: the figures of the periodic business review (Reports > Financial summary) delivered
 * to I-NNOVA's clients: one place for sales, cost of goods sold, profit, expenses, money in and
 * out, who owes what (and for how long), stock, data checks and plain-language observations.
 *
 * Profit, stock values and purchases come from TransactionUtil::getProfitLossDetails(), so the
 * summary always agrees with the Profit / Loss report.
 */
class BusinessReview
{
    /** @var TransactionUtil */
    protected $tu;

    public function __construct(TransactionUtil $tu)
    {
        $this->tu = $tu;
    }

    /** Ready-made periods for the filter: key => [start, end] (Y-m-d). */
    public static function presets(Carbon $today = null)
    {
        $t = ($today ?: Carbon::today())->copy();
        $q = (int) ceil($t->month / 3);
        $qs = Carbon::create($t->year, ($q - 1) * 3 + 1, 1);
        $lqs = $qs->copy()->subMonths(3);

        return [
            'this_month' => [$t->copy()->startOfMonth(), $t->copy()],
            'last_month' => [$t->copy()->subMonthNoOverflow()->startOfMonth(), $t->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$qs->copy(), $t->copy()],
            'last_quarter' => [$lqs->copy(), $lqs->copy()->addMonths(3)->subDay()],
            'this_year' => [$t->copy()->startOfYear(), $t->copy()],
            'last_year' => [$t->copy()->subYear()->startOfYear(), $t->copy()->subYear()->endOfYear()],
        ];
    }

    /**
     * Everything the summary shows for [$start, $end] (Y-m-d), one location or all permitted ones.
     */
    /**
     * The period to compare with: the same dates one step back for a ready-made period (this year
     * with the same dates last year, a month with the month before...), else as many days just before.
     */
    public static function previousPeriod(Carbon $s, Carbon $e, $preset = null)
    {
        switch ($preset) {
            case 'this_month':
            case 'last_month':
                return [$s->copy()->subMonthNoOverflow(), $preset === 'last_month' ? $s->copy()->subDay() : $e->copy()->subMonthNoOverflow()];
            case 'this_quarter':
            case 'last_quarter':
                return [$s->copy()->subMonths(3), $preset === 'last_quarter' ? $s->copy()->subDay() : $e->copy()->subMonthsNoOverflow(3)];
            case 'this_year':
            case 'last_year':
                return [$s->copy()->subYear(), $e->copy()->subYearNoOverflow()];
        }
        $days = $s->diffInDays($e) + 1;

        return [$s->copy()->subDays($days), $s->copy()->subDay()];
    }

    public function financial($business_id, $location_id, $start, $end, $permitted = 'all', $with_previous = true, $preset = null)
    {
        $s = Carbon::parse($start)->startOfDay();
        $e = Carbon::parse($end)->startOfDay();
        $days = $s->diffInDays($e) + 1;

        $pl = $this->tu->getProfitLossDetails($business_id, $location_id, $s->format('Y-m-d'), $e->format('Y-m-d'), null, $permitted);
        $sales = $this->salesActivity($business_id, $location_id, $s, $e, $permitted);
        $cost = $this->costOfSales($business_id, $location_id, $s, $e, $permitted);

        $out = [
            'start' => $s, 'end' => $e, 'days' => $days,
            'pl' => $pl,
            'sales' => $sales,
            'cogs' => $cost,
            'income' => $this->incomeStatement($pl, $cost),
            'money' => $this->moneyInOut($business_id, $location_id, $s, $e, $permitted),
            'expenses' => $this->expensesByCategory($business_id, $location_id, $s, $e, $permitted),
            'receivables' => $this->balances($business_id, $location_id, $e, $permitted, 'customer'),
            'payables' => $this->balances($business_id, $location_id, $e, $permitted, 'supplier'),
            'stock' => $this->stockPosition($business_id, $location_id, $s, $e, $permitted, $pl),
            'top_products' => $this->topProducts($business_id, $location_id, $s, $e, $permitted),
            'top_customers' => $this->topCustomers($business_id, $location_id, $s, $e, $permitted),
            'trend' => $this->trend($business_id, $location_id, $s, $e, $permitted),
            'by_location' => empty($location_id) ? $this->byLocation($business_id, $s, $e, $permitted) : [],
            'checks' => $this->dataChecks($business_id, $location_id, $s, $e, $permitted),
        ];

        // the period just before, as long, for the comparison
        $out['previous'] = null;
        if ($with_previous) {
            [$ps, $pe] = self::previousPeriod($s, $e, $preset);
            $ppl = $this->tu->getProfitLossDetails($business_id, $location_id, $ps->format('Y-m-d'), $pe->format('Y-m-d'), null, $permitted);
            $pcost = $this->costOfSales($business_id, $location_id, $ps, $pe, $permitted);
            $out['previous'] = [
                'start' => $ps, 'end' => $pe,
                'sales' => $this->salesActivity($business_id, $location_id, $ps, $pe, $permitted),
                'income' => $this->incomeStatement($ppl, $pcost),
            ];
        }
        $out['observations'] = $this->observations($out);

        return $out;
    }

    // ------------------------------------------------------------------ the parts

    protected function scoped($q, $col, $location_id, $permitted)
    {
        if (! empty($permitted) && $permitted !== 'all') {
            $q->whereIn($col, (array) $permitted);
        }
        if (! empty($location_id)) {
            $q->where($col, $location_id);
        }

        return $q;
    }

    protected function between($q, $col, Carbon $s, Carbon $e)
    {
        return $q->whereDate($col, '>=', $s->format('Y-m-d'))->whereDate($col, '<=', $e->format('Y-m-d'));
    }

    /** Number of sales, invoiced total, tax, average sale, returns. */
    public function salesActivity($business_id, $location_id, Carbon $s, Carbon $e, $permitted)
    {
        $q = DB::table('transactions as t')->where('t.business_id', $business_id)->where('t.type', 'sell')->where('t.status', 'final');
        $this->scoped($q, 't.location_id', $location_id, $permitted);
        $this->between($q, 't.transaction_date', $s, $e);
        $row = $q->selectRaw('COUNT(*) n, COALESCE(SUM(t.final_total),0) total, COALESCE(SUM(t.tax_amount),0) tax, COUNT(DISTINCT t.contact_id) customers')->first();

        $r = DB::table('transactions as t')->where('t.business_id', $business_id)->where('t.type', 'sell_return');
        $this->scoped($r, 't.location_id', $location_id, $permitted);
        $this->between($r, 't.transaction_date', $s, $e);
        $ret = $r->selectRaw('COUNT(*) n, COALESCE(SUM(t.final_total),0) total')->first();

        return [
            'count' => (int) $row->n,
            'invoiced' => (float) $row->total,
            'tax' => (float) $row->tax,
            'average' => $row->n ? (float) $row->total / $row->n : 0,
            'customers' => (int) $row->customers,
            'returns_count' => (int) $ret->n,
            'returns' => (float) $ret->total,
        ];
    }

    /**
     * Cost of the goods sold, on the same basis as the gross profit of the Profit / Loss report
     * (TransactionUtil::getGrossProfit): sold quantities (less returns) x the purchase price of the
     * lots they came from. Also what was sold with no cost recorded, or at zero cost.
     */
    public function costOfSales($business_id, $location_id, Carbon $s, Carbon $e, $permitted)
    {
        $q = DB::table('transaction_sell_lines as tsl')
            ->join('transactions as sale', 'tsl.transaction_id', '=', 'sale.id')
            ->join('products as P', 'tsl.product_id', '=', 'P.id')
            ->leftJoin('transaction_sell_lines_purchase_lines as TSPL', 'tsl.id', '=', 'TSPL.sell_line_id')
            ->leftJoin('purchase_lines as PL', 'TSPL.purchase_line_id', '=', 'PL.id')
            ->where('sale.business_id', $business_id)->where('sale.type', 'sell')->where('sale.status', 'final')
            ->where('tsl.children_type', '!=', 'combo');
        $this->scoped($q, 'sale.location_id', $location_id, $permitted);
        if ($s->equalTo($e)) {
            $q->whereDate('sale.transaction_date', $e->format('Y-m-d'));
        } else {
            $this->between($q, 'sale.transaction_date', $s, $e);
        }
        $combo_cost = '(SELECT COALESCE(SUM((tspl2.quantity - tspl2.qty_returned) * pl2.purchase_price_inc_tax), 0) FROM transaction_sell_lines AS tsl2
            JOIN transaction_sell_lines_purchase_lines AS tspl2 ON tsl2.id = tspl2.sell_line_id JOIN purchase_lines AS pl2 ON tspl2.purchase_line_id = pl2.id
            WHERE tsl2.parent_sell_line_id = tsl.id)';
        $row = $q->selectRaw('SUM(IF(TSPL.id IS NULL AND P.type = "combo", ' . $combo_cost . ', IF(P.enable_stock = 0, 0, (TSPL.quantity - TSPL.qty_returned) * PL.purchase_price_inc_tax))) cogs,
            SUM(IF(TSPL.id IS NULL AND P.type != "combo" AND P.enable_stock = 1, (tsl.quantity - tsl.quantity_returned) * tsl.unit_price_inc_tax, 0)) uncosted_value,
            COUNT(DISTINCT IF(TSPL.id IS NULL AND P.type != "combo" AND P.enable_stock = 1 AND tsl.quantity > tsl.quantity_returned, tsl.id, NULL)) uncosted_lines,
            SUM(IF(TSPL.id IS NOT NULL AND P.enable_stock = 1 AND PL.purchase_price_inc_tax <= 0, (TSPL.quantity - TSPL.qty_returned) * tsl.unit_price_inc_tax, 0)) zero_cost_value,
            COUNT(DISTINCT IF(TSPL.id IS NOT NULL AND P.enable_stock = 1 AND PL.purchase_price_inc_tax <= 0 AND TSPL.quantity > TSPL.qty_returned, tsl.id, NULL)) zero_cost_lines,
            COUNT(DISTINCT IF(TSPL.id IS NOT NULL AND PL.purchase_price_inc_tax > 0 AND tsl.unit_price_inc_tax < PL.purchase_price_inc_tax AND TSPL.quantity > TSPL.qty_returned, tsl.id, NULL)) below_cost_lines,
            SUM(IF(TSPL.id IS NOT NULL AND PL.purchase_price_inc_tax > 0 AND tsl.unit_price_inc_tax < PL.purchase_price_inc_tax, (TSPL.quantity - TSPL.qty_returned) * (PL.purchase_price_inc_tax - tsl.unit_price_inc_tax), 0)) below_cost_loss')->first();

        return [
            'cogs' => (float) $row->cogs,
            'uncosted_value' => (float) $row->uncosted_value, 'uncosted_lines' => (int) $row->uncosted_lines,
            'zero_cost_value' => (float) $row->zero_cost_value, 'zero_cost_lines' => (int) $row->zero_cost_lines,
            'below_cost_lines' => (int) $row->below_cost_lines, 'below_cost_loss' => (float) $row->below_cost_loss,
        ];
    }

    /**
     * The condensed income statement; every line adds up to the Profit / Loss report's net profit.
     */
    public function incomeStatement(array $pl, array $cost)
    {
        $gross = (float) $pl['gross_profit'];
        $cogs = (float) $cost['cogs'];
        $revenue = $gross + $cogs;
        $discounts = (float) $pl['total_sell_discount'] + (float) $pl['total_reward_amount'];
        $expenses = (float) $pl['total_expense'];
        $losses = (float) $pl['total_adjustment'] - (float) $pl['total_recovered'];
        $net = (float) $pl['net_profit'];
        $other = $net - ($gross - $discounts - $expenses - $losses);

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross' => $gross,
            'margin' => $revenue > 0 ? $gross / $revenue * 100 : null,
            'discounts' => $discounts,
            'expenses' => $expenses,
            'losses' => $losses,
            'other' => abs($other) < 0.005 ? 0 : $other,
            'net' => $net,
            'net_margin' => $revenue > 0 ? $net / $revenue * 100 : null,
        ];
    }

    /** Money received from customers (by method), paid to suppliers, paid for expenses. */
    public function moneyInOut($business_id, $location_id, Carbon $s, Carbon $e, $permitted)
    {
        $base = function (array $types) use ($business_id, $location_id, $s, $e, $permitted) {
            $q = DB::table('transaction_payments as tp')->join('transactions as t', 't.id', '=', 'tp.transaction_id')
                ->where('t.business_id', $business_id)->whereIn('t.type', $types);
            $this->scoped($q, 't.location_id', $location_id, $permitted);
            $this->between($q, 'tp.paid_on', $s, $e);

            return $q;
        };
        $in = $base(['sell', 'opening_balance'])->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->where(function ($w) {
                $w->where('t.type', 'sell')->orWhereIn('c.type', ['customer', 'both']);
            })
            ->selectRaw('tp.method, SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)) amount, COUNT(*) n')->groupBy('tp.method')->get();
        $methods = $this->tu->payment_types(null, true, $business_id);
        $by_method = [];
        $received = 0;
        foreach ($in as $r) {
            if (abs((float) $r->amount) < 0.005) {
                continue;
            }
            $by_method[] = ['method' => $methods[$r->method] ?? ucfirst(str_replace('_', ' ', (string) $r->method)), 'amount' => (float) $r->amount, 'n' => (int) $r->n];
            $received += (float) $r->amount;
        }
        usort($by_method, fn ($a, $b) => $b['amount'] <=> $a['amount']);

        $to_suppliers = (float) $base(['purchase', 'opening_balance'])->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->where(function ($w) {
                $w->where('t.type', 'purchase')->orWhere('c.type', 'supplier');
            })->sum('tp.amount');
        $expenses_paid = (float) $base(['expense'])->sum('tp.amount');
        $refunds = (float) $base(['sell_return'])->sum('tp.amount');
        $supplier_refunds = (float) $base(['purchase_return'])->sum('tp.amount');

        return [
            'received' => $received, 'by_method' => $by_method,
            'to_suppliers' => $to_suppliers, 'expenses_paid' => $expenses_paid,
            'refunds' => $refunds, 'supplier_refunds' => $supplier_refunds,
            'net' => $received + $supplier_refunds - $to_suppliers - $expenses_paid - $refunds,
        ];
    }

    public function expensesByCategory($business_id, $location_id, Carbon $s, Carbon $e, $permitted)
    {
        $q = DB::table('transactions as t')->leftJoin('expense_categories as ec', 'ec.id', '=', 't.expense_category_id')
            ->where('t.business_id', $business_id)->where('t.type', 'expense')->where('t.status', 'final');
        $this->scoped($q, 't.location_id', $location_id, $permitted);
        $this->between($q, 't.transaction_date', $s, $e);
        $rows = $q->selectRaw('COALESCE(ec.name, "") name, SUM(t.final_total) amount, COUNT(*) n')->groupBy('ec.name')->orderByDesc('amount')->get();
        $total = (float) $rows->sum('amount');

        return [
            'total' => $total,
            'rows' => $rows->map(fn ($r) => ['name' => $r->name, 'amount' => (float) $r->amount, 'n' => (int) $r->n, 'share' => $total > 0 ? $r->amount / $total * 100 : 0])->all(),
        ];
    }

    /**
     * What customers owe the business (or what it owes suppliers) at the end of $e: unpaid invoices
     * (and opening balances) less what was paid by then, by age, and the largest balances.
     */
    public function balances($business_id, $location_id, Carbon $e, $permitted, $side)
    {
        $doc = $side === 'customer' ? 'sell' : 'purchase';
        $ret = $side === 'customer' ? 'sell_return' : 'purchase_return';
        $ctype = $side === 'customer' ? ['customer', 'both'] : ['supplier', 'both'];
        $end = $e->format('Y-m-d');
        $paid = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp WHERE tp.transaction_id = t.id AND DATE(tp.paid_on) <= ?)';
        $q = DB::table('transactions as t')->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $business_id)
            ->where(function ($w) use ($doc, $ctype) {
                $w->where('t.type', $doc)->orWhere(function ($o) use ($ctype) {
                    $o->where('t.type', 'opening_balance')->whereIn('c.type', $ctype);
                });
            })
            ->whereDate('t.transaction_date', '<=', $end);
        if ($doc === 'sell') {
            $q->where('t.status', 'final');
        }
        $this->scoped($q, 't.location_id', $location_id, $permitted);
        $rows = $q->selectRaw('t.id, t.contact_id, c.name, c.supplier_business_name, c.contact_id code, c.mobile, DATE(t.transaction_date) d, t.final_total - ' . $paid . ' due', [$end])
            ->havingRaw('due > 0.005')->get();

        // returns not yet refunded / credited reduce what is owed
        $r = DB::table('transactions as t')->where('t.business_id', $business_id)->where('t.type', $ret)->whereDate('t.transaction_date', '<=', $end);
        $this->scoped($r, 't.location_id', $location_id, $permitted);
        $credits = $r->selectRaw('t.contact_id, SUM(t.final_total - ' . $paid . ') due', [$end])->groupBy('t.contact_id')->pluck('due', 'contact_id');

        $ages = ['0_30' => 0, '31_60' => 0, '61_90' => 0, '90_plus' => 0];
        $per = [];
        foreach ($rows as $x) {
            $age = Carbon::parse($x->d)->diffInDays($e, false);
            $k = $age <= 30 ? '0_30' : ($age <= 60 ? '31_60' : ($age <= 90 ? '61_90' : '90_plus'));
            $ages[$k] += (float) $x->due;
            $id = $x->contact_id;
            if (! isset($per[$id])) {
                $name = trim((string) ($x->supplier_business_name ?: $x->name));
                $per[$id] = ['name' => $name !== '' ? $name : (string) $x->name, 'code' => $x->code, 'mobile' => $x->mobile, 'due' => 0, 'invoices' => 0, 'oldest' => $x->d];
            }
            $per[$id]['due'] += (float) $x->due;
            $per[$id]['invoices']++;
            if ($x->d < $per[$id]['oldest']) {
                $per[$id]['oldest'] = $x->d;
            }
        }
        $credit_total = 0;
        foreach ($credits as $cid => $amt) {
            if ($amt > 0.005) {
                $credit_total += (float) $amt;
                if (isset($per[$cid])) {
                    $per[$cid]['due'] -= (float) $amt;
                }
            }
        }
        $gross = array_sum($ages);
        $list = array_values(array_filter($per, fn ($p) => $p['due'] > 0.005));
        usort($list, fn ($a, $b) => $b['due'] <=> $a['due']);
        foreach ($list as &$p) {
            $p['days'] = Carbon::parse($p['oldest'])->diffInDays($e, false);
        }

        return [
            'gross' => $gross,
            'credits' => $credit_total,
            'total' => max(0, $gross - $credit_total),
            'ages' => $ages,
            'contacts' => count($list),
            'invoices' => count($rows),
            'top' => array_slice($list, 0, 10),
            'over_90_share' => $gross > 0 ? $ages['90_plus'] / $gross * 100 : 0,
        ];
    }

    /** Stock at the start / end (cost and selling price), counts and expiry. */
    public function stockPosition($business_id, $location_id, Carbon $s, Carbon $e, $permitted, array $pl)
    {
        $closing_sell = $this->tu->getOpeningClosingStock($business_id, $e->format('Y-m-d'), $location_id, false, true, [], $permitted);

        $c = DB::table('inventory_count_sessions')->where('business_id', $business_id)->where('status', 'posted')->where('count_purpose', '!=', 'migration');
        $this->scoped($c, 'location_id', $location_id, $permitted);
        $this->between($c, 'posted_at', $s, $e);
        $counts = $c->selectRaw('COUNT(*) n, COALESCE(SUM(shortage_value),0) shortage, COALESCE(SUM(surplus_value),0) surplus')->first();

        $exp = (object) $this->expiryNow($business_id, $location_id, $permitted);

        $n = DB::table('variation_location_details as vld')->join('products as p', 'p.id', '=', 'vld.product_id')
            ->where('p.business_id', $business_id)->where('p.enable_stock', 1)->where('vld.qty_available', '<', 0);
        $this->scoped($n, 'vld.location_id', $location_id, $permitted);

        $closing = (float) $pl['closing_stock'];

        return [
            'opening' => (float) $pl['opening_stock'],
            'purchases' => (float) $pl['total_purchase'],
            'closing' => $closing,
            'closing_sell' => (float) $closing_sell,
            'counts' => (int) $counts->n, 'count_shortage' => (float) $counts->shortage, 'count_surplus' => (float) $counts->surplus,
            'expired_value' => (float) $exp->expired_value, 'expired_items' => (int) $exp->expired_items,
            'soon_value' => (float) $exp->soon_value, 'soon_items' => (int) $exp->soon_items,
            'negative_items' => (int) $n->count(),
        ];
    }

    public function topProducts($business_id, $location_id, Carbon $s, Carbon $e, $permitted, $limit = 10)
    {
        $q = DB::table('transaction_sell_lines as tsl')->join('transactions as sale', 'tsl.transaction_id', '=', 'sale.id')
            ->join('products as p', 'p.id', '=', 'tsl.product_id')->join('variations as v', 'v.id', '=', 'tsl.variation_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('sale.business_id', $business_id)->where('sale.type', 'sell')->where('sale.status', 'final')
            ->whereNull('tsl.parent_sell_line_id');
        $this->scoped($q, 'sale.location_id', $location_id, $permitted);
        $this->between($q, 'sale.transaction_date', $s, $e);
        $cost = '(SELECT COALESCE(SUM((x.quantity - x.qty_returned) * y.purchase_price_inc_tax), 0) FROM transaction_sell_lines_purchase_lines x JOIN purchase_lines y ON y.id = x.purchase_line_id WHERE x.sell_line_id = tsl.id)';
        $rows = $q->selectRaw('tsl.variation_id, p.name, v.name vname, p.type, v.sub_sku sku, COALESCE(NULLIF(TRIM(BOTH "." FROM TRIM(u.short_name)), ""), u.actual_name) unit,
                SUM(tsl.quantity - tsl.quantity_returned) qty, SUM((tsl.quantity - tsl.quantity_returned) * tsl.unit_price_inc_tax) revenue,
                SUM(IF(p.enable_stock = 1, ' . $cost . ', 0)) cost')
            ->groupBy('tsl.variation_id', 'p.name', 'v.name', 'p.type', 'v.sub_sku', 'u.short_name', 'u.actual_name')
            ->havingRaw('revenue > 0')->orderByDesc('revenue')->limit($limit)->get();
        $total = $this->salesActivity($business_id, $location_id, $s, $e, $permitted)['invoiced'];

        return $rows->map(function ($r) use ($total) {
            $name = $r->name . ($r->type === 'variable' && $r->vname && strtoupper($r->vname) !== 'DUMMY' ? ' - ' . $r->vname : '');

            return ['name' => $name, 'sku' => $r->sku, 'unit' => $r->unit, 'qty' => (float) $r->qty, 'revenue' => (float) $r->revenue,
                'profit' => (float) $r->revenue - (float) $r->cost, 'margin' => $r->revenue > 0 ? ((float) $r->revenue - (float) $r->cost) / $r->revenue * 100 : null,
                'share' => $total > 0 ? $r->revenue / $total * 100 : 0];
        })->all();
    }

    public function topCustomers($business_id, $location_id, Carbon $s, Carbon $e, $permitted, $limit = 5)
    {
        $q = DB::table('transactions as t')->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $business_id)->where('t.type', 'sell')->where('t.status', 'final')->where('c.is_default', 0);
        $this->scoped($q, 't.location_id', $location_id, $permitted);
        $this->between($q, 't.transaction_date', $s, $e);

        return $q->selectRaw('c.name, c.supplier_business_name, COUNT(*) n, SUM(t.final_total) total')->groupBy('c.id', 'c.name', 'c.supplier_business_name')
            ->orderByDesc('total')->limit($limit)->get()
            ->map(fn ($r) => ['name' => trim($r->supplier_business_name ?: $r->name), 'n' => (int) $r->n, 'total' => (float) $r->total])->all();
    }

    /** Sales by day (up to 2 weeks), week (up to 14 weeks, a quarter) or month. */
    public function trend($business_id, $location_id, Carbon $s, Carbon $e, $permitted)
    {
        $days = $s->diffInDays($e) + 1;
        $by = $days <= 14 ? 'day' : ($days <= 98 ? 'week' : 'month');
        $q = DB::table('transactions as t')->where('t.business_id', $business_id)->where('t.type', 'sell')->where('t.status', 'final');
        $this->scoped($q, 't.location_id', $location_id, $permitted);
        $this->between($q, 't.transaction_date', $s, $e);
        $sums = $q->selectRaw('DATE(t.transaction_date) d, SUM(t.final_total) total, COUNT(*) n')->groupBy('d')->get()->keyBy('d');

        $buckets = [];
        $cur = $s->copy();
        while ($cur->lte($e)) {
            if ($by === 'day') {
                $key = $cur->format('Y-m-d');
                $from = $cur->copy();
                $to = $cur->copy();
            } elseif ($by === 'week') {
                $from = $cur->copy();
                $to = $cur->copy()->addDays(6)->min($e);
                $key = $from->format('Y-m-d');
            } else {
                $from = $cur->copy();
                $to = $cur->copy()->endOfMonth()->startOfDay()->min($e);
                $key = $from->format('Y-m');
            }
            $total = 0;
            $n = 0;
            for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
                if (isset($sums[$d->format('Y-m-d')])) {
                    $total += (float) $sums[$d->format('Y-m-d')]->total;
                    $n += (int) $sums[$d->format('Y-m-d')]->n;
                }
            }
            $buckets[] = ['key' => $key, 'from' => $from, 'to' => $to, 'total' => $total, 'n' => $n];
            $cur = $to->copy()->addDay();
        }
        $max = max(array_merge([0], array_column($buckets, 'total')));

        return ['by' => $by, 'buckets' => $buckets, 'max' => $max];
    }

    public function byLocation($business_id, Carbon $s, Carbon $e, $permitted)
    {
        $q = DB::table('business_locations as bl')->where('bl.business_id', $business_id);
        if (! empty($permitted) && $permitted !== 'all') {
            $q->whereIn('bl.id', (array) $permitted);
        }
        $locations = $q->pluck('bl.name', 'bl.id');
        if (count($locations) < 2) {
            return [];
        }
        $out = [];
        foreach ($locations as $id => $name) {
            $a = $this->salesActivity($business_id, $id, $s, $e, $permitted);
            $gp = $this->tu->getGrossProfit($business_id, $s->format('Y-m-d'), $e->format('Y-m-d'), $id, null, $permitted);
            if ($a['count'] == 0 && abs($gp) < 0.005) {
                continue;
            }
            $out[] = ['name' => $name, 'count' => $a['count'], 'invoiced' => $a['invoiced'], 'gross' => (float) $gp];
        }
        usort($out, fn ($a, $b) => $b['invoiced'] <=> $a['invoiced']);

        return $out;
    }

    /** What makes the figures less reliable, so the assessment can say so. */
    public function dataChecks($business_id, $location_id, Carbon $s, Carbon $e, $permitted)
    {
        $c = $this->costOfSales($business_id, $location_id, $s, $e, $permitted);
        $n = DB::table('variation_location_details as vld')->join('products as p', 'p.id', '=', 'vld.product_id')
            ->where('p.business_id', $business_id)->where('p.enable_stock', 1)->where('vld.qty_available', '<', 0);
        $this->scoped($n, 'vld.location_id', $location_id, $permitted);
        $no_cost = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $business_id)->where('p.enable_stock', 1)->where('p.is_inactive', 0)->whereNull('v.deleted_at')
            ->where(function ($w) {
                $w->whereNull('v.default_purchase_price')->orWhere('v.default_purchase_price', '<=', 0);
            })->count();

        return [
            'uncosted_lines' => $c['uncosted_lines'], 'uncosted_value' => $c['uncosted_value'],
            'zero_cost_lines' => $c['zero_cost_lines'], 'zero_cost_value' => $c['zero_cost_value'],
            'below_cost_lines' => $c['below_cost_lines'], 'below_cost_loss' => $c['below_cost_loss'],
            'negative_stock' => (int) $n->count(),
            'no_purchase_price' => (int) $no_cost,
        ];
    }

    // ------------------------------------------------------------------ quarterly stock control

    /** [start, end] of a quarter. */
    public static function quarter($year, $q)
    {
        $s = Carbon::create((int) $year, ((int) $q - 1) * 3 + 1, 1)->startOfDay();

        return [$s, $s->copy()->addMonths(3)->subDay()];
    }

    public static function quarterOf(Carbon $d)
    {
        return [(int) $d->year, (int) ceil($d->month / 3)];
    }

    protected function locations($business_id, $location_id, $permitted)
    {
        $q = DB::table('business_locations')->where('business_id', $business_id)->where('is_active', 1);
        $this->scoped($q, 'id', $location_id, $permitted);

        return $q->orderBy('name')->pluck('name', 'id');
    }

    /**
     * The Stock Reconciliation Report of a quarter: every count posted in it, what was short or
     * over, which products keep coming up short, other stock write-offs, open recounts, and
     * whether each location was counted at all.
     */
    public function stockControl($business_id, $location_id, $year, $q, $permitted = 'all')
    {
        [$s, $e] = self::quarter($year, $q);
        $upto = $e->copy()->min(Carbon::today());
        $locations = $this->locations($business_id, $location_id, $permitted);
        $loc_ids = $locations->keys()->all();

        $sessions = DB::table('inventory_count_sessions as s')
            ->leftJoin('business_locations as bl', 'bl.id', '=', 's.location_id')
            ->leftJoin('users as ua', 'ua.id', '=', 's.approved_by')
            ->leftJoin('users as up', 'up.id', '=', 's.posted_by')
            ->where('s.business_id', $business_id)->whereIn('s.location_id', $loc_ids ?: [0])
            ->where(function ($w) use ($s, $e) {
                $w->where(function ($p) use ($s, $e) {
                    $p->where('s.status', 'posted')->whereDate('s.posted_at', '>=', $s->format('Y-m-d'))->whereDate('s.posted_at', '<=', $e->format('Y-m-d'));
                })->orWhere(function ($p) use ($s, $e) {
                    $p->whereIn('s.status', ['draft', 'pending_approval'])->whereDate('s.started_at', '<=', $e->format('Y-m-d'));
                });
            })
            ->orderBy('s.posted_at')->orderBy('s.started_at')
            ->get(['s.*', 'bl.name as location', DB::raw("TRIM(CONCAT(COALESCE(ua.first_name,''),' ',COALESCE(ua.last_name,''))) as approver"),
                DB::raw("TRIM(CONCAT(COALESCE(up.first_name,''),' ',COALESCE(up.last_name,''))) as poster")]);

        $ids = $sessions->where('status', 'posted')->pluck('id')->all();
        $line_stats = $ids ? DB::table('inventory_count_session_lines')->whereIn('session_id', $ids)
            ->selectRaw('session_id, SUM(IF(counted_qty IS NOT NULL AND ABS(counted_qty - theoretical_at_start) > 0.0001, 1, 0)) diffs,
                SUM(IF(counted_qty IS NOT NULL AND counted_qty < theoretical_at_start, theoretical_at_start - counted_qty, 0)) short_qty,
                SUM(IF(counted_qty IS NOT NULL AND counted_qty > theoretical_at_start, counted_qty - theoretical_at_start, 0)) over_qty,
                COUNT(DISTINCT IF(counted_qty IS NOT NULL, variation_id, NULL)) items')
            ->groupBy('session_id')->get()->keyBy('session_id') : collect();

        $counts = [];
        $open = [];
        $setup = [];
        $tot = ['shortage' => 0, 'surplus' => 0, 'diffs' => 0, 'lines' => 0, 'short_qty' => 0];
        foreach ($sessions as $x) {
            $ls = $line_stats[$x->id] ?? null;
            $row = [
                'id' => $x->id, 'ref' => $x->reference_no, 'location' => $x->location, 'location_id' => $x->location_id,
                'scope' => $x->scope_label ?: ($x->scope_type === 'all' ? __('inventory_count.scope_all') : $x->scope_type),
                'purpose' => $x->count_purpose, 'status' => $x->status,
                'started' => $x->started_at ? Carbon::parse($x->started_at) : null, 'posted' => $x->posted_at ? Carbon::parse($x->posted_at) : null,
                'approver' => $x->approver ?: $x->poster, 'lines' => (int) $x->lines_counted, 'lines_total' => (int) $x->lines_total,
                'diffs' => $ls ? (int) $ls->diffs : 0, 'short_qty' => $ls ? (float) $ls->short_qty : 0, 'over_qty' => $ls ? (float) $ls->over_qty : 0,
                'shortage' => (float) $x->shortage_value, 'surplus' => (float) $x->surplus_value,
                'net' => (float) $x->surplus_value - (float) $x->shortage_value,
            ];
            if ($x->status !== 'posted') {
                $open[] = $row;
            } elseif ($x->count_purpose === 'migration') {
                $setup[] = $row;
            } else {
                $counts[] = $row;
                $tot['shortage'] += $row['shortage'];
                $tot['surplus'] += $row['surplus'];
                $tot['diffs'] += $row['diffs'];
                $tot['lines'] += $row['lines'];
                $tot['short_qty'] += $row['short_qty'];
            }
        }
        $tot['net'] = $tot['surplus'] - $tot['shortage'];
        $routine_ids = array_column($counts, 'id');

        // each location: counted this quarter? how much of its stock?
        $per_location = [];
        foreach ($locations as $lid => $lname) {
            $mine = array_values(array_filter($counts, fn ($c) => (int) $c['location_id'] === (int) $lid));
            // coverage: of the items in stock at the location, the share counted this quarter
            $in_stock = DB::table('variation_location_details as vld')->join('products as p', 'p.id', '=', 'vld.product_id')
                ->where('p.business_id', $business_id)->where('p.enable_stock', 1)->where('p.is_inactive', 0)->where('vld.location_id', $lid)
                ->where('vld.qty_available', '!=', 0);
            $stocked = (clone $in_stock)->distinct()->count('vld.variation_id');
            $counted = $mine ? (clone $in_stock)->whereIn('vld.variation_id', function ($sub) use ($mine) {
                $sub->select('variation_id')->from('inventory_count_session_lines')->whereIn('session_id', array_column($mine, 'id'))->whereNotNull('counted_qty');
            })->distinct()->count('vld.variation_id') : 0;
            $items = $mine ? DB::table('inventory_count_session_lines')->whereIn('session_id', array_column($mine, 'id'))
                ->whereNotNull('counted_qty')->distinct()->count('variation_id') : 0;
            $value = (float) $this->tu->getOpeningClosingStock($business_id, $upto->format('Y-m-d'), $lid, false, false, [], $permitted);
            $per_location[] = [
                'id' => $lid, 'name' => $lname, 'counts' => count($mine),
                'last' => $mine ? end($mine)['posted'] : null,
                'stocked' => $stocked, 'counted' => $counted, 'items' => $items,
                'coverage' => $stocked > 0 ? min(100, $counted / $stocked * 100) : null,
                'shortage' => array_sum(array_column($mine, 'shortage')), 'surplus' => array_sum(array_column($mine, 'surplus')),
                'stock_value' => $value,
            ];
        }
        $stock_value = array_sum(array_column($per_location, 'stock_value'));

        // products short in this quarter's counts
        $short = $routine_ids ? DB::table('inventory_count_session_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')
            ->join('variations as v', 'v.id', '=', 'l.variation_id')->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->whereIn('l.session_id', $routine_ids)->whereNotNull('l.counted_qty')->whereRaw('l.counted_qty < l.theoretical_at_start - 0.0001')
            ->selectRaw('l.variation_id, p.name, v.name vname, p.type, v.sub_sku sku, COALESCE(NULLIF(TRIM(BOTH "." FROM TRIM(u.short_name)), ""), u.actual_name) unit, COUNT(DISTINCT l.session_id) times,
                SUM(l.theoretical_at_start - l.counted_qty) qty, SUM((l.theoretical_at_start - l.counted_qty) * l.unit_cost) value')
            ->groupBy('l.variation_id', 'p.name', 'v.name', 'p.type', 'v.sub_sku', 'u.short_name', 'u.actual_name')->orderByDesc('value')->limit(15)->get()
            ->map(fn ($r) => ['name' => $r->name . ($r->type === 'variable' && $r->vname && strtoupper($r->vname) !== 'DUMMY' ? ' - ' . $r->vname : ''),
                'sku' => $r->sku, 'unit' => $r->unit, 'times' => (int) $r->times, 'qty' => (float) $r->qty, 'value' => (float) $r->value])->all() : [];

        // other stock adjustments of the quarter (damage, expiry, theft...), not made by a count
        $count_txns = DB::table('inventory_count_sessions')->where('business_id', $business_id)
            ->selectRaw('shortage_transaction_id a, surplus_transaction_id b')->get()
            ->flatMap(fn ($r) => [$r->a, $r->b])->filter()->values()->all();
        $adj = DB::table('transactions as t')->where('t.business_id', $business_id)->where('t.type', 'stock_adjustment')
            ->whereIn('t.location_id', $loc_ids ?: [0])
            ->where(function ($w) {
                $w->whereNull('t.adjustment_type')->orWhereIn('t.adjustment_type', ['normal', 'abnormal']);
            });
        $this->between($adj, 't.transaction_date', $s, $e);
        if ($count_txns) {
            $adj->whereNotIn('t.id', $count_txns);
        }
        $adjustments = $adj->leftJoin('business_locations as bl', 'bl.id', '=', 't.location_id')
            ->orderByDesc('t.final_total')
            ->get(['t.id', 't.ref_no', 't.transaction_date', 't.adjustment_type', 't.final_total', 't.total_amount_recovered', 't.additional_notes', 'bl.name as location'])
            ->map(fn ($r) => ['ref' => $r->ref_no, 'date' => Carbon::parse($r->transaction_date), 'type' => $r->adjustment_type, 'value' => (float) $r->final_total,
                'recovered' => (float) $r->total_amount_recovered, 'note' => $r->additional_notes, 'location' => $r->location])->all();

        // recount queue still open
        $flag = DB::table('inventory_count_session_lines as l')->join('inventory_count_sessions as s', 's.id', '=', 'l.session_id')
            ->where('s.business_id', $business_id)->whereIn('s.location_id', $loc_ids ?: [0])
            ->whereNotNull('l.flagged_at')->whereNull('l.resolved_at')
            ->selectRaw('COUNT(*) n, COALESCE(SUM(ABS(l.flagged_diff_value)),0) value')->first();

        // the quarter before, for the comparison
        [$ps, $pe] = self::quarter($q == 1 ? $year - 1 : $year, $q == 1 ? 4 : $q - 1);
        $prev = DB::table('inventory_count_sessions')->where('business_id', $business_id)->whereIn('location_id', $loc_ids ?: [0])
            ->where('status', 'posted')->where('count_purpose', '!=', 'migration')
            ->whereDate('posted_at', '>=', $ps->format('Y-m-d'))->whereDate('posted_at', '<=', $pe->format('Y-m-d'))
            ->selectRaw('COUNT(*) n, COALESCE(SUM(shortage_value),0) shortage, COALESCE(SUM(surplus_value),0) surplus')->first();

        $expiry = $this->expiryNow($business_id, $location_id, $permitted);
        $low = DB::table('variation_location_details as vld')->join('products as p', 'p.id', '=', 'vld.product_id')
            ->where('p.business_id', $business_id)->where('p.enable_stock', 1)->where('p.is_inactive', 0)
            ->whereNotNull('p.alert_quantity')->whereRaw('vld.qty_available <= p.alert_quantity')
            ->whereIn('vld.location_id', $loc_ids ?: [0])->count();

        $d = [
            'year' => (int) $year, 'q' => (int) $q, 'start' => $s, 'end' => $e, 'upto' => $upto, 'in_progress' => $e->gt(Carbon::today()),
            'locations' => $per_location, 'counts' => $counts, 'open' => $open, 'setup' => $setup, 'totals' => $tot,
            // what was lost, net of what was found, against the stock value
            'stock_value' => $stock_value, 'shortage_share' => $stock_value > 0 && $tot['net'] < 0 ? -$tot['net'] / $stock_value * 100 : null,
            'short_products' => $short, 'adjustments' => $adjustments,
            'adjust_total' => array_sum(array_column($adjustments, 'value')),
            'recount' => ['n' => (int) $flag->n, 'value' => (float) $flag->value],
            'previous' => ['year' => $q == 1 ? $year - 1 : $year, 'q' => $q == 1 ? 4 : $q - 1, 'n' => (int) $prev->n, 'shortage' => (float) $prev->shortage, 'surplus' => (float) $prev->surplus],
            'expiry' => $expiry, 'low_stock' => $low,
        ];
        $d['observations'] = $this->stockObservations($d);

        return $d;
    }

    public function expiryNow($business_id, $location_id, $permitted, $days = 90)
    {
        $remaining = '(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned - COALESCE(pl.mfg_quantity_used, 0))';
        $x = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->join('products as p', 'p.id', '=', 'pl.product_id')
            ->where('t.business_id', $business_id)->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
            ->where('t.status', 'received')->where('p.enable_stock', 1)->whereNotNull('pl.exp_date')->whereRaw($remaining . ' > 0');
        $this->scoped($x, 't.location_id', $location_id, $permitted);
        $today = Carbon::today()->format('Y-m-d');
        $soon = Carbon::today()->addDays($days)->format('Y-m-d');
        $r = $x->selectRaw('SUM(IF(pl.exp_date < ?, ' . $remaining . ' * pl.purchase_price_inc_tax, 0)) expired_value,
                COUNT(DISTINCT IF(pl.exp_date < ?, pl.variation_id, NULL)) expired_items,
                SUM(IF(pl.exp_date >= ? AND pl.exp_date <= ?, ' . $remaining . ' * pl.purchase_price_inc_tax, 0)) soon_value,
                COUNT(DISTINCT IF(pl.exp_date >= ? AND pl.exp_date <= ?, pl.variation_id, NULL)) soon_items', [$today, $today, $today, $soon, $today, $soon])->first();

        return ['expired_value' => (float) $r->expired_value, 'expired_items' => (int) $r->expired_items,
            'soon_value' => (float) $r->soon_value, 'soon_items' => (int) $r->soon_items, 'days' => $days];
    }

    public function stockObservations(array $d)
    {
        $o = [];
        $f = fn ($n) => $this->tu->num_f($n, true);
        foreach ($d['locations'] as $l) {
            if ($l['counts'] === 0) {
                $o[] = ['red', __($d['in_progress'] ? 'review.sobs_not_counted_yet' : 'review.sobs_not_counted', ['location' => $l['name']])];
            } elseif ($l['coverage'] !== null && $l['coverage'] < 80) {
                $o[] = ['amber', __('review.sobs_partial', ['location' => $l['name'], 'pct' => number_format($l['coverage'], 0)])];
            }
        }
        $t = $d['totals'];
        if (count($d['counts'])) {
            if ($t['net'] < 0) {
                $o[] = [$d['shortage_share'] !== null && $d['shortage_share'] >= 1 ? 'red' : 'amber', __('review.sobs_net_loss', ['amount' => $f(-$t['net']),
                    'pct' => $d['shortage_share'] !== null ? number_format($d['shortage_share'], 2) : '—', 'short' => $f($t['shortage']), 'over' => $f($t['surplus'])])];
            } elseif ($t['shortage'] > 0) {
                $o[] = ['slate', __('review.sobs_net_gain', ['amount' => $f($t['net']), 'short' => $f($t['shortage']), 'over' => $f($t['surplus'])])];
            } else {
                $o[] = ['green', __('review.sobs_no_shortage')];
            }
            if ($t['shortage'] > 0 && $t['surplus'] > 0 && $d['stock_value'] > 0 && ($t['shortage'] + $t['surplus']) > $d['stock_value']) {
                // big shortages and surpluses that cancel out: items counted under the wrong product or unit
                $o[] = ['amber', __('review.sobs_mixups')];
            }
            $p = $d['previous'];
            if ($p['n'] > 0) {
                $ch = self::change($t['shortage'], $p['shortage']);
                if ($ch !== null && abs($ch) >= 10) {
                    $o[] = [$ch < 0 ? 'green' : 'red', __($ch < 0 ? 'review.sobs_better' : 'review.sobs_worse', ['pct' => number_format(abs($ch), 0), 'q' => __('review.quarter_short', ['q' => $p['q'], 'year' => $p['year']])])];
                }
            }
        }
        $rep = array_values(array_filter($d['short_products'], fn ($x) => $x['times'] >= 2));
        if ($rep) {
            $o[] = ['amber', trans_choice('review.sobs_repeat', (int) count($rep), ['n' => count($rep), 'name' => $rep[0]['name']])];
        }
        if ($d['open']) {
            $o[] = ['amber', trans_choice('review.sobs_open', (int) count($d['open']), ['n' => count($d['open'])])];
        }
        if ($d['recount']['n'] > 0) {
            $o[] = ['amber', trans_choice('review.sobs_recount', (int) $d['recount']['n'], ['n' => $d['recount']['n']])];
        }
        if ($d['adjust_total'] > 0) {
            $o[] = ['slate', trans_choice('review.sobs_adjustments', (int) count($d['adjustments']), ['n' => count($d['adjustments']), 'amount' => $f($d['adjust_total'])])];
        }
        if ($d['expiry']['expired_value'] > 0) {
            $o[] = ['red', trans_choice('review.obs_expired', (int) $d['expiry']['expired_items'], ['n' => $d['expiry']['expired_items'], 'amount' => $f($d['expiry']['expired_value'])])];
        }
        if ($d['expiry']['soon_value'] > 0) {
            $o[] = ['amber', trans_choice('review.obs_expiring', (int) $d['expiry']['soon_items'], ['n' => $d['expiry']['soon_items'], 'amount' => $f($d['expiry']['soon_value'])])];
        }

        return $o;
    }

    /**
     * The quarterly count reminder: locations with no count posted yet this quarter.
     * null when nothing is due (or the business does not count stock).
     */
    public function countReminder($business_id, $permitted = 'all')
    {
        [$y, $q] = self::quarterOf(Carbon::today());
        [$s, $e] = self::quarter($y, $q);
        $locations = $this->locations($business_id, null, $permitted);
        if ($locations->isEmpty()) {
            return null;
        }
        $done = DB::table('inventory_count_sessions')->where('business_id', $business_id)->where('status', 'posted')
            ->where('count_purpose', '!=', 'migration')->whereIn('location_id', $locations->keys()->all())
            ->whereDate('posted_at', '>=', $s->format('Y-m-d'))->whereDate('posted_at', '<=', $e->format('Y-m-d'))
            ->distinct()->pluck('location_id')->all();
        $missing = $locations->except($done);
        if ($missing->isEmpty()) {
            return null;
        }

        return ['year' => $y, 'q' => $q, 'missing' => $missing->values()->all(), 'days_left' => Carbon::today()->diffInDays($e, false) + 1, 'end' => $e];
    }

    public static function change($now, $before)
    {
        if ($before === null || abs((float) $before) < 0.005) {
            return null;
        }

        return ((float) $now - (float) $before) / abs((float) $before) * 100;
    }

    /** Plain-language points for the assessment: [tone, text]. */
    public function observations(array $d)
    {
        $o = [];
        $f = fn ($n) => $this->tu->num_f($n, true);
        $inc = $d['income'];
        $prev = $d['previous'];
        if ($d['sales']['count'] === 0) {
            $o[] = ['slate', __('review.obs_no_sales')];

            return $o;
        }
        if ($prev) {
            $ch = self::change($d['sales']['invoiced'], $prev['sales']['invoiced']);
            if ($ch !== null && abs($ch) >= 5) {
                $o[] = [$ch > 0 ? 'green' : 'red', __($ch > 0 ? 'review.obs_sales_up' : 'review.obs_sales_down', ['pct' => number_format(abs($ch), 0)])];
            }
            if ($inc['margin'] !== null && $prev['income']['margin'] !== null && abs($inc['margin'] - $prev['income']['margin']) >= 2) {
                $o[] = [$inc['margin'] > $prev['income']['margin'] ? 'green' : 'amber', __('review.obs_margin_moved', ['now' => number_format($inc['margin'], 1), 'before' => number_format($prev['income']['margin'], 1)])];
            }
            $ex = self::change($inc['expenses'], $prev['income']['expenses']);
            if ($ex !== null && $ex >= 20 && $inc['expenses'] > 0) {
                $o[] = ['amber', __('review.obs_expenses_up', ['pct' => number_format($ex, 0)])];
            }
        }
        if ($inc['net'] < 0) {
            $o[] = ['red', __('review.obs_loss', ['amount' => $f(abs($inc['net']))])];
        } elseif ($inc['margin'] !== null) {
            $o[] = ['slate', __('review.obs_margin', ['pct' => number_format($inc['margin'], 1), 'net' => number_format((float) $inc['net_margin'], 1)])];
        }
        $rec = $d['receivables'];
        if ($rec['total'] > 0) {
            $o[] = [$rec['over_90_share'] >= 25 ? 'red' : 'slate', trans_choice('review.obs_receivables', (int) $rec['contacts'], ['amount' => $f($rec['total']), 'n' => $rec['contacts'], 'old' => $f($rec['ages']['90_plus'])])];
        }
        if ($d['payables']['total'] > 0) {
            $o[] = ['slate', trans_choice('review.obs_payables', (int) $d['payables']['contacts'], ['amount' => $f($d['payables']['total']), 'n' => $d['payables']['contacts']])];
        }
        $c = $d['checks'];
        if ($c['uncosted_lines'] > 0) {
            $o[] = ['amber', trans_choice('review.obs_uncosted', (int) $c['uncosted_lines'], ['n' => $c['uncosted_lines'], 'amount' => $f($c['uncosted_value'])])];
        }
        if ($c['zero_cost_lines'] > 0) {
            $o[] = ['amber', trans_choice('review.obs_zero_cost', (int) $c['zero_cost_lines'], ['n' => $c['zero_cost_lines'], 'amount' => $f($c['zero_cost_value'])])];
        }
        if ($c['below_cost_lines'] > 0) {
            $o[] = ['amber', trans_choice('review.obs_below_cost', (int) $c['below_cost_lines'], ['n' => $c['below_cost_lines'], 'amount' => $f($c['below_cost_loss'])])];
        }
        if ($c['negative_stock'] > 0) {
            $o[] = ['amber', trans_choice('review.obs_negative_stock', (int) $c['negative_stock'], ['n' => $c['negative_stock']])];
        }
        $st = $d['stock'];
        if ($st['expired_value'] > 0) {
            $o[] = ['red', trans_choice('review.obs_expired', (int) $st['expired_items'], ['n' => $st['expired_items'], 'amount' => $f($st['expired_value'])])];
        }
        if ($st['soon_value'] > 0) {
            $o[] = ['amber', trans_choice('review.obs_expiring', (int) $st['soon_items'], ['n' => $st['soon_items'], 'amount' => $f($st['soon_value'])])];
        }
        if ($st['counts'] > 0 && $st['count_shortage'] > 0) {
            $o[] = ['amber', trans_choice('review.obs_count_shortage', (int) $st['counts'], ['n' => $st['counts'], 'amount' => $f($st['count_shortage'])])];
        }
        if (! empty($d['top_products'])) {
            $t = $d['top_products'][0];
            $o[] = ['slate', __('review.obs_top_product', ['name' => $t['name'], 'pct' => number_format($t['share'], 1)])];
        }

        return $o;
    }
}
