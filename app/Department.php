<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
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

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('business_id', $business_id);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public static function forDropdown($business_id, $prepend_none = false)
    {
        $query = self::forBusiness($business_id)->active()->orderBy('name');
        $list = $query->pluck('name', 'id');
        if ($prepend_none) {
            $list->prepend(__('messages.please_select'), '');
        }
        return $list;
    }
}
