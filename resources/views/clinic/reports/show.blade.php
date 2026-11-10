@extends('layouts.app')
@section('title', __('clinic.rep_title_' . $key))

{{-- PosInnova clinic report (data: App\Utils\ClinicReports::{key}) --}}
@section('content')
@php
    $tones = [
        'amber' => ['#fffaeb', '#b54708'], 'green' => ['#ecfdf3', '#067647'], 'blue' => ['#eff8ff', '#175cd3'],
        'violet' => ['#f4f3ff', '#5925dc'], 'teal' => ['#f0fdf9', '#107569'], 'red' => ['#fef3f2', '#b42318'],
        'slate' => ['#f2f4f7', '#344054'],
    ];
    $minutes = function ($m) {
        if ($m === null || $m === '') return '';
        $m = (int) round($m);
        return $m < 60 ? __('clinic.rep_min', ['n' => $m]) : __('clinic.rep_h_min', ['h' => intdiv($m, 60), 'm' => sprintf('%02d', $m % 60)]);
    };
    // one value, formatted for its column / tile type
    $fmt = function ($v, $type) use ($minutes) {
        if ($v === null || $v === '') return '<span class="cr-muted">-</span>';
        switch ($type) {
            case 'money': return '<span class="display_currency" data-currency_symbol="true">' . e(round((float) $v, 4)) . '</span>';
            case 'number': return e(number_format((float) $v));
            case 'decimal': return e(number_format((float) $v, 1));
            case 'qty': return e(rtrim(rtrim(number_format((float) $v, 2), '0'), '.'));
            case 'pct': return ((float) $v > 0 && (float) $v < 0.5 ? '&lt;1' : e(round((float) $v))) . '%';
            case 'minutes': return e($minutes($v));
            case 'hours': return e(__('clinic.rep_hours_short', ['n' => number_format((float) $v, 1)]));
            default: return e($v);
        }
    };
    $cell = function ($v, $col) use ($fmt, $tones) {
        if (is_array($v)) {
            $text = e($v['text'] ?? '');
            if (! empty($v['tone'])) {
                $t = $tones[$v['tone']] ?? $tones['slate'];
                $text = '<span class="cr-pill" style="background:' . $t[0] . ';color:' . $t[1] . ';">' . $text . '</span>';
            }
            return ! empty($v['url']) ? '<a href="' . e($v['url']) . '">' . $text . '</a>' : $text;
        }
        return $fmt($v, $col['type'] ?? 'text');
    };
    $is_num = function ($col) {
        return in_array($col['type'] ?? 'text', ['money', 'number', 'decimal', 'qty', 'pct', 'minutes', 'hours']);
    };
    $url = action([\App\Http\Controllers\ClinicReportController::class, 'show'], [$key]);
    $keep = array_filter(['doctor_id' => $f['doctor_choice'] ? $f['doctor_id'] : null, 'q' => $f['q'] !== '' ? $f['q'] : null]);
    $range_q = ['from' => $f['from']->toDateString(), 'to' => $f['to']->toDateString()];
@endphp
@include('clinic.reports._style')

<div class="cr-wrap">
    <div class="cr-crumb cr-noprint"><a href="{{ action([\App\Http\Controllers\ClinicReportController::class, 'index']) }}">{{ __('clinic.rep_all_reports') }}</a> ›</div>
    <h1 class="cr-title">{!! \App\Utils\ClinicIcons::svg(\App\Utils\ClinicReports::ICONS[$key], '') !!} {{ __('clinic.rep_title_' . $key) }}</h1>
    <p class="cr-lead">{{ __('clinic.rep_desc_' . $key) }}</p>

    <div class="cr-bar">
        @if (! $report['no_dates'])
            <div class="cr-presets cr-noprint" role="group" aria-label="{{ __('clinic.rep_period') }}">
                @foreach (['today', 'week', 'month', 'last_month', 'year'] as $p)
                    <a class="cr-chip {{ $f['period'] == $p ? 'is-on' : '' }}" href="{{ $url . '?' . http_build_query(array_merge($keep, ['period' => $p])) }}">{{ __('clinic.rep_period_' . $p) }}</a>
                @endforeach
            </div>
        @endif
        <form method="GET" action="{{ $url }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;margin:0;" class="cr-noprint">
            @if (! $report['no_dates'])
                <div class="cr-field"><label for="cr_from">{{ __('clinic.rep_from') }}</label><input type="date" id="cr_from" name="from" value="{{ $f['from']->toDateString() }}"></div>
                <div class="cr-field"><label for="cr_to">{{ __('clinic.rep_to') }}</label><input type="date" id="cr_to" name="to" value="{{ $f['to']->toDateString() }}"></div>
            @endif
            @if ($f['doctor_choice'])
                <div class="cr-field"><label for="cr_doctor">{{ __('clinic.rep_doctor') }}</label>
                    <select id="cr_doctor" name="doctor_id">
                        <option value="">{{ __('clinic.rep_all_doctors') }}</option>
                        @foreach ($f['doctors'] as $id => $name)
                            <option value="{{ $id }}" @selected($f['doctor_id'] == $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($key == 'consultations')
                <div class="cr-field"><label for="cr_q">{{ __('clinic.rep_diagnosis_contains') }}</label><input type="search" id="cr_q" name="q" value="{{ $f['q'] }}" style="width:200px;"></div>
            @endif
            @if (! $report['no_dates'] || $f['doctor_choice'] || $key == 'consultations')
                <button type="submit" class="cr-btn is-primary">{{ __('clinic.rep_show') }}</button>
            @endif
        </form>
        <span class="cr-spacer"></span>
        <div class="cr-scope">
            @if (! $report['no_dates'])
                <strong>{{ \App\Utils\ClinicReports::d($f['from']) }} – {{ \App\Utils\ClinicReports::d($f['to']) }}</strong>
            @else
                <strong>{{ __('clinic.rep_as_of', ['date' => \App\Utils\ClinicReports::d(now(), true)]) }}</strong>
            @endif
            @if ($f['doctor_filter'])
                · {{ $f['doctor_id'] ? $f['doctor_name'] : __('clinic.rep_all_doctors') }}
            @endif
            @if ($f['q'] !== '')
                · “{{ $f['q'] }}”
            @endif
        </div>
        <button type="button" class="cr-btn cr-noprint" onclick="window.print()">{!! \App\Utils\ClinicIcons::svg('receipt', '') !!} {{ __('clinic.rep_print') }}</button>
    </div>

    @foreach ($report['notes'] as $note)
        <div class="cr-note">{{ $note }}</div>
    @endforeach

    @if (! empty($report['tiles']))
        <div class="cr-tiles">
            @foreach ($report['tiles'] as $t)
                @php
                    $tone = $tones[$t['tone'] ?? 'slate'];
                    $inner = '<span class="cr-ico" style="background:' . $tone[0] . ';color:' . $tone[1] . ';">' . \App\Utils\ClinicIcons::svg($t['icon'] ?? 'report-medical', '') . '</span>'
                        . '<span style="min-width:0;"><span class="cr-tile-label">' . e($t['label']) . '</span>'
                        . '<span class="cr-tile-value ' . (($t['type'] ?? '') == 'money' ? 'is-money' : '') . '">' . $fmt($t['value'], $t['type'] ?? 'text') . '</span>'
                        . (! empty($t['sub']) ? '<span class="cr-tile-sub">' . e($t['sub']) . '</span>' : '') . '</span>';
                @endphp
                @if (! empty($t['url']))
                    <a href="{{ $t['url'] }}" class="cr-tile">{!! $inner !!}</a>
                @else
                    <div class="cr-tile">{!! $inner !!}</div>
                @endif
            @endforeach
        </div>
    @endif

    @foreach ($report['charts'] as $ci => $chart)
        @php
            $pts = $chart['points'];
            $max = max(array_merge([0], array_map(fn ($p) => (float) $p['value'], $pts)));
            $every = max(1, (int) ceil(count($pts) / 12));
            $plain = function ($v) use ($chart, $minutes) {
                return $chart['type'] == 'minutes' ? $minutes($v) : ($chart['type'] == 'money' ? number_format((float) $v, session('business.currency_precision', 2)) : number_format((float) $v, floor($v) == $v ? 0 : 1));
            };
        @endphp
        @if (count($pts) && $max > 0)
            <div class="cr-card">
                <div class="cr-card-head"><h2 class="cr-card-title">{{ $chart['title'] }}</h2></div>
                <div class="cr-chart">
                    @if (! empty($chart['horizontal']))
                        <div class="cr-hbars">
                            @foreach ($pts as $p)
                                <span class="cr-hl">{{ $p['label'] }}</span>
                                <span class="cr-ht" title="{{ $p['label'] }}: {{ $plain($p['value']) }}"><i style="width:{{ max(0, $p['value']) / $max * 100 }}%;"></i></span>
                                <span class="cr-hv">{!! $fmt($p['value'], $chart['type']) !!}</span>
                            @endforeach
                        </div>
                    @else
                        <div class="cr-plot" data-max="{{ $plain($max) }}" role="img" aria-label="{{ $chart['title'] }}">
                            @foreach ($pts as $p)
                                <span class="cr-col" tabindex="0" aria-label="{{ $p['tip'] }}: {{ $plain($p['value']) }}">
                                    <i style="height:{{ $p['value'] > 0 ? max(2, $p['value'] / $max * 100) : 0 }}%;"></i>
                                    <span class="cr-tip">{{ $p['tip'] }} · <b>{{ $plain($p['value']) }}</b></span>
                                </span>
                            @endforeach
                        </div>
                        <div class="cr-xlabels" aria-hidden="true">
                            @foreach ($pts as $i => $p)
                                <span>{{ $i % $every == 0 ? $p['label'] : '' }}</span>
                            @endforeach
                        </div>
                        <details class="cr-noprint">
                            <summary>{{ __('clinic.rep_show_as_table') }}</summary>
                            <table class="cr-table" style="margin-top:6px;max-width:420px;">
                                @foreach ($pts as $p)
                                    <tr><td>{{ $p['tip'] }}</td><td class="is-num">{{ $plain($p['value']) }}</td></tr>
                                @endforeach
                            </table>
                        </details>
                    @endif
                </div>
            </div>
        @endif
    @endforeach

    @foreach ($report['tables'] as $table)
        <div class="cr-card" id="cr_table_{{ $table['id'] }}">
            <div class="cr-card-head">
                <h2 class="cr-card-title">{{ $table['title'] }}</h2>
                @if (! empty($table['rows']))
                    <a class="cr-btn cr-noprint" href="{{ $url . '?' . http_build_query(array_merge($keep, $report['no_dates'] ? [] : $range_q, ['export' => 'csv', 'table' => $table['id']])) }}">{{ __('clinic.rep_csv') }}</a>
                @endif
            </div>
            @if (! empty($table['note'])) <div class="cr-card-note">{{ $table['note'] }}</div> @endif
            @if (empty($table['rows']))
                <div class="cr-empty">{{ $table['empty'] ?? __('clinic.rep_none_in_period') }}</div>
            @else
                <div class="cr-table-wrap">
                    <table class="cr-table">
                        <thead><tr>
                            @foreach ($table['columns'] as $col)
                                <th class="{{ $is_num($col) ? 'is-num' : '' }}" scope="col">{{ $col['label'] }}</th>
                            @endforeach
                        </tr></thead>
                        <tbody>
                            @foreach ($table['rows'] as $row)
                                <tr>
                                    @foreach ($table['columns'] as $col)
                                        <td class="{{ $is_num($col) ? 'is-num' : '' }} {{ ! empty($col['strong']) ? 'is-strong' : '' }}">{!! $cell($row[$col['key']] ?? null, $col) !!}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        @if (! empty($table['total']))
                            <tfoot><tr>
                                @foreach ($table['columns'] as $col)
                                    <td class="{{ $is_num($col) ? 'is-num' : '' }}">{!! ($table['total'][$col['key']] ?? '') === '' ? '' : $cell($table['total'][$col['key']], $col) !!}</td>
                                @endforeach
                            </tr></tfoot>
                        @endif
                    </table>
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
