<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

@php
    $fmt = fn($v) => $currency_symbol . number_format((float)($v ?? 0), 2);
    $input_total  = (float)($input_tax['total_tax'] ?? 0);
    $output_total = (float)($output_tax['total_tax'] ?? 0);
    $expense_total = (float)($expense_tax['total_tax'] ?? 0);
    $diff = $tax_diff;
@endphp

<div class="kpi-row">
    <div class="kpi-card" style="background:#FCE4D6;border-color:#F4A244;">
        <div class="kpi-label">{{ __('lang_v1.input_tax') }} ({{ __('lang_v1.on_purchases') }})</div>
        <div class="kpi-value">{{ $fmt($input_total) }}</div>
    </div>
    <div class="kpi-card" style="background:#E2EFDA;border-color:#90C070;">
        <div class="kpi-label">{{ __('lang_v1.output_tax') }} ({{ __('lang_v1.on_sales') }})</div>
        <div class="kpi-value">{{ $fmt($output_total) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.expense_tax') }}</div>
        <div class="kpi-value">{{ $fmt($expense_total) }}</div>
    </div>
    <div class="kpi-card" style="{{ $diff >= 0 ? 'background:#FFE0E0;border-color:#FF4444;' : 'background:#E2EFDA;border-color:#90C070;' }}">
        <div class="kpi-label">{{ $diff >= 0 ? __('lang_v1.net_tax_payable') : __('lang_v1.net_tax_claimable') }}</div>
        <div class="kpi-value" style="color:{{ $diff >= 0 ? '#C00000' : '#375623' }};">{{ $fmt(abs($diff)) }}</div>
    </div>
</div>

{{-- Sales tax detail --}}
@if(!empty($sell_rows))
<h3 style="font-size:11px;color:#1F4E79;margin:8px 0 4px;border-bottom:1px solid #1F4E79;padding-bottom:2px;">{{ __('lang_v1.sales_tax_details') }}</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('sale.invoice_no') }}</th>
            <th>{{ __('messages.date') }}</th>
            <th>{{ __('contact.customer') }}</th>
            <th class="num">{{ __('lang_v1.taxable_value') }}</th>
            <th class="num">{{ __('report.tax_amount') }}</th>
            <th>{{ __('lang_v1.tax_rate') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $total_tax = 0; @endphp
        @foreach($sell_rows as $i => $row)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->ref_no ?? '' }}</td>
            <td>{{ $row->transaction_date ?? '' }}</td>
            <td>{{ $row->contact_name ?? '' }}</td>
            <td class="num">{{ number_format($row->taxable_value ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->tax_amount ?? 0, 2) }}</td>
            <td>{{ ($row->tax_rate ?? '') }}%</td>
        </tr>
        @php $total_tax += $row->tax_amount ?? 0; @endphp
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>{{ __('lang_v1.total') }}</strong></td>
            <td class="num"></td>
            <td class="num"><strong>{{ number_format($total_tax, 2) }}</strong></td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endif

{{-- Purchase tax detail --}}
@if(!empty($purchase_rows))
<h3 style="font-size:11px;color:#1F4E79;margin:12px 0 4px;border-bottom:1px solid #1F4E79;padding-bottom:2px;">{{ __('lang_v1.purchase_tax_details') }}</h3>
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('purchase.ref_no') }}</th>
            <th>{{ __('messages.date') }}</th>
            <th>{{ __('contact.supplier') }}</th>
            <th class="num">{{ __('lang_v1.taxable_value') }}</th>
            <th class="num">{{ __('report.tax_amount') }}</th>
            <th>{{ __('lang_v1.tax_rate') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $total_tax = 0; @endphp
        @foreach($purchase_rows as $i => $row)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->ref_no ?? '' }}</td>
            <td>{{ $row->transaction_date ?? '' }}</td>
            <td>{{ $row->contact_name ?? '' }}</td>
            <td class="num">{{ number_format($row->taxable_value ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->tax_amount ?? 0, 2) }}</td>
            <td>{{ ($row->tax_rate ?? '') }}%</td>
        </tr>
        @php $total_tax += $row->tax_amount ?? 0; @endphp
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>{{ __('lang_v1.total') }}</strong></td>
            <td class="num"></td>
            <td class="num"><strong>{{ number_format($total_tax, 2) }}</strong></td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endif

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
</div>
</body>
</html>
