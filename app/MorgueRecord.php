<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A morgue intake record (hospital module).
 */
class MorgueRecord extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'date_of_death' => 'datetime',
        'received_at' => 'datetime',
        'released_at' => 'datetime',
        'legal_hold' => 'boolean',
        'daily_storage_fee' => 'decimal:4',
        'preparation_fee' => 'decimal:4',
        'total_fee_charged' => 'decimal:4',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function slot()
    {
        return $this->belongsTo(MorgueSlot::class, 'morgue_slot_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function certifiedBy()
    {
        return $this->belongsTo(User::class, 'certified_by');
    }

    public function familyVisits()
    {
        return $this->hasMany(MorgueFamilyVisit::class, 'morgue_record_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('morgue_records.business_id', $business_id);
    }

    /**
     * Days stored so far (or until release), minimum 1.
     */
    public function getDaysStoredAttribute()
    {
        $end = $this->released_at ?: now();
        $days = $this->received_at->diffInDays($end);

        return max(1, $days);
    }

    /**
     * Computed total fee = (days stored * daily fee) + preparation fee.
     */
    public function getComputedFeeAttribute()
    {
        return ($this->days_stored * $this->daily_storage_fee) + $this->preparation_fee;
    }
}
