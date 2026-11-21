<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova hotel: one closed hotel day, with its daily report in summary (JSON). */
class HotelNightAudit extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['audit_date' => 'date', 'ran_at' => 'datetime', 'summary' => 'array'];
}
