<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova clinic: one correction made to a finished consultation: who, when, why, the parts changed
 * and the consultation as it was just before (see App\Utils\ConsultationCorrections).
 */
class ConsultationCorrection extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'changed' => 'array',
        'before' => 'array',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public function correctedBy()
    {
        return $this->belongsTo(User::class, 'corrected_by');
    }

    /** The examination form as it was before this correction (not a stored row), or null. */
    public function examBefore()
    {
        $e = $this->before['exam'] ?? null;
        if (empty($e)) {
            return null;
        }
        $exam = new ConsultationExam([
            'business_id' => $this->business_id,
            'consultation_id' => $this->consultation_id,
            'template_id' => $e['template_id'] ?? null,
            'template_name' => $e['template_name'] ?? '',
            'blocks' => $e['blocks'] ?? [],
            'values' => $e['values'] ?? [],
        ]);
        $exam->correction_id = $this->id;

        return $exam;
    }
}
