<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A storage slot in the morgue (hospital module).
 */
class MorgueSlot extends Model
{
    protected $guarded = ['id'];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function currentRecord()
    {
        return $this->hasOne(MorgueRecord::class, 'morgue_slot_id')->where('status', 'in_storage');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('morgue_slots.business_id', $business_id);
    }
}
