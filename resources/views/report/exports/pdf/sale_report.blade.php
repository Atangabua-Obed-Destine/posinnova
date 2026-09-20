<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

{{-- KPI Cards --}}
<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.total_sales') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_sales'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('sale.tax') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_tax'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('sale.discount') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_discount'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.num_invoices') }}</div>
        <div class="kpi-value">{{ $kpis['invoice_count'] ?? 0 }}</div>
    </div>
</div>

{{-- Data table --}}
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('lang_v1.contact_id') }}</th>
            <th>{{ __('sale.customer_name') }}</th>
            <th>{{ __('sale.invoice_no') }}</th>
            <th>{{ __('messages.date') }}</th>
            <th class="num">{{ __('sale.total') }} ({{ __('product.exc_of_tax') }})</th>
            <th class="num">{{ __('sale.discount') }}</th>
            <th class="num">{{ __('sale.tax') }}</th>
            <th class="num">{{ __('sale.total') }} ({{ __('product.inc_of_tax') }})</th>
            <th>{{ __('lang_v1.payment_method') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $totals = ['before_tax'=>0,'discount'=>0,'tax'=>0,'final'=>0]; @endphp
        @foreach($rows as $i => $row)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->contact_id ?? '' }}</td>
            <td>{{ $row->name ?? '' }}</td>
            <td>{{ $row->invoice_no ?? '' }}</td>
            <td>{{ $row->sale_date ?? '' }}</td>
            <td class="num">{{ number_format($row->total_before_tax ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->discount_amount ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->tax_amount ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->final_total ?? 0, 2) }}</td>
            <td>{{ $row->payment_methods_plain ?? '' }}</td>
        </tr>
        @php
            $totals['before_tax'] += $row->total_before_tax ?? 0;
            $totals['discount']   += $row->discount_amount ?? 0;
            $totals['tax']        += $row->tax_amount ?? 0;
            $totals['final']      += $row->final_total ?? 0;
        @endphp
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5"><strong>{{ __('lang_v1.total') }}</strong></td>
            <td class="num">{{ number_format($totals['before_tax'], 2) }}</td>
            <td class="num">{{ number_format($totals['discount'], 2) }}</td>
            <td class="num">{{ number_format($totals['tax'], 2) }}</td>
            <td class="num">{{ number_format($totals['final'], 2) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
    &nbsp;&nbsp;&nbsp; {{ __('report.total_records') }}: {{ count($rows) }}
</div>

</body>
</html>
