<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova clinic: the examination form of one consultation, as filled in: a copy of the template's
 * blocks, the answers, and the drawings (PNG files in storage/app/clinic_drawings, served to staff by
 * ConsultationExamController; what the doctor drew, over the template's picture).
 */
class ConsultationExam extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'blocks' => 'array',
        'values' => 'array',
    ];

    public function consultation()
    {
        return $this->belongsTo(Consultation::class, 'consultation_id');
    }

    public static function drawingDir()
    {
        return storage_path('app/clinic_drawings');
    }

    /** File name of what was drawn on a block, or null. */
    public function drawing($block_id)
    {
        $f = ($this->values['_draw'] ?? [])[$block_id] ?? null;

        return $f && is_file(self::drawingDir().DIRECTORY_SEPARATOR.$f) ? $f : null;
    }

    public function drawingUrl($block_id)
    {
        $f = $this->drawing($block_id);

        return $f ? action([\App\Http\Controllers\ConsultationExamController::class, 'drawing'], [$this->id, $block_id]).'?v='.substr(md5($f), 0, 8) : null;
    }

    /** Answers only (no drawings). */
    public function answers()
    {
        $v = $this->values ?: [];
        unset($v['_draw']);

        return $v;
    }

    /** Something was drawn or answered. */
    public function hasContent()
    {
        return ! empty($this->answers()) || ! empty(array_filter(array_keys($this->values['_draw'] ?? []), fn ($b) => $this->drawing($b)));
    }
}
