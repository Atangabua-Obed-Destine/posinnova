<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A caretaker attending an admitted patient (hospital module).
 */
class AdmissionCaretaker extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }
}
