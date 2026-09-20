<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>{{ $report_title }}</title></head>
<body>

@include('report.exports.pdf._header')

<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_items_sold') }}</div>
        <div class="kpi-value">{{ number_format($kpis['total_qty'] ?? 0, 0) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('lang_v1.subtotal') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_subtotal'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_discount') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_discount'] ?? 0, 2) }}</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">{{ __('report.total_tax') }}</div>
        <div class="kpi-value">{{ $currency_symbol }}{{ number_format($kpis['total_tax'] ?? 0, 2) }}</div>
    </div>
</div>

<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>{{ __('product.product_name') }}</th>
            <th>{{ __('product.sku') }}</th>
            <th>{{ __('sale.invoice_no') }}</th>
            <th>{{ __('messages.date') }}</th>
            <th>{{ __('contact.customer') }}</th>
            <th class="num">{{ __('report.unit_price') }}</th>
            <th class="num">{{ __('report.qty') }}</th>
            <th class="num">{{ __('sale.discount') }}</th>
            <th class="num">{{ __('sale.tax') }}</th>
            <th class="num">{{ __('lang_v1.subtotal') }}</th>
        </tr>
    </thead>
    <tbody>
        @php $totals = ['qty'=>0,'subtotal'=>0,'discount'=>0,'tax'=>0]; @endphp
        @foreach($rows as $i => $row)
        @php
            $qty = (float)($row->quantity ?? 0);
            $unit_price = (float)($row->unit_price_before_discount ?? 0);
            $sale_price = (float)($row->unit_price_inc_tax ?? 0);
            $line_discount_amount = (float)($row->line_discount_amount ?? 0);
            if (($row->line_discount_type ?? '') === 'percentage') {
                $line_discount_amount = $unit_price * $qty * ($line_discount_amount / 100);
            }
            $item_tax = (float)($row->item_tax ?? 0) * $qty;
            $subtotal = (float)($row->subtotal ?? ($sale_price * $qty));
        @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->product_name ?? '' }}</td>
            <td>{{ $row->sub_sku ?? '' }}</td>
            <td>{{ $row->invoice_no ?? '' }}</td>
            <td>{{ $row->transaction_date ?? '' }}</td>
            <td>{{ $row->customer_name ?? '' }}</td>
            <td class="num">{{ number_format($sale_price, 2) }}</td>
            <td class="num">{{ number_format($qty, 2) }}</td>
            <td class="num">{{ number_format($line_discount_amount, 2) }}</td>
            <td class="num">{{ number_format($item_tax, 2) }}</td>
            <td class="num">{{ number_format($subtotal, 2) }}</td>
        </tr>
        @php
            $totals['qty'] += $qty;
            $totals['subtotal'] += $subtotal;
            $totals['discount'] += $line_discount_amount;
            $totals['tax'] += $item_tax;
        @endphp
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="7"><strong>{{ __('lang_v1.total') }}</strong></td>
            <td class="num">{{ number_format($totals['qty'], 2) }}</td>
            <td class="num">{{ number_format($totals['discount'], 2) }}</td>
            <td class="num">{{ number_format($totals['tax'], 2) }}</td>
            <td class="num">{{ number_format($totals['subtotal'], 2) }}</td>
        </tr>
    </tfoot>
</table>

<div class="report-footer">
    {{ session('business.name') }} &mdash; {{ $report_title }} &mdash; {{ __('lang_v1.confidential') }}
    &nbsp;&nbsp;&nbsp; {{ __('report.total_records') }}: {{ count($rows) }}
</div>
</body>
</html>
