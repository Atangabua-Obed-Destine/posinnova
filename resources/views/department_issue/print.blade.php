<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@lang('lang_v1.department_issue') - {{ $issue->ref_no }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
        .voucher-header { text-align: center; margin-bottom: 12px; }
        .voucher-header h2 { margin: 0; }
        .voucher-meta { width: 100%; margin-bottom: 10px; }
        .voucher-meta td { padding: 3px 6px; vertical-align: top; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th, table.items td { border: 1px solid #555; padding: 5px 7px; }
        table.items th { background: #f3f3f3; text-align: left; }
        .right { text-align: right; }
        .sign-row { margin-top: 45px; width: 100%; border-collapse: collapse; }
        .sign-row td { width: 33%; vertical-align: top; padding-top: 30px; border-top: 1px solid #000; text-align: center; font-size: 11px; }
        .sign-wrap { padding: 0 25px; }
        @media print { body { margin: 8mm; } }
    </style>
</head>
<body>

<div class="voucher-header">
    @if(! empty($business->logo))
        <img src="{{ asset('uploads/business_logos/' . $business->logo) }}" alt="" style="max-height: 70px;">
    @endif
    <h2>{{ $business->name }}</h2>
    <div>{{ optional($issue->location)->name }}</div>
    <h3 style="margin-top: 6px;">@lang('lang_v1.department_issue_voucher')</h3>
</div>

<table class="voucher-meta">
    <tr>
        <td><b>@lang('purchase.ref_no'):</b> {{ $issue->ref_no }}</td>
        <td><b>@lang('messages.date'):</b> {{ @format_datetime($issue->transaction_date) }}</td>
        <td><b>@lang('sale.status'):</b> {{ strtoupper($issue->status) }}</td>
    </tr>
    <tr>
        <td><b>@lang('lang_v1.issuing_pharmacy'):</b> {{ optional($issue->location)->name }}</td>
        <td><b>@lang('lang_v1.department'):</b> {{ optional($department)->name }}@if(! empty(optional($department)->code)) ({{ $department->code }}) @endif</td>
        <td><b>@lang('lang_v1.hod_name'):</b> {{ optional($department)->hod_name }}</td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th style="width:5%;">#</th>
            <th>@lang('sale.product')</th>
            <th style="width:12%;">@lang('product.sku')</th>
            <th class="right" style="width:10%;">@lang('sale.qty')</th>
            @can('view_purchase_price')
                <th class="right" style="width:12%;">@lang('lang_v1.purchase_price')</th>
                <th class="right" style="width:13%;">@lang('sale.subtotal')</th>
            @endcan
        </tr>
    </thead>
    <tbody>
        @php $i = 1; $grand = 0; @endphp
        @foreach($issue->sell_lines as $line)
            @php
                $unit_label = ! empty($line->sub_unit) && ! empty($line->sub_unit->short_name) ? $line->sub_unit->short_name : '';
                $sub = $line->quantity * $line->unit_price;
                $grand += $sub;
            @endphp
            <tr>
                <td>{{ $i++ }}</td>
                <td>{{ optional($line->product)->name }}</td>
                <td>{{ optional($line->variations)->sub_sku }}</td>
                <td class="right">{{ @num_format($line->quantity) }} {{ $unit_label }}</td>
                @can('view_purchase_price')
                    <td class="right">{{ @num_format($line->unit_price) }}</td>
                    <td class="right">{{ @num_format($sub) }}</td>
                @endcan
            </tr>
        @endforeach
    </tbody>
    @can('view_purchase_price')
        <tfoot>
            <tr>
                <th colspan="5" class="right">@lang('sale.total'):</th>
                <th class="right">{{ @num_format($grand) }}</th>
            </tr>
        </tfoot>
    @endcan
</table>

@if(! empty($issue->additional_notes))
    <p style="margin-top:10px;"><b>@lang('purchase.additional_notes'):</b> {{ $issue->additional_notes }}</p>
@endif

<table class="sign-row">
    <tr>
        <td><div class="sign-wrap">@lang('lang_v1.issued_by')<br>{{ optional($creator)->first_name }} {{ optional($creator)->last_name }}</div></td>
        <td><div class="sign-wrap">@lang('lang_v1.received_by')<br>&nbsp;</div></td>
        <td><div class="sign-wrap">@lang('lang_v1.hod_signature')<br>{{ optional($department)->hod_name }}</div></td>
    </tr>
</table>

<script>
    window.addEventListener('load', function() { window.print(); });
</script>
</body>
</html>
