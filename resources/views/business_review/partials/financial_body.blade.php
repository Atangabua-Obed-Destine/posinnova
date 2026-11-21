{{-- PosInnova: the Financial Assessment Summary (App\Utils\BusinessReview::financial). Same markup on
     screen, on paper and in the PDF. --}}
@php
    $m = fn ($n) => $tu->num_f($n, true);
    $q = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    $dt = fn ($c) => $tu->format_date($c->format('Y-m-d'));
    $pc = fn ($n) => $n === null ? '—' : number_format($n, 1) . '%';
    $inc = $d['income'];
    $prev = $d['previous'];
    $pinc = $prev ? $prev['income'] : null;
    // the change against the previous period; $good_up = false when a rise is bad (expenses)
    $delta = function ($now, $before, $good_up = true) {
        $ch = \App\Utils\BusinessReview::change($now, $before);
        if ($ch === null) {
            return '<span class="br-flat">—</span>';
        }
        if (abs($ch) < 0.5) {
            return '<span class="br-flat">= 0%</span>';
        }
        $good = ($ch > 0) === $good_up;

        return '<span class="' . ($good ? 'br-up' : 'br-down') . '">' . ($ch > 0 ? '▲' : '▼') . ' ' . number_format(abs($ch), abs($ch) < 10 ? 1 : 0) . '%</span>';
    };
    $bar = function ($pct, $tone = '') {
        $pct = max(0, min(100, (float) $pct));
        if ($pct < 0.5) {
            return '<table class="br-bar"><tr><td class="rest"></td></tr></table>';
        }

        return '<table class="br-bar"><tr><td class="fill ' . $tone . '" style="width:' . round($pct, 1) . '%"></td>' . ($pct < 99.5 ? '<td class="rest"></td>' : '') . '</tr></table>';
    };
    $trend_label = function ($b) use ($d, $dt) {
        if ($d['trend']['by'] === 'month') {
            return ucfirst($b['from']->locale(app()->getLocale())->isoFormat('MMM YYYY'));
        }
        if ($d['trend']['by'] === 'week') {
            return $b['from']->locale(app()->getLocale())->isoFormat('D MMM') . ' – ' . $b['to']->locale(app()->getLocale())->isoFormat('D MMM');
        }

        return ucfirst($b['from']->locale(app()->getLocale())->isoFormat('ddd D MMM'));
    };
    $age_labels = ['0_30' => __('review.age_0_30'), '31_60' => __('review.age_31_60'), '61_90' => __('review.age_61_90'), '90_plus' => __('review.age_90_plus')];
    $age_tone = ['0_30' => '', '31_60' => 'amber', '61_90' => 'amber', '90_plus' => 'red'];
    $checks = $d['checks'];
@endphp
<div class="br">
    {{-- header --}}
    <table class="br-head">
        <tr>
            <td style="width:62%;">
                @if (! empty($logo))
                    <img src="{{ $logo }}" class="br-logo" alt=""><br>
                @endif
                <div class="br-biz">{{ $biz->name }}</div>
                @if ($location_name) <div class="br-sub">{{ $location_name }}</div> @endif
                <div class="br-title">{{ __('review.fin_title') }}</div>
                <div class="br-sub">{{ $dt($d['start']) }} – {{ $dt($d['end']) }} · {{ trans_choice('review.days_n', $d['days'], ['n' => $d['days']]) }}</div>
            </td>
            <td class="br-meta">
                {{ __('review.prepared_with') }} <strong>I-NNOVA POS</strong><br>
                {{ __('review.generated', ['date' => $tu->format_date(now()->format('Y-m-d H:i'), true)]) }}<br>
                @if (! empty($prepared_by)) {{ __('review.by', ['name' => $prepared_by]) }}<br> @endif
                @if ($prev) <span class="br-muted">{{ __('review.compared_with', ['from' => $dt($prev['start']), 'to' => $dt($prev['end'])]) }}</span> @endif
            </td>
        </tr>
    </table>
    <div class="br-rule"></div>

    {{-- key figures --}}
    <table class="br-kpis br-keep">
        <tr>
            <td><div class="br-kpi" id="br_kpi_sales">
                <div class="br-kpi-label">{{ __('review.kpi_sales') }}</div>
                <div class="br-kpi-val">{{ $m($d['sales']['invoiced']) }}</div>
                <div class="br-kpi-sub">{{ trans_choice('review.sales_n', $d['sales']['count'], ['n' => number_format($d['sales']['count'])]) }} @if ($prev) · {!! $delta($d['sales']['invoiced'], $prev['sales']['invoiced']) !!} @endif</div>
            </div></td>
            <td><div class="br-kpi" id="br_kpi_gross">
                <div class="br-kpi-label">{{ __('review.kpi_gross') }}</div>
                <div class="br-kpi-val">{{ $m($inc['gross']) }}</div>
                <div class="br-kpi-sub">{{ __('review.margin_of', ['pct' => $pc($inc['margin'])]) }} @if ($prev) · {!! $delta($inc['gross'], $pinc['gross']) !!} @endif</div>
            </div></td>
            <td><div class="br-kpi" id="br_kpi_expenses">
                <div class="br-kpi-label">{{ __('review.kpi_expenses') }}</div>
                <div class="br-kpi-val">{{ $m($inc['expenses']) }}</div>
                <div class="br-kpi-sub">@if ($prev) {!! $delta($inc['expenses'], $pinc['expenses'], false) !!} {{ __('review.vs_previous') }} @else &nbsp; @endif</div>
            </div></td>
            <td><div class="br-kpi" id="br_kpi_net">
                <div class="br-kpi-label">{{ __('review.kpi_net') }}</div>
                <div class="br-kpi-val {{ $inc['net'] < 0 ? 'br-neg' : '' }}">{{ $m($inc['net']) }}</div>
                <div class="br-kpi-sub">{{ __('review.margin_of', ['pct' => $pc($inc['net_margin'])]) }} @if ($prev) · {!! $delta($inc['net'], $pinc['net']) !!} @endif</div>
            </div></td>
        </tr>
    </table>

    {{-- observations --}}
    @if (! empty($d['observations']))
        <div class="br-h">{{ __('review.observations') }}</div>
        <div class="br-intro">{{ __('review.intro_obs') }}</div>
        <table class="br-obs" id="br_observations">
            @foreach ($d['observations'] as [$tone, $text])
                <tr><td style="width:18px;"><span class="br-dot {{ $tone }}"></span></td><td>{{ $text }}</td></tr>
            @endforeach
        </table>
    @endif

    {{-- income statement --}}
    <div class="br-h">{{ __('review.income_statement') }} <small>· {{ __('review.income_note') }}</small></div>
    <div class="br-intro">{{ __('review.intro_income') }}</div>
    <div class="br-scroll">
    <table class="br-t br-keep" id="br_income">
        <thead><tr><th></th><th class="num">{{ __('review.this_period') }}</th>@if ($prev)<th class="num">{{ __('review.previous_period') }}</th><th class="num">{{ __('review.change') }}</th>@endif</tr></thead>
        <tbody>
            <tr><td>{{ __('review.line_revenue') }}</td><td class="num">{{ $m($inc['revenue']) }}</td>@if ($prev)<td class="num br-muted">{{ $m($pinc['revenue']) }}</td><td class="num">{!! $delta($inc['revenue'], $pinc['revenue']) !!}</td>@endif</tr>
            <tr><td class="indent">{{ __('review.line_cogs') }}</td><td class="num">− {{ $m($inc['cogs']) }}</td>@if ($prev)<td class="num br-muted">− {{ $m($pinc['cogs']) }}</td><td class="num">{!! $delta($inc['cogs'], $pinc['cogs'], false) !!}</td>@endif</tr>
            <tr class="sub"><td>{{ __('review.line_gross') }} <span class="br-muted br-small">({{ $pc($inc['margin']) }})</span></td><td class="num">{{ $m($inc['gross']) }}</td>@if ($prev)<td class="num br-muted">{{ $m($pinc['gross']) }}</td><td class="num">{!! $delta($inc['gross'], $pinc['gross']) !!}</td>@endif</tr>
            <tr><td class="indent">{{ __('review.line_discounts') }}</td><td class="num">− {{ $m($inc['discounts']) }}</td>@if ($prev)<td class="num br-muted">− {{ $m($pinc['discounts']) }}</td><td class="num">{!! $delta($inc['discounts'], $pinc['discounts'], false) !!}</td>@endif</tr>
            <tr><td class="indent">{{ __('review.line_expenses') }}</td><td class="num">− {{ $m($inc['expenses']) }}</td>@if ($prev)<td class="num br-muted">− {{ $m($pinc['expenses']) }}</td><td class="num">{!! $delta($inc['expenses'], $pinc['expenses'], false) !!}</td>@endif</tr>
            <tr><td class="indent">{{ __('review.line_losses') }}</td><td class="num">{{ $inc['losses'] < 0 ? '+ ' . $m(-$inc['losses']) : '− ' . $m($inc['losses']) }}</td>@if ($prev)<td class="num br-muted">{{ $pinc['losses'] < 0 ? '+ ' . $m(-$pinc['losses']) : '− ' . $m($pinc['losses']) }}</td><td class="num">{!! $delta($inc['losses'], $pinc['losses'], false) !!}</td>@endif</tr>
            @if (abs($inc['other']) > 0 || ($prev && abs($pinc['other']) > 0))
                <tr><td class="indent">{{ __('review.line_other') }}</td><td class="num">{{ $inc['other'] < 0 ? '− ' . $m(-$inc['other']) : '+ ' . $m($inc['other']) }}</td>@if ($prev)<td class="num br-muted">{{ $pinc['other'] < 0 ? '− ' . $m(-$pinc['other']) : '+ ' . $m($pinc['other']) }}</td><td class="num"></td>@endif</tr>
            @endif
            <tr class="grand"><td>{{ __('review.line_net') }} <span class="br-muted br-small">({{ $pc($inc['net_margin']) }})</span></td><td class="num {{ $inc['net'] < 0 ? 'br-neg' : '' }}">{{ $m($inc['net']) }}</td>@if ($prev)<td class="num br-muted">{{ $m($pinc['net']) }}</td><td class="num">{!! $delta($inc['net'], $pinc['net']) !!}</td>@endif</tr>
        </tbody>
    </table>
    </div>
    <div class="br-muted br-small" style="margin-top:4px;">{{ __('review.income_footnote') }}</div>

    {{-- sales activity --}}
    <div class="br-h">{{ __('review.sales_activity') }}</div>
    <div class="br-intro">{{ __('review.intro_sales') }}</div>
    <table class="br-two"><tr>
        <td class="col l">
            <table class="br-t" id="br_sales">
                <tr><td>{{ __('review.sa_count') }}</td><td class="num">{{ number_format($d['sales']['count']) }}</td>@if ($prev)<td class="num">{!! $delta($d['sales']['count'], $prev['sales']['count']) !!}</td>@endif</tr>
                <tr><td>{{ __('review.sa_invoiced') }}</td><td class="num">{{ $m($d['sales']['invoiced']) }}</td>@if ($prev)<td class="num">{!! $delta($d['sales']['invoiced'], $prev['sales']['invoiced']) !!}</td>@endif</tr>
                <tr><td>{{ __('review.sa_average') }}</td><td class="num">{{ $m($d['sales']['average']) }}</td>@if ($prev)<td class="num">{!! $delta($d['sales']['average'], $prev['sales']['average']) !!}</td>@endif</tr>
                <tr><td>{{ __('review.sa_per_day') }}</td><td class="num">{{ $m($d['sales']['invoiced'] / max(1, $d['days'])) }}</td>@if ($prev)<td></td>@endif</tr>
                <tr><td>{{ __('review.sa_tax') }}</td><td class="num">{{ $m($d['sales']['tax']) }}</td>@if ($prev)<td></td>@endif</tr>
                <tr><td>{{ __('review.sa_returns') }}</td><td class="num">{{ $m($d['sales']['returns']) }} <span class="br-muted">({{ $d['sales']['returns_count'] }})</span></td>@if ($prev)<td class="num">{!! $delta($d['sales']['returns'], $prev['sales']['returns'], false) !!}</td>@endif</tr>
            </table>
        </td>
        <td class="col r">
            <table class="br-t" id="br_trend">
                <thead><tr><th>{{ __('review.trend_' . $d['trend']['by']) }}</th><th style="width:45%;"></th><th class="num">{{ __('review.sales') }}</th></tr></thead>
                @foreach ($d['trend']['buckets'] as $b)
                    <tr><td class="br-small" style="white-space:nowrap;">{{ $trend_label($b) }}</td><td style="vertical-align:middle;">{!! $bar($d['trend']['max'] > 0 ? $b['total'] / $d['trend']['max'] * 100 : 0, 'blue') !!}</td><td class="num br-small">{{ $m($b['total']) }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr></table>

    @if (! empty($d['by_location']))
        <table class="br-t br-keep" id="br_by_location" style="margin-top:10px;">
            <thead><tr><th>{{ __('business.location') }}</th><th class="num">{{ __('review.sa_count') }}</th><th class="num">{{ __('review.sa_invoiced') }}</th><th class="num">{{ __('review.line_gross') }}</th><th style="width:22%;"></th></tr></thead>
            @php $lmax = max(array_column($d['by_location'], 'invoiced') ?: [0]); @endphp
            @foreach ($d['by_location'] as $l)
                <tr><td>{{ $l['name'] }}</td><td class="num">{{ number_format($l['count']) }}</td><td class="num">{{ $m($l['invoiced']) }}</td><td class="num">{{ $m($l['gross']) }}</td><td style="vertical-align:middle;">{!! $bar($lmax > 0 ? $l['invoiced'] / $lmax * 100 : 0, 'blue') !!}</td></tr>
            @endforeach
        </table>
    @endif

    @if (! empty($d['top_products']))
        <div class="br-h">{{ __('review.top_products') }}</div>
        <div class="br-scroll">
        <table class="br-t" id="br_top_products">
            <thead><tr><th>#</th><th>{{ __('sale.product') }}</th><th class="num">{{ __('review.qty') }}</th><th class="num">{{ __('review.sales') }}</th><th class="num">{{ __('review.profit') }}</th><th class="num">{{ __('review.margin') }}</th><th class="num">{{ __('review.share') }}</th></tr></thead>
            @foreach ($d['top_products'] as $i => $p)
                <tr>
                    <td class="br-muted">{{ $i + 1 }}</td>
                    <td>{{ $p['name'] }} @if ($p['sku'])<span class="br-muted br-small">· {{ $p['sku'] }}</span>@endif</td>
                    <td class="num">{{ $q($p['qty']) }} <span class="br-muted br-small">{{ $p['unit'] }}</span></td>
                    <td class="num">{{ $m($p['revenue']) }}</td>
                    <td class="num {{ $p['profit'] < 0 ? 'br-neg' : '' }}">{{ $m($p['profit']) }}</td>
                    <td class="num">{{ $pc($p['margin']) }}</td>
                    <td class="num">{{ $pc($p['share']) }}</td>
                </tr>
            @endforeach
        </table>
        </div>
    @endif

    @if (! empty($d['top_customers']))
        <div class="br-h">{{ __('review.top_customers') }}</div>
        <table class="br-t br-keep" id="br_top_customers">
            <thead><tr><th>{{ __('contact.customer') }}</th><th class="num">{{ __('review.sa_count') }}</th><th class="num">{{ __('review.sales') }}</th></tr></thead>
            @foreach ($d['top_customers'] as $c)
                <tr><td>{{ $c['name'] }}</td><td class="num">{{ number_format($c['n']) }}</td><td class="num">{{ $m($c['total']) }}</td></tr>
            @endforeach
        </table>
    @endif

    {{-- money in and out --}}
    <div class="br-h">{{ __('review.money') }} <small>· {{ __('review.money_note') }}</small></div>
    <div class="br-intro">{{ __('review.intro_money') }}</div>
    <table class="br-two"><tr>
        <td class="col l">
            <table class="br-t" id="br_money_in">
                <thead><tr><th>{{ __('review.received_by_method') }}</th><th class="num">{{ __('review.amount') }}</th><th class="num">{{ __('review.share') }}</th></tr></thead>
                @forelse ($d['money']['by_method'] as $pm)
                    <tr><td>{{ $pm['method'] }} <span class="br-muted br-small">({{ number_format($pm['n']) }})</span></td><td class="num">{{ $m($pm['amount']) }}</td><td class="num">{{ $pc($d['money']['received'] > 0 ? $pm['amount'] / $d['money']['received'] * 100 : null) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="br-muted">{{ __('review.none') }}</td></tr>
                @endforelse
                <tr class="total"><td>{{ __('review.received_total') }}</td><td class="num">{{ $m($d['money']['received']) }}</td><td></td></tr>
            </table>
        </td>
        <td class="col r">
            <table class="br-t" id="br_money_out">
                <tr><td>{{ __('review.received_total') }}</td><td class="num br-pos">+ {{ $m($d['money']['received']) }}</td></tr>
                @if ($d['money']['supplier_refunds'] > 0)<tr><td>{{ __('review.supplier_refunds') }}</td><td class="num br-pos">+ {{ $m($d['money']['supplier_refunds']) }}</td></tr>@endif
                <tr><td>{{ __('review.paid_suppliers') }}</td><td class="num">− {{ $m($d['money']['to_suppliers']) }}</td></tr>
                <tr><td>{{ __('review.paid_expenses') }}</td><td class="num">− {{ $m($d['money']['expenses_paid']) }}</td></tr>
                @if ($d['money']['refunds'] > 0)<tr><td>{{ __('review.refunds') }}</td><td class="num">− {{ $m($d['money']['refunds']) }}</td></tr>@endif
                <tr class="total"><td>{{ __('review.net_cash') }}</td><td class="num {{ $d['money']['net'] < 0 ? 'br-neg' : '' }}">{{ $m($d['money']['net']) }}</td></tr>
            </table>
        </td>
    </tr></table>

    {{-- from profit to cash --}}
    @php
        $br = $d['bridge'];
        $sign = fn ($v) => $v < 0 ? '− ' . $m(-$v) : '+ ' . $m($v);
    @endphp
    <div class="br-h">{{ __('review.bridge_title') }}</div>
    <div class="br-intro">{{ __('review.intro_bridge') }}</div>
    <table class="br-t br-keep" id="br_bridge">
        <tr class="sub"><td>{{ __('review.b_net') }}</td><td class="num">{{ $m($br['profit']) }}</td></tr>
        <tr><td class="indent">{{ __($br['receivables'] < 0 ? 'review.b_rec_up' : 'review.b_rec_down') }}
            <div class="br-muted br-small">{{ __('review.b_rec_detail', ['start' => $m($br['rec_start']), 'end' => $m($d['receivables']['total'])]) }}</div></td>
            <td class="num">{{ $sign($br['receivables']) }}</td></tr>
        <tr><td class="indent">{{ __($br['payables'] >= 0 ? 'review.b_pay_up' : 'review.b_pay_down') }}
            <div class="br-muted br-small">{{ __('review.b_pay_detail', ['start' => $m($br['pay_start']), 'end' => $m($d['payables']['total'])]) }}</div></td>
            <td class="num">{{ $sign($br['payables']) }}</td></tr>
        <tr><td class="indent">{{ __($br['stock'] < 0 ? 'review.b_stock_up' : 'review.b_stock_down') }}
            <div class="br-muted br-small">{{ __('review.b_stock_detail', ['start' => $m($d['stock']['opening']), 'end' => $m($d['stock']['closing'])]) }}</div></td>
            <td class="num">{{ $sign($br['stock']) }}</td></tr>
        @if (abs($br['other']) > 0)
            <tr><td class="indent">{{ __('review.b_other') }}</td><td class="num">{{ $sign($br['other']) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ __('review.b_cash') }}</td><td class="num {{ $br['cash'] < 0 ? 'br-neg' : '' }}">{{ $m($br['cash']) }}</td></tr>
    </table>
    @if (abs($br['other']) > max(1000, 0.25 * max(abs($br['cash']), abs($br['profit']))))
        <div class="br-intro" id="br_bridge_other" style="margin-top:6px;">⚠ {{ __('review.b_other_big') }}</div>
    @endif

    {{-- expenses --}}
    <div class="br-h">{{ __('review.expenses_by_category') }}</div>
    <div class="br-intro">{{ __('review.intro_expenses') }}</div>
    <table class="br-t" id="br_expenses">
        <thead><tr><th>{{ __('review.category') }}</th><th class="num">{{ __('review.count') }}</th><th class="num">{{ __('review.amount') }}</th><th style="width:28%;"></th><th class="num">{{ __('review.share') }}</th></tr></thead>
        @forelse ($d['expenses']['rows'] as $x)
            <tr><td>{{ $x['name'] !== '' ? $x['name'] : __('review.no_category') }}</td><td class="num">{{ number_format($x['n']) }}</td><td class="num">{{ $m($x['amount']) }}</td><td style="vertical-align:middle;">{!! $bar($x['share'], 'amber') !!}</td><td class="num">{{ $pc($x['share']) }}</td></tr>
        @empty
            <tr><td colspan="5" class="br-muted">{{ __('review.no_expenses') }}</td></tr>
        @endforelse
        @if (! empty($d['expenses']['rows']))
            <tr class="total"><td>{{ __('review.total') }}</td><td></td><td class="num">{{ $m($d['expenses']['total']) }}</td><td></td><td></td></tr>
        @endif
    </table>

    {{-- receivables / payables --}}
    @foreach (['receivables' => 'customer', 'payables' => 'supplier'] as $key => $side)
        @php $b = $d[$key]; @endphp
        <div class="br-h">{{ __('review.' . $key . '_title', ['date' => $dt($d['end'])]) }}</div>
        <div class="br-intro">{{ __('review.intro_' . $key) }}</div>
        @if ($b['total'] <= 0 && $b['gross'] <= 0)
            <div class="br-muted" id="br_{{ $key }}_none">{{ __('review.' . $key . '_none') }}</div>
        @else
            <table class="br-two"><tr>
                <td class="col l">
                    <table class="br-t" id="br_{{ $key }}_ages">
                        <thead><tr><th>{{ __('review.age') }}</th><th style="width:40%;"></th><th class="num">{{ __('review.amount') }}</th></tr></thead>
                        @foreach ($b['ages'] as $ak => $amt)
                            <tr><td style="white-space:nowrap;">{{ $age_labels[$ak] }}</td><td style="vertical-align:middle;">{!! $bar($b['gross'] > 0 ? $amt / $b['gross'] * 100 : 0, $age_tone[$ak]) !!}</td><td class="num">{{ $m($amt) }}</td></tr>
                        @endforeach
                        @if ($b['credits'] > 0)
                            <tr><td colspan="2">{{ __('review.less_returns') }}</td><td class="num">− {{ $m($b['credits']) }}</td></tr>
                        @endif
                        <tr class="total"><td colspan="2">{{ __('review.' . $key . '_total', ['n' => $b['contacts'], 'i' => $b['invoices']]) }}</td><td class="num">{{ $m($b['total']) }}</td></tr>
                    </table>
                </td>
                <td class="col r">
                    <table class="br-t" id="br_{{ $key }}_top">
                        <thead><tr><th>{{ __('review.' . $key . '_who') }}</th><th class="num">{{ __('review.oldest') }}</th><th class="num">{{ __('review.balance') }}</th></tr></thead>
                        @foreach ($b['top'] as $c)
                            <tr>
                                <td>{{ $c['name'] }} @if ($c['mobile'])<span class="br-muted br-small">· {{ $c['mobile'] }}</span>@endif</td>
                                <td class="num {{ $c['days'] > 90 ? 'br-neg' : '' }}">{{ trans_choice('review.days_n', $c['days'], ['n' => $c['days']]) }}</td>
                                <td class="num">{{ $m($c['due']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </td>
            </tr></table>
        @endif
    @endforeach

    {{-- stock --}}
    @php $st = $d['stock']; @endphp
    <div class="br-h">{{ __('review.stock') }}</div>
    <div class="br-intro">{{ __('review.intro_stock') }}</div>
    <table class="br-two"><tr>
        <td class="col l">
            <table class="br-t" id="br_stock">
                <tr><td>{{ __('review.stock_opening', ['date' => $dt($d['start']->copy()->subDay())]) }}</td><td class="num">{{ $m($st['opening']) }}</td></tr>
                <tr><td>{{ __('review.stock_purchases') }}</td><td class="num">+ {{ $m($st['purchases']) }}</td></tr>
                <tr class="total"><td>{{ __('review.stock_closing', ['date' => $dt($d['end'])]) }}</td><td class="num">{{ $m($st['closing']) }}</td></tr>
                <tr><td class="indent">{{ __('review.stock_closing_sell') }}</td><td class="num br-muted">{{ $m($st['closing_sell']) }}</td></tr>
            </table>
        </td>
        <td class="col r">
            <table class="br-t" id="br_stock_risk">
                @if ($st['counts'])
                    <tr><td style="width:58%;">{{ __('review.stock_counts_short', ['n' => $st['counts']]) }}</td><td class="num br-neg">− {{ $m($st['count_shortage']) }}</td></tr>
                    <tr><td>{{ __('review.stock_counts_over') }}</td><td class="num br-pos">+ {{ $m($st['count_surplus']) }}</td></tr>
                @else
                    <tr><td style="width:58%;">{{ __('review.stock_counts_none') }}</td><td class="num">—</td></tr>
                @endif
                <tr><td>{{ __('review.stock_expired', ['n' => $st['expired_items']]) }}</td><td class="num {{ $st['expired_value'] > 0 ? 'br-neg' : '' }}">{{ $m($st['expired_value']) }}</td></tr>
                <tr><td>{{ __('review.stock_expiring', ['n' => $st['soon_items']]) }}</td><td class="num">{{ $m($st['soon_value']) }}</td></tr>
                <tr><td>{{ __('review.stock_negative') }}</td><td class="num {{ $st['negative_items'] > 0 ? 'br-neg' : '' }}">{{ number_format($st['negative_items']) }}</td></tr>
            </table>
            <div class="br-muted br-small" style="margin-top:4px;">{{ __('review.stock_now_note') }}</div>
        </td>
    </tr></table>

    {{-- data checks --}}
    <div class="br-h">{{ __('review.checks') }} <small>· {{ __('review.checks_note') }}</small></div>
    <div class="br-intro">{{ __('review.intro_checks') }}</div>
    <table class="br-t br-keep" id="br_checks">
        @php
            $rows = [
                ['uncosted', $checks['uncosted_lines'], $checks['uncosted_lines'] ? __('review.chk_uncosted_v', ['n' => number_format($checks['uncosted_lines']), 'amount' => $m($checks['uncosted_value'])]) : null],
                ['zero_cost', $checks['zero_cost_lines'], $checks['zero_cost_lines'] ? __('review.chk_zero_cost_v', ['n' => number_format($checks['zero_cost_lines']), 'amount' => $m($checks['zero_cost_value'])]) : null],
                ['below_cost', $checks['below_cost_lines'], $checks['below_cost_lines'] ? __('review.chk_below_cost_v', ['n' => number_format($checks['below_cost_lines']), 'amount' => $m($checks['below_cost_loss'])]) : null],
                ['negative', $checks['negative_stock'], $checks['negative_stock'] ? __('review.chk_negative_v', ['n' => number_format($checks['negative_stock'])]) : null],
                ['no_price', $checks['no_purchase_price'], $checks['no_purchase_price'] ? __('review.chk_no_price_v', ['n' => number_format($checks['no_purchase_price'])]) : null],
            ];
        @endphp
        @foreach ($rows as [$k, $n, $text])
            <tr>
                <td style="width:30%;">{{ __('review.chk_' . $k) }}</td>
                <td style="width:12%;">@if ($n) <span class="br-pill warn">{{ __('review.to_check') }}</span> @else <span class="br-pill ok">{{ __('review.ok') }}</span> @endif</td>
                <td>{{ $text ?: __('review.chk_ok') }} @if ($n)<div class="br-muted br-small">{{ __('review.chk_' . $k . '_fix') }}</div>@endif</td>
            </tr>
        @endforeach
    </table>

    {{-- how to read this report --}}
    <div class="br-h">{{ __('review.glossary') }}</div>
    <table class="br-t br-gloss" id="br_glossary">
        @foreach (['g_sales', 'g_cogs', 'g_gross', 'g_margin', 'g_expenses', 'g_losses', 'g_net', 'g_cash', 'g_receivables', 'g_payables', 'g_stock_value', 'g_compare'] as $g)
            <tr><td class="term">{{ __('review.' . $g) }}</td><td>{{ __('review.' . $g . '_d') }}</td></tr>
        @endforeach
    </table>

    {{-- notes and signatures --}}
    <div class="br-h">{{ __('review.notes') }}</div>
    <div class="br-note" id="br_notes_print">{{ $notes ?? '' }}</div>
    <table class="br-sign br-keep">
        <tr>
            <td><span>{{ __('review.sign_prepared') }}</span></td>
            <td><span>{{ __('review.sign_client') }}</span></td>
            <td><span>{{ __('review.sign_date') }}</span></td>
        </tr>
    </table>
    <div class="br-foot">{{ __('review.footer', ['business' => $biz->name]) }}</div>
</div>
