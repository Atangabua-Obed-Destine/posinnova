<?php

namespace App\Utils;

use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: what the clinic can give of a medicine, for the doctor writing a prescription.
 *
 * Per variation:
 *   here          stock at the consultation's location (variation_location_details), less what sits in
 *                 expired lots there, never below 0
 *   expired_here  stock at that location in lots past their expiry date
 *   elsewhere     stock at the business's other locations (e.g. the store room), with their names
 *   next_expiry   the earliest expiry date among lots still holding stock at the location (not expired)
 *   status        in_stock | low | out | expired_only | not_tracked | not_here
 *
 * A lot's remaining stock is quantity - sold - adjusted - returned - used in manufacturing, at
 * COALESCE(line location, transaction location) - the same rules the stock reports use.
 */
class DrugAvailability
{
    /** A lot expiring within this many days is flagged "expires soon". */
    const EXPIRY_SOON_DAYS = 90;

    /**
     * Medicines matching a search, with their availability at a location. Sold here and in stock first.
     *
     * @return array of arrays
     */
    public static function search($business_id, $term, $location_id, $limit = 20)
    {
        $term = trim((string) $term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $rows = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.unit_id')
            ->where('p.business_id', $business_id)
            ->where('p.is_inactive', 0)
            ->where('p.type', '!=', 'modifier')
            ->whereNull('v.deleted_at')
            ->where(function ($q) use ($like) {
                $q->where('p.name', 'like', $like)
                    ->orWhere('p.sku', 'like', $like)
                    ->orWhere('v.sub_sku', 'like', $like);
            })
            ->where('p.not_for_selling', 0)
            ->orderBy('p.name')
            ->limit(60)
            ->get([
                'v.id as variation_id', 'p.id as product_id', 'p.name as product_name', 'p.type', 'v.name as variation_name',
                'pv.name as product_variation_name', 'p.enable_stock', 'p.alert_quantity', 'u.short_name as unit',
                'v.sell_price_inc_tax',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        $info = self::forVariations($business_id, $rows->pluck('variation_id')->all(), $location_id, $rows);

        $out = [];
        foreach ($rows as $r) {
            $name = $r->product_name;
            if ($r->type == 'variable') {
                $name .= ' - '.$r->product_variation_name.' - '.$r->variation_name;
            }
            $out[] = array_merge([
                'product_id' => (int) $r->product_id,
                'variation_id' => (int) $r->variation_id,
                'name' => $name,
                'unit' => $r->unit,
                'price' => (float) $r->sell_price_inc_tax,
            ], $info[$r->variation_id]);
        }

        // what the dispensary can give first, then the rest; names starting with the search first inside each group
        $rank = ['in_stock' => 0, 'low' => 0, 'not_tracked' => 0, 'out' => 1, 'expired_only' => 1, 'not_here' => 2];
        $key = fn ($d) => [$rank[$d['status']], mb_stripos($d['name'], $term) === 0 ? 0 : 1, mb_strtolower($d['name'])];
        usort($out, fn ($a, $b) => $key($a) <=> $key($b));

        return array_slice($out, 0, $limit);
    }

    /**
     * Availability of the given variations at a location, keyed by variation id.
     *
     * @param  \Illuminate\Support\Collection|null  $products  rows with variation_id, product_id, enable_stock, alert_quantity
     *                                                       (looked up when not given)
     */
    public static function forVariations($business_id, array $variation_ids, $location_id, $products = null)
    {
        $variation_ids = array_values(array_unique(array_filter(array_map('intval', $variation_ids))));
        if (empty($variation_ids)) {
            return [];
        }
        if ($products === null) {
            $products = DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
                ->where('p.business_id', $business_id)->whereIn('v.id', $variation_ids)
                ->get(['v.id as variation_id', 'p.id as product_id', 'p.enable_stock', 'p.alert_quantity']);
        }
        $location_id = (int) $location_id;
        $today = \Carbon\Carbon::today()->toDateString();

        $locations = DB::table('business_locations')->where('business_id', $business_id)->pluck('name', 'id');

        $stock = DB::table('variation_location_details')
            ->whereIn('variation_id', $variation_ids)
            ->get(['variation_id', 'location_id', 'qty_available'])
            ->groupBy('variation_id');

        // product sold at the location (UltimatePOS "business locations" of a product)
        $sold_here = DB::table('product_locations')
            ->whereIn('product_id', $products->pluck('product_id')->unique()->all())
            ->where('location_id', $location_id)
            ->pluck('product_id')->flip();

        // lots still holding stock at the location: expired quantity and the nearest expiry date to come
        $remaining = '(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned - pl.mfg_quantity_used)';
        $lots = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $business_id)
            ->whereIn('pl.variation_id', $variation_ids)
            ->whereRaw('COALESCE(pl.location_id, t.location_id) = ?', [$location_id])
            ->whereNotNull('pl.exp_date')
            ->whereRaw($remaining.' > 0')
            ->groupBy('pl.variation_id')
            ->select(
                'pl.variation_id',
                DB::raw("SUM(CASE WHEN pl.exp_date < '$today' THEN $remaining ELSE 0 END) as expired_qty"),
                DB::raw("MIN(CASE WHEN pl.exp_date >= '$today' THEN pl.exp_date END) as next_expiry")
            )
            ->get()->keyBy('variation_id');

        $out = [];
        foreach ($products as $p) {
            $vid = (int) $p->variation_id;
            $rows = $stock->get($p->variation_id, collect());
            $at_location = (float) optional($rows->firstWhere('location_id', $location_id))->qty_available;
            $lot = $lots->get($p->variation_id);
            $expired = $lot ? max(0, (float) $lot->expired_qty) : 0;
            $here = max(0, $at_location - $expired);

            $elsewhere = [];
            foreach ($rows as $r) {
                if ((int) $r->location_id != $location_id && (float) $r->qty_available > 0 && isset($locations[$r->location_id])) {
                    $elsewhere[] = ['location' => $locations[$r->location_id], 'qty' => (float) $r->qty_available];
                }
            }

            $next_expiry = $lot && $lot->next_expiry ? $lot->next_expiry : null;
            $expires_soon = $next_expiry && $here > 0
                && \Carbon\Carbon::parse($next_expiry)->lte(\Carbon\Carbon::today()->addDays(self::EXPIRY_SOON_DAYS));

            if (! $sold_here->has($p->product_id)) {
                $status = 'not_here';
            } elseif (! $p->enable_stock) {
                $status = 'not_tracked';
            } elseif ($here > 0) {
                $status = ($p->alert_quantity !== null && (float) $p->alert_quantity > 0 && $here <= (float) $p->alert_quantity) ? 'low' : 'in_stock';
            } elseif ($expired > 0) {
                $status = 'expired_only';
            } else {
                $status = 'out';
            }

            $out[$vid] = [
                'status' => $status,
                'here' => $p->enable_stock ? $here : null,
                'expired_here' => $expired,
                'elsewhere' => $elsewhere,
                'elsewhere_total' => array_sum(array_column($elsewhere, 'qty')),
                'next_expiry' => $next_expiry,
                'next_expiry_formatted' => $next_expiry ? \Carbon\Carbon::parse($next_expiry)->format(session('business.date_format') ?: 'Y-m-d') : null,
                'expires_soon' => (bool) $expires_soon,
                'can_give' => in_array($status, ['in_stock', 'low', 'not_tracked']),
            ];
        }

        return $out;
    }
}
