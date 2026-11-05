<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One insured charge (clinic phase 5): the patient's invoice for their part and
 * the insurer's invoice for its part. What the insurer still owes is read live
 * from its invoice (total minus payments), never stored here.
 */
class ClinicInsuranceShare extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'pct' => 'decimal:2',
        'full_amount' => 'decimal:4',
        'amount' => 'decimal:4',
        'rebilled_amount' => 'decimal:4',
        'lines' => 'array',
        'rebilled_at' => 'datetime',
    ];

    public function insurer()
    {
        return $this->belongsTo(ClinicInsurer::class, 'insurer_id')->withTrashed();
    }

    public function patient()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function patientInvoice()
    {
        return $this->belongsTo(Transaction::class, 'patient_transaction_id');
    }

    public function insurerInvoice()
    {
        return $this->belongsTo(Transaction::class, 'insurer_transaction_id');
    }

    public function rebillInvoice()
    {
        return $this->belongsTo(Transaction::class, 'rebill_transaction_id');
    }

    public function claim()
    {
        return $this->belongsTo(ClinicClaim::class, 'claim_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_insurance_shares.business_id', $business_id);
    }
}
