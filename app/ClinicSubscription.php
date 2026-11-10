<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A patient on a monthly plan (a monthly ClinicPackage). Billing is done by
 * App\Utils\ClinicPlans: one ClinicSubscriptionPeriod (and one invoice) per
 * month, from next_bill_date.
 */
class ClinicSubscription extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'next_bill_date' => 'date',
        'cancelled_at' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function package()
    {
        return $this->belongsTo(ClinicPackage::class, 'package_id')->withTrashed();
    }

    public function periods()
    {
        return $this->hasMany(ClinicSubscriptionPeriod::class, 'subscription_id')->orderByDesc('period_start');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy()
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_subscriptions.business_id', $business_id);
    }

    public function scopeActive($query)
    {
        return $query->where('clinic_subscriptions.status', 'active');
    }
}
