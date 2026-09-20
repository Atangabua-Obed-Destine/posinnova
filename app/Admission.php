<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An inpatient admission (hospital module).
 */
class Admission extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'admission_date' => 'datetime',
        'discharge_date' => 'datetime',
        'deposit_amount' => 'decimal:4',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    public function bed()
    {
        return $this->belongsTo(Bed::class, 'bed_id');
    }

    public function admittedBy()
    {
        return $this->belongsTo(User::class, 'admitted_by');
    }

    public function admittingDoctor()
    {
        return $this->belongsTo(User::class, 'admitting_doctor_id');
    }

    public function caretakers()
    {
        return $this->hasMany(AdmissionCaretaker::class, 'admission_id');
    }

    public function transfers()
    {
        return $this->hasMany(WardTransfer::class, 'admission_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('admissions.business_id', $business_id);
    }

    public function scopeActive($query)
    {
        return $query->where('admissions.status', 'active');
    }

    /**
     * Whole-days the patient has been (or was) admitted, minimum 1.
     */
    public function getDaysAdmittedAttribute()
    {
        $end = $this->discharge_date ?: now();
        $days = $this->admission_date->diffInDays($end);

        return max(1, $days);
    }
}
