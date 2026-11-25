<?php

namespace App\Payroll;

use App\User;
use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova payroll: one staff member's pay for one month (paxhitestbed Payroll). status 0 unpaid,
 * 1 paid; "approved" = approved_at set (matters only when approval is required).
 * gross = earning + allowances - deductions; net = gross - employee tax;
 * total cost = net + employee tax + employer charges.
 */
class Payroll extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'salary_month' => 'date',
        'pay_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function details()
    {
        return $this->hasMany(PayrollDetail::class, 'payroll_id')->orderBy('status', 'desc')->orderBy('id');
    }

    public function taxLines()
    {
        return $this->hasMany(PayrollTaxLine::class, 'payroll_id')->orderBy('id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPaid()
    {
        return (int) $this->status === 1;
    }

    public function isApproved()
    {
        return $this->approved_at !== null;
    }
}
