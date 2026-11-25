<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova pressing: a service on the price list (wash & iron, dry cleaning, iron only…). */
class PressingService extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];
}
