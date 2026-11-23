<?php

namespace App\Http\Controllers;

use App\Utils\Hotel;
use App\Utils\HotelRoomCharge;

/** PosInnova hotel: the guests in the hotel now, for the POS "Charge to room" picker. */
class HotelRoomChargeController extends Controller
{
    public function inHouse()
    {
        $business_id = (int) request()->session()->get('user.business_id');
        if (! auth()->user()->can('sell.create') || ! HotelRoomCharge::enabled($business_id)) {
            abort(403);
        }

        return response()->json(['stays' => HotelRoomCharge::inHouse($business_id)]);
    }
}
