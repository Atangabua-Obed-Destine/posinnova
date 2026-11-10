<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A family visit to a body in the morgue (hospital module).
 */
class MorgueFamilyVisit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'visited_at' => 'datetime',
    ];

    public function morgueRecord()
    {
        return $this->belongsTo(MorgueRecord::class, 'morgue_record_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
