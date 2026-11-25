<?php

namespace App\Payroll;

use Illuminate\Database\Eloquent\Model;

/** PosInnova payroll: an allowance (status 1) or a deduction (status 0) on a payroll. */
class PayrollDetail extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_taxable' => 'boolean'];
}
