<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A chronological nursing/shift note (hospital module).
 */
class NursingNote extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'noted_at' => 'datetime',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
