<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A consultation package reception checks a patient in under (Standard, VIP,
 * ...). Backed by a non-stock service product (product_id / variation_id) so
 * the check-in invoice has a real line. See App\Utils\ClinicServices.
 */
class ClinicPackage extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'price' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_packages.business_id', $business_id);
    }

    public function scopeActive($query)
    {
        return $query->where('clinic_packages.is_active', 1);
    }

    /** Packages charged at every check-in (monthly ones are billed by plans). */
    public function scopePerVisit($query)
    {
        return $query->where('clinic_packages.billing', 'per_visit');
    }

    /** Monthly plans (patients subscribe; billed once a month). */
    public function scopeMonthly($query)
    {
        return $query->where('clinic_packages.billing', 'monthly');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('clinic_packages.sort_order')->orderBy('clinic_packages.name');
    }
}
