<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A clinical consultation within a visit (hospital module).
 */
class Consultation extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'admission_requested' => 'boolean',
        'consultation_fee' => 'decimal:4',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function visit()
    {
        return $this->belongsTo(Visit::class, 'visit_id');
    }

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function consultedBy()
    {
        return $this->belongsTo(User::class, 'consulted_by');
    }

    public function referralDepartment()
    {
        return $this->belongsTo(Department::class, 'referral_to');
    }

    public function prescriptions()
    {
        return $this->hasMany(ConsultationPrescription::class, 'consultation_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('consultations.business_id', $business_id);
    }

    /**
     * Body mass index from recorded vitals, or null when weight/height missing.
     */
    public function getBmiAttribute()
    {
        if (empty($this->vitals_weight) || empty($this->vitals_height)) {
            return null;
        }

        $height_m = $this->vitals_height / 100;
        if ($height_m <= 0) {
            return null;
        }

        return round($this->vitals_weight / ($height_m * $height_m), 1);
    }
}
