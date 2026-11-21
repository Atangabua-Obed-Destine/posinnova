<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova hotel: a kind of room (Standard, Deluxe…) and its prices per night. */
class HotelRoomType extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    const PLANS = ['room_only', 'bb', 'corporate'];

    public function rooms()
    {
        return $this->hasMany(HotelRoom::class, 'room_type_id');
    }

    /** The price per night for a rate plan; plans without a price fall back to room only. */
    public function rateFor($plan)
    {
        $col = ['room_only' => 'rate_room_only', 'bb' => 'rate_bb', 'corporate' => 'rate_corporate'][$plan] ?? 'rate_room_only';
        $v = $this->{$col};

        return $v === null ? (float) $this->rate_room_only : (float) $v;
    }

    /** The plans this type offers (room only always). */
    public function plans()
    {
        return array_values(array_filter(self::PLANS, fn ($p) => $p === 'room_only' || $this->{'rate_'.$p} !== null));
    }
}
