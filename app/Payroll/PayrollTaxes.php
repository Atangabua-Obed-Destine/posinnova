<?php

namespace App\Payroll;

/**
 * PosInnova payroll: every tax on a pay, itemised (paxhitestbed PayrollTaxBreakdownService::forSalary).
 * In this order: (a) each group in force charges its one band (step lookup), the employer share
 * from that band when it is paid by the employer or both; (b) single taxes, applied only when the
 * pay is within both bounds; (c) dependent taxes, a percentage of another tax's charge (both sides),
 * with no threshold. Each line rounded to 2 decimals.
 */
class PayrollTaxes
{
    /** @return array<int, array{tax_group_id:?int, tax_setting_id:?int, label:string, employee:float, employer:float, liability_code:?string}> */
    public static function forPay($business_id, float $pay, $date): array
    {
        $lines = [];
        $groups = [];
        foreach (PayrollTaxGroup::inForce($business_id, $date) as $g) {
            $band = $g->bandFor($pay, $date);
            $employee = 0.0;
            $employer = 0.0;
            if ($band) {
                $employee = (int) $band->tax_type === 2 ? (float) $band->fixed_amount
                    : max(0, $pay - (float) $band->threshold) * (float) $band->percentage / 100;
                if (in_array($band->paid_by, ['employer', 'both'], true)) {
                    $employer = $band->employerOn($pay);
                }
            }
            $groups[$g->id] = ['employee' => $employee, 'employer' => $employer];
            if ($employee == 0.0 && $employer == 0.0) {
                continue;
            }
            $lines[] = ['tax_group_id' => (int) $g->id, 'tax_setting_id' => $band ? (int) $band->id : null, 'label' => $g->title,
                'employee' => round($employee, 2), 'employer' => round($employer, 2), 'liability_code' => $band->liability_code ?: $g->liability_code];
        }

        $single = [];
        foreach (PayrollTaxSetting::standaloneInForce($business_id, $date, false) as $t) {
            $single[$t->id] = ['employee' => 0.0, 'employer' => 0.0];
            if ($pay < (float) $t->min_amount || $pay > (float) $t->max_amount) {
                continue;
            }
            [$employee, $employer] = self::amounts($t, $pay, false);
            $single[$t->id] = ['employee' => $employee, 'employer' => $employer];
            if ($employee == 0.0 && $employer == 0.0) {
                continue;
            }
            $lines[] = ['tax_group_id' => null, 'tax_setting_id' => (int) $t->id, 'label' => $t->title,
                'employee' => round($employee, 2), 'employer' => round($employer, 2), 'liability_code' => $t->liability_code];
        }

        foreach (PayrollTaxSetting::standaloneInForce($business_id, $date, true) as $t) {
            if ($pay < (float) $t->min_amount || $pay > (float) $t->max_amount) {
                continue;
            }
            $src = $t->depends_on_type === 'tax_group' ? ($groups[$t->depends_on_id] ?? null) : ($single[$t->depends_on_id] ?? null);
            $base = $src ? $src['employee'] + $src['employer'] : 0.0;
            [$employee, $employer] = self::amounts($t, $base, true);
            if ($employee == 0.0 && $employer == 0.0) {
                continue;
            }
            $lines[] = ['tax_group_id' => null, 'tax_setting_id' => (int) $t->id, 'label' => $t->title,
                'employee' => round($employee, 2), 'employer' => round($employer, 2), 'liability_code' => $t->liability_code];
        }

        return $lines;
    }

    private static function amounts(PayrollTaxSetting $t, float $base, bool $dependent): array
    {
        $taxable = $dependent ? $base : max(0, $base - (float) $t->threshold);
        $employee = 0.0;
        $employer = 0.0;
        if (in_array($t->paid_by, ['employee', 'both'], true)) {
            $employee = (int) $t->tax_type === 2 ? (float) $t->fixed_amount : $taxable * (float) $t->percentage / 100;
        }
        if (in_array($t->paid_by, ['employer', 'both'], true)) {
            $employer = (int) $t->tax_type === 2 ? (float) $t->employer_fixed_amount : $taxable * (float) $t->employer_percentage / 100;
        }

        return [$employee, $employer];
    }

    /** Why the income tax cannot be worked out for this pay (null = it can). */
    public static function coverageProblem($business_id, float $pay, $date)
    {
        $it = PayrollTaxGroup::inForce($business_id, $date)->firstWhere('is_income_tax', true);
        if (! $it) {
            return __('payroll.err_no_income_tax');
        }
        foreach ($it->gaps($date) as [$from, $to]) {
            if ($pay >= $from && $pay <= $to) {
                return __('payroll.err_tax_gap', ['from' => number_format($from), 'to' => number_format($to)]);
            }
        }
        $top = $it->bandsInForce($date)->max(fn ($b) => (float) $b->max_amount);
        if ($top !== null && $pay > $top) {
            return __('payroll.err_tax_top', ['top' => number_format($top)]);
        }

        return null;
    }
}
