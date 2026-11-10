<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One billed month of a plan: its dates, its invoice, and how many visits it
 * covers (null = unlimited). Covered visits point at it via
 * visits.subscription_period_id.
 */
class ClinicSubscriptionPeriod extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:4',
    ];

    public function subscription()
    {
        return $this->belongsTo(ClinicSubscription::class, 'subscription_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function visits()
    {
        return $this->hasMany(Visit::class, 'subscription_period_id');
    }

    /** Visits this period has covered so far (cancelled check-ins do not count). */
    public function visitsUsed()
    {
        return Visit::where('subscription_period_id', $this->id)->where('status', '!=', 'cancelled')->count();
    }
}
