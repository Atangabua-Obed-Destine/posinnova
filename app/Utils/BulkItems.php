<?php

namespace App\Utils;

use App\BulkItem;
use App\BulkItemCycle;
use App\Product;
use App\PurchaseLine;
use App\Transaction;
use App\Variation;
use App\VariationLocationDetails;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova bulk items: a bag of coffee, a pack of spaghetti, a bottle of oil...
 *
 * - The menu items are combo products; their recipe says how much of the bag each sale uses
 *   ("Coffee, small" = 1/100 of a bag), so every sale already takes its share off the stock.
 * - When the bag is empty, staff tap "bag finished": the system adds up what the sales since the
 *   bag was opened should have used, compares it with the bag really used (yield %), and settles
 *   the stock so it is right in whole bags again: more used than the recipes explain becomes a
 *   stock adjustment (waste); less used adds the difference back.
 * - A new bag is opened at the same moment; a tap can be undone for 15 minutes.
 */
class BulkItems
{
    const UNDO_MINUTES = 15;

    /** @var ProductUtil */
    protected $pu;

    /** @var TransactionUtil */
    protected $tu;

    public function __construct(ProductUtil $pu, TransactionUtil $tu)
    {
        $this->pu = $pu;
        $this->tu = $tu;
    }

    /** The menu items (combos) whose recipe uses this item, with how much each sale uses and per container. */
    public function users(BulkItem $item)
    {
        $out = [];
        $base_unit = optional($item->product)->unit_id;
        $combos = Product::where('business_id', $item->business_id)->where('type', 'combo')->with('variations')->get();
        foreach ($combos as $p) {
            foreach ($p->variations as $v) {
                foreach ((array) $v->combo_variations as $c) {
                    if ((int) ($c['variation_id'] ?? 0) !== (int) $item->variation_id) {
                        continue;
                    }
                    $mult = 1;
                    if (! empty($c['unit_id']) && $base_unit && (int) $c['unit_id'] !== (int) $base_unit) {
                        $mult = (float) $this->pu->getMultiplierOf2Units($base_unit, $c['unit_id']) ?: 1;
                    }
                    $per_sale = (float) ($c['quantity'] ?? 0) * $mult;
                    $out[] = [
                        'variation_id' => $v->id, 'product_id' => $p->id, 'name' => $p->name, 'is_inactive' => (bool) $p->is_inactive,
                        'per_sale' => $per_sale,
                        'per_container' => $per_sale > 0 ? $item->container_size / $per_sale : null,
                    ];
                }
            }
        }
        usort($out, fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $out;
    }

    /**
     * What the sales at a location used of the item between two moments (the moment each sale
     * became final), by menu item and by seller.
     */
    public function usage(BulkItem $item, $location_id, Carbon $from, Carbon $to = null)
    {
        $when = 'COALESCE(t.finalized_at, t.transaction_date)';
        $q = DB::table('transaction_sell_lines as c')
            ->join('transaction_sell_lines as p', 'p.id', '=', 'c.parent_sell_line_id')
            ->join('transactions as t', 't.id', '=', 'c.transaction_id')
            ->leftJoin('products as pr', 'pr.id', '=', 'p.product_id')
            ->leftJoin('users as u', 'u.id', '=', 't.created_by')
            ->where('t.business_id', $item->business_id)->where('t.location_id', $location_id)
            ->where('t.type', 'sell')->where('t.status', 'final')
            ->where('c.children_type', 'combo')->where('c.variation_id', $item->variation_id)
            ->whereRaw($when . ' >= ?', [$from->format('Y-m-d H:i:s')]);
        if ($to) {
            $q->whereRaw($when . ' < ?', [$to->format('Y-m-d H:i:s')]);
        }
        $rows = $q->selectRaw('p.variation_id, pr.name, t.created_by, TRIM(CONCAT(COALESCE(u.first_name, ""), " ", COALESCE(u.last_name, ""))) seller,
                SUM(p.quantity - p.quantity_returned) sold, SUM(c.quantity - c.quantity_returned) used, COUNT(DISTINCT t.id) sales')
            ->groupBy('p.variation_id', 'pr.name', 't.created_by', 'u.first_name', 'u.last_name')->get();

        $items = [];
        $sellers = [];
        $used = 0;
        foreach ($rows as $r) {
            $used += (float) $r->used;
            $k = (int) $r->variation_id;
            $items[$k] = $items[$k] ?? ['name' => $r->name, 'sold' => 0, 'used' => 0];
            $items[$k]['sold'] += (float) $r->sold;
            $items[$k]['used'] += (float) $r->used;
            $s = (int) $r->created_by;
            $sellers[$s] = $sellers[$s] ?? ['name' => $r->seller, 'sold' => 0, 'used' => 0];
            $sellers[$s]['sold'] += (float) $r->sold;
            $sellers[$s]['used'] += (float) $r->used;
        }

        return ['used' => $used, 'items' => array_values($items), 'sellers' => array_values($sellers)];
    }

    public function openCycle(BulkItem $item, $location_id)
    {
        return BulkItemCycle::where('bulk_item_id', $item->id)->where('location_id', $location_id)->where('status', 'open')->latest('id')->first();
    }

    /** Every active item at a location, with its open container and how much of it the sales have used so far. */
    public function current($business_id, $location_id)
    {
        $out = [];
        foreach (BulkItem::where('business_id', $business_id)->where('is_active', 1)->with('product.unit', 'variation')->get() as $item) {
            $cycle = $this->openCycle($item, $location_id);
            $so_far = $cycle ? $this->usage($item, $location_id, $cycle->opened_at) : null;
            $last = BulkItemCycle::where('bulk_item_id', $item->id)->where('location_id', $location_id)->where('status', 'closed')->latest('closed_at')->with('closer')->first();
            $out[] = [
                'item' => $item, 'cycle' => $cycle, 'so_far' => $so_far,
                'share' => $cycle && $item->container_size > 0 ? $so_far['used'] / $item->container_size : null,
                'last' => $last,
                'can_undo' => $last && $last->closed_at && $last->closed_at->gt(now()->subMinutes(self::UNDO_MINUTES)),
            ];
        }

        return $out;
    }

    /** The first container opened (tracking starts). */
    public function start(BulkItem $item, $location_id, $user_id)
    {
        return DB::transaction(function () use ($item, $location_id, $user_id) {
            $open = BulkItemCycle::where('bulk_item_id', $item->id)->where('location_id', $location_id)->where('status', 'open')->lockForUpdate()->first();
            if ($open) {
                return $open;
            }

            return BulkItemCycle::create(['business_id' => $item->business_id, 'bulk_item_id' => $item->id, 'location_id' => $location_id,
                'status' => 'open', 'opened_at' => now(), 'opened_by' => $user_id]);
        });
    }

    /**
     * The container is empty ($containers of them, if a tap was forgotten). Closes the cycle with its
     * yield, settles the stock, and opens the next container (unless $open_next is false: none left).
     */
    public function finish(BulkItem $item, $location_id, $user_id, $containers = 1, $open_next = true, $note = null)
    {
        $containers = max(1, min(20, (float) $containers));

        return DB::transaction(function () use ($item, $location_id, $user_id, $containers, $open_next, $note) {
            $cycle = BulkItemCycle::where('bulk_item_id', $item->id)->where('location_id', $location_id)->where('status', 'open')->lockForUpdate()->first();
            if (! $cycle) {
                throw new \RuntimeException(__('bulk.err_not_started'));
            }
            $now = now();
            $usage = $this->usage($item, $location_id, $cycle->opened_at, $now);
            $actual = $containers * (float) $item->container_size;
            $expected = $usage['used'];
            $diff = $actual - $expected; // > 0: more used than the sales explain
            $cost = $this->unitCost($item->variation_id, $location_id);

            $business = \App\Business::find($item->business_id);
            $business_arr = ['id' => $business->id, 'accounting_method' => $business->accounting_method, 'location_id' => $location_id, 'pos_settings' => $business->pos_settings];
            $ref = __('bulk.adj_note', ['name' => $item->name(), 'label' => __('bulk.label_' . $item->container_label), 'n' => $cycle->id]);
            $adj_id = $sur_id = null;
            if ($diff > 0.00005) {
                $adj_id = $this->shortage($item, $location_id, $user_id, $diff, $cost, $ref, $business_arr);
            } elseif ($diff < -0.00005) {
                $sur_id = $this->surplus($item, $location_id, $user_id, -$diff, $cost, $ref);
            }

            $cycle->fill([
                'status' => 'closed', 'closed_at' => $now, 'closed_by' => $user_id, 'containers' => $containers,
                'expected_usage' => $expected, 'actual_usage' => $actual,
                'yield_pct' => $actual > 0 ? round($expected / $actual * 100, 2) : null,
                'unit_cost' => $cost, 'value' => $diff * $cost,
                'breakdown' => ['items' => $usage['items'], 'sellers' => $usage['sellers']],
                'adjustment_transaction_id' => $adj_id, 'surplus_transaction_id' => $sur_id,
                'note' => $note ? mb_substr($note, 0, 1000) : null,
            ])->save();

            if ($open_next) {
                BulkItemCycle::create(['business_id' => $item->business_id, 'bulk_item_id' => $item->id, 'location_id' => $location_id,
                    'status' => 'open', 'opened_at' => $now, 'opened_by' => $user_id]);
            }

            return $cycle;
        });
    }

    /** Undo the last "finished" tap of an item at a location (within UNDO_MINUTES): the container is open again. */
    public function undo(BulkItemCycle $cycle)
    {
        return DB::transaction(function () use ($cycle) {
            $cycle = BulkItemCycle::lockForUpdate()->find($cycle->id);
            $latest = BulkItemCycle::where('bulk_item_id', $cycle->bulk_item_id)->where('location_id', $cycle->location_id)->where('status', 'closed')->latest('closed_at')->first();
            if ($cycle->status !== 'closed' || ! $latest || $latest->id !== $cycle->id || $cycle->closed_at->lt(now()->subMinutes(self::UNDO_MINUTES))) {
                throw new \RuntimeException(__('bulk.err_undo_late'));
            }
            // the surplus added back must not have been sold yet
            if ($cycle->surplus_transaction_id) {
                $pls = PurchaseLine::where('transaction_id', $cycle->surplus_transaction_id)->get();
                foreach ($pls as $pl) {
                    if ((float) $pl->quantity_sold + (float) $pl->quantity_adjusted + (float) $pl->quantity_returned > 0.00005) {
                        throw new \RuntimeException(__('bulk.err_undo_used'));
                    }
                }
                foreach ($pls as $pl) {
                    $this->pu->decreaseProductQuantity($pl->product_id, $pl->variation_id, $cycle->location_id, (float) $pl->quantity);
                    $pl->delete();
                }
                Transaction::where('id', $cycle->surplus_transaction_id)->delete();
            }
            if ($cycle->adjustment_transaction_id) {
                $adj = Transaction::with('stock_adjustment_lines')->find($cycle->adjustment_transaction_id);
                if ($adj) {
                    $ids = [];
                    foreach ($adj->stock_adjustment_lines as $l) {
                        $this->pu->updateProductQuantity($cycle->location_id, $l->product_id, $l->variation_id, (float) $l->quantity, 0, null, false); // raw numbers
                        $ids[] = $l->id;
                    }
                    if ($ids) {
                        $this->tu->mapPurchaseQuantityForDeleteStockAdjustment($ids);
                    }
                    $adj->delete();
                }
            }
            // the container opened at that tap goes away; this one is open again
            BulkItemCycle::where('bulk_item_id', $cycle->bulk_item_id)->where('location_id', $cycle->location_id)
                ->where('status', 'open')->where('opened_at', '>=', $cycle->closed_at)->delete();
            $cycle->fill(['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'containers' => 1, 'expected_usage' => 0, 'actual_usage' => 0,
                'yield_pct' => null, 'unit_cost' => 0, 'value' => 0, 'breakdown' => null, 'adjustment_transaction_id' => null, 'surplus_transaction_id' => null])->save();

            return $cycle;
        });
    }

    /** History of finished containers, newest first. */
    public function history($business_id, array $filters = [], $limit = 200)
    {
        $q = BulkItemCycle::where('business_id', $business_id)->where('status', 'closed')->with('item.product', 'item.variation', 'location', 'opener', 'closer');
        if (! empty($filters['bulk_item_id'])) {
            $q->where('bulk_item_id', $filters['bulk_item_id']);
        }
        if (! empty($filters['location_id'])) {
            $q->where('location_id', $filters['location_id']);
        }
        if (! empty($filters['permitted']) && $filters['permitted'] !== 'all') {
            $q->whereIn('location_id', (array) $filters['permitted']);
        }
        if (! empty($filters['from'])) {
            $q->whereDate('closed_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $q->whereDate('closed_at', '<=', $filters['to']);
        }

        return $q->orderByDesc('closed_at')->limit($limit)->get();
    }

    /** Is a finished container outside the expected range? low | high | null */
    public static function flag(BulkItemCycle $c, BulkItem $item)
    {
        if ($c->yield_pct === null) {
            return null;
        }
        if ($c->yield_pct < $item->low_pct) {
            return 'low';
        }
        if ($c->yield_pct > $item->high_pct) {
            return 'high';
        }

        return null;
    }

    /** Containers finished in the last $days days that gave less than expected (for the Home alert). */
    public function recentLow($business_id, $permitted = 'all', $days = 7)
    {
        $q = BulkItemCycle::where('bulk_item_cycles.business_id', $business_id)->where('bulk_item_cycles.status', 'closed')
            ->join('bulk_items as bi', 'bi.id', '=', 'bulk_item_cycles.bulk_item_id')
            ->whereRaw('bulk_item_cycles.yield_pct < bi.low_pct')
            ->where('bulk_item_cycles.closed_at', '>=', now()->subDays($days));
        if ($permitted !== 'all') {
            $q->whereIn('bulk_item_cycles.location_id', (array) $permitted);
        }

        return $q->count();
    }

    // ------------------------------------------------------------------ stock

    protected function unitCost($variation_id, $location_id)
    {
        $row = DB::table('purchase_lines as pl')->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('pl.variation_id', $variation_id)->where('t.location_id', $location_id)
            ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer'])->where('pl.purchase_price_inc_tax', '>', 0)
            ->orderByDesc('t.transaction_date')->orderByDesc('pl.id')->value('pl.purchase_price_inc_tax');
        if ($row) {
            return (float) $row;
        }

        return (float) optional(Variation::find($variation_id))->dpp_inc_tax;
    }

    /** More used than the sales explain: a stock adjustment (waste) of the difference. */
    protected function shortage(BulkItem $item, $location_id, $user_id, $qty, $cost, $note, array $business)
    {
        $ref_count = $this->pu->setAndGetReferenceCount('stock_adjustment');
        $ref_no = $this->pu->generateReferenceNumber('stock_adjustment', $ref_count);
        $this->pu->decreaseProductQuantity($item->product_id, $item->variation_id, $location_id, $qty);
        $txn = Transaction::create([
            'business_id' => $item->business_id, 'location_id' => $location_id, 'type' => 'stock_adjustment', 'status' => 'received',
            'adjustment_type' => 'normal', 'ref_no' => $ref_no, 'transaction_date' => Carbon::now(),
            'total_amount_recovered' => 0, 'final_total' => $qty * $cost, 'additional_notes' => $note, 'created_by' => $user_id,
        ]);
        $txn->stock_adjustment_lines()->createMany([[
            'product_id' => $item->product_id, 'variation_id' => $item->variation_id, 'quantity' => $qty, 'unit_price' => $cost,
        ]]);
        // link it to the purchase batches; when the bag stock was never fully entered, keep the adjustment anyway
        try {
            DB::transaction(function () use ($business, $txn) {
                $this->tu->mapPurchaseSell($business, $txn->stock_adjustment_lines, 'stock_adjustment');
            });
        } catch (\App\Exceptions\PurchaseSellMismatch $e) {
            \Illuminate\Support\Facades\Log::info('bulk items: adjustment ' . $txn->id . ' not linked to a batch: ' . $e->getMessage());
        }

        return $txn->id;
    }

    /** Less used than the sales explain (recipes too generous, or a bag bigger than set): put the difference back. */
    protected function surplus(BulkItem $item, $location_id, $user_id, $qty, $cost, $note)
    {
        $txn = Transaction::create([
            'business_id' => $item->business_id, 'location_id' => $location_id, 'type' => 'opening_stock', 'status' => 'received',
            'opening_stock_product_id' => $item->product_id, 'ref_no' => 'BULK+/' . $item->business_id . '/' . $location_id . '/' . date('Ymd-His'),
            'transaction_date' => Carbon::now(), 'total_before_tax' => $qty * $cost, 'final_total' => $qty * $cost,
            'payment_status' => 'paid', 'additional_notes' => $note, 'created_by' => $user_id,
        ]);
        $pl = new PurchaseLine();
        $pl->transaction_id = $txn->id;
        $pl->product_id = $item->product_id;
        $pl->variation_id = $item->variation_id;
        $pl->location_id = $location_id;
        $pl->quantity = $qty;
        $pl->pp_without_discount = $cost;
        $pl->purchase_price = $cost;
        $pl->purchase_price_inc_tax = $cost;
        $pl->item_tax = 0;
        $pl->quantity_sold = 0;
        $pl->quantity_adjusted = 0;
        $pl->quantity_returned = 0;
        $pl->save();
        $variation = Variation::find($item->variation_id);
        $vld = VariationLocationDetails::firstOrCreate(
            ['variation_id' => $item->variation_id, 'product_id' => $item->product_id, 'location_id' => $location_id],
            ['product_variation_id' => $variation->product_variation_id, 'qty_available' => 0]
        );
        $vld->increment('qty_available', $qty);

        return $txn->id;
    }
}
