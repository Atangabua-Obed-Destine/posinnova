<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A claim sent to an insurer: the insured charges of a period (clinic phase 5).
 * Draft while it is being prepared; submitted once sent, after which its items
 * are fixed and the insurer's payment is recorded against it.
 */
class ClinicClaim extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'submitted_at' => 'datetime',
    ];

    public function insurer()
    {
        return $this->belongsTo(ClinicInsurer::class, 'insurer_id')->withTrashed();
    }

    public function items()
    {
        return $this->hasMany(ClinicInsuranceShare::class, 'claim_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_claims.business_id', $business_id);
    }
}
