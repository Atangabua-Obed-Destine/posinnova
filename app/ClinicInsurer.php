<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An insurance company (clinic phase 5). Its share of each insured charge is
 * an invoice on its own customer account (contact_id), so what it owes is an
 * ordinary customer balance. See App\Utils\ClinicInsurance.
 */
class ClinicInsurer extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'default_pct' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function shares()
    {
        return $this->hasMany(ClinicInsuranceShare::class, 'insurer_id');
    }

    public function claims()
    {
        return $this->hasMany(ClinicClaim::class, 'insurer_id');
    }

    public function scopeForBusiness($query, $business_id)
    {
        return $query->where('clinic_insurers.business_id', $business_id);
    }

    public function scopeActive($query)
    {
        return $query->where('clinic_insurers.is_active', 1);
    }
}
