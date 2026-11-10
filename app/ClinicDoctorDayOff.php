<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A date on which a doctor does not consult: no slots are offered.
 */
class ClinicDoctorDayOff extends Model
{
    protected $table = 'clinic_doctor_days_off';

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date'];

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_doctor_days_off.business_id', $business_id);
    }
}
