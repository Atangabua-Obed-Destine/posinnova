<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Clinical extension of a contact (1:1) for the hospital module.
 * A patient is an ordinary customer contact with is_patient = 1 plus a row here.
 */
class PatientDetail extends Model
{
    protected $guarded = ['id'];

    //PosInnova: the portal PIN hash never leaves the server in JSON
    protected $hidden = ['portal_pin'];

    protected $casts = [
        'date_of_birth' => 'date',
        'insurance_expiry_date' => 'date',
        'insurance_coverage_pct' => 'decimal:2',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    /**
     * The insurer (clinic phase 5); insurance_provider keeps its name as text.
     */
    public function insurer()
    {
        return $this->belongsTo(ClinicInsurer::class, 'insurer_id')->withTrashed();
    }

    /**
     * The mother's patient contact (for babies registered via maternity).
     */
    public function mother()
    {
        return $this->belongsTo(Contact::class, 'mother_contact_id');
    }

    /**
     * Computed age in years from date_of_birth (null when DOB is unknown).
     */
    public function getAgeAttribute()
    {
        if (empty($this->date_of_birth)) {
            return null;
        }

        return $this->date_of_birth->age;
    }

    /**
     * PosInnova: is this a valid portal PIN? 4 to 6 digits.
     */
    public static function isValidPin($pin)
    {
        return is_string($pin) && preg_match('/^\d{4,6}$/', $pin) === 1;
    }

    /**
     * PosInnova: set the portal PIN (hashed) and lift any sign-in lock.
     * Does not save; the caller saves the row.
     */
    public function setPortalPin($pin)
    {
        $this->portal_pin = \Illuminate\Support\Facades\Hash::make($pin);
        $this->portal_pin_set_at = now();
        $this->portal_failed_attempts = 0;
        $this->portal_locked_until = null;
    }

    /**
     * Atomically increment the business file counter and return the next
     * formatted patient file number, e.g. PMI-0001. Shared by patient
     * registration and maternity baby auto-registration.
     */
    public static function generateFileNumber($business_id)
    {
        $business = Business::lockForUpdate()->find($business_id);
        $prefix = $business->hospital_file_prefix ?: 'PMI';
        $business->increment('hospital_file_counter');
        $counter = $business->hospital_file_counter;

        return $prefix.'-'.str_pad($counter, 4, '0', STR_PAD_LEFT);
    }
}
