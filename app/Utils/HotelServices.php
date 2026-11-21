<?php

namespace App\Utils;

use App\BusinessLocation;
use App\HotelRoomType;
use App\Product;
use App\Unit;
use App\Variation;

/**
 * PosInnova hotel: the non-stock service products behind hotel invoices, so
 * receipts say what was charged and product reports count room nights by type.
 * One per room type ("Room – Standard, night") and one per extra (laundry…).
 * Like App\Utils\ClinicServices: enable_stock = 0, not for selling on the till.
 */
class HotelServices
{
    /** The service variation of a room type (created, or renamed / repriced to match the type). */
    public static function roomNight(HotelRoomType $type, $user_id)
    {
        $name = __('hotel.product_room_night', ['type' => $type->name]);
        $variation = $type->variation_id ? Variation::whereHas('product', fn ($q) => $q->where('business_id', $type->business_id))->find($type->variation_id) : null;

        if ($variation) {
            $product = $variation->product;
            if ($product->name !== $name || $product->is_inactive) {
                $product->name = $name;
                $product->is_inactive = 0;
                $product->save();
            }
            if ((float) $variation->default_sell_price != (float) $type->rate_room_only) {
                $variation->default_sell_price = $type->rate_room_only;
                $variation->sell_price_inc_tax = $type->rate_room_only;
                $variation->save();
            }
        } else {
            $variation = self::make($type->business_id, $name, 'HTL-ROOM-'.$type->id, (float) $type->rate_room_only, $user_id);
            $type->product_id = $variation->product_id;
            $type->variation_id = $variation->id;
            $type->save();
        }

        return $variation;
    }

    /** The service variation of an extra: laundry, minibar, extra_bed, phone, damage, other, late_checkout, tourist_tax. */
    public static function extra($business_id, $key, $user_id)
    {
        $sku = 'HTL-'.strtoupper(str_replace('_', '-', $key));
        $product = Product::where('business_id', $business_id)->where('sku', $sku)->first();
        if ($product) {
            return Variation::where('product_id', $product->id)->first();
        }

        return self::make($business_id, __('hotel.product_'.$key), $sku, 0, $user_id);
    }

    private static function make($business_id, $name, $sku, $price, $user_id)
    {
        $product = Product::create([
            'name' => $name,
            'business_id' => $business_id,
            'type' => 'single',
            'unit_id' => self::serviceUnit($business_id, $user_id),
            'tax' => null,
            'tax_type' => 'exclusive',
            'enable_stock' => 0,
            'sku' => $sku,
            'barcode_type' => 'C128',
            'created_by' => $user_id,
            'not_for_selling' => 1,
        ]);
        $location_ids = BusinessLocation::where('business_id', $business_id)->pluck('id')->toArray();
        if (! empty($location_ids)) {
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
