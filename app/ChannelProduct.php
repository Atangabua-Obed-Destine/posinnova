<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova online channels: the owner's E-Ride choice for one product. */
class ChannelProduct extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['publish' => 'boolean', 'never' => 'boolean'];
}
