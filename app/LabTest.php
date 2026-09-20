<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A catalogue entry for a laboratory test (hospital module).
 */
class LabTest extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'fee' => 'decimal:4',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('lab_tests.business_id', $business_id);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }
}
