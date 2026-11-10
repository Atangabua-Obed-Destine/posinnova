<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A Medication Administration Record (MAR) entry (hospital module, nursing).
 */
class MedicationAdministration extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'administered_at' => 'datetime',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }

    public function prescription()
    {
        return $this->belongsTo(ConsultationPrescription::class, 'consultation_prescription_id');
    }

    public function administeredBy()
    {
        return $this->belongsTo(User::class, 'administered_by');
    }
}
