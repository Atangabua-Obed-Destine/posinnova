@php
    $business = \App\Business::find(request()->session()->get('user.business_id'));
@endphp
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Balance Sheet — As of {{ $as_of_date }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color:#222; margin:0; }
        h1 { font-size:18px; margin:0 0 2px 0; text-align:center; }
        h2 { font-size:12px; margin:0 0 8px 0; text-align:center; color:#555; font-weight:normal; }
        .status-bar { text-align:center; margin-bottom:10px; font-size:11px; }
        .balanced { color:#1b5e20; font-weight:bold; }
        .out-of-balance { color:#b71c1c; font-weight:bold; }
        table { width:100%; border-collapse: collapse; }
        th, td { border:1px solid #ccc; padding:4px 6px; }
        thead th { background:#eee; }
        .num { text-align:right; font-variant-numeric: tabular-nums; }
        .section td { background:#222; color:#fff; font-weight:bold; text-transform:uppercase; }
        .subgroup td { background:#e8e8e8; font-weight:bold; color:#333; }
        .acct td { padding-left:14px; }
        .syn td { padding-left:14px; font-style:italic; color:#0d47a1; }
        .subtotal td { background:#f4f4f4; font-style:italic; }
        .section-total td { background:#dde3f4; font-weight:bold; border-top:2px solid #444; }
        .grand td { background:#222; color:#fff; font-weight:bold; font-size:11px; }
        .footer-note { margin-top:8px; color:#666; font-size:9px; }
    </style>
</head>
<body>
    <h1>{{ $business->name ?? '' }}</h1>
    <h2>Balance Sheet &middot; As of {{ $as_of_date }}</h2>
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
                <th style="width:70px;">Code</th>
                <th>Account</th>
                <th class="num" style="width:130px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach(['asset', 'liability', 'equity'] as $pt)
                <tr class="section"><td colspan="3">{{ $bs[$pt]['label'] }}</td></tr>
                @foreach($bs[$pt]['subgroups'] as $sub_name => $g)
                    <tr class="subgroup"><td colspan="3">{{ $sub_name }}</td></tr>
                    @foreach($g['rows'] as $row)
                        <tr class="{{ !empty($row->__synthetic) ? 'syn' : 'acct' }}">
                            <td>{{ $row->gl_code ?: '-' }}</td>
                            <td>{{ $row->name }}@if(!empty($row->__synthetic)) <em>(auto)</em>@endif</td>
                            <td class="num">{{ number_format((float)$row->balance, 2) }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        <td colspan="2">Subtotal — {{ $sub_name }}</td>
                        <td class="num">{{ number_format($g['subtotal'], 2) }}</td>
                    </tr>
                @endforeach
                <tr class="section-total">
                    <td colspan="2">Total {{ $bs[$pt]['label'] }}</td>
                    <td class="num">{{ number_format($bs[$pt]['total'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="grand">
                <td colspan="2">TOTAL ASSETS</td>
                <td class="num">{{ number_format($total_assets, 2) }}</td>
            </tr>
            <tr class="grand">
                <td colspan="2">TOTAL LIABILITIES + EQUITY</td>
                <td class="num">{{ number_format($total_liab_owners, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="footer-note">
        Net income (current period) is computed automatically from income minus expense activity and shown as an equity line so the sheet balances while books are open.
        Generated {{ now()->format('Y-m-d H:i') }}.
    </div>
</body>
</html>
