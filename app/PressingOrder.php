<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova pressing: one drop-off (a ticket). Its money is an ordinary sale (transaction_id);
 * its pieces carry the tag numbers. status: received | washing | ironing | ready | collected | cancelled.
 */
class PressingOrder extends Model
{
    const STAGES = ['received', 'washing', 'ironing', 'ready', 'collected'];

    protected $guarded = ['id'];

    protected $casts = [
        'express' => 'boolean',
        'whatsapp' => 'boolean',
        'on_room_bill' => 'boolean',
        'received_at' => 'datetime',
        'ready_at' => 'datetime',
        'ready_marked_at' => 'datetime',
        'reminded_at' => 'datetime',
        'collected_at' => 'datetime',
    ];

    public function pieces()
    {
        return $this->hasMany(PressingPiece::class, 'order_id')->orderBy('tag');
    }

    public function events()
    {
        return $this->hasMany(PressingEvent::class, 'order_id')->orderBy('id');
    }

    public function problems()
    {
        return $this->hasMany(PressingProblem::class, 'order_id')->orderBy('id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function reservation()
    {
        return $this->belongsTo(HotelReservation::class, 'reservation_id');
    }

    /** Still in the shop (not collected, not cancelled). */
    public function isOpen()
    {
        return ! in_array($this->status, ['collected', 'cancelled'], true);
    }

    /** Past its ready date and not ready yet. */
    public function isLate()
    {
        return in_array($this->status, ['received', 'washing', 'ironing'], true) && $this->ready_at && $this->ready_at->isPast();
    }
}
