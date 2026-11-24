<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova hotel: a group booking. Several reservations (one per room) paid by one contact.
 * pays: payer (rooms may leave owing; the last room to leave settles the group) | each (every room settles its own).
 */
class HotelGroup extends Model
{
    protected $guarded = ['id'];

    const PAYS = ['payer', 'each'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function reservations()
    {
        return $this->hasMany(HotelReservation::class, 'group_id');
    }
}
