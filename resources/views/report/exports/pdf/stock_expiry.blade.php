<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

<div class="kpi-row">
    <div class="kpi-card" style="background:#FFE0E0;border-color:#FF4444;">
        <div class="kpi-label">{{ __('lang_v1.expired') }}</div>
        <div class="kpi-value" style="color:#C00000;">{{ $kpis['expired_count'] ?? 0 }}</div>
    </div>
    <div class="kpi-card" style="background:#FFE8D0;border-color:#FF8C00;">
        <div class="kpi-label">{{ __('lang_v1.expiring_30_days') }}</div>
        <div class="kpi-value" style="color:#D06000;">{{ $kpis['expiring_30'] ?? 0 }}</div>
    </div>
    <div class="kpi-card" style="background:#FFFBEA;border-color:#FFD700;">
        <div class="kpi-label">{{ __('lang_v1.expiring_90_days') }}</div>
        <div class="kpi-value" style="color:#806000;">{{ $kpis['expiring_90'] ?? 0 }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.total_items') }}</div>
        <div class="kpi-value">{{ $kpis['total_items'] ?? 0 }}</div>
    </div>
</div>

{{-- Colour key --}}
<p style="font-size:8px;margin-bottom:6px;">
    <span style="background:#FF4444;padding:1px 6px;color:#fff;">&nbsp;</span> {{ __('lang_v1.expired') }} &nbsp;
    <span style="background:#FF8C00;padding:1px 6px;color:#fff;">&nbsp;</span> {{ __('lang_v1.expiring_30_days') }} &nbsp;
    <span style="background:#FFD700;padding:1px 6px;color:#000;">&nbsp;</span> {{ __('lang_v1.expiring_90_days') }} &nbsp;
    <span style="background:#C6EFCE;padding:1px 6px;color:#000;">&nbsp;</span> {{ __('lang_v1.safe') }}
</p>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('product.product_name') }}</th>
            <th>{{ __('product.variation') }}</th>
            <th>{{ __('lang_v1.lot_number') }}</th>
            <th>{{ __('lang_v1.mfg_date') }}</th>
            <th>{{ __('lang_v1.exp_date') }}</th>
            <th class="num">{{ __('lang_v1.days_to_expiry') }}</th>
            <th class="num">{{ __('report.current_stock') }}</th>
            <th>{{ __('product.unit') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        @php
            $exp_date = !empty($row->exp_date) ? \Carbon\Carbon::parse($row->exp_date) : null;
            $days = $exp_date ? now()->diffInDays($exp_date, false) : null;
            if ($days === null) $row_color = '';
            elseif ($days < 0)    $row_color = 'background:#FF4444;color:#fff;';
            elseif ($days <= 30)  $row_color = 'background:#FF8C00;color:#fff;';
            elseif ($days <= 90)  $row_color = 'background:#FFD700;';
            else                  $row_color = 'background:#C6EFCE;';
        @endphp
        <tr style="{{ $row_color }}">
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->product ?? '' }}</td>
            <td>{{ $row->variation ?? '' }}</td>
            <td>{{ $row->lot_number ?? '' }}</td>
            <td>{{ $row->mfg_date ?? '' }}</td>
            <td>{{ $row->exp_date ?? '' }}</td>
            <td class="num">{{ $days !== null ? intval($days) : '' }}</td>
            <td class="num">{{ number_format($row->qty_available ?? 0, 2) }}</td>
            <td>{{ $row->unit ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
    &nbsp;&nbsp;&nbsp; {{ __('report.total_records') }}: {{ count($rows) }}
</div>
</body>
</html>
