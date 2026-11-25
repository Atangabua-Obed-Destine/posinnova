<?php

namespace App\Payroll;

use Illuminate\Database\Eloquent\Model;

/** PosInnova payroll: an allowance type (taxed or not) or a deduction type, for the payroll lines. */
class PayrollLineType extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_taxable' => 'boolean', 'status' => 'boolean'];
}
