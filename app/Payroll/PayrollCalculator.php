<?php

namespace App\Payroll;

use Carbon\Carbon;

/**
 * PosInnova payroll: every figure of a payroll, worked out on the server (paxhitestbed
 * PayrollCalculator). The screen asks it for a preview as lines are typed; saving asks it again and
 * stores its answer, so nothing typed in the browser can change a figure. From the person
 * generating it, it takes only the allowances and deductions; everything else comes from the staff
 * member's pay details, the settings and the taxes in force for the month.
 *
 *   earning   = the monthly salary (or the days between joining and leaving, when part months are prorated)
 *   gross     = earning + all allowances - all deductions
 *   taxable   = earning + taxable allowances (deductions are not taken off it)
 *   net       = gross - employee taxes;   total cost = net + employee taxes + employer charges
 */
class PayrollCalculator
{
    /**
     * @param  array  $allowances  [['title' => …, 'amount' => …], …]
     * @param  array  $deductions  [['title' => …, 'amount' => …], …]
     */
    public static function calculate($business_id, PayrollStaff $staff, Carbon $month, array $allowances, array $deductions, ?Payroll $existing = null): array
    {
        $month = $month->copy()->startOfMonth();
        $s = PayrollRules::settings($business_id);
        $problems = [];
        $notes = [];
        $basic = (float) $staff->basic_salary;
        $paidDays = null;
        $periodDays = null;

        if ($basic <= 0) {
            $problems[] = __('payroll.err_no_salary');
        }
        $earning = $basic;
        if (! empty($s['prorate_partial_months'])) {
            [$earning, $paidDays, $periodDays, $why] = self::prorate($staff, $month, $basic, $s['proration_basis']);
            if ($why) {
                $problems[] = $why;
            } elseif ($paidDays < $periodDays) {
                $notes[] = __('payroll.note_prorated', ['paid' => self::num($paidDays), 'period' => self::num($periodDays)]);
            }
        }
        $earning = round($earning);

        $types = PayrollLineType::where('business_id', $business_id)->where('kind', 'allowance')->get()->keyBy(fn ($t) => mb_strtolower(trim($t->title)));
        $onPayroll = $existing ? $existing->details()->where('status', 1)->get()->keyBy(fn ($d) => mb_strtolower(trim($d->title))) : collect();
        $allow = [];
        foreach (self::clean($allowances, $problems, 'allowance') as $l) {
            $key = mb_strtolower($l['title']);
            $type = $types->get($key);
            if (! ($type && $type->status) && ! $onPayroll->has($key)) {
                $problems[] = __('payroll.err_allowance_type', ['title' => $l['title']]);
                continue;
            }
            $l['is_taxable'] = $type ? (bool) $type->is_taxable : (bool) optional($onPayroll->get($key))->is_taxable;
            $allow[] = $l;
        }
        $deduct = self::clean($deductions, $problems, 'deduction');

        $totalAllowance = round(array_sum(array_column($allow, 'amount')), 2);
        $taxableAllowance = round(array_sum(array_map(fn ($l) => $l['is_taxable'] ? $l['amount'] : 0, $allow)), 2);
        $totalDeduction = round(array_sum(array_column($deduct, 'amount')), 2);
        $taxable = round($earning + $taxableAllowance, 2);

        $lines = [];
        if ($basic > 0) {
            if ($why = PayrollTaxes::coverageProblem($business_id, $taxable, $month)) {
                $problems[] = $why;
            } else {
                $lines = PayrollTaxes::forPay($business_id, $taxable, $month);
            }
        }
        $tax = round(array_sum(array_column($lines, 'employee')), 2);
        $employerTax = round(array_sum(array_column($lines, 'employer')), 2);
        $gross = round($earning + $totalAllowance - $totalDeduction, 2);
        $net = round($gross - $tax, 2);
        if ($basic > 0 && $net < 0) {
            $problems[] = __('payroll.err_net_negative');
        }

        return [
            'earning' => $earning, 'paid_days' => $paidDays, 'period_days' => $periodDays,
            'allowances' => $allow, 'deductions' => $deduct,
            'total_allowance' => $totalAllowance, 'taxable_allowance' => $taxableAllowance, 'total_deduction' => $totalDeduction,
            'gross' => $gross, 'taxable' => $taxable, 'tax_lines' => $lines, 'tax' => $tax, 'employer_tax' => $employerTax,
            'net' => $net, 'total_cost' => round($net + $tax + $employerTax, 2),
            'effective_rate' => $taxable > 0 ? round($tax / $taxable * 100, 2) : 0,
            'problems' => array_values(array_unique($problems)), 'notes' => $notes,
        ];
    }

    /** [earning, paid days, period days, problem] for a fixed salary in a part month. */
    private static function prorate(PayrollStaff $staff, Carbon $month, float $basic, $basis)
    {
        $start = $month->copy();
        $end = $month->copy()->endOfMonth()->startOfDay();
        $from = $staff->joining_date && $staff->joining_date->gt($start) ? $staff->joining_date->copy() : $start->copy();
        $to = $staff->ending_date && $staff->ending_date->lt($end) ? $staff->ending_date->copy() : $end->copy();
        $count = function (Carbon $a, Carbon $b) use ($basis) {
            if ($b->lt($a)) {
                return 0;
            }
            if ($basis !== 'working_days') {
                return $a->diffInDays($b) + 1;
            }
            $n = 0;
            for ($d = $a->copy(); $d->lte($b); $d->addDay()) {
                $n += $d->isWeekend() ? 0 : 1;
            }

            return $n;
        };
        $period = $count($start, $end);
        $paid = $count($from, $to);
        if ($paid <= 0) {
            return [0, 0, $period, __('payroll.err_not_employed')];
        }

        return [$period > 0 ? $basic * $paid / $period : $basic, $paid, $period, null];
    }

    private static function clean(array $rows, array &$problems, $kind)
    {
        $out = [];
        foreach ($rows as $r) {
            $title = trim((string) ($r['title'] ?? ''));
            $raw = str_replace([',', ' '], '', (string) ($r['amount'] ?? ''));
            if ($raw === '' && $title === '') {
                continue;
            }
            if ($title === '') {
                $problems[] = __('payroll.err_line_title');
                continue;
            }
            if (! is_numeric($raw) || (float) $raw < 0) {
                $problems[] = __('payroll.err_line_amount', ['title' => $title]);
                continue;
            }
            if ((float) $raw <= 0.005) {
                continue;
            }
            $out[] = ['title' => mb_substr($title, 0, 150), 'amount' => round((float) $raw, 2)];
        }

        return $out;
    }

    private static function num($v)
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
