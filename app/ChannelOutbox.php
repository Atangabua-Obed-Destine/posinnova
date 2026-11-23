<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova online channels: a message for E-Ride, retried until it arrives. */
class ChannelOutbox extends Model
{
    protected $table = 'channel_outbox';

    protected $guarded = ['id'];

    protected $casts = ['body' => 'array', 'next_try_at' => 'datetime', 'done_at' => 'datetime'];
}
