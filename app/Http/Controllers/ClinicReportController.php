<?php

namespace App\Http\Controllers;

use App\Utils\ClinicQueue;
use App\Utils\ClinicReports;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Clinic reports: /clinic/reports (the list) and /clinic/reports/{key}.
 * The figures come from App\Utils\ClinicReports. A doctor with "My practice"
 * sees their own patients only; "All doctors" lets them pick any doctor.
 */
class ClinicReportController extends Controller
{
    private function businessId()
    {
        $business_id = request()->session()->get('user.business_id');
        if (! in_array('hospital', (array) (session('business.enabled_modules') ?: []))) {
            abort(404);
        }

        return $business_id;
    }

    public function index()
    {
        $this->businessId();
        $available = ClinicReports::available(auth()->user());
        if (empty($available)) {
            abort(403, 'Unauthorized action.');
        }

        return view('clinic.reports.index', compact('available'));
    }

    public function show(Request $request, $key)
    {
        $business_id = $this->businessId();
        if (! ClinicReports::groupOf($key) || ! method_exists(ClinicReports::class, $key)) {
            abort(404);
        }
        $user = auth()->user();
        if (! ClinicReports::can($user, $key)) {
            abort(403, 'Unauthorized action.');
        }

        $f = $this->filters($request, $business_id, $key);
        $report = ClinicReports::$key($business_id, $f);
        $report += ['notes' => [], 'charts' => [], 'no_dates' => false];

        if ($request->input('export') == 'csv') {
            return $this->csv($key, $report, $request->input('table'), $f);
        }

        $available = ClinicReports::available($user);

        return view('clinic.reports.show', compact('key', 'report', 'f', 'available'));
    }

    /** from / to (or a preset), the doctor and a search text, checked against what the user may see. */
    private function filters(Request $request, $business_id, $key)
    {
        $today = Carbon::today();
        $presets = [
            'today' => [$today->copy(), $today->copy()],
            'week' => [$today->copy()->startOfWeek(), $today->copy()],
            'month' => [$today->copy()->startOfMonth(), $today->copy()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()->startOfDay()],
            'year' => [$today->copy()->startOfYear(), $today->copy()],
        ];
        $preset = $request->input('period');
        if (isset($presets[$preset])) {
            [$from, $to] = $presets[$preset];
        } else {
            try {
                $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : $presets['month'][0];
                $to = $request->filled('to') ? Carbon::parse($request->input('to'))->startOfDay() : $presets['month'][1];
            } catch (\Exception $e) {
                [$from, $to] = $presets['month'];
            }
            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }
            $preset = null;
            foreach ($presets as $k => [$a, $b]) {
                if ($a->eq($from) && $b->eq($to)) {
                    $preset = $k;
                }
            }
        }

        // doctor: practice and attendance reports only. Without "All doctors" a doctor is held to themselves.
        $user = auth()->user();
        $doctors = ClinicQueue::doctors($business_id);
        $group = ClinicReports::groupOf($key);
        $doctor_filter = in_array($group, ['practice', 'activity']);
        $all = $user->can('clinic_report.all_doctors') || ($group == 'activity' && ($user->can('clinic_report.attendance') || $user->can('clinic_report.finance')));
        $doctor_id = null;
        if ($doctor_filter) {
            if ($all) {
                $doctor_id = $request->filled('doctor_id') && isset($doctors[(int) $request->input('doctor_id')]) ? (int) $request->input('doctor_id') : null;
            } else {
                $doctor_id = (int) $user->id;
            }
        }

        return [
            'from' => $from, 'to' => $to, 'period' => $preset,
            'doctor_id' => $doctor_id, 'doctor_filter' => $doctor_filter, 'doctor_choice' => $doctor_filter && $all,
            'doctors' => $doctors, 'doctor_name' => $doctor_id ? ($doctors[$doctor_id] ?? trim($user->user_full_name)) : null,
            'q' => trim((string) $request->input('q')),
        ];
    }

    private function csv($key, array $report, $table_id, array $f)
    {
        $table = collect($report['tables'])->firstWhere('id', $table_id) ?? ($report['tables'][0] ?? null);
        if (! $table) {
            abort(404);
        }
        $name = 'clinic-'.$key.'-'.$table['id'].($report['no_dates'] ? '-'.Carbon::today()->toDateString() : '-'.$f['from']->toDateString().'_'.$f['to']->toDateString()).'.csv';
        $plain = function ($cell) {
            if (is_array($cell)) {
                $cell = $cell['text'] ?? '';
            }

            return is_float($cell) ? round($cell, 2) : $cell;
        };

        return response()->streamDownload(function () use ($table, $plain) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel reads the accents as UTF-8
            fputcsv($out, array_column($table['columns'], 'label'));
            foreach (array_merge($table['rows'], ! empty($table['total']) ? [$table['total']] : []) as $row) {
                fputcsv($out, array_map(function ($c) use ($row, $plain) {
                    return $plain($row[$c['key']] ?? '');
                }, $table['columns']));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
