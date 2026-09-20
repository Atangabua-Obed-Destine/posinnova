<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Record of a patient being moved between wards/beds (hospital module).
 */
class WardTransfer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'transfer_date' => 'datetime',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class, 'admission_id');
    }

    public function fromWard()
    {
        return $this->belongsTo(Ward::class, 'from_ward_id');
    }

    public function toWard()
    {
        return $this->belongsTo(Ward::class, 'to_ward_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
