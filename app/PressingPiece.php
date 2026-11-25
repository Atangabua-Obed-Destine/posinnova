<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova pressing: one piece of an order, with its tag number (1, 2, 3… within the ticket). */
class PressingPiece extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['handed_over' => 'boolean'];

    public function item()
    {
        return $this->belongsTo(PressingItem::class, 'item_id');
    }

    public function service()
    {
        return $this->belongsTo(PressingService::class, 'service_id');
    }
}
