<?php

namespace App\Payroll;

use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova payroll: tax or contributions withheld for a salary month, paid over to the body they
 * were withheld for (paxhitestbed TaxRemittance). A voided one keeps its row (the audit trail), so
 * every sum of what has been paid uses live().
 */
class PayrollRemittance extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['salary_month' => 'date', 'payment_date' => 'date', 'voided_at' => 'datetime'];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeLive($q)
    {
        return $q->whereNull('voided_at');
    }

    public function isVoided()
    {
        return $this->voided_at !== null;
    }
}
