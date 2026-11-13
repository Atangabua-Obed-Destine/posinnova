<?php

namespace App\Utils;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Clinic reports (/clinic/reports/...). Every report reads the real records the
 * clinic works with - invoices and their lines, payments, visits, consultations,
 * prescription lines, lab requests - so its figures match the cashier, the
 * dispensary and the patient accounts screens. (The older hospital reports read
 * the visit_charges index, which the cashier never updates.)
 *
 * Each method returns the same shape, drawn by clinic/reports/show:
 *   tiles  : [['label', 'value', 'type' => number|money|pct|minutes|text, 'sub', 'tone']]
 *   charts : [['title', 'type', 'points' => [['label', 'value', 'tip']]]]   one series each
 *   tables : [['id', 'title', 'note', 'columns' => [['key', 'label', 'type']], 'rows', 'empty', 'total']]
 * A cell is a scalar, or ['text' => .., 'url' => .., 'tone' => ..] for a link / badge.
 */
class ClinicReports
{
    const MAX_ROWS = 1000;

    /** Report keys by group, with the permission that opens them (the Admin passes every check). */
    const REPORTS = [
        'practice' => ['consultations', 'diagnoses', 'prescriptions', 'lab', 'waiting'],
        'money' => ['cash', 'income', 'owing', 'insurers', 'plans'],
        'activity' => ['attendance'],
    ];

    const ICONS = [
        'consultations' => 'stethoscope', 'diagnoses' => 'report-medical', 'prescriptions' => 'pill', 'lab' => 'microscope',
        'waiting' => 'calendar-time', 'cash' => 'cash', 'income' => 'receipt', 'owing' => 'users', 'insurers' => 'shield-check',
        'plans' => 'calendar-repeat', 'attendance' => 'door-enter',
    ];

    public static function groupOf($key)
    {
        foreach (self::REPORTS as $group => $keys) {
            if (in_array($key, $keys)) {
                return $group;
            }
        }

        return null;
    }

    /** May the user open this report? practice = own patients (or everyone's with all_doctors). */
    public static function can($user, $key)
    {
        switch (self::groupOf($key)) {
            case 'practice':
                return $user->can('clinic_report.my_practice') || $user->can('clinic_report.all_doctors');
            case 'money':
                // the old "Revenue and insurance report" permission opens the reports that replaced it
                return $user->can('clinic_report.finance') || (in_array($key, ['income', 'insurers']) && $user->can('hospital_report.revenue'));
            case 'activity':
                return $user->can('clinic_report.attendance') || $user->can('clinic_report.all_doctors') || $user->can('clinic_report.finance');
        }

        return false;
    }

    public static function available($user)
    {
        $out = [];
        foreach (self::REPORTS as $group => $keys) {
            foreach ($keys as $key) {
                if (self::can($user, $key)) {
                    $out[$group][] = $key;
                }
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ helpers

    private static function between($f)
    {
        return [$f['from']->copy()->startOfDay()->toDateTimeString(), $f['to']->copy()->endOfDay()->toDateTimeString()];
    }

    private static function dates($f)
    {
        return [$f['from']->toDateString(), $f['to']->toDateString()];
    }

    private static function userName($first, $sur = null, $last = null)
    {
        return trim(preg_replace('/\s+/', ' ', ($sur ?? '').' '.($first ?? '').' '.($last ?? '')));
    }

    /** A date in the business's own date format (Business settings), optionally with the time. */
    public static function d($date, $time = false)
    {
        $fmt = session('business.date_format') ?: 'd/m/Y';

        return Carbon::parse($date)->format($fmt.($time ? ' H:i' : ''));
    }

    /** Day and month only, in the order of the business's date format (11/10 or 10/11). */
    public static function dayMonth()
    {
        return strpos((string) (session('business.date_format') ?: 'd/m/Y'), 'm') === 0 ? 'm/d' : 'd/m';
    }

    /** A share as a whole percent; a small but real share reads "<1" rather than "0". */
    private static function pct($part, $whole)
    {
        if ($whole == 0) {
            return 0;
        }
        $p = $part / $whole * 100;

        return $p > 0 && $p < 0.5 ? '<1' : round($p);
    }

    /**
     * One point per day (or per month for ranges over 62 days), zero-filled.
     * $by_day is [Y-m-d => total], or with $average [Y-m-d => [samples]]: then each point is the
     * mean of its samples, so a month's average wait is not the sum of its daily averages.
     */
    private static function series($f, array $by_day, $average = false)
    {
        $days = $f['from']->diffInDays($f['to']) + 1;
        $value = function ($v) use ($average) {
            if (! $average) {
                return $v;
            }

            return $v ? round(array_sum($v) / count($v)) : 0;
        };
        $points = [];
        if ($days <= 62) {
            for ($d = $f['from']->copy(); $d->lte($f['to']); $d->addDay()) {
                $v = $by_day[$d->toDateString()] ?? ($average ? [] : 0);
                $points[] = ['label' => $d->format(self::dayMonth()), 'value' => $value($v), 'tip' => $d->locale(app()->getLocale())->isoFormat('ddd D MMM')];
            }
        } else {
            $months = [];
            foreach ($by_day as $day => $v) {
                $m = substr($day, 0, 7);
                $months[$m] = $average ? array_merge($months[$m] ?? [], $v) : ($months[$m] ?? 0) + $v;
            }
            for ($d = $f['from']->copy()->startOfMonth(); $d->lte($f['to']); $d->addMonth()) {
                $points[] = ['label' => $d->format('m/Y'), 'value' => $value($months[$d->format('Y-m')] ?? ($average ? [] : 0)), 'tip' => $d->locale(app()->getLocale())->isoFormat('MMMM YYYY')];
            }
        }

        return $points;
    }

    private static function median(array $values)
    {
        if (empty($values)) {
            return null;
        }
        sort($values);
        $n = count($values);

        return $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
    }

    /** SQL: quantity given so far on a prescription line (same rule as the dispensary). */
    private static function givenSql()
    {
        // from the prescription's sell lines to their sale, not every sale of the shop (see Dispensing::waitingCountSql)
        return "(COALESCE(rx.legacy_given, 0) + COALESCE((SELECT SUM(tsl.quantity - tsl.quantity_returned) FROM transaction_sell_lines tsl
            STRAIGHT_JOIN transactions t ON t.id = tsl.transaction_id
            WHERE tsl.consultation_prescription_id = rx.id AND IFNULL(tsl.parent_sell_line_id, -1) = -1
            AND t.type = 'sell' AND t.status = 'final'), 0))";
    }

    private static function drugName($product, $variation)
    {
        return $variation && $variation !== 'DUMMY' ? $product.' - '.$variation : $product;
    }

    // ================================================================== 1. consultations

    public static function consultations($b, $f)
    {
        $q = DB::table('consultations as co')
            ->join('contacts as c', 'c.id', '=', 'co.contact_id')
            ->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'co.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'co.consulted_by')
            ->where('co.business_id', $b)->whereNull('co.deleted_at')
            ->whereBetween('co.created_at', self::between($f));
        if ($f['doctor_id']) {
            $q->where('co.consulted_by', $f['doctor_id']);
        }
        if ($f['q'] !== '') {
            $q->where('co.diagnosis', 'like', '%'.$f['q'].'%');
        }
        $rows = $q->orderByDesc('co.created_at')->select(
            'co.id', 'co.created_at', 'co.status', 'co.diagnosis', 'co.chief_complaint', 'co.contact_id', 'c.name', 'pd.file_number',
            'u.surname', 'u.first_name', 'u.last_name',
            DB::raw('(SELECT MIN(x.id) FROM consultations x WHERE x.contact_id = co.contact_id AND x.business_id = co.business_id AND x.deleted_at IS NULL) as first_id')
        )->get();

        $by_day = [];
        $patients = [];
        $new = [];
        foreach ($rows as $r) {
            $day = substr($r->created_at, 0, 10);
            $by_day[$day] = ($by_day[$day] ?? 0) + 1;
            $patients[$r->contact_id] = true;
            if ($r->first_id == $r->id) {
                $new[$r->contact_id] = true;
            }
        }
        $days_worked = count($by_day);

        $list = [];
        foreach ($rows->take(self::MAX_ROWS) as $r) {
            $list[] = [
                'date' => self::d($r->created_at, true),
                'patient' => ['text' => $r->name, 'url' => action([\App\Http\Controllers\ConsultationController::class, 'show'], [$r->id])],
                'file' => $r->file_number,
                'kind' => $r->first_id == $r->id ? ['text' => __('clinic.rep_new'), 'tone' => 'blue'] : ['text' => __('clinic.rep_returning'), 'tone' => 'slate'],
                'complaint' => \Illuminate\Support\Str::limit((string) $r->chief_complaint, 60),
                'diagnosis' => \Illuminate\Support\Str::limit((string) $r->diagnosis, 80) ?: ['text' => __('clinic.rep_no_diagnosis'), 'tone' => 'amber'],
                'doctor' => self::userName($r->first_name, $r->surname, $r->last_name),
                'status' => $r->status == 'final' ? ['text' => __('clinic.rep_finalised'), 'tone' => 'green'] : ['text' => __('clinic.rep_draft'), 'tone' => 'amber'],
            ];
        }

        $columns = [['key' => 'date', 'label' => __('lang_v1.date')], ['key' => 'patient', 'label' => __('clinic.rep_patient')], ['key' => 'file', 'label' => __('clinic.rep_file_no')],
            ['key' => 'kind', 'label' => __('clinic.rep_new_or_returning')], ['key' => 'complaint', 'label' => __('clinic.rep_complaint')], ['key' => 'diagnosis', 'label' => __('clinic.rep_diagnosis')]];
        if (! $f['doctor_id']) {
            $columns[] = ['key' => 'doctor', 'label' => __('clinic.rep_doctor')];
        }
        $columns[] = ['key' => 'status', 'label' => __('sale.status')];

        return [
            'tiles' => [
                ['label' => __('clinic.rep_consultations'), 'value' => $rows->count(), 'type' => 'number', 'tone' => 'green', 'icon' => 'stethoscope'],
                ['label' => __('clinic.rep_patients_seen'), 'value' => count($patients), 'type' => 'number', 'tone' => 'blue', 'icon' => 'users'],
                ['label' => __('clinic.rep_new_patients'), 'value' => count($new), 'type' => 'number', 'tone' => 'violet', 'icon' => 'user-plus',
                    'sub' => count($patients) ? __('clinic.rep_returning_n', ['n' => count($patients) - count($new)]) : null],
                ['label' => __('clinic.rep_per_day'), 'value' => $days_worked ? round($rows->count() / $days_worked, 1) : 0, 'type' => 'decimal', 'tone' => 'slate', 'icon' => 'calendar-event',
                    'sub' => __('clinic.rep_days_worked_n', ['n' => $days_worked])],
            ],
            'charts' => [['title' => __('clinic.rep_consultations_per_day'), 'type' => 'number', 'points' => self::series($f, $by_day)]],
            'tables' => [[
                'id' => 'consultations', 'title' => __('clinic.rep_consultation_list'), 'columns' => $columns, 'rows' => $list,
                'empty' => __('clinic.rep_none_in_period'),
                'note' => $rows->count() > self::MAX_ROWS ? __('clinic.rep_first_n', ['n' => self::MAX_ROWS]) : null,
            ]],
        ];
    }

    // ================================================================== 2. diagnoses

    public static function normaliseDiagnosis($text)
    {
        $t = mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $text)));

        return rtrim($t, " .,;:");
    }

    public static function diagnoses($b, $f)
    {
        $q = DB::table('consultations as co')->where('co.business_id', $b)->whereNull('co.deleted_at')->where('co.status', 'final')
            ->whereBetween('co.created_at', self::between($f));
        if ($f['doctor_id']) {
            $q->where('co.consulted_by', $f['doctor_id']);
        }
        $rows = $q->select('co.id', 'co.contact_id', 'co.diagnosis', 'co.created_at')->get();

        $groups = [];
        $missing = 0;
        foreach ($rows as $r) {
            $key = self::normaliseDiagnosis($r->diagnosis);
            if ($key === '') {
                $missing++;

                continue;
            }
            $g = $groups[$key] ?? ['n' => 0, 'patients' => [], 'last' => null, 'forms' => []];
            $g['n']++;
            $g['patients'][$r->contact_id] = true;
            $g['last'] = max($g['last'], $r->created_at);
            $form = trim(preg_replace('/\s+/u', ' ', $r->diagnosis));
            $g['forms'][$form] = ($g['forms'][$form] ?? 0) + 1;
            $groups[$key] = $g;
        }
        uasort($groups, function ($a, $b) {
            return $b['n'] <=> $a['n'];
        });
        $with = $rows->count() - $missing;
        $list = [];
        foreach ($groups as $g) {
            arsort($g['forms']);
            $label = array_key_first($g['forms']);
            $list[] = [
                'diagnosis' => ['text' => $label, 'url' => action([\App\Http\Controllers\ClinicReportController::class, 'show'], ['consultations'])
                    .'?'.http_build_query(['from' => $f['from']->toDateString(), 'to' => $f['to']->toDateString(), 'doctor_id' => $f['doctor_id'] ?: null, 'q' => $label])],
                'count' => $g['n'],
                'patients' => count($g['patients']),
                'share' => $with ? $g['n'] / $with * 100 : 0,
                'last' => self::d($g['last']),
            ];
        }

        return [
            'notes' => [__('clinic.rep_diagnoses_note')],
            'tiles' => [
                ['label' => __('clinic.rep_finalised_consultations'), 'value' => $rows->count(), 'type' => 'number', 'tone' => 'green', 'icon' => 'stethoscope'],
                ['label' => __('clinic.rep_different_diagnoses'), 'value' => count($groups), 'type' => 'number', 'tone' => 'blue', 'icon' => 'report-medical'],
                ['label' => __('clinic.rep_without_diagnosis'), 'value' => $missing, 'type' => 'number', 'tone' => $missing ? 'amber' : 'slate', 'icon' => 'clipboard-list',
                    'sub' => $missing ? __('clinic.rep_without_diagnosis_sub') : null],
            ],
            'charts' => [],
            'tables' => [[
                'id' => 'diagnoses', 'title' => __('clinic.rep_most_common_diagnoses'), 'rows' => array_slice($list, 0, self::MAX_ROWS),
                'columns' => [['key' => 'diagnosis', 'label' => __('clinic.rep_diagnosis')], ['key' => 'count', 'label' => __('clinic.rep_consultations'), 'type' => 'number'],
                    ['key' => 'patients', 'label' => __('clinic.rep_patients'), 'type' => 'number'], ['key' => 'share', 'label' => __('clinic.rep_share'), 'type' => 'pct'],
                    ['key' => 'last', 'label' => __('clinic.rep_last_seen')]],
                'empty' => __('clinic.rep_none_in_period'),
            ]],
        ];
    }

    // ================================================================== 3. prescribed vs given

    public static function prescriptions($b, $f)
    {
        $q = DB::table('consultation_prescriptions as rx')
            ->join('consultations as co', 'co.id', '=', 'rx.consultation_id')
            ->leftJoin('products as p', 'p.id', '=', 'rx.product_id')
            ->leftJoin('variations as v', 'v.id', '=', 'rx.variation_id')
            ->where('co.business_id', $b)->whereNull('co.deleted_at')->where('co.status', 'final')
            ->whereBetween('co.created_at', self::between($f));
        if ($f['doctor_id']) {
            $q->where('co.consulted_by', $f['doctor_id']);
        }
        $lines = $q->select('rx.id', 'rx.variation_id', 'rx.product_id', 'rx.quantity', 'rx.closed_at', 'rx.close_reason', 'co.contact_id', 'co.id as consultation_id',
            'p.name as product', 'v.name as variation', DB::raw(self::givenSql().' as given'))->get();

        $drugs = [];
        $tot = ['lines' => 0, 'full' => 0, 'part' => 0, 'none' => 0, 'oos' => 0, 'waiting' => 0, 'qty' => 0, 'given' => 0];
        $reasons = \App\Http\Controllers\ClinicDispensaryController::CLOSE_REASONS;
        foreach ($lines as $l) {
            $qty = (float) $l->quantity;
            $given = max(0, (float) $l->given);
            $given_capped = min($given, $qty);
            $key = $l->variation_id ?: 'p'.$l->product_id;
            $d = $drugs[$key] ?? ['name' => self::drugName($l->product, $l->variation), 'lines' => 0, 'patients' => [], 'qty' => 0, 'given' => 0, 'waiting' => 0, 'reasons' => array_fill_keys($reasons, 0)];
            $d['lines']++;
            $d['patients'][$l->contact_id] = true;
            $d['qty'] += $qty;
            $d['given'] += $given_capped;
            $tot['lines']++;
            $tot['qty'] += $qty;
            $tot['given'] += $given_capped;
            if ($given >= $qty) {
                $tot['full']++;
            } elseif ($given > 0) {
                $tot['part']++;
            } else {
                $tot['none']++;
            }
            if ($given < $qty) {
                if ($l->closed_at) {
                    $code = trim(explode(':', (string) $l->close_reason)[0]);
                    $code = in_array($code, $reasons) ? $code : 'other';
                    $d['reasons'][$code]++;
                    if ($code == 'out_of_stock') {
                        $tot['oos']++;
                    }
                } else {
                    $d['waiting'] += $qty - $given;
                    $tot['waiting']++;
                }
            }
            $drugs[$key] = $d;
        }
        uasort($drugs, function ($a, $b) {
            return $b['lines'] <=> $a['lines'];
        });

        $rows = [];
        $oos = [];
        foreach ($drugs as $d) {
            $why = [];
            foreach ($d['reasons'] as $code => $n) {
                if ($n) {
                    $why[] = __('clinic.rx_reason_'.$code).' ('.$n.')';
                }
            }
            $rows[] = [
                'drug' => $d['name'], 'lines' => $d['lines'], 'patients' => count($d['patients']),
                'qty' => $d['qty'], 'given' => $d['given'], 'pct' => $d['qty'] > 0 ? $d['given'] / $d['qty'] * 100 : 0,
                'waiting' => $d['waiting'] ?: '', 'why' => implode(', ', $why),
            ];
            if ($d['reasons']['out_of_stock']) {
                $oos[] = ['drug' => $d['name'], 'times' => $d['reasons']['out_of_stock'], 'lines' => $d['lines']];
            }
        }
        usort($oos, function ($a, $b) {
            return $b['times'] <=> $a['times'];
        });

        return [
            'tiles' => [
                ['label' => __('clinic.rep_rx_lines'), 'value' => $tot['lines'], 'type' => 'number', 'tone' => 'teal', 'icon' => 'pill',
                    'sub' => __('clinic.rep_rx_consultations_n', ['n' => $lines->pluck('consultation_id')->unique()->count()])],
                ['label' => __('clinic.rep_fully_given'), 'value' => $tot['lines'] ? $tot['full'] / $tot['lines'] * 100 : 0, 'type' => 'pct', 'tone' => 'green', 'icon' => 'shield-check',
                    'sub' => __('clinic.rep_partly_given_n', ['n' => $tot['part']])],
                ['label' => __('clinic.rep_not_given'), 'value' => $tot['none'], 'type' => 'number', 'tone' => $tot['none'] ? 'red' : 'slate', 'icon' => 'clipboard-list',
                    'sub' => __('clinic.rep_still_waiting_n', ['n' => $tot['waiting']])],
                ['label' => __('clinic.rep_out_of_stock'), 'value' => $tot['oos'], 'type' => 'number', 'tone' => $tot['oos'] ? 'amber' : 'slate', 'icon' => 'building-store',
                    'sub' => __('clinic.rep_out_of_stock_sub')],
            ],
            'charts' => [],
            'tables' => [
                [
                    'id' => 'out_of_stock', 'title' => __('clinic.rep_out_of_stock_title'), 'note' => __('clinic.rep_out_of_stock_note'),
                    'columns' => [['key' => 'drug', 'label' => __('clinic.rep_medicine')], ['key' => 'times', 'label' => __('clinic.rep_times_out_of_stock'), 'type' => 'number'],
                        ['key' => 'lines', 'label' => __('clinic.rep_times_prescribed'), 'type' => 'number']],
                    'rows' => $oos, 'empty' => __('clinic.rep_no_out_of_stock'),
                ],
                [
                    'id' => 'medicines', 'title' => __('clinic.rep_prescribed_vs_given'),
                    'columns' => [['key' => 'drug', 'label' => __('clinic.rep_medicine')], ['key' => 'lines', 'label' => __('clinic.rep_times_prescribed'), 'type' => 'number'],
                        ['key' => 'patients', 'label' => __('clinic.rep_patients'), 'type' => 'number'], ['key' => 'qty', 'label' => __('clinic.rep_qty_prescribed'), 'type' => 'qty'],
                        ['key' => 'given', 'label' => __('clinic.rep_qty_given'), 'type' => 'qty'], ['key' => 'pct', 'label' => __('clinic.rep_given_pct'), 'type' => 'pct'],
                        ['key' => 'waiting', 'label' => __('clinic.rep_qty_waiting'), 'type' => 'qty'], ['key' => 'why', 'label' => __('clinic.rep_why_not_given')]],
                    'rows' => array_slice($rows, 0, self::MAX_ROWS), 'empty' => __('clinic.rep_none_in_period'),
                ],
            ],
        ];
    }

    // ================================================================== 4. lab results

    public static function lab($b, $f)
    {
        $base = DB::table('lab_requests as lr')
            ->leftJoin('consultations as co', 'co.id', '=', 'lr.consultation_id')
            ->leftJoin('contacts as c', 'c.id', '=', 'lr.contact_id')
            ->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'lr.contact_id')
            ->where('lr.business_id', $b)->whereNull('lr.deleted_at')
            ->whereBetween('lr.requested_at', self::between($f));
        if ($f['doctor_id']) {
            $base->whereRaw('COALESCE(co.consulted_by, lr.requested_by) = ?', [$f['doctor_id']]);
        }
        $requests = (clone $base)->select('lr.id', 'lr.ref_no', 'lr.status', 'lr.requested_at', 'lr.resulted_at', 'c.name', 'pd.file_number',
            DB::raw('(SELECT COUNT(*) FROM lab_request_lines l WHERE l.lab_request_id = lr.id) as tests'))->orderBy('lr.requested_at')->get();
        $ids = $requests->pluck('id')->all();
        $tests = empty($ids) ? collect() : DB::table('lab_request_lines')->whereIn('lab_request_id', $ids)
            ->select('lab_request_id', 'test_name', 'result_value', 'unit', 'normal_range', 'result_flag', 'resulted_at')->get();
        $by_id = $requests->keyBy('id');

        $abnormal = [];
        foreach ($tests->whereIn('result_flag', ['critical', 'high', 'low'])->sortBy(function ($t) {
            return array_search($t->result_flag, ['critical', 'high', 'low']).'-'.(9999999999 - strtotime((string) $t->resulted_at));
        }) as $t) {
            $r = $by_id[$t->lab_request_id];
            $abnormal[] = [
                'date' => $t->resulted_at ? self::d($t->resulted_at, true) : '',
                'patient' => ['text' => $r->name, 'url' => action([\App\Http\Controllers\LabRequestController::class, 'show'], [$r->id])],
                'file' => $r->file_number,
                'test' => $t->test_name,
                'result' => trim($t->result_value.' '.$t->unit),
                'range' => $t->normal_range,
                'flag' => ['text' => __('lang_v1.lab_flag_'.$t->result_flag), 'tone' => $t->result_flag == 'critical' ? 'red' : 'amber'],
            ];
        }
        $pending = [];
        $now = Carbon::now();
        foreach ($requests->whereIn('status', ['pending', 'sample_collected', 'processing']) as $r) {
            $hours = Carbon::parse($r->requested_at)->diffInHours($now);
            $pending[] = [
                'date' => self::d($r->requested_at, true),
                'patient' => ['text' => $r->name, 'url' => action([\App\Http\Controllers\LabRequestController::class, 'show'], [$r->id])],
                'file' => $r->file_number, 'ref' => $r->ref_no, 'tests' => (int) $r->tests,
                'status' => ['text' => __('lang_v1.lab_status_'.$r->status), 'tone' => 'amber'],
                'age' => $hours >= 48 ? ['text' => __('clinic.rep_days_n', ['n' => intdiv($hours, 24)]), 'tone' => 'red'] : __('clinic.rep_hours_n', ['n' => $hours]),
            ];
        }
        $tat = [];
        foreach ($requests as $r) {
            if ($r->resulted_at) {
                $tat[] = Carbon::parse($r->requested_at)->diffInMinutes(Carbon::parse($r->resulted_at)) / 60;
            }
        }
        $popular = [];
        foreach ($tests->groupBy('test_name') as $name => $g) {
            $popular[] = ['test' => $name, 'n' => $g->count(), 'abnormal' => $g->whereIn('result_flag', ['critical', 'high', 'low'])->count()];
        }
        usort($popular, function ($a, $b) {
            return $b['n'] <=> $a['n'];
        });

        return [
            'tiles' => [
                ['label' => __('clinic.rep_lab_requests'), 'value' => $requests->count(), 'type' => 'number', 'tone' => 'violet', 'icon' => 'microscope',
                    'sub' => __('clinic.rep_tests_n', ['n' => $tests->count()])],
                ['label' => __('clinic.rep_abnormal_results'), 'value' => count($abnormal), 'type' => 'number', 'tone' => count($abnormal) ? 'red' : 'slate', 'icon' => 'heartbeat',
                    'sub' => __('clinic.rep_critical_n', ['n' => $tests->where('result_flag', 'critical')->count()])],
                ['label' => __('clinic.rep_waiting_results'), 'value' => count($pending), 'type' => 'number', 'tone' => count($pending) ? 'amber' : 'slate', 'icon' => 'test-pipe'],
                ['label' => __('clinic.rep_turnaround'), 'value' => $tat ? round(array_sum($tat) / count($tat), 1) : null, 'type' => 'hours', 'tone' => 'blue', 'icon' => 'calendar-time',
                    'sub' => __('clinic.rep_turnaround_sub')],
            ],
            'charts' => [],
            'tables' => [
                ['id' => 'abnormal', 'title' => __('clinic.rep_abnormal_results'), 'note' => __('clinic.rep_abnormal_note'), 'rows' => $abnormal, 'empty' => __('clinic.rep_no_abnormal'),
                    'columns' => [['key' => 'date', 'label' => __('clinic.rep_result_date')], ['key' => 'patient', 'label' => __('clinic.rep_patient')], ['key' => 'file', 'label' => __('clinic.rep_file_no')],
                        ['key' => 'test', 'label' => __('clinic.rep_test')], ['key' => 'result', 'label' => __('clinic.rep_result')], ['key' => 'range', 'label' => __('clinic.rep_normal_range')],
                        ['key' => 'flag', 'label' => __('clinic.rep_flag')]]],
                ['id' => 'pending', 'title' => __('clinic.rep_waiting_results'), 'rows' => $pending, 'empty' => __('clinic.rep_no_waiting_results'),
                    'columns' => [['key' => 'date', 'label' => __('clinic.rep_requested')], ['key' => 'patient', 'label' => __('clinic.rep_patient')], ['key' => 'file', 'label' => __('clinic.rep_file_no')],
                        ['key' => 'ref', 'label' => __('clinic.rep_reference')], ['key' => 'tests', 'label' => __('clinic.rep_tests'), 'type' => 'number'],
                        ['key' => 'status', 'label' => __('sale.status')], ['key' => 'age', 'label' => __('clinic.rep_waiting_for')]]],
                ['id' => 'tests', 'title' => __('clinic.rep_most_requested_tests'), 'rows' => array_slice($popular, 0, 50), 'empty' => __('clinic.rep_none_in_period'),
                    'columns' => [['key' => 'test', 'label' => __('clinic.rep_test')], ['key' => 'n', 'label' => __('clinic.rep_times_requested'), 'type' => 'number'],
                        ['key' => 'abnormal', 'label' => __('clinic.rep_abnormal_results'), 'type' => 'number']]],
            ],
        ];
    }

    // ================================================================== 5. waiting time

    public static function waiting($b, $f)
    {
        [$d1, $d2] = self::dates($f);
        $q = DB::table('visits as v')->leftJoin('users as u', 'u.id', '=', 'v.doctor_id')
            ->where('v.business_id', $b)->whereNull('v.deleted_at')->whereNotNull('v.queue_no')
            ->whereBetween('v.visit_date', [$d1, $d2])->where('v.queue_status', '!=', 'cancelled');
        if ($f['doctor_id']) {
            $q->where('v.doctor_id', $f['doctor_id']);
        }
        $visits = $q->select('v.id', 'v.visit_date', 'v.doctor_id', 'v.queue_status', 'v.checked_in_at', 'v.called_at', 'v.seen_at', 'u.surname', 'u.first_name', 'u.last_name')->get();

        $waits = [];
        $consults = [];
        $days = [];
        $docs = [];
        foreach ($visits as $v) {
            $day = substr($v->visit_date, 0, 10);
            $d = $days[$day] ?? ['in' => 0, 'seen' => 0, 'no_show' => 0, 'waits' => [], 'consults' => []];
            $doc = $docs[$v->doctor_id] ?? ['name' => self::userName($v->first_name, $v->surname, $v->last_name), 'seen' => 0, 'waits' => [], 'consults' => []];
            $d['in']++;
            if ($v->queue_status == 'no_show') {
                $d['no_show']++;
            }
            if ($v->checked_in_at && $v->called_at) {
                $w = max(0, Carbon::parse($v->checked_in_at)->diffInMinutes(Carbon::parse($v->called_at), false));
                $waits[] = $w;
                $d['waits'][] = $w;
                $doc['waits'][] = $w;
                $d['seen']++;
                $doc['seen']++;
                if ($v->seen_at) {
                    $c = max(0, Carbon::parse($v->called_at)->diffInMinutes(Carbon::parse($v->seen_at), false));
                    $consults[] = $c;
                    $d['consults'][] = $c;
                    $doc['consults'][] = $c;
                }
            }
            $days[$day] = $d;
            $docs[$v->doctor_id] = $doc;
        }
        ksort($days);
        $avg = function ($a) {
            return $a ? round(array_sum($a) / count($a)) : null;
        };
        $day_rows = [];
        $chart = [];
        foreach ($days as $day => $d) {
            $chart[$day] = $d['waits'];
            $day_rows[] = ['date' => Carbon::parse($day)->locale(app()->getLocale())->isoFormat('ddd D MMM YYYY'), 'in' => $d['in'], 'seen' => $d['seen'], 'no_show' => $d['no_show'],
                'avg' => $avg($d['waits']), 'max' => $d['waits'] ? max($d['waits']) : null, 'consult' => $avg($d['consults'])];
        }
        $doc_rows = [];
        foreach ($docs as $doc) {
            $doc_rows[] = ['doctor' => $doc['name'], 'seen' => $doc['seen'], 'avg' => $avg($doc['waits']), 'median' => self::median($doc['waits']), 'consult' => $avg($doc['consults'])];
        }
        $long = count(array_filter($waits, function ($w) {
            return $w > 30;
        }));
        $no_shows = $visits->where('queue_status', 'no_show')->count();

        $tables = [['id' => 'days', 'title' => __('clinic.rep_wait_by_day'), 'rows' => array_reverse($day_rows), 'empty' => __('clinic.rep_none_in_period'),
            'columns' => [['key' => 'date', 'label' => __('lang_v1.date')], ['key' => 'in', 'label' => __('clinic.rep_checked_in'), 'type' => 'number'],
                ['key' => 'seen', 'label' => __('clinic.rep_called_in'), 'type' => 'number'], ['key' => 'no_show', 'label' => __('clinic.queue_no_show'), 'type' => 'number'],
                ['key' => 'avg', 'label' => __('clinic.rep_avg_wait'), 'type' => 'minutes'], ['key' => 'max', 'label' => __('clinic.rep_longest_wait'), 'type' => 'minutes'],
                ['key' => 'consult', 'label' => __('clinic.rep_avg_consultation'), 'type' => 'minutes']]]];
        if (! $f['doctor_id']) {
            $tables[] = ['id' => 'doctors', 'title' => __('clinic.rep_wait_by_doctor'), 'rows' => $doc_rows, 'empty' => __('clinic.rep_none_in_period'),
                'columns' => [['key' => 'doctor', 'label' => __('clinic.rep_doctor')], ['key' => 'seen', 'label' => __('clinic.rep_called_in'), 'type' => 'number'],
                    ['key' => 'avg', 'label' => __('clinic.rep_avg_wait'), 'type' => 'minutes'], ['key' => 'median', 'label' => __('clinic.rep_median_wait'), 'type' => 'minutes'],
                    ['key' => 'consult', 'label' => __('clinic.rep_avg_consultation'), 'type' => 'minutes']]];
        }

        return [
            'notes' => [__('clinic.rep_wait_note')],
            'tiles' => [
                ['label' => __('clinic.rep_avg_wait'), 'value' => $avg($waits), 'type' => 'minutes', 'tone' => 'amber', 'icon' => 'calendar-time',
                    'sub' => __('clinic.rep_median_n', ['n' => self::median($waits) !== null ? round(self::median($waits)) : '-'])],
                ['label' => __('clinic.rep_longest_wait'), 'value' => $waits ? max($waits) : null, 'type' => 'minutes', 'tone' => 'red', 'icon' => 'list-numbers'],
                ['label' => __('clinic.rep_waited_over_30'), 'value' => $waits ? $long / count($waits) * 100 : 0, 'type' => 'pct', 'tone' => $long ? 'amber' : 'slate', 'icon' => 'users',
                    'sub' => __('clinic.rep_patients_n', ['n' => $long])],
                ['label' => __('clinic.rep_avg_consultation'), 'value' => $avg($consults), 'type' => 'minutes', 'tone' => 'green', 'icon' => 'stethoscope',
                    'sub' => __('clinic.rep_seen_n', ['n' => count($waits)]).($no_shows ? ' · '.__('clinic.rep_no_shows_n', ['n' => $no_shows]) : '')],
            ],
            'charts' => [['title' => __('clinic.rep_avg_wait_per_day'), 'type' => 'minutes', 'points' => self::series($f, $chart, true)]],
            'tables' => $tables,
        ];
    }

    // ================================================================== 6. cash received

    public static function cash($b, $f)
    {
        $insurer_contacts = DB::table('clinic_insurers')->where('business_id', $b)->pluck('contact_id')->all();
        $rows = DB::table('transaction_payments as tp')
            ->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->leftJoin('users as u', 'u.id', '=', 'tp.created_by')
            ->where('t.business_id', $b)->whereIn('t.type', ['sell', 'sell_return'])
            ->whereBetween('tp.paid_on', self::between($f))
            ->select('tp.paid_on', 'tp.method', 'tp.created_by', 'tp.amount', 'tp.is_return', 't.type', 't.contact_id', 'c.is_patient',
                'u.surname', 'u.first_name', 'u.last_name')->get();
        $methods = (new Util())->payment_types(null, true, $b);

        $grid = [];
        $by_method = [];
        $by_user = [];
        $by_payer = ['patients' => 0, 'insurers' => 0, 'counter' => 0];
        $refunds = 0;
        $count = 0;
        foreach ($rows as $p) {
            // change handed back (is_return) and refunds on returns go out of the drawer
            $sign = ($p->type == 'sell_return' ? -1 : 1) * ($p->is_return ? -1 : 1);
            $amt = $sign * (float) $p->amount;
            $day = substr($p->paid_on, 0, 10);
            $m = $p->method ?: 'other';
            $grid[$day][$m] = ($grid[$day][$m] ?? 0) + $amt;
            $by_method[$m] = ($by_method[$m] ?? 0) + $amt;
            $u = $by_user[$p->created_by] ?? ['name' => self::userName($p->first_name, $p->surname, $p->last_name), 'n' => 0, 'total' => 0];
            $u['total'] += $amt;
            if (! $p->is_return && $p->type == 'sell') {
                $u['n']++;
                $count++;
            }
            $by_user[$p->created_by] = $u;
            if ($p->type == 'sell_return') {
                $refunds += (float) $p->amount;
            }
            $payer = in_array($p->contact_id, $insurer_contacts) ? 'insurers' : ($p->is_patient ? 'patients' : 'counter');
            $by_payer[$payer] += $amt;
        }
        krsort($grid);
        arsort($by_method);
        $used = array_keys($by_method);
        $columns = [['key' => 'date', 'label' => __('lang_v1.date')]];
        foreach ($used as $m) {
            $columns[] = ['key' => 'm_'.$m, 'label' => $methods[$m] ?? ucfirst($m), 'type' => 'money'];
        }
        $columns[] = ['key' => 'total', 'label' => __('sale.total'), 'type' => 'money', 'strong' => true];
        $day_rows = [];
        $chart = [];
        foreach ($grid as $day => $ms) {
            $row = ['date' => Carbon::parse($day)->locale(app()->getLocale())->isoFormat('ddd D MMM YYYY')];
            foreach ($used as $m) {
                $row['m_'.$m] = $ms[$m] ?? 0;
            }
            $row['total'] = array_sum($ms);
            $chart[$day] = $row['total'];
            $day_rows[] = $row;
        }
        $total_row = ['date' => __('sale.total')];
        foreach ($used as $m) {
            $total_row['m_'.$m] = $by_method[$m];
        }
        $total_row['total'] = array_sum($by_method);
        $total = array_sum($by_method);
        $cash = $by_method['cash'] ?? 0;

        return [
            'notes' => [__('clinic.rep_cash_note')],
            'tiles' => [
                ['label' => __('clinic.rep_total_received'), 'value' => $total, 'type' => 'money', 'tone' => 'green', 'icon' => 'cash',
                    'sub' => __('clinic.rep_payments_n', ['n' => $count])],
                ['label' => $methods['cash'] ?? __('lang_v1.cash'), 'value' => $cash, 'type' => 'money', 'tone' => 'teal', 'icon' => 'cash'],
                ['label' => __('clinic.rep_other_methods'), 'value' => $total - $cash, 'type' => 'money', 'tone' => 'blue', 'icon' => 'receipt'],
                ['label' => __('clinic.rep_refunds'), 'value' => $refunds, 'type' => 'money', 'tone' => $refunds ? 'red' : 'slate', 'icon' => 'receipt'],
            ],
            'charts' => [['title' => __('clinic.rep_received_per_day'), 'type' => 'money', 'points' => self::series($f, $chart)]],
            'tables' => [
                ['id' => 'days', 'title' => __('clinic.rep_received_by_day'), 'columns' => $columns, 'rows' => $day_rows, 'total' => $day_rows ? $total_row : null, 'empty' => __('clinic.rep_none_in_period')],
                ['id' => 'cashiers', 'title' => __('clinic.rep_received_by_cashier'), 'empty' => __('clinic.rep_none_in_period'),
                    'rows' => array_values(array_map(function ($u) {
                        return ['name' => $u['name'], 'n' => $u['n'], 'total' => $u['total']];
                    }, $by_user)),
                    'columns' => [['key' => 'name', 'label' => __('clinic.rep_cashier')], ['key' => 'n', 'label' => __('clinic.rep_payments'), 'type' => 'number'],
                        ['key' => 'total', 'label' => __('clinic.rep_received'), 'type' => 'money']]],
                ['id' => 'payers', 'title' => __('clinic.rep_received_from'), 'empty' => __('clinic.rep_none_in_period'),
                    'rows' => $rows->isEmpty() ? [] : [
                        ['who' => __('clinic.rep_from_patients'), 'total' => $by_payer['patients']],
                        ['who' => __('clinic.rep_from_insurers'), 'total' => $by_payer['insurers']],
                        ['who' => __('clinic.rep_from_counter'), 'total' => $by_payer['counter']],
                    ],
                    'columns' => [['key' => 'who', 'label' => __('clinic.rep_payer')], ['key' => 'total', 'label' => __('clinic.rep_received'), 'type' => 'money']]],
            ],
        ];
    }

    // ================================================================== 7. income by doctor and service

    const CATEGORIES = ['consultations', 'plans', 'lab', 'imaging', 'procedures', 'drugs', 'other'];

    private static function category($sku, $is_plan)
    {
        $sku = (string) $sku;
        if (strpos($sku, 'SVC-PKG-') === 0) {
            return $is_plan ? 'plans' : 'consultations';
        }
        switch ($sku) {
            case 'SVC-CONSULT':
                return 'consultations';
            case 'SVC-LAB':
                return 'lab';
            case 'SVC-IMAGING':
                return 'imaging';
            case 'SVC-PROCEDURE':
                return 'procedures';
            case 'SVC-PHARMACY':
            case 'SVC-WARD':
                return 'drugs';
        }

        return strpos($sku, 'SVC-') === 0 ? 'other' : 'drugs';
    }

    public static function income($b, $f)
    {
        $txs = DB::table('transactions as t')
            ->leftJoin('visits as v', 'v.id', '=', 't.visit_id')
            ->leftJoin('consultations as co', 'co.id', '=', 't.consultation_id')
            ->where('t.business_id', $b)->where('t.type', 'sell')->where('t.status', 'final')
            ->whereBetween('t.transaction_date', self::between($f))
            ->select('t.id', 't.final_total', 't.contact_id', 't.visit_id', DB::raw('COALESCE(v.doctor_id, co.consulted_by) as doctor_id'))
            ->get()->keyBy('id');
        $ids = $txs->keys()->all();
        $lines = empty($ids) ? collect() : DB::table('transaction_sell_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')
            ->whereIn('l.transaction_id', $ids)->whereNull('l.parent_sell_line_id')
            ->select('l.id', 'l.transaction_id', 'l.quantity', 'l.quantity_returned', 'l.unit_price_inc_tax', 'p.sku', 'p.name')
            ->orderBy('l.id')->get()->groupBy('transaction_id');
        $plan_tx = empty($ids) ? [] : array_flip(DB::table('clinic_subscription_periods')->whereIn('transaction_id', $ids)->pluck('transaction_id')->all());
        $shares = empty($ids) ? collect() : DB::table('clinic_insurance_shares')->whereIn('insurer_transaction_id', $ids)->get()->keyBy('insurer_transaction_id');

        // the patient invoices behind the insurer invoices (may be outside the period)
        $pt_ids = $shares->pluck('patient_transaction_id')->filter()->all();
        $pt_lines = empty($pt_ids) ? collect() : DB::table('transaction_sell_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')
            ->whereIn('l.transaction_id', $pt_ids)->whereNull('l.parent_sell_line_id')
            ->select('l.transaction_id', 'p.sku', 'p.name')->orderBy('l.id')->get()->groupBy('transaction_id');
        $pt_doctor = empty($pt_ids) ? collect() : DB::table('transactions as t')->leftJoin('visits as v', 'v.id', '=', 't.visit_id')
            ->leftJoin('consultations as co', 'co.id', '=', 't.consultation_id')->whereIn('t.id', $pt_ids)
            ->select('t.id', DB::raw('COALESCE(v.doctor_id, co.consulted_by) as doc'))->pluck('doc', 'id');
        $pt_plan = empty($pt_ids) ? [] : array_flip(DB::table('clinic_subscription_periods')->whereIn('transaction_id', $pt_ids)->pluck('transaction_id')->all());

        $cat = array_fill_keys(self::CATEGORIES, ['patients' => 0, 'insurers' => 0]);
        $docs = [];
        $packages = [];
        $add = function ($doctor, $category, $amount, $who) use (&$cat, &$docs) {
            $cat[$category][$who] += $amount;
            $key = $doctor ?: ($category == 'plans' ? 'plans' : 'none');
            $docs[$key][$category] = ($docs[$key][$category] ?? 0) + $amount;
        };

        foreach ($txs as $t) {
            if (isset($shares[$t->id])) {
                // an insurer's invoice: its amount belongs to the services of the patient invoice behind it
                $s = $shares[$t->id];
                $detail = json_decode($s->lines, true) ?: [];
                $plines = ($pt_lines[$s->patient_transaction_id] ?? collect())->values();
                $sum = array_sum(array_column($detail, 'share'));
                $scale = $sum > 0 ? (float) $t->final_total / $sum : 0;
                $doctor = $pt_doctor[$s->patient_transaction_id] ?? null;
                foreach ($detail as $i => $d) {
                    $pl = $plines[$i] ?? null;
                    $c = $pl ? self::category($pl->sku, isset($pt_plan[$s->patient_transaction_id])) : 'other';
                    $add($doctor, $c, (float) $d['share'] * $scale, 'insurers');
                    if ($pl && strpos((string) $pl->sku, 'SVC-PKG-') === 0) {
                        $packages[$pl->name]['amount'] = ($packages[$pl->name]['amount'] ?? 0) + (float) $d['share'] * $scale;
                    }
                }
                if (empty($detail)) {
                    $add($doctor, 'other', (float) $t->final_total, 'insurers');
                }

                continue;
            }
            $tl = $lines[$t->id] ?? collect();
            $gross = $tl->sum(function ($l) {
                return (float) $l->quantity * (float) $l->unit_price_inc_tax;
            });
            if ($gross <= 0) {
                if ((float) $t->final_total != 0) {
                    $add($t->doctor_id, 'other', (float) $t->final_total, 'patients');
                }

                continue;
            }
            // invoice discounts are shared over the lines; returned quantities are left out
            $factor = (float) $t->final_total / $gross;
            foreach ($tl as $l) {
                $value = ((float) $l->quantity - (float) $l->quantity_returned) * (float) $l->unit_price_inc_tax * $factor;
                $c = self::category($l->sku, isset($plan_tx[$t->id]));
                $add($t->doctor_id, $c, $value, 'patients');
                if (strpos((string) $l->sku, 'SVC-PKG-') === 0) {
                    $packages[$l->name]['n'] = ($packages[$l->name]['n'] ?? 0) + ((float) $l->quantity - (float) $l->quantity_returned);
                    $packages[$l->name]['amount'] = ($packages[$l->name]['amount'] ?? 0) + $value;
                }
            }
        }

        // consultations per doctor = visits checked in with them in the period
        [$d1, $d2] = self::dates($f);
        $visit_counts = DB::table('visits')->where('business_id', $b)->whereNull('deleted_at')->whereNotNull('queue_no')
            ->whereBetween('visit_date', [$d1, $d2])->whereNotIn('queue_status', ['cancelled', 'no_show'])
            ->groupBy('doctor_id')->select('doctor_id', DB::raw('COUNT(*) as n'))->pluck('n', 'doctor_id');
        $names = DB::table('users')->whereIn('id', array_merge(array_keys($docs), $visit_counts->keys()->all()))->get(['id', 'surname', 'first_name', 'last_name'])->keyBy('id');

        $grand = 0;
        $cat_rows = [];
        foreach (self::CATEGORIES as $c) {
            $tot = $cat[$c]['patients'] + $cat[$c]['insurers'];
            $grand += $tot;
        }
        foreach (self::CATEGORIES as $c) {
            $tot = $cat[$c]['patients'] + $cat[$c]['insurers'];
            if (abs($tot) < 0.005) {
                continue;
            }
            $cat_rows[] = ['service' => __('clinic.rep_cat_'.$c), 'total' => $tot, 'patients' => $cat[$c]['patients'], 'insurers' => $cat[$c]['insurers'],
                'share' => $grand ? $tot / $grand * 100 : 0];
        }
        $doc_rows = [];
        foreach ($docs as $key => $vals) {
            $n = is_numeric($key) && isset($names[$key]) ? self::userName($names[$key]->first_name, $names[$key]->surname, $names[$key]->last_name) : null;
            $doc_rows[] = [
                'doctor' => $n ?: ($key == 'plans' ? __('clinic.rep_doc_plans') : __('clinic.rep_doc_none')),
                'visits' => is_numeric($key) ? (int) ($visit_counts[$key] ?? 0) : '',
                'consultations' => ($vals['consultations'] ?? 0) + ($vals['plans'] ?? 0),
                'lab' => ($vals['lab'] ?? 0) + ($vals['imaging'] ?? 0),
                'drugs' => $vals['drugs'] ?? 0,
                'other' => ($vals['other'] ?? 0) + ($vals['procedures'] ?? 0),
                'total' => array_sum($vals),
                '_sort' => is_numeric($key) ? 0 : 1,
            ];
        }
        usort($doc_rows, function ($a, $b) {
            return [$a['_sort'], -$a['total']] <=> [$b['_sort'], -$b['total']];
        });
        $pkg_rows = [];
        foreach ($packages as $name => $p) {
            $pkg_rows[] = ['package' => $name, 'n' => $p['n'] ?? 0, 'amount' => $p['amount'] ?? 0];
        }
        usort($pkg_rows, function ($a, $b) {
            return $b['amount'] <=> $a['amount'];
        });
        $ins_total = array_sum(array_column($cat, 'insurers'));
        $chart = [];
        foreach ($cat_rows as $r) {
            $chart[] = ['label' => $r['service'], 'value' => $r['total'], 'tip' => $r['service']];
        }

        $money = function ($key, $label, $strong = false) {
            return ['key' => $key, 'label' => $label, 'type' => 'money', 'strong' => $strong];
        };

        return [
            'notes' => [__('clinic.rep_income_note')],
            'tiles' => [
                ['label' => __('clinic.rep_total_billed'), 'value' => $grand, 'type' => 'money', 'tone' => 'green', 'icon' => 'receipt',
                    'sub' => __('clinic.rep_invoices_n', ['n' => $txs->count()])],
                ['label' => __('clinic.rep_consultations_and_plans'), 'value' => $cat['consultations']['patients'] + $cat['consultations']['insurers'] + $cat['plans']['patients'] + $cat['plans']['insurers'], 'type' => 'money', 'tone' => 'blue', 'icon' => 'stethoscope'],
                ['label' => __('clinic.rep_cat_drugs'), 'value' => $cat['drugs']['patients'] + $cat['drugs']['insurers'], 'type' => 'money', 'tone' => 'teal', 'icon' => 'pill'],
                ['label' => __('clinic.rep_billed_to_insurers'), 'value' => $ins_total, 'type' => 'money', 'tone' => 'violet', 'icon' => 'shield-check',
                    'sub' => $grand ? __('clinic.rep_pct_of_total', ['pct' => self::pct($ins_total, $grand)]) : null],
            ],
            'charts' => [['title' => __('clinic.rep_income_by_service'), 'type' => 'money', 'horizontal' => true, 'points' => $chart]],
            'tables' => [
                ['id' => 'services', 'title' => __('clinic.rep_income_by_service'), 'rows' => $cat_rows, 'empty' => __('clinic.rep_none_in_period'),
                    'total' => $cat_rows ? ['service' => __('sale.total'), 'total' => $grand, 'patients' => array_sum(array_column($cat, 'patients')), 'insurers' => $ins_total, 'share' => 100] : null,
                    'columns' => [['key' => 'service', 'label' => __('clinic.rep_service')], $money('total', __('clinic.rep_billed'), true), $money('patients', __('clinic.rep_to_patients')),
                        $money('insurers', __('clinic.rep_to_insurers')), ['key' => 'share', 'label' => __('clinic.rep_share'), 'type' => 'pct']]],
                ['id' => 'doctors', 'title' => __('clinic.rep_income_by_doctor'), 'note' => __('clinic.rep_income_by_doctor_note'), 'rows' => $doc_rows, 'empty' => __('clinic.rep_none_in_period'),
                    'columns' => [['key' => 'doctor', 'label' => __('clinic.rep_doctor')], ['key' => 'visits', 'label' => __('clinic.rep_patients_seen'), 'type' => 'number'],
                        $money('consultations', __('clinic.rep_cat_consultations')), $money('lab', __('clinic.rep_cat_lab')), $money('drugs', __('clinic.rep_cat_drugs')),
                        $money('other', __('clinic.rep_cat_other')), $money('total', __('sale.total'), true)]],
                ['id' => 'packages', 'title' => __('clinic.rep_income_by_package'), 'rows' => $pkg_rows, 'empty' => __('clinic.rep_none_in_period'),
                    'columns' => [['key' => 'package', 'label' => __('clinic.rep_package')], ['key' => 'n', 'label' => __('clinic.rep_times_billed'), 'type' => 'number'],
                        $money('amount', __('clinic.rep_billed'), true)]],
            ],
        ];
    }

    // ================================================================== 8. who owes us

    public static function owing($b, $f)
    {
        $paid_sql = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp WHERE tp.transaction_id = t.id)';
        $invoices = DB::table('transactions as t')->join('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $b)->where('t.type', 'sell')->where('t.status', 'final')
            ->where('c.is_patient', 1)->whereNull('c.deleted_at')
            ->select('t.contact_id', 't.transaction_date', 't.final_total', DB::raw($paid_sql.' as paid'))->get();
        // a patient's balance is worked out like Patient accounts (all invoices minus all payments);
        // the ageing comes from the unpaid invoices, oldest paid off first by any credit
        $people = [];
        foreach ($invoices as $i) {
            $p = $people[$i->contact_id] ?? ['balance' => 0, 'open' => []];
            $p['balance'] += (float) $i->final_total - (float) $i->paid;
            $out = (float) $i->final_total - (float) $i->paid;
            if ($out > 0.004) {
                $p['open'][] = ['date' => $i->transaction_date, 'out' => $out];
            }
            $people[$i->contact_id] = $p;
        }
        $people = array_filter($people, function ($p) {
            return $p['balance'] > 0.004;
        });
        $info = empty($people) ? collect() : DB::table('contacts as c')->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'c.id')
            ->whereIn('c.id', array_keys($people))->get(['c.id', 'c.name', 'c.mobile', 'pd.file_number'])->keyBy('id');
        $last_pay = empty($people) ? collect() : DB::table('transaction_payments as tp')->join('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->whereIn('t.contact_id', array_keys($people))->where('tp.is_return', 0)->groupBy('t.contact_id')->select('t.contact_id', DB::raw('MAX(tp.paid_on) as last_paid'))->pluck('last_paid', 'contact_id');

        $today = Carbon::today();
        $buckets = ['b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0];
        $rows = [];
        foreach ($people as $id => $p) {
            usort($p['open'], function ($a, $b) {
                return strcmp($a['date'], $b['date']);
            });
            $credit = max(0, array_sum(array_column($p['open'], 'out')) - $p['balance']);
            $row = ['b30' => 0, 'b60' => 0, 'b90' => 0, 'b90p' => 0];
            $oldest = null;
            foreach ($p['open'] as $o) {
                $use = min($credit, $o['out']);
                $credit -= $use;
                $left = $o['out'] - $use;
                if ($left <= 0.004) {
                    continue;
                }
                $oldest = $oldest ?? $o['date'];
                $age = Carbon::parse($o['date'])->startOfDay()->diffInDays($today);
                $k = $age <= 30 ? 'b30' : ($age <= 60 ? 'b60' : ($age <= 90 ? 'b90' : 'b90p'));
                $row[$k] += $left;
            }
            foreach ($row as $k => $v) {
                $buckets[$k] += $v;
            }
            $c = $info[$id] ?? null;
            $rows[] = array_merge([
                'patient' => ['text' => $c->name ?? '', 'url' => action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$id])],
                'file' => $c->file_number ?? '', 'phone' => $c->mobile ?? '',
            ], $row, [
                'total' => $p['balance'],
                'oldest' => $oldest ? self::d($oldest) : '',
                'last_paid' => isset($last_pay[$id]) ? self::d($last_pay[$id]) : ['text' => __('clinic.rep_never'), 'tone' => 'amber'],
            ]);
        }
        usort($rows, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });
        $total = array_sum(array_column($rows, 'total'));

        $insurers_owe = (float) DB::table('transactions as t')->join('clinic_insurers as i', 'i.contact_id', '=', 't.contact_id')
            ->where('t.business_id', $b)->where('t.type', 'sell')->where('t.status', 'final')
            ->sum(DB::raw('t.final_total - '.$paid_sql));

        $money = function ($key, $label, $strong = false) {
            return ['key' => $key, 'label' => $label, 'type' => 'money', 'strong' => $strong];
        };

        return [
            'no_dates' => true,
            'notes' => [__('clinic.rep_owing_note')],
            'tiles' => [
                ['label' => __('clinic.rep_patients_owe'), 'value' => $total, 'type' => 'money', 'tone' => 'red', 'icon' => 'receipt',
                    'sub' => trans_choice('clinic.dash_patients_owing', count($rows), ['n' => count($rows)])],
                ['label' => __('clinic.rep_owed_over_60'), 'value' => $buckets['b90'] + $buckets['b90p'], 'type' => 'money', 'tone' => 'amber', 'icon' => 'calendar-time',
                    'sub' => $total ? __('clinic.rep_pct_of_total', ['pct' => self::pct($buckets['b90'] + $buckets['b90p'], $total)]) : null],
                ['label' => __('clinic.rep_insurers_owe'), 'value' => max(0, $insurers_owe), 'type' => 'money', 'tone' => 'violet', 'icon' => 'shield-check',
                    'url' => self::can(auth()->user(), 'insurers') ? action([\App\Http\Controllers\ClinicReportController::class, 'show'], ['insurers']) : null],
            ],
            'charts' => [],
            'tables' => [[
                'id' => 'owing', 'title' => __('clinic.rep_patients_owing_title'), 'rows' => array_slice($rows, 0, self::MAX_ROWS), 'empty' => __('clinic.dash_empty_bills'),
                'total' => $rows ? array_merge(['patient' => __('sale.total'), 'file' => '', 'phone' => ''], $buckets, ['total' => $total, 'oldest' => '', 'last_paid' => '']) : null,
                'columns' => [['key' => 'patient', 'label' => __('clinic.rep_patient')], ['key' => 'file', 'label' => __('clinic.rep_file_no')], ['key' => 'phone', 'label' => __('clinic.rep_phone')],
                    $money('b30', __('clinic.rep_age_30')), $money('b60', __('clinic.rep_age_60')), $money('b90', __('clinic.rep_age_90')), $money('b90p', __('clinic.rep_age_90p')),
                    $money('total', __('clinic.rep_owes'), true), ['key' => 'oldest', 'label' => __('clinic.rep_oldest_unpaid')], ['key' => 'last_paid', 'label' => __('clinic.rep_last_payment')]],
            ]],
        ];
    }

    // ================================================================== 9. insurers

    public static function insurers($b, $f)
    {
        $paid_sql = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp WHERE tp.transaction_id = t.id)';
        $insurers = DB::table('clinic_insurers')->where('business_id', $b)->whereNull('deleted_at')->orderBy('name')->get();
        $between = self::between($f);
        $rows = [];
        $tot = ['billed' => 0, 'received' => 0, 'owed' => 0, 'unclaimed' => 0];
        foreach ($insurers as $i) {
            $tx = DB::table('transactions as t')->where('t.business_id', $b)->where('t.contact_id', $i->contact_id)->where('t.type', 'sell')->where('t.status', 'final');
            $billed = (float) (clone $tx)->whereBetween('t.transaction_date', $between)->sum('t.final_total');
            $owed = (float) (clone $tx)->sum(DB::raw('t.final_total - '.$paid_sql));
            $received = (float) DB::table('transaction_payments as tp')->join('transactions as t', 't.id', '=', 'tp.transaction_id')
                ->where('t.contact_id', $i->contact_id)->where('t.type', 'sell')->whereBetween('tp.paid_on', $between)
                ->sum(DB::raw('IF(tp.is_return = 1, -1 * tp.amount, tp.amount)'));
            $unclaimed = (float) DB::table('clinic_insurance_shares as s')->join('transactions as t', 't.id', '=', 's.insurer_transaction_id')
                ->where('s.insurer_id', $i->id)->whereNull('s.claim_id')->where('t.status', 'final')->sum(DB::raw('t.final_total - '.$paid_sql));
            $patients = DB::table('clinic_insurance_shares')->where('insurer_id', $i->id)->whereBetween('created_at', $between)->distinct()->count('contact_id');
            if (! $i->is_active && $billed == 0 && abs($owed) < 0.005 && $received == 0) {
                continue;
            }
            $rows[] = ['insurer' => ['text' => $i->name, 'url' => action([\App\Http\Controllers\ClinicClaimController::class, 'index'])],
                'patients' => $patients, 'billed' => $billed, 'received' => $received, 'owed' => max(0, $owed), 'unclaimed' => max(0, $unclaimed)];
            $tot['billed'] += $billed;
            $tot['received'] += $received;
            $tot['owed'] += max(0, $owed);
            $tot['unclaimed'] += max(0, $unclaimed);
        }
        $money = function ($key, $label, $strong = false) {
            return ['key' => $key, 'label' => $label, 'type' => 'money', 'strong' => $strong];
        };

        return [
            'notes' => [__('clinic.rep_insurers_note')],
            'tiles' => [
                ['label' => __('clinic.rep_billed_to_insurers'), 'value' => $tot['billed'], 'type' => 'money', 'tone' => 'violet', 'icon' => 'shield-check'],
                ['label' => __('clinic.rep_received_from_insurers'), 'value' => $tot['received'], 'type' => 'money', 'tone' => 'green', 'icon' => 'cash'],
                ['label' => __('clinic.rep_insurers_owe'), 'value' => $tot['owed'], 'type' => 'money', 'tone' => 'red', 'icon' => 'receipt', 'sub' => __('clinic.rep_all_time')],
                ['label' => __('clinic.rep_not_claimed'), 'value' => $tot['unclaimed'], 'type' => 'money', 'tone' => $tot['unclaimed'] ? 'amber' : 'slate', 'icon' => 'clipboard-list',
                    'sub' => __('clinic.rep_not_claimed_sub'), 'url' => action([\App\Http\Controllers\ClinicClaimController::class, 'index'])],
            ],
            'charts' => [],
            'tables' => [['id' => 'insurers', 'title' => __('clinic.rep_by_insurer'), 'rows' => $rows, 'empty' => __('clinic.rep_no_insurers'),
                'total' => $rows ? ['insurer' => __('sale.total'), 'patients' => '', 'billed' => $tot['billed'], 'received' => $tot['received'], 'owed' => $tot['owed'], 'unclaimed' => $tot['unclaimed']] : null,
                'columns' => [['key' => 'insurer', 'label' => __('clinic.rep_insurer')], ['key' => 'patients', 'label' => __('clinic.rep_patients_covered'), 'type' => 'number'],
                    $money('billed', __('clinic.rep_billed_in_period')), $money('received', __('clinic.rep_received_in_period')),
                    $money('owed', __('clinic.rep_owes_now'), true), $money('unclaimed', __('clinic.rep_not_claimed'))]]],
        ];
    }

    // ================================================================== 10. monthly plans

    public static function plans($b, $f)
    {
        [$d1, $d2] = self::dates($f);
        $paid_sql = '(SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp WHERE tp.transaction_id = per.transaction_id)';
        $active = DB::table('clinic_subscriptions as s')->join('clinic_packages as p', 'p.id', '=', 's.package_id')
            ->where('s.business_id', $b)->where('s.status', 'active')
            ->groupBy('p.id', 'p.name', 'p.price')->select('p.name', 'p.price', DB::raw('COUNT(*) as n'))->orderByDesc('n')->get();
        $new = DB::table('clinic_subscriptions')->where('business_id', $b)->whereBetween('start_date', [$d1, $d2])->count();
        $cancelled = DB::table('clinic_subscriptions')->where('business_id', $b)->whereBetween('cancelled_at', self::between($f))->count();
        $periods = DB::table('clinic_subscription_periods as per')
            ->join('clinic_subscriptions as s', 's.id', '=', 'per.subscription_id')
            ->join('clinic_packages as p', 'p.id', '=', 's.package_id')
            ->join('contacts as c', 'c.id', '=', 'per.contact_id')
            ->leftJoin('patient_details as pd', 'pd.contact_id', '=', 'per.contact_id')
            ->leftJoin('transactions as t', 't.id', '=', 'per.transaction_id')
            ->where('per.business_id', $b)
            ->select('per.id', 'per.period_start', 'per.period_end', 'per.contact_id', 'c.name', 'pd.file_number', 'p.name as package',
                DB::raw('COALESCE(t.final_total, per.amount) as billed'), DB::raw($paid_sql.' as paid'))->get();
        $in_period = $periods->filter(function ($p) use ($d1, $d2) {
            return $p->period_start >= $d1 && $p->period_start <= $d2;
        });
        $billed = $in_period->sum('billed');
        $collected = $in_period->sum(function ($p) {
            return min((float) $p->paid, (float) $p->billed);
        });
        $unpaid_rows = [];
        $today = Carbon::today();
        foreach ($periods->sortBy('period_start') as $p) {
            $due = (float) $p->billed - (float) $p->paid;
            if ($due <= 0.004) {
                continue;
            }
            // a month billed ahead of time is not owed until it starts
            $started = Carbon::parse($p->period_start)->lte($today);
            $unpaid_rows[] = [
                'patient' => ['text' => $p->name, 'url' => action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$p->contact_id])],
                'file' => $p->file_number, 'package' => $p->package,
                'period' => self::d($p->period_start).' - '.self::d($p->period_end),
                'due' => $due,
                'late' => $started ? __('clinic.rep_days_n', ['n' => Carbon::parse($p->period_start)->diffInDays($today)]) : ['text' => __('clinic.rep_not_due_yet'), 'tone' => 'blue'],
                '_started' => $started,
            ];
        }
        $overdue = array_sum(array_map(function ($r) {
            return $r['_started'] ? $r['due'] : 0;
        }, $unpaid_rows));
        $plan_rows = [];
        foreach ($active as $a) {
            $plan_rows[] = ['package' => $a->name, 'n' => (int) $a->n, 'price' => (float) $a->price, 'monthly' => (int) $a->n * (float) $a->price];
        }

        return [
            'tiles' => [
                ['label' => __('clinic.rep_active_plans'), 'value' => $active->sum('n'), 'type' => 'number', 'tone' => 'blue', 'icon' => 'calendar-repeat',
                    'sub' => __('clinic.rep_new_cancelled', ['new' => $new, 'cancelled' => $cancelled])],
                ['label' => __('clinic.rep_plans_billed'), 'value' => $billed, 'type' => 'money', 'tone' => 'violet', 'icon' => 'receipt',
                    'sub' => __('clinic.rep_months_n', ['n' => $in_period->count()])],
                ['label' => __('clinic.rep_plans_collected'), 'value' => $collected, 'type' => 'money', 'tone' => 'green', 'icon' => 'cash',
                    'sub' => $billed > 0 ? __('clinic.rep_pct_of_billed', ['pct' => self::pct($collected, $billed)]) : null],
                ['label' => __('clinic.rep_plans_unpaid'), 'value' => $overdue, 'type' => 'money', 'tone' => $overdue > 0 ? 'red' : 'slate', 'icon' => 'clipboard-list',
                    'sub' => __('clinic.rep_plans_unpaid_sub')],
            ],
            'charts' => [],
            'tables' => [
                ['id' => 'plans', 'title' => __('clinic.rep_active_by_package'), 'rows' => $plan_rows, 'empty' => __('clinic.rep_no_active_plans'),
                    'columns' => [['key' => 'package', 'label' => __('clinic.rep_package')], ['key' => 'n', 'label' => __('clinic.rep_subscribers'), 'type' => 'number'],
                        ['key' => 'price', 'label' => __('clinic.rep_monthly_price'), 'type' => 'money'], ['key' => 'monthly', 'label' => __('clinic.rep_expected_per_month'), 'type' => 'money', 'strong' => true]]],
                ['id' => 'unpaid', 'title' => __('clinic.rep_unpaid_months'), 'rows' => $unpaid_rows, 'empty' => __('clinic.rep_no_unpaid_months'),
                    'columns' => [['key' => 'patient', 'label' => __('clinic.rep_patient')], ['key' => 'file', 'label' => __('clinic.rep_file_no')], ['key' => 'package', 'label' => __('clinic.rep_package')],
                        ['key' => 'period', 'label' => __('clinic.rep_period')], ['key' => 'due', 'label' => __('clinic.rep_owes'), 'type' => 'money', 'strong' => true],
                        ['key' => 'late', 'label' => __('clinic.rep_overdue_for')]]],
            ],
        ];
    }

    // ================================================================== 11. attendance

    public static function attendance($b, $f)
    {
        [$d1, $d2] = self::dates($f);
        $q = DB::table('visits as v')->leftJoin('users as u', 'u.id', '=', 'v.doctor_id')
            ->where('v.business_id', $b)->whereNull('v.deleted_at')->whereNotNull('v.queue_no')
            ->whereBetween('v.visit_date', [$d1, $d2])->where('v.queue_status', '!=', 'cancelled');
        if ($f['doctor_id']) {
            $q->where('v.doctor_id', $f['doctor_id']);
        }
        $visits = $q->select('v.visit_date', 'v.doctor_id', 'v.queue_status', 'v.checked_in_at', 'v.appointment_id', 'u.surname', 'u.first_name', 'u.last_name')->get();
        $aq = DB::table('clinic_appointments')->where('business_id', $b)->whereBetween('date', [$d1, $d2]);
        if ($f['doctor_id']) {
            $aq->where('doctor_id', $f['doctor_id']);
        }
        $appts = $aq->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');
        $new_patients = DB::table('contacts')->where('business_id', $b)->where('is_patient', 1)->whereNull('deleted_at')->whereBetween('created_at', self::between($f))->count();

        $by_day = [];
        $weekday = array_fill(1, 7, 0);
        $hours = [];
        $docs = [];
        foreach ($visits as $v) {
            $day = substr($v->visit_date, 0, 10);
            $by_day[$day] = ($by_day[$day] ?? 0) + 1;
            $weekday[Carbon::parse($day)->isoWeekday()]++;
            if ($v->checked_in_at) {
                $h = (int) substr($v->checked_in_at, 11, 2);
                $hours[$h] = ($hours[$h] ?? 0) + 1;
            }
            $d = $docs[$v->doctor_id] ?? ['name' => self::userName($v->first_name, $v->surname, $v->last_name), 'in' => 0, 'booked' => 0, 'no_show' => 0];
            $d['in']++;
            if ($v->appointment_id) {
                $d['booked']++;
            }
            if ($v->queue_status == 'no_show') {
                $d['no_show']++;
            }
            $docs[$v->doctor_id] = $d;
        }
        // how many of each weekday the period holds, for a fair average
        $weekday_count = array_fill(1, 7, 0);
        for ($d = $f['from']->copy(); $d->lte($f['to']); $d->addDay()) {
            $weekday_count[$d->isoWeekday()]++;
        }
        $wk_rows = [];
        foreach ($weekday as $i => $n) {
            $wk_rows[] = ['day' => __('clinic.weekday_'.$i), 'total' => $n, 'avg' => $weekday_count[$i] ? round($n / $weekday_count[$i], 1) : 0];
        }
        $hour_points = [];
        if ($hours) {
            for ($h = min(array_keys($hours)); $h <= max(array_keys($hours)); $h++) {
                $hour_points[] = ['label' => sprintf('%02dh', $h), 'value' => $hours[$h] ?? 0, 'tip' => sprintf('%02d:00 - %02d:59', $h, $h)];
            }
        }
        $booked = $visits->whereNotNull('appointment_id')->count();
        $kept = (int) ($appts['checked_in'] ?? 0);
        $no_show = (int) ($appts['no_show'] ?? 0);
        $made = $kept + $no_show + (int) ($appts['confirmed'] ?? 0) + (int) ($appts['requested'] ?? 0) + (int) ($appts['cancelled'] ?? 0) + (int) ($appts['declined'] ?? 0);
        $doc_rows = array_values(array_map(function ($d) {
            return ['doctor' => $d['name'], 'in' => $d['in'], 'booked' => $d['booked'], 'walk_in' => $d['in'] - $d['booked'], 'no_show' => $d['no_show']];
        }, $docs));
        usort($doc_rows, function ($a, $b) {
            return $b['in'] <=> $a['in'];
        });

        $tables = [
            ['id' => 'weekdays', 'title' => __('clinic.rep_by_weekday'), 'rows' => $visits->isEmpty() ? [] : $wk_rows, 'empty' => __('clinic.rep_none_in_period'),
                'columns' => [['key' => 'day', 'label' => __('clinic.rep_weekday')], ['key' => 'total', 'label' => __('clinic.rep_checked_in'), 'type' => 'number'],
                    ['key' => 'avg', 'label' => __('clinic.rep_avg_per_day'), 'type' => 'decimal']]],
        ];
        if (! $f['doctor_id']) {
            $tables[] = ['id' => 'doctors', 'title' => __('clinic.rep_by_doctor'), 'rows' => $doc_rows, 'empty' => __('clinic.rep_none_in_period'),
                'columns' => [['key' => 'doctor', 'label' => __('clinic.rep_doctor')], ['key' => 'in', 'label' => __('clinic.rep_checked_in'), 'type' => 'number'],
                    ['key' => 'booked', 'label' => __('clinic.rep_with_appointment'), 'type' => 'number'], ['key' => 'walk_in', 'label' => __('clinic.rep_walk_ins'), 'type' => 'number'],
                    ['key' => 'no_show', 'label' => __('clinic.queue_no_show'), 'type' => 'number']]];
        }
        $tables[] = ['id' => 'appointments', 'title' => __('clinic.rep_appointments'), 'empty' => __('clinic.rep_none_in_period'),
            'rows' => $made ? array_values(array_filter(array_map(function ($s) use ($appts) {
                return ['status' => __('clinic.appt_status_'.$s), 'n' => (int) ($appts[$s] ?? 0)];
            }, ['checked_in', 'no_show', 'confirmed', 'requested', 'cancelled', 'declined']), function ($r) {
                return $r['n'] > 0;
            })) : [],
            'columns' => [['key' => 'status', 'label' => __('sale.status')], ['key' => 'n', 'label' => __('clinic.rep_appointments'), 'type' => 'number']]];

        return [
            'tiles' => [
                ['label' => __('clinic.rep_checked_in'), 'value' => $visits->count(), 'type' => 'number', 'tone' => 'blue', 'icon' => 'door-enter',
                    'sub' => __('clinic.rep_walk_ins_n', ['n' => $visits->count() - $booked])],
                ['label' => __('clinic.rep_new_patients_registered'), 'value' => $new_patients, 'type' => 'number', 'tone' => 'violet', 'icon' => 'user-plus'],
                ['label' => __('clinic.rep_appointments_kept'), 'value' => ($kept + $no_show) ? $kept / ($kept + $no_show) * 100 : null, 'type' => 'pct', 'tone' => 'green', 'icon' => 'calendar-event',
                    'sub' => __('clinic.rep_no_shows_n', ['n' => $no_show])],
                ['label' => __('clinic.rep_busiest_day'), 'value' => $visits->isEmpty() ? null : __('clinic.weekday_'.array_search(max($weekday), $weekday)), 'type' => 'text', 'tone' => 'amber', 'icon' => 'calendar-time',
                    'sub' => $hours ? __('clinic.rep_busiest_hour', ['h' => sprintf('%02d:00', array_search(max($hours), $hours))]) : null],
            ],
            'charts' => [
                ['title' => __('clinic.rep_checked_in_per_day'), 'type' => 'number', 'points' => self::series($f, $by_day)],
                ['title' => __('clinic.rep_arrivals_by_hour'), 'type' => 'number', 'points' => $hour_points],
            ],
            'tables' => $tables,
        ];
    }
}
