<?php

namespace App\Payroll;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * PosInnova payroll: the file a bank takes to pay salaries (paxhitestbed PayrollBankFile): one row
 * per payroll, the columns, separator and reference as set in Payroll Settings. A payroll whose staff
 * member has no bank account number is left out and named, never written with a blank number.
 */
class PayrollBankFile
{
    /** @return array{rows: array, missing: array, total: float} */
    public static function build($business_id, Collection $payrolls)
    {
        $s = PayrollRules::settings($business_id);
        $decimals = (int) $s['bank_file_decimals'] === 2 ? 2 : 0;
        $currency = (string) optional(optional(\App\Business::find($business_id))->currency)->code;
        $staff = PayrollStaff::where('business_id', $business_id)->whereIn('user_id', $payrolls->pluck('user_id'))->get()->keyBy('user_id');
        $rows = [];
        $missing = [];
        $total = 0.0;
        foreach ($payrolls as $p) {
            $u = $p->user;
            $st = $staff->get($p->user_id);
            $name = trim(($u->first_name ?? '').' '.($u->last_name ?? '')) ?: ($u->username ?? '#'.$p->user_id);
            if (! $st || trim((string) $st->bank_account_no) === '') {
                $missing[] = $name;
                continue;
            }
            $m = Carbon::parse($p->salary_month);
            $values = [
                'staff_no' => (string) ($u->username ?? $p->user_id),
                'name' => $name,
                'bank_name' => (string) $st->bank_name,
                'account_number' => (string) $st->bank_account_no,
                'amount' => number_format((float) $p->net_salary, $decimals, '.', ''),
                'currency' => $currency,
                'reference' => strtr((string) $s['bank_file_reference'], ['{MONTH}' => mb_strtoupper($m->locale(app()->getLocale())->isoFormat('MMM')),
                    '{MONTH_NUMBER}' => $m->format('m'), '{YEAR}' => $m->format('Y'), '{NAME}' => $name, '{STAFF_NO}' => (string) ($u->username ?? '')]),
                'month' => $m->format('Y-m'),
                'cnps_no' => (string) $st->cnps_no,
                'job_title' => (string) $st->job_title,
            ];
            $rows[] = array_intersect_key($values, array_flip(self::columns($business_id)));
            $total += round((float) $p->net_salary, $decimals);
        }

        return ['rows' => $rows, 'missing' => $missing, 'total' => round($total, $decimals)];
    }

    /** The chosen columns, in the fixed order of PayrollRules::BANK_COLUMNS. */
    public static function columns($business_id)
    {
        $chosen = (array) PayrollRules::settings($business_id)['bank_file_columns'];
        $cols = array_values(array_filter(PayrollRules::BANK_COLUMNS, fn ($c) => in_array($c, $chosen, true)));

        return $cols ?: PayrollRules::DEFAULTS['bank_file_columns'];
    }

    /** The rows as a delimited text file, with a header row when the settings ask for one. */
    public static function csv($business_id, array $rows)
    {
        $s = PayrollRules::settings($business_id);
        $delimiter = $s['bank_file_delimiter'] === 'tab' ? "\t" : ($s['bank_file_delimiter'] === ';' ? ';' : ',');
        $out = fopen('php://temp', 'r+');
        if (! empty($s['bank_file_header'])) {
            fputcsv($out, array_map(fn ($c) => __('payroll.bank_col_'.$c), self::columns($business_id)), $delimiter);
        }
        foreach ($rows as $row) {
            // a leading = + - @ makes a spreadsheet treat a cell as a formula
            fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@-]/', (string) $v) && ! is_numeric($v) ? "'".$v : $v, array_values($row)), $delimiter);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv;
    }
}
