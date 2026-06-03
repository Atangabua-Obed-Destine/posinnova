<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>@lang('inventory_count.blank_sheet_title') — {{ $location->name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #222; }
        h1 { font-size: 14pt; margin: 0 0 4px; }
        .header-meta { font-size: 8pt; color: #555; margin-bottom: 8px; }
        .header-fields { width: 100%; border-collapse: collapse; margin: 8px 0; }
        .header-fields td { padding: 4px 6px; border-bottom: 1px solid #999; font-size: 9pt; }
        .header-fields .label { width: 100px; font-weight: bold; color: #555; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th, table.sheet td { border: 1px solid #999; padding: 3px 4px; vertical-align: top; }
        table.sheet thead th { background: #eee; font-size: 8pt; text-align: left; }
        .sku { width: 70px; font-family: monospace; font-size: 8pt; }
        .product { width: 240px; }
        .unit { width: 70px; }
        .unit small { color: #555; font-size: 7pt; display: block; }
        .theoretical { width: 60px; text-align: right; }
        .counted { width: 80px; }
        .notes-col { width: 90px; }
        .blind .theoretical { background: #fafafa; color: #fafafa; }
        .footer { font-size: 8pt; color: #666; text-align: center; margin-top: 6px; }
        .unit-notice {
            border: 1px solid #c89000;
            background: #fff8e1;
            padding: 6px 8px;
            margin: 6px 0 8px;
            font-size: 8.5pt;
            color: #5a4500;
        }
        .unit-notice strong { color: #7a3a00; }
        @page { margin: 12mm 10mm; }
    </style>
</head>
<body class="{{ $hide_theoretical ? 'blind' : '' }}">

<h1>@lang('inventory_count.blank_sheet_title')</h1>
<div class="header-meta">
    {{ $location->name }} —
    @lang('inventory_count.date'): {{ $generated_at->format('Y-m-d H:i') }}
    @if($hide_theoretical) — <strong>BLIND COUNT</strong> @endif
</div>

<table class="header-fields">
    <tr>
        <td class="label">@lang('inventory_count.counter_name'):</td>
        <td>&nbsp;</td>
        <td class="label">@lang('inventory_count.date'):</td>
        <td>&nbsp;</td>
    </tr>
    <tr>
        <td class="label">@lang('inventory_count.counter_signature'):</td>
        <td colspan="3">&nbsp;</td>
    </tr>
</table>

@if(!empty($any_multi_unit))
    <div class="unit-notice">
        <strong>Unit reminder:</strong>
        The <em>Unit</em> column shows the product's base unit first, with any
        alternative units allowed by the system in parentheses (e.g.
        <em>Tab &nbsp; card-10 (×10)</em>). Always write the counted quantity
        in the <strong>base unit</strong> when possible. If you count in an
        alternative unit, write the unit name next to the number — for
        example <strong>&ldquo;5 card-10&rdquo;</strong> instead of just
        &ldquo;5&rdquo; — so the operator entering the count in the system
        selects the correct unit and the conversion is applied.
    </div>
@endif

<table class="sheet">
    <thead>
        <tr>
            <th class="sku">@lang('product.sku')</th>
            <th class="product">@lang('product.product')</th>
            <th class="unit">@lang('product.unit')</th>
            @if(!empty($lot_tracking))
                <th>@lang('lang_v1.lot')</th>
                <th>@lang('lang_v1.expiry')</th>
            @endif
            <th class="theoretical">@lang('inventory_count.theoretical_stock')</th>
            <th class="counted">@lang('inventory_count.actual_stock')</th>
            <th class="notes-col">@lang('lang_v1.notes')</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $r)
            <tr>
                <td class="sku">{{ $r->sku }}</td>
                <td class="product">
                    {{ $r->product }}
                    @if($r->product_type === 'variable')
                        <br><small>{{ $r->product_variation }} / {{ $r->variation_name }}</small>
                    @endif
                </td>
                <td class="unit">
                    <strong>{{ $r->unit }}</strong>
                    @if(!empty($r->unit_alternatives))
                        <small>or {{ implode(', ', $r->unit_alternatives) }}</small>
                    @endif
                </td>
                @if(!empty($lot_tracking))
                    <td>{{ $r->lot_number ?? '—' }}</td>
                    <td>{{ $r->exp_date ? \Carbon\Carbon::parse($r->exp_date)->format('d M Y') : '—' }}</td>
                @endif
                <td class="theoretical">{{ number_format((float) $r->theoretical, 2) }}</td>
                <td class="counted">&nbsp;</td>
                <td class="notes-col">&nbsp;</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    {{ $rows->count() }} @lang('inventory_count.lines') — @lang('inventory_count.inventory_count') — {{ $generated_at->format('Y-m-d H:i') }}
</div>

</body>
</html>
