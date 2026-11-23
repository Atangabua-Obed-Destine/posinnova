<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova online channels: an E-Ride order and the POS sale that rang it up. */
class ChannelOrder extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['rung_up_at' => 'datetime', 'cancel_seen_at' => 'datetime', 'matches' => 'boolean'];

    public function link()
    {
        return $this->belongsTo(ChannelLink::class, 'link_id');
    }
}
