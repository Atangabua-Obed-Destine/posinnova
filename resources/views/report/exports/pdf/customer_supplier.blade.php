<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_purchase') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_purchase'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_sales') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_invoice'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card" style="{{ ($kpis['total_due'] ?? 0) > 0 ? 'background:#FFE0E0;border-color:#FF4444;' : 'background:#E2EFDA;' }}">
        <div class="kpi-label">{{ __('lang_v1.total_due') }}</div>
        <div class="kpi-value" style="color:{{ ($kpis['total_due'] ?? 0) > 0 ? '#C00000' : '#375623' }};">{{ $currency_symbol }}{{ number_format($kpis['total_due'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_contacts') }}</div>
        <div class="kpi-value">{{ $kpis['contact_count'] ?? 0 }}</div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('contact.name') }}</th>
            <th>{{ __('lang_v1.contact_type') }}</th>
            <th class="num">{{ __('report.total_purchase') }}</th>
            <th class="num">{{ __('lang_v1.purchase_return') }}</th>
            <th class="num">{{ __('report.total_sales') }}</th>
            <th class="num">{{ __('lang_v1.sell_return') }}</th>
            <th class="num">{{ __('lang_v1.opening_balance') }}</th>
            <th class="num">{{ __('lang_v1.total_due') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $totals = ['purchase'=>0,'purchase_return'=>0,'invoice'=>0,'sell_return'=>0,'due'=>0]; @endphp
        @foreach($rows as $i => $row)
        @php
            $name = $row->name ?? '';
            if (!empty($row->supplier_business_name)) $name .= ' (' . $row->supplier_business_name . ')';
            $tld_sell = $row->total_ledger_discount_sell ?? 0;
            $tld_purch = $row->total_ledger_discount_purchase ?? 0;
            $due = ((float)($row->total_invoice ?? 0) - (float)($row->invoice_received ?? 0) - $tld_sell)
                 - ((float)($row->total_purchase ?? 0) - (float)($row->purchase_paid ?? 0) - $tld_purch)
                 - ((float)($row->total_sell_return ?? 0) - (float)($row->sell_return_paid ?? 0))
                 + ((float)($row->total_purchase_return ?? 0) - (float)($row->purchase_return_received ?? 0));
            if (($row->contact_type ?? '') === 'supplier') {
                $due -= (float)($row->opening_balance ?? 0) - (float)($row->opening_balance_paid ?? 0);
            } else {
                $due += (float)($row->opening_balance ?? 0) - (float)($row->opening_balance_paid ?? 0);
            }
        @endphp
        <tr style="{{ $due > 0 ? 'background:#FFF3F3;' : '' }}">
            <td>{{ $i + 1 }}</td>
            <td>{{ $name }}</td>
            <td>{{ ucfirst($row->contact_type ?? '') }}</td>
            <td class="num">{{ number_format($row->total_purchase ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->total_purchase_return ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->total_invoice ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->total_sell_return ?? 0, 2) }}</td>
            <td class="num">{{ number_format($row->opening_balance ?? 0, 2) }}</td>
            <td class="num" style="{{ $due > 0 ? 'color:#C00000;font-weight:bold;' : '' }}">{{ number_format($due, 2) }}</td>
        </tr>
        @php
            $totals['purchase'] += $row->total_purchase ?? 0;
            $totals['purchase_return'] += $row->total_purchase_return ?? 0;
            $totals['invoice'] += $row->total_invoice ?? 0;
            $totals['sell_return'] += $row->total_sell_return ?? 0;
            $totals['due'] += $due;
        @endphp
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3"><strong>{{ __('lang_v1.total') }}</strong></td>
            <td class="num">{{ number_format($totals['purchase'], 2) }}</td>
            <td class="num">{{ number_format($totals['purchase_return'], 2) }}</td>
            <td class="num">{{ number_format($totals['invoice'], 2) }}</td>
            <td class="num">{{ number_format($totals['sell_return'], 2) }}</td>
            <td class="num"></td>
            <td class="num">{{ number_format($totals['due'], 2) }}</td>
        </tr>
    </tfoot>
</table>

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
    &nbsp;&nbsp;&nbsp; {{ __('report.total_records') }}: {{ count($rows) }}
</div>
</body>
</html>
