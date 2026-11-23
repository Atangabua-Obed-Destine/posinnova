<?php

namespace App\Utils;

use App\ChannelLink;
use App\ChannelOrder;
use App\ChannelOutbox;
use App\Contact;
use App\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PosInnova online channels: E-Ride orders on the POS screen.
 * POS never writes a sale by itself: the salesperson rings the order up on the normal POS screen.
 * This class shows what is waiting, prepares the cart, and tells E-Ride which sale rang it up.
 */
class ChannelOrders
{
    const CACHE_SECONDS = 15;

    /** The live link of a location, if any. */
    public static function linkFor($business_id, $location_id)
    {
        return ChannelLink::where('business_id', $business_id)->where('location_id', $location_id)->shop()->live()->first();
    }

    /**
     * For the POS screen: paid orders not rung up yet, and orders cancelled after they were rung up.
     * E-Ride is asked at most every 15 seconds per location, however many tills are open.
     */
    public static function pending(ChannelLink $link)
    {
        $key = 'channel_pending_'.$link->id;
        $fresh = Cache::get($key);
        $error = null;
        if ($fresh === null) {
            try {
                // A rung-up that is still waiting to go out leaves first, so the order stops showing.
                ChannelSync::flushOutbox($link);
                $fresh = (new ChannelClient($link))->get('orders/pending', [], 10);
                Cache::put($key, $fresh, self::CACHE_SECONDS);
                ChannelSync::ok($link);
            } catch (ChannelException $e) {
                ChannelSync::fail($link, $e);
                $error = $e->isRevoked() ? 'revoked' : 'offline';
                $fresh = ['orders' => [], 'cancelled' => []];
                Cache::put($key, $fresh, self::CACHE_SECONDS);
            }
        }

        $orders = [];
        $local = ChannelOrder::where('link_id', $link->id)->get()->keyBy('order_id');
        foreach ((array) ($fresh['orders'] ?? []) as $o) {
            $id = (string) ($o['order_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $mine = $local[$id] ?? null;
            if ($mine && $mine->status === 'rung_up') {
                continue; // rung up here; E-Ride will hear it with the next message
            }
            $orders[] = self::summary($o);
        }

        $cancelled = [];
        foreach ((array) ($fresh['cancelled'] ?? []) as $c) {
            $id = (string) ($c['order_id'] ?? '');
            $mine = $local[$id] ?? null;
            if ($mine && $mine->cancel_seen_at) {
                continue;
            }
            $cancelled[] = self::cancelledRow($link, $c, $mine);
        }
        // Rung up here, but E-Ride refused it because the customer had cancelled meanwhile.
        foreach ($local as $mine) {
            if ($mine->status === 'cancelled' && ! $mine->cancel_seen_at && ! collect($cancelled)->contains('order_id', $mine->order_id)) {
                $cancelled[] = self::cancelledRow($link, ['order_id' => $mine->order_id, 'number' => $mine->number, 'pos_sale_id' => $mine->transaction_id, 'reason' => null], $mine);
            }
        }

        return ['orders' => $orders, 'cancelled' => $cancelled, 'error' => $error, 'link_error' => $link->last_error];
    }

    private static function summary(array $o)
    {
        $lines = [];
        foreach ((array) ($o['lines'] ?? []) as $l) {
            $lines[] = [
                'name' => (string) ($l['name'] ?? ''), 'qty' => (float) ($l['qty'] ?? 0), 'unit' => $l['unit'] ?? null,
                'total' => (int) ($l['total'] ?? 0), 'linked' => ! empty($l['variation_id']),
            ];
        }

        return [
            'order_id' => (string) $o['order_id'],
            'number' => (string) ($o['number'] ?? ''),
            'paid_at' => $o['paid_at'] ?? null,
            'customer' => (string) data_get($o, 'customer.name', ''),
            'phone' => (string) data_get($o, 'customer.phone', ''),
            'way' => (string) ($o['way'] ?? ''),
            'total' => (int) ($o['total'] ?? 0),
            'paid_to' => (string) data_get($o, 'payment.paid_to', 'shop'),
            'note' => (string) ($o['note'] ?? ''),
            'lines' => $lines,
        ];
    }

    private static function cancelledRow(ChannelLink $link, array $c, $mine)
    {
        $sale_id = (int) ($c['pos_sale_id'] ?? ($mine ? $mine->transaction_id : 0));
        $t = $sale_id ? Transaction::where('business_id', $link->business_id)->find($sale_id) : null;

        return [
            'order_id' => (string) ($c['order_id'] ?? ''),
            'number' => (string) ($c['number'] ?? ($mine ? $mine->number : '')),
            'reason' => (string) ($c['reason'] ?? ''),
            'sale_id' => $t ? $t->id : null,
            'invoice_no' => $t ? $t->invoice_no : null,
            'sale_deleted' => $sale_id && ! $t,
        ];
    }

    /**
     * "Ring up": a fresh look at one order (no cache), and everything the POS needs to fill the cart.
     *
     * @return array{status:string, cart?:array, message?:string}
     */
    public static function forRingUp(ChannelLink $link, $order_id, $user_id)
    {
        $existing = ChannelOrder::where('order_id', $order_id)->where('status', 'rung_up')->first();
        if ($existing) {
            $inv = Transaction::where('id', $existing->transaction_id)->value('invoice_no');

            return ['status' => 'rung_up', 'invoice_no' => $inv, 'sale_id' => $existing->transaction_id];
        }

        $o = (new ChannelClient($link))->get('orders/'.rawurlencode($order_id), [], 10);
        ChannelSync::ok($link);
        $status = (string) ($o['status'] ?? 'paid');
        if (! in_array($status, ['paid', 'confirmed'], true)) { // a room-bill order is confirmed, not paid
            Cache::forget('channel_pending_'.$link->id);

            return ['status' => $status];
        }

        $business_id = $link->business_id;
        $paid_to = in_array(data_get($o, 'payment.paid_to'), ['eride', 'room_bill'], true) ? data_get($o, 'payment.paid_to') : 'shop';
        $name = trim((string) data_get($o, 'customer.name', ''));
        $way = (string) ($o['way'] ?? '');
        $room = (array) ($o['room'] ?? []);
        $room_no = trim((string) ($room['number'] ?? ''));
        $hotel = trim((string) ($room['hotel'] ?? ''));
        $phone = trim((string) data_get($o, 'customer.phone', ''));
        $address = trim((string) data_get($o, 'customer.address', ''));
        $number = (string) ($o['number'] ?? '');

        // The contact: E-Ride's own account when E-Ride holds the money; otherwise the customer by phone, or walk-in.
        $contact = null;
        if ($paid_to === 'eride') {
            $contact = self::erideContact($link, $user_id);
        } elseif ($phone !== '') {
            $contact = self::contactByPhone($business_id, $phone);
        }
        // Room service on the room bill: the stay must be in the hotel, in this business, and the hotel must allow it.
        $room_charge = null;
        $room_charge_error = null;
        if ($paid_to === 'room_bill') {
            $res = ! empty($room['hotel_pos_ref']) ? \App\HotelReservation::where('business_id', $business_id)->where('ref_no', (string) $room['hotel_pos_ref'])->first() : null;
            if (! $res) {
                $room_charge_error = __('hotel.rc_err_other_business');
            } elseif (! HotelRoomCharge::erideAllowed($business_id)) {
                $room_charge_error = __('hotel.rc_err_eride_off');
            } elseif ($why = HotelRoomCharge::refusal($business_id, $res->id)) {
                $room_charge_error = $why;
            } else {
                $res->loadMissing(['room', 'contact']);
                $room_charge = ['reservation_id' => $res->id, 'room' => optional($res->room)->number ?: $room_no, 'guest' => optional($res->contact)->name];
                $contact = $res->contact;
            }
        }
        if (! $contact) {
            $contact = Contact::where('business_id', $business_id)->where('type', 'customer')->where('is_default', 1)->first();
        }

        // Lines: only products of this business that this location sells. The rest are shown, not added.
        $lines = [];
        $missing = [];
        foreach ((array) ($o['lines'] ?? []) as $l) {
            $vid = (int) ($l['variation_id'] ?? 0);
            $v = $vid ? DB::table('variations as v')->join('products as p', 'p.id', '=', 'v.product_id')
                ->join('product_locations as pl', function ($j) use ($link) {
                    $j->on('pl.product_id', '=', 'p.id')->where('pl.location_id', '=', $link->location_id);
                })
                ->where('p.business_id', $business_id)->whereNull('v.deleted_at')->where('v.id', $vid)
                ->first(['v.id', 'p.unit_id']) : null;
            if (! $v) {
                $missing[] = ['name' => (string) ($l['name'] ?? ''), 'qty' => (float) ($l['qty'] ?? 0), 'total' => (int) ($l['total'] ?? 0)];

                continue;
            }
            $unit_id = (int) ($l['unit_id'] ?? 0);
            $lines[] = [
                'variation_id' => (int) $v->id,
                'quantity' => (float) ($l['qty'] ?? 1),
                'sub_unit_id' => ($unit_id && $unit_id !== (int) $v->unit_id) ? $unit_id : null,
                'unit_price_inc_tax' => (float) ($l['unit_price'] ?? 0),
                'total' => (int) ($l['total'] ?? 0),
                'name' => (string) ($l['name'] ?? ''),
            ];
        }

        $who = trim(implode(' · ', array_filter([$name, $phone])));
        $tag = 'E-Ride #'.$number;
        $to_room = $way === 'room' && $room_no !== '';
        $collected = $paid_to === 'shop' && ! $to_room && ! empty($o['delivery_fee_collected_by_shop']);
        $room_text = $to_room ? trim(__('hotel.rc_room_n', ['room' => $room_no]).($hotel !== '' ? ' · '.$hotel : '')) : '';
        $note = $tag.($to_room ? ' · '.__('hotel.rc_room_n', ['room' => $room_no]) : '').($who !== '' ? ' · '.$who : '');
        if ($address !== '') {
            $note .= "\n".$address;
        }
        if (trim((string) ($o['note'] ?? '')) !== '') {
            $note .= "\n".__('channel.pos_customer_note').': '.trim((string) $o['note']);
        }

        return ['status' => 'paid', 'cart' => [
            'order_id' => (string) $o['order_id'],
            'number' => $number,
            'paid_to' => $paid_to,
            'contact_id' => $contact ? $contact->id : null,
            'contact_text' => $contact ? ($contact->name ?: $contact->supplier_business_name) : null,
            'lines' => $lines,
            'missing' => $missing,
            'discount' => (int) ($o['discount'] ?? 0),
            'shipping_charges' => $collected ? (int) ($o['delivery_fee'] ?? 0) : 0,
            'delivered_to' => $name,
            'shipping_address' => $to_room ? $room_text : trim($phone.($address !== '' ? "\n".$address : '')),
            'shipping_details' => $to_room ? $tag.' · '.__('hotel.rc_room_service') : $tag,
            'room_charge' => $room_charge,
            'room_charge_error' => $room_charge_error,
            'shipping_status' => 'ordered',
            'sale_note' => $note,
            'pay_method' => $paid_to === 'shop' || ($paid_to === 'room_bill' && ! $room_charge) ? ($link->pay_method ?: null) : null,
            'total' => (int) ($o['total'] ?? 0),
            // What the POS total should be with the lines put in the cart (lines not added are listed apart).
            'expected_total' => (int) array_sum(array_column($lines, 'total')) - (int) ($o['discount'] ?? 0) + ($collected ? (int) ($o['delivery_fee'] ?? 0) : 0),
        ]];
    }

    public static function contactByPhone($business_id, $phone)
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) < 8) {
            return null;
        }
        $tail = substr($digits, -9);

        return Contact::where('business_id', $business_id)->whereIn('type', ['customer', 'both'])
            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(mobile,''),' ',''),'+',''),'-',''),'.','') LIKE ?", ['%'.$tail])
            ->orderByDesc('id')->first();
    }

    /** The business's "E-Ride" contact: what E-Ride owes the shop for orders it collected. Made once, when first needed. */
    public static function erideContact(ChannelLink $link, $user_id)
    {
        $c = $link->credit_contact_id ? Contact::where('business_id', $link->business_id)->find($link->credit_contact_id) : null;
        if (! $c) {
            $c = Contact::where('business_id', $link->business_id)->whereIn('type', ['customer', 'both'])->where('name', 'E-Ride')->first();
        }
        if (! $c) {
            $c = (new ContactUtil())->createNewContact([
                'business_id' => $link->business_id, 'type' => 'customer', 'name' => 'E-Ride', 'first_name' => 'E-Ride',
                'created_by' => $user_id,
            ])['data'];
        }
        ChannelLink::where('business_id', $link->business_id)->whereNull('credit_contact_id')->update(['credit_contact_id' => $c->id]);
        $link->credit_contact_id = $c->id;

        return $c;
    }

    /**
     * After a POS sale is saved with an E-Ride order on it (SellPosController@store).
     * Only a finished sale counts; a draft, quotation or suspended sale is not a ring-up.
     *
     * @return array{ok:bool, message?:string}
     */
    public static function afterSale(Transaction $t, $order_id)
    {
        $order_id = trim((string) $order_id);
        if ($order_id === '' || $t->type !== 'sell' || $t->status !== 'final' || ! empty($t->is_suspend)) {
            return ['ok' => false];
        }
        $link = self::linkFor($t->business_id, $t->location_id);
        if (! $link) {
            return ['ok' => false];
        }
        $row = ChannelOrder::where('order_id', $order_id)->first();
        if ($row && $row->status === 'rung_up' && (int) $row->transaction_id !== (int) $t->id) {
            $inv = Transaction::where('id', $row->transaction_id)->value('invoice_no');

            return ['ok' => false, 'message' => __('channel.pos_already_rung', ['invoice' => $inv])];
        }
        $number = trim((string) request()->input('channel_order_number', ''));
        $row = ChannelOrder::updateOrCreate(['order_id' => $order_id], [
            'link_id' => $link->id, 'business_id' => $t->business_id, 'number' => $number !== '' ? Str::limit($number, 40, '') : ($row->number ?? null),
            'transaction_id' => $t->id, 'status' => 'rung_up', 'paid_to' => in_array(request()->input('channel_paid_to'), ['eride', 'room_bill'], true) ? request()->input('channel_paid_to') : 'shop',
            'rung_up_at' => now(), 'matches' => null, 'cancel_seen_at' => null,
        ]);
        Cache::forget('channel_pending_'.$link->id);
        $m = ChannelSync::queue($link, 'rung_up', $order_id, 'orders/'.rawurlencode($order_id).'/rung-up', self::rungUpBody($t));

        return ['ok' => true, 'outbox_id' => $m->id, 'link' => $link];
    }

    /** Send it now (after the sale is committed); when the line is down the scheduler retries. */
    public static function sendNow(ChannelLink $link, $outbox_id)
    {
        try {
            $client = new ChannelClient($link);
            $client->cap = 6;
            ChannelSync::flushOutbox($link, $client, $outbox_id);
        } catch (\Throwable $e) {
            Log::info('channel: rung-up kept for later: '.$e->getMessage());
        }
        $m = ChannelOutbox::find($outbox_id);
        $row = $m ? ChannelOrder::where('order_id', $m->ref)->first() : null;

        return ['delivered' => $m && $m->done_at && ! $m->last_error, 'cancelled' => $row && $row->status === 'cancelled', 'matches' => $row ? $row->matches : null];
    }

    /** A sale that rang an order up was edited: E-Ride gets the new figures (same pos_sale_id). */
    public static function afterUpdate(Transaction $t)
    {
        $row = ChannelOrder::where('transaction_id', $t->id)->where('status', 'rung_up')->first();
        if (! $row) {
            return;
        }
        $link = ChannelLink::find($row->link_id);
        if ($link && $link->isLive()) {
            ChannelSync::queue($link, 'rung_up', $row->order_id, 'orders/'.rawurlencode($row->order_id).'/rung-up', self::rungUpBody($t->fresh()));
        }
    }

    /** A sale that rang an order up was deleted: the order is "not yet rung up" again. */
    public static function afterDelete($business_id, $transaction_id)
    {
        $row = ChannelOrder::where('business_id', $business_id)->where('transaction_id', $transaction_id)->where('status', 'rung_up')->first();
        if (! $row) {
            return;
        }
        $row->forceFill(['status' => 'unrung'])->save();
        $link = ChannelLink::find($row->link_id);
        if ($link) {
            ChannelSync::queue($link, 'unrung', $row->order_id, 'orders/'.rawurlencode($row->order_id).'/unrung', ['pos_sale_id' => (int) $transaction_id, 'reason' => 'sale deleted']);
            Cache::forget('channel_pending_'.$link->id);
        }
    }

    /** The salesperson saw that a rung-up order was cancelled. */
    public static function cancelSeen(ChannelLink $link, $order_id, $number = null)
    {
        $row = ChannelOrder::where('order_id', $order_id)->first();
        if ($row) {
            $row->forceFill(['cancel_seen_at' => now()])->save();
        } else {
            ChannelOrder::create(['order_id' => $order_id, 'link_id' => $link->id, 'business_id' => $link->business_id,
                'number' => $number, 'status' => 'unrung', 'cancel_seen_at' => now()]);
        }
        Cache::forget('channel_pending_'.$link->id);
        $m = ChannelSync::queue($link, 'cancel_seen', $order_id, 'orders/'.rawurlencode($order_id).'/cancel-seen', ['seen_at' => ChannelSync::now($link)->toIso8601String()]);
        try {
            ChannelSync::flushOutbox($link, null, $m->id);
        } catch (\Throwable $e) {
            // kept for the scheduler
        }
    }

    public static function rungUpBody(Transaction $t)
    {
        $t->loadMissing(['sell_lines', 'sales_person']);
        $by_v = [];
        foreach ($t->sell_lines as $sl) {
            if (! empty($sl->parent_sell_line_id)) {
                continue;
            }
            $mult = 1;
            if (! empty($sl->sub_unit_id)) {
                $mult = (float) (DB::table('units')->where('id', $sl->sub_unit_id)->value('base_unit_multiplier') ?: 1);
            }
            $qty = (float) $sl->quantity - (float) ($sl->quantity_returned ?? 0);
            $vid = (int) $sl->variation_id;
            $by_v[$vid] = $by_v[$vid] ?? ['variation_id' => $vid, 'qty' => 0, 'total' => 0];
            $by_v[$vid]['qty'] += $mult > 0 ? $qty / $mult : $qty;
            $by_v[$vid]['total'] += $qty * (float) $sl->unit_price_inc_tax;
        }
        $lines = array_map(fn ($l) => ['variation_id' => $l['variation_id'], 'qty' => round($l['qty'], 4), 'total' => (int) round($l['total'])], array_values($by_v));
        $tz = DB::table('business')->where('id', $t->business_id)->value('time_zone') ?: config('app.timezone');
        $by = $t->sales_person ? trim((string) $t->sales_person->first_name) : '';

        $extra = [];
        if (ChannelOrder::where('transaction_id', $t->id)->value('paid_to') === 'room_bill') {
            $extra['charged_to_room'] = HotelRoomCharge::stayOf($t->id) !== null;
        }

        return $extra + [
            'pos_sale_id' => (int) $t->id,
            'invoice_no' => (string) $t->invoice_no,
            'rung_up_at' => Carbon::parse($t->created_at ?: now(), $tz)->toIso8601String(),
            'by' => $by !== '' ? $by : null,
            'sale_total' => (int) round((float) $t->final_total),
            'lines' => $lines,
        ];
    }

    /** E-Ride took a message: remember what it said (rung-up: matches). */
    public static function delivered(ChannelOutbox $m, $answer)
    {
        if ($m->kind === 'rung_up' && is_array($answer)) {
            ChannelOrder::where('order_id', $m->ref)->update(['matches' => array_key_exists('matches', $answer) ? (bool) $answer['matches'] : null]);
        }
    }

    /** E-Ride refused a message for good (409 etc.). */
    public static function refused(ChannelOutbox $m, ChannelException $e)
    {
        if ($m->kind !== 'rung_up') {
            return;
        }
        if ($e->errorCode === 'order_cancelled') {
            // The customer cancelled while the sale was being rung up: the POS shows it to be voided.
            ChannelOrder::where('order_id', $m->ref)->update(['status' => 'cancelled']);
        }
        if ($e->errorCode === 'already_rung_up') {
            ChannelOrder::where('order_id', $m->ref)->update(['status' => 'duplicate']);
        }
        Log::warning('channel: rung-up refused for order '.$m->ref.': '.$e->errorCode);
    }
}
