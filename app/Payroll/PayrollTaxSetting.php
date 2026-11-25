<?php

namespace App\Payroll;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * PosInnova payroll: a band of a group, or a single tax (paxhitestbed TaxSetting). tax_type 1 a
 * percentage of the pay above `threshold`, 2 a fixed amount; paid_by employee | employer | both.
 * A dependent tax is a percentage of another tax's charge (the council tax: 10% of the income tax).
 */
class PayrollTaxSetting extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['status' => 'boolean', 'is_dependent' => 'boolean', 'effective_from' => 'date', 'effective_to' => 'date'];

    public static function standaloneInForce($business_id, $date, $dependent)
    {
        $d = Carbon::parse($date)->toDateString();

        return static::where('business_id', $business_id)->whereNull('tax_group_id')->where('is_dependent', $dependent ? 1 : 0)->where('status', 1)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', $d))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d))
            ->orderBy('bracket_order')->orderBy('id')->get();
    }

    public function employerOn(float $base)
    {
        if ($this->paid_by === 'employee') {
            return 0.0;
        }

        return (int) $this->tax_type === 2 ? (float) $this->employer_fixed_amount
            : max(0, $base - (float) $this->threshold) * (float) $this->employer_percentage / 100;
    }
}
