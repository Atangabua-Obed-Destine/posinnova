<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** PosInnova gym: one paid period (or card of visits) of a member. */
class GymMembership extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'reminded_before_at' => 'datetime',
        'reminded_after_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(GymPlan::class, 'plan_id');
    }

    public function member()
    {
        return $this->belongsTo(GymMember::class, 'member_id');
    }
}
