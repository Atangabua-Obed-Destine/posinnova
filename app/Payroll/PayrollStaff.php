<?php

namespace App\Payroll;

use App\User;
use Illuminate\Database\Eloquent\Model;

/** PosInnova payroll: a staff member's pay details (the salary on paxhitestbed's staff record). */
class PayrollStaff extends Model
{
    protected $table = 'payroll_staff';

    protected $guarded = ['id'];

    protected $casts = ['joining_date' => 'date', 'ending_date' => 'date', 'is_active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
