<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A single line on a patient's consolidated bill (hospital module). Written by
 * each billable event (consultation, lab, imaging, ward dispense, procedure…)
 * so the admission running bill / discharge bill assemble from one place.
 */
class VisitCharge extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:4',
        'insurance_covered' => 'decimal:4',
        'patient_owes' => 'decimal:4',
        'is_paid' => 'boolean',
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

    public function admission()
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('visit_charges.business_id', $business_id);
    }

    /**
     * Record a charge. patient_owes defaults to amount minus any insurance
     * portion (insurance handling lands in Phase 8).
     */
    public static function record(array $data)
    {
        $amount = $data['amount'] ?? 0;
        $insurance = $data['insurance_covered'] ?? 0;
        $data['patient_owes'] = $data['patient_owes'] ?? max(0, $amount - $insurance);

        return self::create($data);
    }
}
