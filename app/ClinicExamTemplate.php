<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PosInnova clinic: an examination form for a specialty (see App\Utils\ClinicExamTemplates).
 */
class ClinicExamTemplate extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'specialties' => 'array',
        'blocks' => 'array',
        'is_active' => 'boolean',
    ];

    public function drawingCount()
    {
        return collect($this->blocks ?: [])->where('type', 'drawing')->count();
    }

    public function fieldCount()
    {
        return collect($this->blocks ?: [])->whereNotIn('type', ['heading', 'drawing'])->count();
    }
}
