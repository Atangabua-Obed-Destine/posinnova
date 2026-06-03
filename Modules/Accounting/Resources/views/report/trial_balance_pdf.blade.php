@php
    $business = \App\Business::find(request()->session()->get('user.business_id'));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Trial Balance — {{ $start_date }} ~ {{ $end_date }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color:#222; margin:0; }
        h1 { font-size:16px; margin:0 0 2px 0; text-align:center; }
        h2 { font-size:11px; margin:0 0 8px 0; text-align:center; color:#555; font-weight:normal; }
        .status-bar { text-align:center; margin-bottom:8px; font-size:10px; }
        .balanced { color:#1b5e20; font-weight:bold; }
        .out-of-balance { color:#b71c1c; font-weight:bold; }
        table { width:100%; border-collapse: collapse; }
        th, td { border:1px solid #ccc; padding:3px 5px; }
        thead th { background:#eee; font-size:9px; }
        .num { text-align:right; font-variant-numeric: tabular-nums; }
        .group td {
            background:#e8e8e8;
            font-weight:bold;
            text-transform:uppercase;
            letter-spacing:0.5px;
        }
        .subtotal td { background:#f4f4f4; font-weight:bold; }
        .grand td { background:#222; color:#fff; font-weight:bold; }
        .footer-note { margin-top:6px; color:#666; font-size:8px; }
    </style>
</head>
<body>
    <h1>{{ $business->name ?? '' }}</h1>
    <h2>Trial Balance &middot; {{ $start_date }} ~ {{ $end_date }}</h2>
    <div class="status-bar">
        @if($is_balanced)
            <span class="balanced">✓ BALANCED</span>
        @else
            <span class="out-of-balance">✗ OUT OF BALANCE BY {{ number_format($imbalance, 2) }}</span>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:60px;">Code</th>
                <th rowspan="2">Account</th>
                <th rowspan="2" style="width:90px;">Type</th>
                <th colspan="2" style="text-align:center;">Opening</th>
                <th colspan="2" style="text-align:center;">Period</th>
                <th colspan="2" style="text-align:center;">Closing</th>
            </tr>
            <tr>
                <th class="num">Dr</th><th class="num">Cr</th>
                <th class="num">Dr</th><th class="num">Cr</th>
                <th class="num">Dr</th><th class="num">Cr</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ordered_groups as $pt => $g)
                <tr class="group"><td colspan="9">{{ $g['label'] }}</td></tr>
                @foreach($g['rows'] as $row)
                    <tr>
                        <td>{{ $row->gl_code ?: '-' }}</td>
                        <td>{{ $row->name }}</td>
                        <td>{{ $row->sub_type }}</td>
                        <td class="num">@if($row->opening_debit  != 0){{ number_format($row->opening_debit, 2) }}@endif</td>
                        <td class="num">@if($row->opening_credit != 0){{ number_format($row->opening_credit, 2) }}@endif</td>
                        <td class="num">@if((float)$row->period_debit  != 0){{ number_format($row->period_debit, 2) }}@endif</td>
                        <td class="num">@if((float)$row->period_credit != 0){{ number_format($row->period_credit, 2) }}@endif</td>
                        <td class="num">@if($row->closing_debit  != 0){{ number_format($row->closing_debit, 2) }}@endif</td>
                        <td class="num">@if($row->closing_credit != 0){{ number_format($row->closing_credit, 2) }}@endif</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="3">Subtotal — {{ $g['label'] }}</td>
                    <td class="num">{{ number_format($g['subtotals']['opening_debit'], 2) }}</td>
                    <td class="num">{{ number_format($g['subtotals']['opening_credit'], 2) }}</td>
                    <td class="num">{{ number_format($g['subtotals']['period_debit'], 2) }}</td>
                    <td class="num">{{ number_format($g['subtotals']['period_credit'], 2) }}</td>
                    <td class="num">{{ number_format($g['subtotals']['closing_debit'], 2) }}</td>
                    <td class="num">{{ number_format($g['subtotals']['closing_credit'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="grand">
                <td colspan="3">GRAND TOTAL</td>
                <td class="num">{{ number_format($totals['opening_debit'], 2) }}</td>
                <td class="num">{{ number_format($totals['opening_credit'], 2) }}</td>
                <td class="num">{{ number_format($totals['period_debit'], 2) }}</td>
                <td class="num">{{ number_format($totals['period_credit'], 2) }}</td>
                <td class="num">{{ number_format($totals['closing_debit'], 2) }}</td>
                <td class="num">{{ number_format($totals['closing_credit'], 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer-note">
        Generated {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
