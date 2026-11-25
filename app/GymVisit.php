<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova gym: one entry at the door (a member, a day pass or a hotel guest). */
class GymVisit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['visited_at' => 'datetime'];

    public function member()
    {
        return $this->belongsTo(GymMember::class, 'member_id');
    }
}
