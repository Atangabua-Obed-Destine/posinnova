<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova pressing: one step of an order's progress (who moved it, when). */
class PressingEvent extends Model
{
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['created_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
