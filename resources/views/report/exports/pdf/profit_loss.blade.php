<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

@php
    $fmt = fn($v) => $currency_symbol . number_format((float)($v ?? 0), 2);
    $gross_profit = $fmt($data['gross_profit'] ?? 0);
    $net_profit   = (float)($data['net_profit'] ?? 0);
@endphp

{{-- Summary Cards --}}
<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_sell') }} ({{ __('product.exc_of_tax') }})</div>
        <div class="kpi-value">{{ $fmt($data['total_sell'] ?? 0) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_purchase') }} ({{ __('product.exc_of_tax') }})</div>
        <div class="kpi-value">{{ $fmt($data['total_purchase'] ?? 0) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.gross_profit') }}</div>
        <div class="kpi-value" style="color:#375623;">{{ $gross_profit }}</div>
    </div>
    <div class="kpi-card" style="{{ $net_profit >= 0 ? 'background:#E2EFDA;border-color:#90C070;' : 'background:#FFE0E0;border-color:#FF4444;' }}">
        <div class="kpi-label">{{ __('lang_v1.net_profit') }}</div>
        <div class="kpi-value" style="color:{{ $net_profit >= 0 ? '#375623' : '#C00000' }};">{{ $fmt($net_profit) }}</div>
    </div>
</div>

{{-- P&L Statement --}}
<table style="width:60%;border-collapse:collapse;font-size:10px;margin-bottom:16px;">
    <thead>
        <tr style="background:#1F4E79;color:#fff;">
            <th style="padding:5px 8px;text-align:left;">{{ __('report.description') }}</th>
            <th style="padding:5px 8px;text-align:right;">{{ __('report.amount') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr style="background:#F0F4FA;">
            <td style="padding:4px 8px;font-weight:bold;">{{ __('lang_v1.opening_stock') }}</td>
            <td style="padding:4px 8px;text-align:right;">{{ $fmt($data['opening_stock'] ?? 0) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 8px;">+ {{ __('report.total_purchase') }} ({{ __('product.exc_of_tax') }})</td>
            <td style="padding:4px 8px;text-align:right;">{{ $fmt($data['total_purchase'] ?? 0) }}</td>
        </tr>
        <tr style="background:#F5F5F5;">
            <td style="padding:4px 8px;">− {{ __('lang_v1.total_purchase_return') }}</td>
            <td style="padding:4px 8px;text-align:right;">({{ $fmt($data['total_purchase_return'] ?? 0) }})</td>
        </tr>
        <tr>
            <td style="padding:4px 8px;">− {{ __('lang_v1.closing_stock') }}</td>
            <td style="padding:4px 8px;text-align:right;">({{ $fmt($data['closing_stock'] ?? 0) }})</td>
        </tr>
        <tr style="background:#DCE6F1;font-weight:bold;border-top:2px solid #1F4E79;">
            <td style="padding:5px 8px;">{{ __('lang_v1.cost_of_goods_sold') }}</td>
            <td style="padding:5px 8px;text-align:right;">{{ $fmt(($data['opening_stock'] ?? 0) + ($data['total_purchase'] ?? 0) - ($data['total_purchase_return'] ?? 0) - ($data['closing_stock'] ?? 0)) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 8px;">{{ __('report.total_sell') }} ({{ __('product.exc_of_tax') }})</td>
            <td style="padding:4px 8px;text-align:right;">{{ $fmt($data['total_sell'] ?? 0) }}</td>
        </tr>
        <tr style="background:#F5F5F5;">
            <td style="padding:4px 8px;">− {{ __('lang_v1.total_sell_return') }}</td>
            <td style="padding:4px 8px;text-align:right;">({{ $fmt($data['total_sell_return'] ?? 0) }})</td>
        </tr>
        <tr style="background:#E2EFDA;font-weight:bold;border-top:2px solid #375623;">
            <td style="padding:5px 8px;">{{ __('lang_v1.gross_profit') }}</td>
            <td style="padding:5px 8px;text-align:right;color:#375623;">{{ $fmt($data['gross_profit'] ?? 0) }}</td>
        </tr>
        <tr>
            <td style="padding:4px 8px;">− {{ __('lang_v1.total_expense') }}</td>
            <td style="padding:4px 8px;text-align:right;">({{ $fmt($data['total_expense'] ?? 0) }})</td>
        </tr>
        @if(!empty($data['total_reconciliation']))
        <tr style="color:#888;">
            <td style="padding:4px 8px;">{{ __('lang_v1.inventory_reconciliation') }} <span style="font-size:8px;">({{ __('lang_v1.no_profit_impact') }})</span></td>
            <td style="padding:4px 8px;text-align:right;">{{ $fmt($data['total_reconciliation']) }}</td>
        </tr>
        @endif
        <tr style="{{ $net_profit >= 0 ? 'background:#E2EFDA;' : 'background:#FFE0E0;' }}font-weight:bold;border-top:2px solid #1F4E79;font-size:12px;">
            <td style="padding:6px 8px;">{{ __('lang_v1.net_profit') }}</td>
            <td style="padding:6px 8px;text-align:right;color:{{ $net_profit >= 0 ? '#375623' : '#C00000' }};">{{ $fmt($net_profit) }}</td>
        </tr>
    </tbody>
</table>

{{-- Top Products by Revenue (top 20) --}}
@if(!empty($profit_by_product))
<h3 style="font-size:11px;color:#1F4E79;margin-bottom:4px;border-bottom:1px solid #1F4E79;padding-bottom:2px;">{{ __('lang_v1.total_sales') }} — {{ __('lang_v1.products') }}</h3>
<table class="data-table" style="width:60%;">
    <thead>
        <tr>
            <th>{{ __('product.product_name') }}</th>
            <th class="num">{{ __('lang_v1.total_sell_inc_tax') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach(array_slice($profit_by_product, 0, 20) as $row)
        <tr>
            <td>{{ $row->product ?? '' }}</td>
            <td class="num" style="color:{{ ($row->gross_profit ?? 0) >= 0 ? '#375623' : '#C00000' }};">{{ $fmt($row->gross_profit ?? 0) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@if(count($profit_by_product) > 20)
<p style="font-size:8px;color:#888;margin-top:3px;">{{ __('lang_v1.showing_top_20') }} {{ __('lang_v1.of') }} {{ count($profit_by_product) }} {{ __('lang_v1.products') }}</p>
@endif
@endif

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
</div>
</body>
</html>
