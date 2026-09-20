<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A bed within a ward (hospital module).
 */
class Bed extends Model
{
    protected $guarded = ['id'];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function ward()
    {
        return $this->belongsTo(Ward::class, 'ward_id');
    }

    /**
     * The current active admission occupying this bed (if any).
     */
    public function currentAdmission()
    {
        return $this->hasOne(Admission::class, 'bed_id')->where('status', 'active');
    }
}
