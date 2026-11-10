<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.cash') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_cash'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.card') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_card'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.cheque') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_cheque'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.bank_transfer') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_bank_transfer'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card" style="background:#E2EFDA;border-color:#90C070;">
        <div class="kpi-label">{{ __('lang_v1.grand_total') }}</div>
        <div class="kpi-value" style="color:#375623;">{{ $currency_symbol }}{{ number_format($kpis['grand_total'] ?? 0, 2) }}</div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('report.cashier') }}</th>
            <th>{{ __('lang_v1.shift') }}</th>
            <th>{{ __('report.status') }}</th>
            <th>{{ __('report.register_open_time') }}</th>
            <th>{{ __('report.register_close_time') }}</th>
            <th class="num">{{ __('lang_v1.cash') }}</th>
            <th class="num">{{ __('lang_v1.card') }}</th>
            <th class="num">{{ __('lang_v1.cheque') }}</th>
            <th class="num">{{ __('lang_v1.bank_transfer') }}</th>
            <th class="num">{{ __('lang_v1.other') }}</th>
            <th class="num">{{ __('lang_v1.total') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $totals = array_fill(0, 6, 0); @endphp
        @foreach($rows as $i => $row)
        @php
            $total = (float)($row->total_cash_payment ?? 0)
                + (float)($row->total_card_payment ?? 0)
                + (float)($row->total_cheque_payment ?? 0)
                + (float)($row->total_bank_transfer_payment ?? 0)
                + (float)($row->total_other_payment ?? 0)
                + (float)($row->total_advance_payment ?? 0)
                + (float)($row->total_custom_pay_1 ?? 0)
                + (float)($row->total_custom_pay_2 ?? 0)
                + (float)($row->total_custom_pay_3 ?? 0);
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->user_name_plain ?? '' }}</td>
            <td>{{ $row->shift ?? '' }}</td>
            <td>{{ ucfirst($row->status ?? '') }}</td>
            <td>{{ $row->created_at ?? '' }}</td>
            <td>{{ $row->status === 'close' ? ($row->closed_at ?? '') : '—' }}</td>
            <td class="num">{{ number_format($row->total_cash_payment ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->total_card_payment ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->total_cheque_payment ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->total_bank_transfer_payment ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->total_other_payment ?? 0, 2) }}</td>
            <td class="num"><strong>{{ number_format($total, 2) }}</strong></td>
        </tr>
        @php
            $totals[0] += $row->total_cash_payment ?? 0;
            $totals[1] += $row->total_card_payment ?? 0;
            $totals[2] += $row->total_cheque_payment ?? 0;
            $totals[3] += $row->total_bank_transfer_payment ?? 0;
            $totals[4] += $row->total_other_payment ?? 0;
            $totals[5] += $total;
        @endphp
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6"><strong>{{ __('lang_v1.total') }}</strong></td>
            <td class="num">{{ number_format($totals[0], 2) }}</td>
            <td class="num">{{ number_format($totals[1], 2) }}</td>
            <td class="num">{{ number_format($totals[2], 2) }}</td>
            <td class="num">{{ number_format($totals[3], 2) }}</td>
            <td class="num">{{ number_format($totals[4], 2) }}</td>
            <td class="num"><strong>{{ number_format($totals[5], 2) }}</strong></td>
        </tr>
    </tfoot>
</table>

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
    &nbsp;&nbsp;&nbsp; {{ __('report.total_records') }}: {{ count($rows) }}
</div>
</body>
</html>
