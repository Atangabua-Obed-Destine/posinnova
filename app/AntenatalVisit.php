<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A single ANC visit within a pregnancy (hospital module, maternity).
 */
class AntenatalVisit extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'visit_date' => 'date',
        'next_visit_date' => 'date',
        'fee' => 'decimal:4',
    ];

    public function record()
    {
        return $this->belongsTo(AntenatalRecord::class, 'antenatal_record_id');
    }

    public function attendedBy()
    {
        return $this->belongsTo(User::class, 'attended_by');
    }
}
