<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A hospital ward (hospital module), optionally mapped to a business location.
 */
class Ward extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    public function location()
    {
        return $this->belongsTo(BusinessLocation::class, 'location_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function beds()
    {
        return $this->hasMany(Bed::class, 'ward_id');
    }

    public function activeAdmissions()
    {
        return $this->hasMany(Admission::class, 'ward_id')->where('status', 'active');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('wards.business_id', $business_id);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public static function forDropdown($business_id, $prepend_none = false)
    {
        $list = self::forBusiness($business_id)->active()->orderBy('name')->pluck('name', 'id');
        if ($prepend_none) {
            $list->prepend(__('messages.please_select'), '');
        }

        return $list;
    }
}
