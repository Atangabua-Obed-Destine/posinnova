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
        'dob_estimated' => 'boolean',
        'insurance_expiry_date' => 'date',
        'insurance_coverage_pct' => 'decimal:2',
        'whatsapp_opt_in' => 'boolean',
        'whatsapp_opt_in_at' => 'datetime',
        'whatsapp_opt_out_at' => 'datetime',
    ];

    /**
     * Records the patient's WhatsApp consent (patient form, portal, or STOP).
     * The dates change only when the answer changes.
     */
    public function setWhatsappConsent($agrees)
    {
        $agrees = (bool) $agrees;
        if ($agrees && ! $this->whatsapp_opt_in) {
            $this->whatsapp_opt_in_at = now();
            $this->whatsapp_opt_out_at = null;
        } elseif (! $agrees && $this->whatsapp_opt_in) {
            $this->whatsapp_opt_out_at = now();
        }
        $this->whatsapp_opt_in = $agrees;
    }

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
     * PosInnova: the birth date as shown to people. An estimated one (the patient gave an age, not a
     * date) is shown as "about N years" rather than as a precise date nobody gave.
     */
    public function dobLabel($format = 'd-m-Y')
    {
        if (empty($this->date_of_birth)) {
            return null;
        }
        if ($this->dob_estimated) {
            $months = $this->date_of_birth->diffInMonths(now());
            $age = $months < 24 ? trans_choice('clinic.pf_age_months_n', $months, ['n' => $months]) : trans_choice('clinic.pf_age_years_n', $this->date_of_birth->age, ['n' => $this->date_of_birth->age]);

            return __('clinic.pf_dob_estimated', ['age' => $age]);
        }

        return $this->date_of_birth->format($format);
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
