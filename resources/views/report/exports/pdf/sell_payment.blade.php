<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.cash') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['cash'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.card') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['card'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.cheque') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['cheque'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.bank_transfer') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['bank_transfer'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card" style="background:#E2EFDA;border-color:#90C070;">
        <div class="kpi-label">{{ __('lang_v1.total_payments') }}</div>
        <div class="kpi-value" style="color:#375623;">{{ $currency_symbol }}{{ number_format($kpis['total'] ?? 0, 2) }}</div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('contact.customer') }}</th>
            <th>{{ __('sale.invoice_no') }}</th>
            <th>{{ __('report.payment_date') }}</th>
            <th>{{ __('lang_v1.payment_method') }}</th>
            <th class="num">{{ __('report.amount') }}</th>
            <th>{{ __('lang_v1.payment_ref_no') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $total = 0; @endphp
        @foreach($rows as $i => $row)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ ($row->name ?? '') . (!empty($row->business_name) ? ' (' . $row->business_name . ')' : '') }}</td>
            <td>{{ $row->invoice_no ?? '' }}</td>
            <td>{{ $row->paid_on ?? '' }}</td>
            <td>{{ $row->payment_method ?? '' }}</td>
            <td class="num">{{ number_format($row->amount ?? 0, 2) }}</td>
            <td>{{ $row->payment_ref_no ?? '' }}</td>
        </tr>
        @php $total += $row->amount ?? 0; @endphp
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5"><strong>{{ __('lang_v1.total') }}</strong></td>
            <td class="num">{{ number_format($total, 2) }}</td>
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
