<?php

namespace App\Utils;

use App\BusinessLocation;
use App\ClinicPackage;
use App\Product;
use App\Unit;
use App\Variation;

/**
 * PosInnova clinic: the service products behind hospital invoices.
 *
 * Every hospital charge used to be a sell with no lines, so its receipt printed
 * an empty table and product reports never saw consultations or tests. Each
 * charge now carries real lines on non-stock, not-for-sale service products:
 *
 *  - one product per consultation package (SKU SVC-PKG-<id>), kept in step with
 *    the package name and price;
 *  - one generic product per charge type (SKU SVC-LAB, SVC-IMAGING, ...), whose
 *    line note says what was done (the test name, the study, ...).
 *
 * Products are created on first use, so nothing needs seeding. enable_stock = 0
 * means sell lines on them never touch stock or purchase mapping.
 */
class ClinicServices
{
    const GENERIC_SKU = [
        'consultation' => 'SVC-CONSULT',
        'lab' => 'SVC-LAB',
        'imaging' => 'SVC-IMAGING',
        'pharmacy' => 'SVC-PHARMACY',
        'ward_dispense' => 'SVC-WARD',
        'procedure' => 'SVC-PROCEDURE',
        'registration' => 'SVC-REGISTRATION',
        'anc' => 'SVC-ANC',
        'morgue' => 'SVC-MORGUE',
        'deposit' => 'SVC-DEPOSIT',
        'insurance' => 'SVC-INSURER',
        'other' => 'SVC-OTHER',
    ];

    /**
     * The generic service variation for a charge type.
     */
    public static function generic($business_id, $charge_type, $user_id)
    {
        $charge_type = isset(self::GENERIC_SKU[$charge_type]) ? $charge_type : 'other';
        $sku = self::GENERIC_SKU[$charge_type];

        $variation = self::findBySku($business_id, $sku);
        if ($variation) {
            return $variation;
        }

        return self::make($business_id, __('clinic.service_'.$charge_type), $sku, 0, $user_id, null);
    }

    /**
     * The service variation behind a package, created or renamed/repriced as
     * needed. Stores product_id / variation_id on the package.
     */
    public static function forPackage(ClinicPackage $package, $user_id)
    {
        $name = __('clinic.package_product_name', ['name' => $package->name]);
        $variation = null;

        if (! empty($package->variation_id)) {
            $variation = Variation::where('id', $package->variation_id)
                ->whereHas('product', function ($q) use ($package) {
                    $q->where('business_id', $package->business_id);
                })->first();
        }

        if ($variation) {
            $product = $variation->product;
            $product->name = $name;
            $product->product_description = $package->description;
            $product->is_inactive = 0;
            $product->save();
            $variation->default_sell_price = $package->price;
            $variation->sell_price_inc_tax = $package->price;
            $variation->save();
        } else {
            $variation = self::make($package->business_id, $name, 'SVC-PKG-'.$package->id, $package->price, $user_id, $package->description);
        }

        if ($package->product_id != $variation->product_id || $package->variation_id != $variation->id) {
            $package->product_id = $variation->product_id;
            $package->variation_id = $variation->id;
            $package->save();
        }

        return $variation;
    }

    /** Hide the product of a package that was removed (old invoices keep it). */
    public static function retirePackage(ClinicPackage $package)
    {
        if (! empty($package->product_id)) {
            Product::where('business_id', $package->business_id)
                ->where('id', $package->product_id)
                ->update(['is_inactive' => 1]);
        }
    }

    private static function findBySku($business_id, $sku)
    {
        $product = Product::where('business_id', $business_id)->where('sku', $sku)->first();
        if (! $product) {
            return null;
        }

        return Variation::where('product_id', $product->id)->first();
    }

    private static function make($business_id, $name, $sku, $price, $user_id, $description)
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
            'product_description' => $description,
        ]);

        $location_ids = BusinessLocation::where('business_id', $business_id)->pluck('id')->toArray();
        if (! empty($location_ids)) {
            $product->product_locations()->sync($location_ids);
        }

        $product_variation = $product->product_variations()->create(['name' => 'DUMMY', 'is_dummy' => 1]);

        return $product_variation->variations()->create([
            'name' => 'DUMMY',
            'product_id' => $product->id,
            'sub_sku' => $sku,
            'default_purchase_price' => 0,
            'dpp_inc_tax' => 0,
            'profit_percent' => 0,
            'default_sell_price' => $price,
            'sell_price_inc_tax' => $price,
        ]);
    }

    /** A "Service" unit, so receipts that print the unit never meet a null. */
    private static function serviceUnit($business_id, $user_id)
    {
        $unit = Unit::where('business_id', $business_id)
            ->whereNull('base_unit_id')
            ->whereIn('actual_name', ['Service', 'Services', __('clinic.service_unit')])
            ->first();

        if (! $unit) {
            $unit = Unit::create([
                'business_id' => $business_id,
                'actual_name' => __('clinic.service_unit'),
                'short_name' => __('clinic.service_unit_short'),
                'allow_decimal' => 0,
                'created_by' => $user_id,
            ]);
        }

        return $unit->id;
    }
}
