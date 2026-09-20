<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A theatre booking / operative record (hospital module).
 */
class TheatreBooking extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'fee' => 'decimal:4',
        'anaesthesia_fee' => 'decimal:4',
        'fee_charged' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class, 'procedure_id');
    }

    public function surgeon()
    {
        return $this->belongsTo(User::class, 'surgeon_id');
    }

    public function anaesthetist()
    {
        return $this->belongsTo(User::class, 'anaesthetist_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('theatre_bookings.business_id', $business_id);
    }

    /**
     * Total billable amount = procedure fee + anaesthesia fee.
     */
    public function getTotalFeeAttribute()
    {
        return $this->fee + $this->anaesthesia_fee;
    }
}
