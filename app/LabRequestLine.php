<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * One requested test + its result on a lab request (hospital module).
 */
class LabRequestLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'resulted_at' => 'datetime',
        'fee' => 'decimal:4',
    ];

    public function labRequest()
    {
        return $this->belongsTo(LabRequest::class, 'lab_request_id');
    }

    public function labTest()
    {
        return $this->belongsTo(LabTest::class, 'lab_test_id');
    }
}
