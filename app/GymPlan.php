<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova gym: a plan or pass. kind: day (a day pass) | months (a dated period) | visits (a card of N visits). */
class GymPlan extends Model
{
    const KINDS = ['day', 'months', 'visits'];

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
