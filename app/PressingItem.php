<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova pressing: a type of item on the price list (shirt, suit, bedsheet…). */
class PressingItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function prices()
    {
        return $this->hasMany(PressingPrice::class, 'item_id');
    }
}
