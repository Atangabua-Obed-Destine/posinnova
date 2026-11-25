<?php

namespace App\Utils;

use App\BusinessLocation;
use App\PressingItem;
use App\PressingPrice;
use App\PressingService;
use App\Product;
use App\Unit;
use App\Variation;

/**
 * PosInnova pressing: the non-stock service products behind pressing sales, one per item and
 * service ("Shirt · Wash & iron", SKU PRS-{item}-{service}) and one for the express surcharge, so
 * receipts say what was cleaned and the product reports count pieces. Not sold on the till.
 */
class PressingServices
{
    public static function variation($business_id, PressingItem $item, PressingService $svc, $user_id)
    {
        $name = $item->name.' · '.$svc->name;
        $sku = 'PRS-'.$item->id.'-'.$svc->id;
        $price = PressingPrice::where('item_id', $item->id)->where('service_id', $svc->id)->first();
        $variation = $price && $price->variation_id ? Variation::whereHas('product', fn ($q) => $q->where('business_id', $business_id))->find($price->variation_id) : null;
        if (! $variation) {
            $product = Product::where('business_id', $business_id)->where('sku', $sku)->first();
            $variation = $product ? Variation::where('product_id', $product->id)->first() : self::make($business_id, $name, $sku, (float) optional($price)->price, $user_id);
            if ($price) {
                $price->variation_id = $variation->id;
                $price->save();
            }
        }
        $product = $variation->product;
        if ($product->name !== $name || $product->is_inactive) {
            $product->name = $name;
            $product->is_inactive = 0;
            $product->save();
        }

        return $variation;
    }

    public static function express($business_id, $user_id)
    {
        $product = Product::where('business_id', $business_id)->where('sku', 'PRS-EXPRESS')->first();

        return $product ? Variation::where('product_id', $product->id)->first() : self::make($business_id, __('pressing.product_express'), 'PRS-EXPRESS', 0, $user_id);
    }

    private static function make($business_id, $name, $sku, $price, $user_id)
    {
        $product = Product::create([
            'name' => mb_substr($name, 0, 190), 'business_id' => $business_id, 'type' => 'single',
            'unit_id' => self::serviceUnit($business_id, $user_id), 'tax' => null, 'tax_type' => 'exclusive',
            'enable_stock' => 0, 'sku' => $sku, 'barcode_type' => 'C128', 'created_by' => $user_id, 'not_for_selling' => 1,
        ]);
        $location_ids = BusinessLocation::where('business_id', $business_id)->pluck('id')->toArray();
        if ($location_ids) {
            $product->product_locations()->sync($location_ids);
        }
        $pv = $product->product_variations()->create(['name' => 'DUMMY', 'is_dummy' => 1]);

        return $pv->variations()->create([
            'name' => 'DUMMY', 'product_id' => $product->id, 'sub_sku' => $sku,
            'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0,
            'default_sell_price' => $price, 'sell_price_inc_tax' => $price,
        ]);
    }

    private static function serviceUnit($business_id, $user_id)
    {
        $unit = Unit::where('business_id', $business_id)->whereNull('base_unit_id')
            ->whereIn('actual_name', ['Service', 'Services', __('clinic.service_unit')])->first();
        if (! $unit) {
            $unit = Unit::create(['business_id' => $business_id, 'actual_name' => __('clinic.service_unit'),
                'short_name' => __('clinic.service_unit_short'), 'allow_decimal' => 0, 'created_by' => $user_id]);
        }

        return $unit->id;
    }
}
