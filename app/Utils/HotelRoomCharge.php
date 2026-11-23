<?php

namespace App\Utils;

use App\HotelFolioLine;
use App\HotelReservation;
use App\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova hotel: "Charge to room". A sale at the hotel's bar or restaurant (any location of the
 * same business) is saved as a credit sale on the guest's contact and put on the stay's bill as a
 * folio line, so check-out collects it with the nights. Also used by E-Ride room service orders
 * (paid_to "room_bill"), which the hotel must allow separately.
 */
class HotelRoomCharge
{
    /** Staff can charge sales to rooms at the POS (hotel setting, on by default). */
    public static function enabled($business_id)
    {
        return Hotel::enabled($business_id) && ! empty(Hotel::settings($business_id)['charge_to_room']);
    }

    /** E-Ride room service may go on the room bill (hotel setting, off by default). */
    public static function erideAllowed($business_id)
    {
        return Hotel::enabled($business_id) && ! empty(Hotel::settings($business_id)['eride_room_bill']);
    }

    /** Guests in the hotel now, for the POS picker. */
    public static function inHouse($business_id)
    {
        return HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')
            ->with(['room:id,number', 'contact:id,name,mobile'])->get()
            ->sortBy(fn ($r) => optional($r->room)->number)
            ->map(fn ($r) => [
                'id' => $r->id, 'ref_no' => $r->ref_no, 'room' => optional($r->room)->number,
                'guest' => optional($r->contact)->name, 'contact_id' => $r->contact_id,
                'departure' => $r->departure->toDateString(),
            ])->values()->all();
    }

    /** Why this stay cannot take the charge (null = it can). */
    public static function refusal($business_id, $reservation_id, $contact_id = null)
    {
        $res = HotelReservation::where('business_id', $business_id)->find($reservation_id);
        if (! $res) {
            return __('hotel.rc_err_none');
        }
        if ($res->status !== 'checked_in') {
            return __('hotel.rc_err_not_in', ['ref' => $res->ref_no]);
        }
        if ($contact_id !== null && (int) $contact_id !== (int) $res->contact_id) {
            return __('hotel.rc_err_guest');
        }

        return null;
    }

    /**
     * After the POS sale is saved (inside its database transaction): a finished, unpaid sale on the
     * guest goes on the stay's bill. Returns the folio line, or null when it was not a room charge.
     */
    public static function attach(Transaction $t, $reservation_id, $user_id)
    {
        if ($t->type !== 'sell' || $t->status !== 'final' || ! empty($t->is_suspend)) {
            return null;
        }
        if (self::refusal($t->business_id, $reservation_id, $t->contact_id) !== null) {
            return null;
        }
        $paid = (float) DB::table('transaction_payments')->where('transaction_id', $t->id)->sum('amount');
        if ($paid > 0.004) {
            return null; // paid at the till: nothing to put on the bill
        }
        $res = HotelReservation::find($reservation_id);
        $line = HotelFolioLine::create([
            'business_id' => $t->business_id, 'reservation_id' => $res->id, 'kind' => 'charge', 'item' => 'room_service',
            'transaction_id' => $t->id, 'amount' => round((float) $t->final_total, 4),
            'description' => mb_substr(__('hotel.item_room_service').' · '.$t->invoice_no, 0, 255), 'created_by' => $user_id,
        ]);
        // money the guest already left with the hotel pays it at once
        (new HotelFolio())->applyCredit($res->fresh(), $user_id);

        return $line;
    }

    /** The stay a sale was charged to, if any. */
    public static function stayOf($transaction_id)
    {
        $rid = HotelFolioLine::where('transaction_id', $transaction_id)->where('item', 'room_service')->whereNull('voided_at')->value('reservation_id');

        return $rid ? HotelReservation::find($rid) : null;
    }
}
