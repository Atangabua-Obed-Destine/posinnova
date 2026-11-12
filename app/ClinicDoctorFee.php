<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A doctor's own price for a consultation package (package_key = package id) or
 * for the plain consultation fee (package_key = 0). See App\Utils\ClinicFees.
 */
class ClinicDoctorFee extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['price' => 'decimal:4'];

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_doctor_fees.business_id', $business_id);
    }
}
