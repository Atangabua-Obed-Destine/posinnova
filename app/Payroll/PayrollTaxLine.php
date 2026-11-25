<?php

namespace App\Payroll;

use Illuminate\Database\Eloquent\Model;

/** PosInnova payroll: one tax or contribution on a payroll, both shares, and who it is owed to. */
class PayrollTaxLine extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['salary_month' => 'date'];
}
