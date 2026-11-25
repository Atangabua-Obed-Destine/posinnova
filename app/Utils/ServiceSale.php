<?php

namespace App\Utils;

use App\BusinessLocation;
use App\Events\TransactionPaymentAdded;
use App\Product;
use App\Transaction;
use App\TransactionPayment;
use App\Unit;
use App\Variation;

/**
 * PosInnova: selling a service from a module screen (gym plans and passes) as an ordinary final
 * sale, and taking a payment on it the way Add payment does (into the cashier's open register).
 * The products are non-stock services, not sold on the till; the ledger posts them to 706.
 */
class ServiceSale
{
    /** A non-stock service product by SKU (made once, renamed and repriced to match). */
    public static function product($business_id, $sku, $name, $price, $user_id)
    {
        $product = Product::where('business_id', $business_id)->where('sku', $sku)->first();
        if ($product) {
            if ($product->name !== $name || $product->is_inactive) {
                $product->name = mb_substr($name, 0, 190);
                $product->is_inactive = 0;
                $product->save();
            }
            $v = Variation::where('product_id', $product->id)->first();
            if ($v && (float) $v->default_sell_price != (float) $price) {
                $v->default_sell_price = $price;
                $v->sell_price_inc_tax = $price;
                $v->save();
            }

            return $v;
        }
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

    /** A final sale of one service line. */
    public static function sell($business_id, $location_id, $contact_id, Variation $v, $price, $note, $user_id)
    {
        $tu = new TransactionUtil();
        $tx = $tu->createSellTransaction($business_id, [
            'type' => 'sell', 'status' => 'final', 'contact_id' => $contact_id, 'location_id' => $location_id,
            'transaction_date' => now()->toDateTimeString(), 'discount_amount' => 0, 'final_total' => $price, 'sale_note' => $note,
        ], ['total_before_tax' => $price, 'tax' => 0, 'discount' => 0], $user_id, false);
        $tu->createOrUpdateSellLines($tx, [[
            'product_id' => $v->product_id, 'variation_id' => $v->id, 'quantity' => 1, 'unit_price' => $price, 'unit_price_inc_tax' => $price,
            'item_tax' => 0, 'tax_id' => null, 'line_discount_type' => null, 'line_discount_amount' => 0, 'sell_line_note' => mb_substr((string) $note, 0, 180),
        ]], $location_id, false, null, [], false);
        $tu->updatePaymentStatus($tx->id, $tx->final_total);

        return $tx->fresh();
    }

    /** A payment on a sale, with the location's default account for the method, into the open register. */
    public static function pay(Transaction $t, $amount, $method, $user_id, $note = null)
    {
        $tu = new TransactionUtil();
        $loc = BusinessLocation::find($t->location_id);
        $defaults = $loc ? (json_decode((string) $loc->default_payment_accounts, true) ?: []) : [];
        $account = ! empty($defaults[$method]['is_enabled']) && ! empty($defaults[$method]['account']) ? (int) $defaults[$method]['account'] : null;
        $inputs = ['amount' => $amount, 'method' => $method, 'note' => $note, 'paid_on' => now()->toDateTimeString(),
            'transaction_id' => $t->id, 'created_by' => $user_id, 'payment_for' => $t->contact_id, 'business_id' => $t->business_id,
            'account_id' => $account,
            'payment_ref_no' => $tu->generateReferenceNumber('sell_payment', $tu->setAndGetReferenceCount('sell_payment', $t->business_id), $t->business_id)];
        $tp = TransactionPayment::create($inputs);
        $inputs['transaction_type'] = 'sell';
        event(new TransactionPaymentAdded($tp, $inputs));
        $tu->updatePaymentStatus($t->id, $t->final_total);
        ClinicCashier::postToRegister($user_id, $t->id, $method, $amount);

        return $tp;
    }

    public static function validMethod($business_id, $method)
    {
        return $method !== 'advance' && array_key_exists((string) $method, (new Util())->payment_types(null, false, $business_id));
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
