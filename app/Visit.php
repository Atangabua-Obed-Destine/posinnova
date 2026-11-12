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
        'cover_until' => 'date',
        'cover_same_doctor' => 'boolean',
        'vitals_taken_at' => 'datetime',
    ];

    /** Vitals fields shared with consultations (same names, same units). */
    const VITALS = ['vitals_weight', 'vitals_height', 'vitals_bp_systolic', 'vitals_bp_diastolic', 'vitals_temperature', 'vitals_pulse', 'vitals_spo2'];

    /** The paid visit whose fee covers this return visit (ClinicCover). */
    public function coveredBy()
    {
        return $this->belongsTo(Visit::class, 'covered_by_visit_id');
    }

    public function vitalsTakenBy()
    {
        return $this->belongsTo(User::class, 'vitals_taken_by');
    }

    public function hasVitals()
    {
        return ! empty($this->vitals_taken_at);
    }

    /** "72 kg · BP 120/80 · 37.2 °C · pulse 78 · SpO₂ 98%", or '' when none were taken. */
    public function vitalsSummary()
    {
        $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
        $parts = [];
        if ($this->vitals_weight) { $parts[] = $n($this->vitals_weight).' kg'; }
        if ($this->vitals_height) { $parts[] = $n($this->vitals_height).' cm'; }
        if ($this->vitals_bp_systolic && $this->vitals_bp_diastolic) { $parts[] = __('clinic.vit_bp_short').' '.$this->vitals_bp_systolic.'/'.$this->vitals_bp_diastolic; }
        if ($this->vitals_temperature) { $parts[] = $n($this->vitals_temperature).' °C'; }
        if ($this->vitals_pulse) { $parts[] = __('clinic.vit_pulse_short').' '.$this->vitals_pulse; }
        if ($this->vitals_spo2) { $parts[] = 'SpO₂ '.$this->vitals_spo2.'%'; }

        return implode(' · ', $parts);
    }

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
