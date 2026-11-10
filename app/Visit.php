<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A patient encounter (hospital module). Container for consultations, lab
 * requests, pharmacy dispenses and admissions during one attendance.
 */
class Visit extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    /** The appointment this check-in came from (clinic appointments). */
    public function appointment()
    {
        return $this->belongsTo(ClinicAppointment::class, 'appointment_id');
    }

    protected $casts = [
        'visit_date' => 'date',
        'closed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'called_at' => 'datetime',
        'seen_at' => 'datetime',
        'pay_override_at' => 'datetime',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function consultations()
    {
        return $this->hasMany(Consultation::class, 'visit_id');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    /** The doctor the patient was checked in to (clinic phase 2). */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function package()
    {
        return $this->belongsTo(ClinicPackage::class, 'package_id')->withTrashed();
    }

    /** The invoice raised at check-in. */
    public function feeTransaction()
    {
        return $this->belongsTo(Transaction::class, 'fee_transaction_id');
    }

    public function subscriptionPeriod()
    {
        return $this->belongsTo(ClinicSubscriptionPeriod::class, 'subscription_period_id');
    }

    /**
     * Was the consultation settled at check-in? (an invoice, a package, even
     * a free one, or a monthly plan). Such a consultation is never charged again.
     */
    public function billedAtCheckin()
    {
        return ! empty($this->fee_transaction_id) || ! empty($this->subscription_period_id) || ! empty($this->package_id);
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('visits.business_id', $business_id);
    }

    public function scopeOpen($query)
    {
        return $query->where('visits.status', 'open');
    }
}
