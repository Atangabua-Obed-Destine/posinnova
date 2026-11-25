<?php

namespace App\Payroll;

use App\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: a loan or salary advance to a staff member (paxhitestbed StaffLoan), repaid by a
 * deduction on each payroll from its start month until nothing is left.
 *
 * What has been repaid is not stored: it is the sum of this loan's lines on payrolls that are paid.
 * Reversing a salary payment therefore puts the instalment back on the balance with no extra
 * bookkeeping, and nothing can drift.
 */
class PayrollLoan extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['start_month' => 'date', 'given_on' => 'date'];

    const KINDS = ['loan', 'advance'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function lines()
    {
        return $this->hasMany(PayrollDetail::class, 'staff_loan_id');
    }

    private function onPayrolls($status, $exceptPayrollId = null)
    {
        return round((float) DB::table('payroll_details as d')->join('payrolls as p', 'p.id', '=', 'd.payroll_id')
            ->where('d.staff_loan_id', $this->id)->where('p.status', $status)
            ->when($exceptPayrollId, fn ($q) => $q->where('p.id', '!=', $exceptPayrollId))
            ->sum('d.amount'), 2);
    }

    /** Taken on payrolls that have been paid. */
    public function repaid()
    {
        return $this->onPayrolls(1);
    }

    /** Set aside on payrolls generated but not yet paid, other than $exceptPayrollId. */
    public function pending($exceptPayrollId = null)
    {
        return $this->onPayrolls(0, $exceptPayrollId);
    }

    public function balance()
    {
        return round(max(0, (float) $this->amount - $this->repaid()), 2);
    }

    /** What a payroll may still take, given what other unpaid payrolls already hold. */
    public function available($exceptPayrollId = null)
    {
        return round(max(0, (float) $this->amount - $this->repaid() - $this->pending($exceptPayrollId)), 2);
    }

    /** The instalment due on a month's payroll: nothing before the start month or once cleared. */
    public function dueFor($month, $exceptPayrollId = null)
    {
        if ($this->status !== 'active' || Carbon::parse($month)->startOfMonth()->lt($this->start_month->copy()->startOfMonth())) {
            return 0.0;
        }

        return round(min((float) $this->instalment, $this->available($exceptPayrollId)), 2);
    }

    public function isCleared()
    {
        return $this->balance() <= 0.004;
    }

    /** Open loans of a staff member with something to take for a month: deduction lines. */
    public static function dueLines($business_id, $user_id, $month, $exceptPayrollId = null)
    {
        $lines = [];
        foreach (static::where('business_id', $business_id)->where('user_id', $user_id)->where('status', 'active')->orderBy('id')->get() as $loan) {
            $due = $loan->dueFor($month, $exceptPayrollId);
            if ($due > 0) {
                $lines[] = ['title' => $loan->title, 'amount' => $due, 'loan_id' => $loan->id];
            }
        }

        return $lines;
    }
}
