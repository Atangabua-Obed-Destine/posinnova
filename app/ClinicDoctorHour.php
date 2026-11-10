<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A block of a doctor's weekly consulting hours (weekday 1 = Monday ... 7 =
 * Sunday), cut into slots of slot_minutes for appointments.
 */
class ClinicDoctorHour extends Model
{
    protected $guarded = ['id'];

    public function doctor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_doctor_hours.business_id', $business_id);
    }
}
