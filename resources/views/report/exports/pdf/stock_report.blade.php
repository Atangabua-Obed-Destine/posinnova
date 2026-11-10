<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_sku') }}</div>
        <div class="kpi-value">{{ $kpis['total_sku'] ?? 0 }}</div>
    </div>
    <div class="kpi-card" style="background:#FFE0E0;border-color:#FF8888;">
        <div class="kpi-label">{{ __('report.low_stock_alert_count') }}</div>
        <div class="kpi-value" style="color:#C00000;">{{ $kpis['low_stock_count'] ?? 0 }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.stock_value_by_purchase_price') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_stock_value_purchase'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.stock_value_by_sale_price') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_stock_value_sale'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card" style="background:#E2EFDA;border-color:#90C070;">
        <div class="kpi-label">{{ __('lang_v1.potential_profit') }}</div>
        <div class="kpi-value" style="color:#375623;">{{ $currency_symbol }}{{ number_format($kpis['total_potential_profit'] ?? 0, 2) }}</div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('product.product_name') }}</th>
            <th>{{ __('product.variation') }}</th>
            <th>{{ __('product.sku') }}</th>
            <th class="num">{{ __('report.current_stock') }}</th>
            <th class="num">{{ __('report.total_sold') }}</th>
            <th class="num">{{ __('lang_v1.stock_value_by_purchase_price') }}</th>
            <th class="num">{{ __('lang_v1.stock_value_by_sale_price') }}</th>
            <th class="num">{{ __('lang_v1.potential_profit') }}</th>
            <th>{{ __('product.category') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        @php
            $stock = (float)($row->stock ?? 0);
            $unit_price = (float)(($row->group_price ?? 0) > 0 ? $row->group_price : ($row->unit_price ?? 0));
            $stock_value_sale = $stock * $unit_price;
            $stock_price = (float)($row->stock_price ?? 0);
            $potential_profit = $stock_value_sale - $stock_price;
            $is_low = $row->enable_stock && $stock <= ($row->alert_quantity ?? 0);
            $variation = ($row->type ?? '') === 'variable' ? ($row->product_variation ?? '') . '-' . ($row->variation_name ?? '') : '';
        @endphp
        <tr style="{{ $is_low ? 'background:#FFE0E0;' : '' }}">
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->product ?? '' }}{{ $is_low ? ' ⚠' : '' }}</td>
            <td>{{ $variation }}</td>
            <td>{{ $row->sub_sku ?? '' }}</td>
            <td class="num">{{ number_format($stock, 2) }} {{ $row->unit ?? '' }}</td>
            <td class="num">{{ number_format($row->total_sold ?? 0, 2) }}</td>
            <td class="num">{{ number_format($stock_price, 2) }}</td>
            <td class="num">{{ number_format($stock_value_sale, 2) }}</td>
            <td class="num" style="{{ $potential_profit >= 0 ? 'color:#375623;' : 'color:#C00000;' }}">{{ number_format($potential_profit, 2) }}</td>
            <td>{{ $row->category ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@if(($kpis['low_stock_count'] ?? 0) > 0)
<p style="margin-top:6px;font-size:8px;color:#C00000;">⚠ {{ __('report.low_stock_alert_count') }}: {{ $kpis['low_stock_count'] }} {{ __('lang_v1.products') }}</p>
@endif

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
    &nbsp;&nbsp;&nbsp; {{ __('report.total_records') }}: {{ count($rows) }}
</div>
</body>
</html>
