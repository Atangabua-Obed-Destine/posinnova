<?php

namespace App\Http\Controllers;

use App\PurchaseLine;
use App\Transaction;
use App\Utils\ProductUtil;
use DB;
use Illuminate\Http\Request;

/**
 * PosInnova "Stock Doctor".
 *
 * Detects and repairs stock-ledger drift that blocks POS sales with:
 *   "Mismatch between sold and purchase quantity ..."
 *
 * Background: stock lives in two parallel ledgers —
 *   1. variation_location_details.qty_available  (what the UI/cart shows)
 *   2. purchase_lines remaining                  (what checkout maps against)
 * When (1) > (2) a product looks in-stock but refuses to sell (GHOST stock).
 *
 * scan()  — read-only; lists ghost rows for the current business.
 * heal()  — creates a backing opening_stock PurchaseLine for each gap so the
 *           product becomes sellable immediately. It NEVER changes
 *           qty_available, so the numbers cashiers see stay identical.
 *
 * This mirrors the logic proven in app/Console/Commands/StockAudit.php
 * (pos:stockAudit fix-ghost) but exposes it as an in-POS, one-click action.
 */
class StockIntegrityController extends Controller
{
    protected $productUtil;

    public function __construct(ProductUtil $productUtil)
    {
        $this->productUtil = $productUtil;
    }

    /**
     * Read-only scan for ghost-stock rows in the current business.
     * Returns a small JSON payload used by the POS header badge + modal.
     */
    public function scan(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        $rows = $this->ghostRows($business_id);

        $data = [];
        $total_gap = 0;
        foreach ($rows as $r) {
            $gap = round((float) $r->displayed_stock - (float) $r->real_remaining, 4);
            if ($gap <= 0) {
                continue;
            }
            $total_gap += $gap;
            $data[] = [
                'variation_id' => (int) $r->variation_id,
                'location_id' => (int) $r->location_id,
                'location_name' => $r->location_name,
                'sub_sku' => $r->sub_sku,
                'product' => $r->product_name,
                'display' => (float) $r->displayed_stock,
                'sellable' => (float) $r->real_remaining,
                'gap' => $gap,
            ];
        }

        return response()->json([
            'success' => 1,
            'count' => count($data),
            'total_gap' => $total_gap,
            'rows' => $data,
        ]);
    }

    /**
     * Repair ghost stock by back-filling the gap with opening_stock purchase
     * lines. Optionally limited to a set of variation ids (per-product heal).
     * qty_available is never modified.
     */
    public function heal(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $user_id = $request->session()->get('user.id');

        // Optional list of variation ids for a targeted (per-product) heal.
        $only_variations = $request->input('variation_ids', []);
        if (! is_array($only_variations)) {
            $only_variations = array_filter(explode(',', (string) $only_variations));
        }
        $only_variations = array_map('intval', $only_variations);

        $rows = $this->ghostRows($business_id, $only_variations);

        if (empty($rows) || count($rows) === 0) {
            return response()->json([
                'success' => 1,
                'healed_rows' => 0,
                'healed_units' => 0,
                'msg' => __('lang_v1.stock_doctor_nothing_to_heal'),
            ]);
        }

        DB::beginTransaction();
        try {
            $healed_rows = 0;
            $healed_units = 0;

            foreach ($rows as $r) {
                $gap = round((float) $r->displayed_stock - (float) $r->real_remaining, 4);
                if ($gap <= 0) {
                    continue;
                }

                $cost = $this->resolveCost($r);

                // One tidy opening_stock transaction per (business,location,product,day).
                $ref_no = 'SD-FIX-'.$business_id.'-'.$r->location_id.'-'.$r->product_id.'-'.date('Ymd');

                $txn = Transaction::firstOrCreate(
                    [
                        'business_id' => $business_id,
                        'location_id' => $r->location_id,
                        'type' => 'opening_stock',
                        'ref_no' => $ref_no,
                    ],
                    [
                        'opening_stock_product_id' => $r->product_id,
                        'status' => 'received',
                        'transaction_date' => now(),
                        'total_before_tax' => 0,
                        'final_total' => 0,
                        'payment_status' => 'paid',
                        'additional_notes' => 'Stock Doctor: auto back-fill of display/purchase drift.',
                        'created_by' => $user_id,
                    ]
                );

                $line = new PurchaseLine();
                $line->transaction_id = $txn->id;
                $line->product_id = $r->product_id;
                $line->variation_id = $r->variation_id;
                $line->location_id = $r->location_id;
                $line->quantity = $gap;
                $line->pp_without_discount = $cost;
                $line->purchase_price = $cost;
                $line->purchase_price_inc_tax = $cost;
                $line->item_tax = 0;
                $line->quantity_sold = 0;
                $line->quantity_adjusted = 0;
                $line->quantity_returned = 0;
                $line->save();

                $txn->total_before_tax = (float) $txn->total_before_tax + ($cost * $gap);
                $txn->final_total = (float) $txn->final_total + ($cost * $gap);
                $txn->save();

                $healed_rows++;
                $healed_units += $gap;
            }

            DB::commit();

            return response()->json([
                'success' => 1,
                'healed_rows' => $healed_rows,
                'healed_units' => $healed_units,
                'msg' => __('lang_v1.stock_doctor_healed', ['rows' => $healed_rows, 'units' => $this->productUtil->num_f($healed_units)]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('StockDoctor heal failed. File:'.$e->getFile().' Line:'.$e->getLine().' Message:'.$e->getMessage());

            return response()->json([
                'success' => 0,
                'msg' => __('messages.something_went_wrong'),
            ], 500);
        }
    }

    /**
     * Ghost-stock rows (displayed_stock > real_remaining) for a business.
     * Mirrors StockAudit::baseStockQuery + ghostStockQuery, honouring per-line
     * purchase_lines.location_id overrides.
     *
     * @param  int  $business_id
     * @param  array  $only_variations  optional variation_id filter
     * @return \Illuminate\Support\Collection
     */
    protected function ghostRows($business_id, array $only_variations = [])
    {
        $subSql = '(SELECT pl.variation_id, COALESCE(pl.location_id, t.location_id) AS location_id, '
            .'SUM(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned) AS real_remaining '
            .'FROM purchase_lines pl '
            .'JOIN transactions t ON t.id = pl.transaction_id '
            .'WHERE t.type IN ("purchase","opening_stock","purchase_transfer","production_purchase") AND t.status = "received" '
            .'GROUP BY pl.variation_id, COALESCE(pl.location_id, t.location_id))';

        $q = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
            ->leftJoin(DB::raw($subSql.' as stock_calc'), function ($j) {
                $j->on('stock_calc.variation_id', '=', 'vld.variation_id')
                  ->on('stock_calc.location_id', '=', 'vld.location_id');
            })
            ->where('p.business_id', $business_id)
            ->where('p.enable_stock', 1)
            ->where('vld.qty_available', '>', 0)
            ->whereRaw('IFNULL(stock_calc.real_remaining, 0) < vld.qty_available')
            ->select(
                'p.business_id',
                'vld.location_id',
                'bl.name as location_name',
                'p.id as product_id',
                'p.name as product_name',
                'v.id as variation_id',
                'v.sub_sku',
                'v.default_purchase_price',
                'vld.qty_available as displayed_stock',
                DB::raw('IFNULL(stock_calc.real_remaining, 0) as real_remaining')
            );

        if (! empty($only_variations)) {
            $q->whereIn('vld.variation_id', $only_variations);
        }

        return $q->orderBy('p.name')->orderBy('v.sub_sku')->get();
    }

    /**
     * Defensible unit cost for the back-fill line:
     * last purchase at that location → variation default → 0.
     */
    protected function resolveCost($row)
    {
        $last = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('pl.variation_id', $row->variation_id)
            ->whereRaw('COALESCE(pl.location_id, t.location_id) = ?', [$row->location_id])
            ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
            ->orderByDesc('t.transaction_date')
            ->orderByDesc('pl.id')
            ->value('pl.purchase_price');

        if ((float) $last > 0) {
            return (float) $last;
        }

        return (float) $row->default_purchase_price;
    }
}
