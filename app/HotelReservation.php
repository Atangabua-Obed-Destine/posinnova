<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova hotel: a reservation, which becomes the stay once checked in.
 * status: tentative | confirmed | checked_in | checked_out | cancelled | no_show
 */
class HotelReservation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'arrival' => 'date', 'departure' => 'date', 'hold_until' => 'datetime',
        'checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'cancelled_at' => 'datetime',
    ];

    /** Statuses that hold a room for their nights */
    const HOLDING = ['tentative', 'confirmed', 'checked_in'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function group()
    {
        return $this->belongsTo(HotelGroup::class, 'group_id');
    }

    /** Who sleeps there: the name given for the room, else the guest (payer). */
    public function sleeperName()
    {
        return $this->occupant ?: optional($this->contact)->name;
    }

    public function roomType()
    {
        return $this->belongsTo(HotelRoomType::class, 'room_type_id');
    }

    public function room()
    {
        return $this->belongsTo(HotelRoom::class, 'room_id');
    }

    public function guests()
    {
        return $this->hasMany(HotelGuest::class, 'reservation_id')->orderByDesc('is_primary')->orderBy('id');
    }

    public function folioLines()
    {
        return $this->hasMany(HotelFolioLine::class, 'reservation_id');
    }

    public function nights()
    {
        return max(1, $this->arrival->diffInDays($this->departure));
    }

    public function isOpen()
    {
        return in_array($this->status, self::HOLDING, true);
    }
}
