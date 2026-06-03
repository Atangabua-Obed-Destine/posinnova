<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>@lang('inventory_count.variance_report') — {{ $session->reference_no }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
        h1 { font-size: 14pt; margin: 0 0 4px; }
        h2 { font-size: 11pt; margin: 14px 0 4px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9pt; }
        .meta td { padding: 3px 6px; border-bottom: 1px solid #ccc; vertical-align: top; }
        .meta .label { width: 110px; font-weight: bold; color: #555; }

        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #999; padding: 3px 4px; vertical-align: top; }
        table.lines thead th { background: #eee; font-size: 8pt; text-align: left; }
        .sku { width: 70px; font-family: monospace; font-size: 8pt; }
        .num { text-align: right; }
        .neg { color: #c0392b; }
        .pos { color: #27ae60; }

        .totals { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 10pt; }
        .totals td { padding: 4px 8px; }
        .totals .label { font-weight: bold; width: 200px; text-align: right; color: #555; }

        .signatures { width: 100%; margin-top: 25px; border-collapse: collapse; }
        .signatures td { width: 33%; padding: 22px 8px 4px; border-top: 1px solid #333; text-align: center; font-size: 8pt; color: #555; }

        .footer { font-size: 7pt; color: #999; text-align: center; margin-top: 10px; }
        @page { margin: 12mm 10mm; }
    </style>
</head>
<body>

<h1>@lang('inventory_count.variance_report')</h1>
<div style="font-size:9pt;color:#666;">{{ $session->reference_no }} — {{ $session->location->name ?? '' }}</div>

<table class="meta">
    <tr>
        <td class="label">@lang('inventory_count.started'):</td>
        <td>{{ optional($session->started_at)->format('Y-m-d H:i') }}
            ({{ optional($session->startedBy)->first_name }} {{ optional($session->startedBy)->surname }})</td>
        <td class="label">@lang('inventory_count.posted_at'):</td>
        <td>{{ optional($session->posted_at)->format('Y-m-d H:i') }}
            ({{ optional($session->postedBy)->first_name }} {{ optional($session->postedBy)->surname }})</td>
    </tr>
    <tr>
        <td class="label">@lang('inventory_count.scope'):</td>
        <td>
            @if(! $session->scope_type || $session->scope_type === 'all')
                @lang('inventory_count.scope_all')
            @else
                @lang('inventory_count.scope_' . $session->scope_type): {{ $session->scope_label }}
            @endif
        </td>
        <td class="label">@lang('inventory_count.progress'):</td>
        <td>{{ $session->lines_counted }} / {{ $session->lines_total }} @lang('inventory_count.lines')</td>
    </tr>
    @if($reason_label)
    <tr>
        <td class="label">@lang('inventory_count.reason'):</td>
        <td colspan="3">{{ $reason_label }}</td>
    </tr>
    @endif
    @if($session->notes)
    <tr>
        <td class="label">@lang('lang_v1.notes'):</td>
        <td colspan="3">{{ $session->notes }}</td>
    </tr>
    @endif
</table>

<h2>@lang('inventory_count.variances') ({{ $rows->count() }})</h2>

@if($rows->isEmpty())
    <p><em>@lang('inventory_count.no_variances_detected')</em></p>
@else
    <table class="lines">
        <thead>
            <tr>
                <th class="sku">@lang('product.sku')</th>
                <th>@lang('product.product')</th>
                <th>@lang('product.unit')</th>
                @if(!empty($lot_tracking))
                    <th>@lang('lang_v1.lot')</th>
                    <th>@lang('lang_v1.expiry')</th>
                @endif
                <th class="num">@lang('inventory_count.theoretical_stock')</th>
                <th class="num">@lang('inventory_count.actual_stock')</th>
                <th class="num">@lang('inventory_count.variance')</th>
                <th class="num">@lang('inventory_count.unit_cost')</th>
                <th class="num">@lang('inventory_count.variance_value')</th>
            </tr>
        </thead>
        <tbody>
            @php
                $tot_short_qty = 0; $tot_surplus_qty = 0;
                $tot_short_val = 0; $tot_surplus_val = 0;
            @endphp
            @foreach($rows as $r)
                @php
                    $diff = (float) $r->diff;
                    $val = (float) $r->diff_value;
                    if ($diff < 0) { $tot_short_qty += abs($diff); $tot_short_val += abs($val); }
                    else            { $tot_surplus_qty += $diff;   $tot_surplus_val += $val; }
                    $cls = $diff < 0 ? 'neg' : 'pos';
                @endphp
                <tr>
                    <td class="sku">{{ $r->sku }}</td>
                    <td>
                        {{ $r->product }}
                        @if($r->product_type === 'variable')
                            <br><small style="color:#666;">{{ $r->product_variation }} / {{ $r->variation_name }}</small>
                        @endif
                    </td>
                    <td>{{ $r->unit }}</td>
                    @if(!empty($lot_tracking))
                        <td>{{ $r->lot_number ?? '—' }}</td>
                        <td>{{ $r->exp_date ? \Carbon\Carbon::parse($r->exp_date)->format('d M Y') : '—' }}</td>
                    @endif
                    <td class="num">{{ number_format((float) $r->theoretical_at_start, 2) }}</td>
                    <td class="num">{{ number_format((float) $r->counted_qty, 2) }}</td>
                    <td class="num {{ $cls }}">{{ ($diff > 0 ? '+' : '') . number_format($diff, 2) }}</td>
                    <td class="num">{{ number_format((float) $r->unit_cost, 2) }}</td>
                    <td class="num {{ $cls }}">{{ ($val > 0 ? '+' : '') . number_format($val, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label">@lang('inventory_count.total_shortage_qty'):</td>
            <td class="neg">{{ number_format($tot_short_qty, 2) }}</td>
            <td class="label">@lang('inventory_count.total_shortage_value'):</td>
            <td class="neg">{{ number_format($tot_short_val, 2) }}</td>
        </tr>
        <tr>
            <td class="label">@lang('inventory_count.total_surplus_qty'):</td>
            <td class="pos">{{ number_format($tot_surplus_qty, 2) }}</td>
            <td class="label">@lang('inventory_count.total_surplus_value'):</td>
            <td class="pos">{{ number_format($tot_surplus_val, 2) }}</td>
        </tr>
        <tr>
            <td class="label"><strong>@lang('inventory_count.net_impact'):</strong></td>
            <td colspan="3"><strong>{{ number_format($tot_surplus_val - $tot_short_val, 2) }}</strong></td>
        </tr>
    </table>
@endif

@if($session->shortage_transaction_id || $session->surplus_transaction_id)
<table class="meta" style="margin-top:14px;">
    @if($session->shortage_transaction_id)
    <tr>
        <td class="label">@lang('inventory_count.shortage_txn'):</td>
        <td>#{{ $session->shortage_transaction_id }}</td>
    </tr>
    @endif
    @if($session->surplus_transaction_id)
    <tr>
        <td class="label">@lang('inventory_count.surplus_txn'):</td>
        <td>#{{ $session->surplus_transaction_id }}</td>
    </tr>
    @endif
</table>
@endif

<table class="signatures">
    <tr>
        <td>@lang('inventory_count.counter_signature')</td>
        <td>@lang('inventory_count.supervisor_signature')</td>
        <td>@lang('inventory_count.finance_signature')</td>
    </tr>
</table>

<div class="footer">
    @lang('inventory_count.generated_at'): {{ $generated_at->format('Y-m-d H:i') }} — {{ $session->reference_no }}
</div>

</body>
</html>
