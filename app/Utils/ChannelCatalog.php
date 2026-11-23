<?php

namespace App\Utils;

use App\Business;
use App\BusinessLocation;
use App\ChannelLink;
use App\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova online channels: what E-Ride is told about the products of one linked location
 * (the <Item> of contract v1). Read-only: never changes stock, prices or products.
 *
 * - Published = ticked "On E-Ride", not "Never on E-Ride", not in a blocked category, sold at the
 *   location, active, for sale, single or variable (no combos), and not a hidden system service.
 * - price: the owner's E-Ride price, else the location's price group, else the selling price;
 *   tax included, in the published unit, rounded to whole FCFA.
 * - qty_available: stock at the location minus lots that are expired (or inside the stop-selling
 *   days), in the published unit, never below 0. null when the product does not track stock.
 */
class ChannelCatalog
{
    /** Published variation ids of the link's location, optionally limited to $only. */
    public static function publishedQuery(ChannelLink $link, array $only = null)
    {
        $q = DB::table('products as p')
            ->join('channel_products as cp', function ($j) {
                $j->on('cp.product_id', '=', 'p.id')->on('cp.business_id', '=', 'p.business_id');
            })
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->join('product_locations as pl', function ($j) use ($link) {
                $j->on('pl.product_id', '=', 'p.id')->where('pl.location_id', '=', $link->location_id);
            })
            ->where('p.business_id', $link->business_id)
            ->where('cp.publish', 1)->where('cp.never', 0)
            ->whereNull('v.deleted_at')
            ->where('p.is_inactive', 0)
            ->where('p.not_for_selling', 0)
            ->whereIn('p.type', ['single', 'variable']);
        Product::hideSystemServices($q, 'p');

        $blocked = self::blockedCategories($link->business_id);
        if ($blocked) {
            $q->where(function ($w) use ($blocked) {
                $w->whereNull('p.category_id')->orWhereNotIn('p.category_id', $blocked);
            })->where(function ($w) use ($blocked) {
                $w->whereNull('p.sub_category_id')->orWhereNotIn('p.sub_category_id', $blocked);
            });
        }
        if ($only !== null) {
            $q->whereIn('v.id', $only ?: [0]);
        }

        return $q;
    }

    public static function blockedCategories($business_id)
    {
        return DB::table('channel_category_blocks')->where('business_id', $business_id)->pluck('category_id')->map(fn ($x) => (int) $x)->all();
    }

    public static function publishedCount(ChannelLink $link)
    {
        return (int) self::publishedQuery($link)->count();
    }

    /**
     * The Items for the link, keyed by variation id. $only limits it to those variations.
     *
     * @return array<int, array>
     */
    public static function items(ChannelLink $link, array $only = null)
    {
        $rows = self::publishedQuery($link, $only)
            ->leftJoin('product_variations as pv', 'pv.id', '=', 'v.product_variation_id')
            ->leftJoin('variation_location_details as vld', function ($j) use ($link) {
                $j->on('vld.variation_id', '=', 'v.id')->where('vld.location_id', '=', $link->location_id);
            })
            ->select([
                'v.id as variation_id', 'p.id as product_id', 'v.sub_sku', 'p.name', 'p.type', 'pv.name as attr', 'v.name as value',
                'p.product_description', 'p.category_id', 'p.sub_category_id', 'p.image', 'p.unit_id', 'p.sub_unit_ids', 'p.tax',
                'p.enable_stock', 'p.alert_quantity', 'v.sell_price_inc_tax', 'cp.price as own_price', 'cp.unit_id as pub_unit_id',
                'cp.low_stock', DB::raw('COALESCE(vld.qty_available, 0) as qty'),
                DB::raw('GREATEST(p.updated_at, v.updated_at, cp.updated_at, COALESCE(vld.updated_at, p.updated_at)) as changed_at'),
            ])
            ->orderBy('v.id')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $business_id = $link->business_id;
        $vids = $rows->pluck('variation_id')->all();
        $units = DB::table('units')->where('business_id', $business_id)->get()->keyBy('id');
        $cats = DB::table('categories')->where('business_id', $business_id)->pluck('name', 'id');

        $group_id = BusinessLocation::where('id', $link->location_id)->value('selling_price_group_id');
        $group_prices = [];
        if ($group_id) {
            $group_prices = DB::table('variation_group_prices')->where('price_group_id', $group_id)->whereIn('variation_id', $vids)
                ->get()->keyBy('variation_id')->all();
        }
        $unit_prices = DB::table('variation_unit_prices')->whereIn('variation_id', $vids)->get()
            ->groupBy('variation_id')->map(fn ($g) => $g->keyBy('unit_id'))->all();

        [$excluded, $next_expiry] = self::expiryFigures($link, $vids);
        $tz = Business::where('id', $business_id)->value('time_zone') ?: config('app.timezone');

        $items = [];
        foreach ($rows as $r) {
            // Published unit: the owner's choice when it is this product's unit or one of its sub-units.
            $unit_id = (int) $r->unit_id;
            $mult = 1.0;
            if ($r->pub_unit_id && (int) $r->pub_unit_id !== (int) $r->unit_id && isset($units[$r->pub_unit_id])
                && (int) $units[$r->pub_unit_id]->base_unit_id === (int) $r->unit_id && (float) $units[$r->pub_unit_id]->base_unit_multiplier > 0) {
                $unit_id = (int) $r->pub_unit_id;
                $mult = (float) $units[$unit_id]->base_unit_multiplier;
            }
            $unit_name = isset($units[$unit_id]) ? $units[$unit_id]->actual_name : null;

            // Price, tax included, in the published unit.
            $base = (float) $r->sell_price_inc_tax;
            if (isset($group_prices[$r->variation_id]) && (string) $group_prices[$r->variation_id]->price_inc_tax !== '') {
                $gp = $group_prices[$r->variation_id];
                $g = $gp->price_type === 'percentage' ? $base * (float) $gp->price_inc_tax / 100 : (float) $gp->price_inc_tax;
                if ($g > 0) {
                    $base = $g;
                }
            }
            $price = $base * $mult;
            if ($mult != 1.0 && isset($unit_prices[$r->variation_id][$unit_id]) && (float) $unit_prices[$r->variation_id][$unit_id]->price_inc_tax > 0) {
                $price = (float) $unit_prices[$r->variation_id][$unit_id]->price_inc_tax;
            }
            if ($r->type === 'single' && $r->own_price !== null && (float) $r->own_price > 0) {
                $price = (float) $r->own_price;
            }

            $track = (int) $r->enable_stock === 1;
            $qty = null;
            if ($track) {
                $sellable = max(0.0, (float) $r->qty - (float) ($excluded[$r->variation_id] ?? 0));
                $sellable = min($sellable, max(0.0, (float) $r->qty));
                $qty = round($sellable / $mult, 4);
            }
            $threshold = $r->low_stock !== null ? (float) $r->low_stock : ($r->alert_quantity !== null ? (float) $r->alert_quantity / $mult : 0.0);

            $variation_name = null;
            if ($r->type === 'variable') {
                $variation_name = trim(($r->attr ? $r->attr.': ' : '').$r->value);
            }

            $img = self::image($r->image);

            $items[(int) $r->variation_id] = [
                'variation_id' => (int) $r->variation_id,
                'product_id' => (int) $r->product_id,
                'sku' => (string) $r->sub_sku,
                'name' => (string) $r->name,
                'variation_name' => $variation_name,
                'description' => self::plain($r->product_description),
                'category' => $r->category_id && isset($cats[$r->category_id]) ? ['id' => (int) $r->category_id, 'name' => $cats[$r->category_id]] : null,
                'sub_category' => $r->sub_category_id && isset($cats[$r->sub_category_id]) ? ['id' => (int) $r->sub_category_id, 'name' => $cats[$r->sub_category_id]] : null,
                'image_sha1' => $img ? $img['sha1'] : null,
                'unit_id' => $unit_id,
                'unit' => $unit_name,
                'price' => (int) round($price),
                'track_stock' => $track,
                'qty_available' => $qty,
                'low_stock_threshold' => round(max(0.0, $threshold), 4),
                'next_expiry' => $next_expiry[$r->variation_id] ?? null,
                // Web requests write times in the business time zone (Timezone middleware).
                'updated_at' => Carbon::parse($r->changed_at, $tz)->toIso8601String(),
            ];
        }

        return $items;
    }

    /** Hash of what E-Ride shows: a change in it is a change to send. updated_at is left out. */
    public static function hash(array $item)
    {
        unset($item['updated_at']);

        return sha1(json_encode($item));
    }

    /**
     * Per variation: quantity in lots that must not be sold any more, and the earliest expiry
     * among the lots that still can be. Only when the business tracks expiry.
     */
    private static function expiryFigures(ChannelLink $link, array $vids)
    {
        $b = DB::table('business')->where('id', $link->business_id)->first(['enable_product_expiry', 'on_product_expiry', 'stop_selling_before', 'time_zone']);
        if (empty($b) || (int) $b->enable_product_expiry !== 1) {
            return [[], []];
        }
        $today = Carbon::now($b->time_zone ?: config('app.timezone'))->toDateString();
        $cutoff = $today;
        if ($b->on_product_expiry === 'stop_selling' && (int) $b->stop_selling_before > 0) {
            $cutoff = Carbon::parse($today)->addDays((int) $b->stop_selling_before)->toDateString();
        }

        $has_line_loc = \Illuminate\Support\Facades\Schema::hasColumn('purchase_lines', 'location_id');
        $loc = $has_line_loc ? 'COALESCE(pl.location_id, t.location_id)' : 't.location_id';
        $left = '(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned)';

        $rows = DB::table('purchase_lines as pl')
            ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
            ->where('t.business_id', $link->business_id)
            ->whereRaw("$loc = ?", [$link->location_id])
            ->whereIn('pl.variation_id', $vids)
            ->whereNotNull('pl.exp_date')
            ->whereRaw("$left > 0")
            ->groupBy('pl.variation_id')
            ->select([
                'pl.variation_id',
                DB::raw("SUM(CASE WHEN pl.exp_date < '$cutoff' THEN $left ELSE 0 END) as excluded"),
                DB::raw("MIN(CASE WHEN pl.exp_date >= '$cutoff' THEN pl.exp_date END) as next_expiry"),
            ])->get();

        $excluded = [];
        $next = [];
        foreach ($rows as $r) {
            $excluded[$r->variation_id] = (float) $r->excluded;
            $next[$r->variation_id] = $r->next_expiry ? Carbon::parse($r->next_expiry)->toDateString() : null;
        }

        return [$excluded, $next];
    }

    /** Rich-editor HTML -> plain text. */
    public static function plain($html)
    {
        if ($html === null || trim($html) === '') {
            return null;
        }
        $t = preg_replace('/<(br|\/p|\/div|\/li)\b[^>]*>/i', "\n", $html);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t\x{00A0}]+/u", ' ', $t);
        $t = trim(preg_replace("/\s*\n\s*/", "\n", $t));

        return $t === '' ? null : \Illuminate\Support\Str::limit($t, 2000, '…');
    }

    /**
     * The main image, ready to upload: at most 1200 px and 5 MB. Prepared once per file version
     * (path + size + time) and kept in storage/app/channel_images with its sha1.
     *
     * @return array{sha1:string, path:string}|null
     */
    public static function image($file)
    {
        static $memo = [];
        if (empty($file)) {
            return null;
        }
        $src = public_path('uploads/'.config('constants.product_img_path', 'img').'/'.$file);
        if (! is_file($src)) {
            return null;
        }
        $key = md5($src.'|'.filesize($src).'|'.filemtime($src));
        if (isset($memo[$key])) {
            return $memo[$key];
        }
        $dir = storage_path('app/channel_images');
        $side = $dir.'/'.$key.'.json';
        if (is_file($side)) {
            $j = json_decode((string) file_get_contents($side), true);
            if (! empty($j['sha1']) && is_file($j['path'])) {
                return $memo[$key] = $j;
            }
        }
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $out = ['sha1' => sha1_file($src), 'path' => $src];
        $info = @getimagesize($src);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null; // not a picture E-Ride can take
        }
        if (($info[0] > 1200 || $info[1] > 1200 || filesize($src) > 5 * 1024 * 1024) && function_exists('imagecreatetruecolor')) {
            $img = $info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($src) : ($info[2] === IMAGETYPE_WEBP ? @imagecreatefromwebp($src) : @imagecreatefromjpeg($src));
            if ($img) {
                $scale = min(1, 1200 / max($info[0], $info[1]));
                $w = max(1, (int) round($info[0] * $scale));
                $h = max(1, (int) round($info[1] * $scale));
                $dst = imagecreatetruecolor($w, $h);
                imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $w, $h, $info[0], $info[1]);
                $path = $dir.'/'.$key.'.jpg';
                imagejpeg($dst, $path, 85);
                imagedestroy($img);
                imagedestroy($dst);
                $out = ['sha1' => sha1_file($path), 'path' => $path];
            }
        }
        @file_put_contents($side, json_encode($out));

        return $memo[$key] = $out;
    }
}
