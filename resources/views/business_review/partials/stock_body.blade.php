{{-- PosInnova: the quarterly Stock Reconciliation Report (App\Utils\BusinessReview::stockControl). Same markup on
     screen, on paper and in the PDF. --}}
@php
    $m = fn ($n) => $tu->num_f($n, true);
    $n0 = fn ($n) => $tu->num_f($n, false);
    $cur = (session('currency') ?? [])['symbol'] ?? '';
    $in = fn ($label) => $label . ($cur !== '' ? ' (' . $cur . ')' : '');
    $q = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
    $dt = fn ($c) => $c ? $tu->format_date($c->format('Y-m-d')) : '—';
    $pc = fn ($n, $dec = 1) => $n === null ? '—' : number_format($n, $dec) . '%';
    $t = $s['totals'];
    $qlabel = __('review.quarter_long', ['q' => $s['q'], 'year' => $s['year']]);
    $purposes = ['routine' => __('review.purpose_routine'), 'recount' => __('review.purpose_recount'), 'migration' => __('review.purpose_setup')];
    $statuses = ['draft' => __('review.status_draft'), 'pending_approval' => __('review.status_pending'), 'posted' => __('review.status_posted')];
    $adj_types = ['normal' => __('review.adj_normal'), 'abnormal' => __('review.adj_abnormal')];
@endphp
<div class="br">
    <table class="br-head">
        <tr>
            <td style="width:62%;">
                @if (! empty($logo))
                    <img src="{{ $logo }}" class="br-logo" alt=""><br>
                @endif
                <div class="br-biz">{{ $biz->name }}</div>
                @if ($location_name) <div class="br-sub">{{ $location_name }}</div> @endif
                <div class="br-title">{{ __('review.sc_title') }}</div>
                <div class="br-sub">{{ $qlabel }} · {{ $dt($s['start']) }} – {{ $dt($s['end']) }}@if ($s['in_progress']) · <strong>{{ __('review.in_progress', ['date' => $dt($s['upto'])]) }}</strong>@endif</div>
            </td>
            <td class="br-meta">
                {{ __('review.prepared_with') }} <strong>I-NNOVA POS</strong><br>
                {{ __('review.generated', ['date' => $tu->format_date(now()->format('Y-m-d H:i'), true)]) }}<br>
                @if (! empty($prepared_by)) {{ __('review.by', ['name' => $prepared_by]) }}<br> @endif
                <span class="br-muted">{{ __('review.values_at_cost') }}</span>
            </td>
        </tr>
    </table>
    <div class="br-rule"></div>

    <table class="br-kpis br-keep">
        <tr>
            <td><div class="br-kpi" id="sc_kpi_counts">
                <div class="br-kpi-label">{{ __('review.sc_kpi_counts') }}</div>
                <div class="br-kpi-val">{{ count($s['counts']) }}</div>
                <div class="br-kpi-sub">{{ trans_choice('review.lines_n', $t['lines'], ['n' => number_format($t['lines'])]) }} · {{ trans_choice('review.diffs_n', $t['diffs'], ['n' => number_format($t['diffs'])]) }}</div>
            </div></td>
            <td><div class="br-kpi" id="sc_kpi_short">
                <div class="br-kpi-label">{{ __('review.sc_kpi_short') }}</div>
                <div class="br-kpi-val br-neg">{{ $m($t['shortage']) }}</div>
                <div class="br-kpi-sub">{{ __('review.sc_prev', ['q' => __('review.quarter_short', ['q' => $s['previous']['q'], 'year' => $s['previous']['year']]), 'amount' => $s['previous']['n'] ? $m($s['previous']['shortage']) : '—']) }}</div>
            </div></td>
            <td><div class="br-kpi" id="sc_kpi_over">
                <div class="br-kpi-label">{{ __('review.sc_kpi_over') }}</div>
                <div class="br-kpi-val br-pos">{{ $m($t['surplus']) }}</div>
                <div class="br-kpi-sub">&nbsp;</div>
            </div></td>
            <td><div class="br-kpi" id="sc_kpi_net">
                <div class="br-kpi-label">{{ __('review.sc_kpi_net') }}</div>
                <div class="br-kpi-val {{ $t['net'] < 0 ? 'br-neg' : 'br-pos' }}">{{ ($t['net'] > 0 ? '+ ' : '') . $m($t['net']) }}</div>
                <div class="br-kpi-sub">@if ($s['shortage_share'] !== null){{ __('review.sc_of_stock', ['pct' => $pc($s['shortage_share'], 2)]) }}@else {{ __('review.sc_stock_value', ['amount' => $m($s['stock_value'])]) }} @endif</div>
            </div></td>
        </tr>
    </table>

    @if (! empty($s['observations']))
        <div class="br-h">{{ __('review.observations') }}</div>
        <div class="br-intro">{{ __('review.intro_obs') }}</div>
        <table class="br-obs" id="sc_observations">
            @foreach ($s['observations'] as [$tone, $text])
                <tr><td style="width:18px;"><span class="br-dot {{ $tone }}"></span></td><td>{{ $text }}</td></tr>
            @endforeach
        </table>
    @endif

    {{-- each location --}}
    <div class="br-h">{{ __('review.sc_locations') }}</div>
    <div class="br-intro">{{ __('review.intro_sc_locations') }}</div>
    <div class="br-scroll">
    <table class="br-t br-keep" id="sc_locations">
        <thead><tr><th>{{ __('business.location') }}</th><th class="num">{{ __('review.sc_counts') }}</th><th>{{ __('review.sc_last') }}</th><th class="num">{{ __('review.sc_coverage') }}</th><th class="num">{{ $in(__('review.sc_short')) }}</th><th class="num">{{ $in(__('review.sc_over')) }}</th><th class="num">{{ $in(__('review.sc_stock_value_h')) }}</th></tr></thead>
        @foreach ($s['locations'] as $l)
            <tr>
                <td>{{ $l['name'] }}</td>
                <td class="num">@if ($l['counts']) {{ $l['counts'] }} @else <span class="br-pill bad">{{ __('review.not_counted') }}</span> @endif</td>
                <td>{{ $l['last'] ? $dt($l['last']) : '—' }}</td>
                <td class="num">@if ($l['coverage'] !== null)<span class="{{ $l['coverage'] < 80 ? 'br-neg' : '' }}">{{ $pc($l['coverage'], 0) }}</span> <span class="br-muted br-small">({{ number_format($l['counted']) }}/{{ number_format($l['stocked']) }})</span>@else — @endif</td>
                <td class="num br-neg">{{ $l['shortage'] ? $n0($l['shortage']) : '—' }}</td>
                <td class="num br-pos">{{ $l['surplus'] ? $n0($l['surplus']) : '—' }}</td>
                <td class="num">{{ $n0($l['stock_value']) }}</td>
            </tr>
        @endforeach
    </table>
    </div>
    <div class="br-muted br-small" style="margin-top:4px;">{{ __('review.sc_coverage_note') }}</div>

    {{-- the counts --}}
    <div class="br-h">{{ __('review.sc_counts_title') }}</div>
    <div class="br-intro">{{ __('review.intro_sc_counts') }}</div>
    @if (empty($s['counts']))
        <div class="br-muted" id="sc_no_counts">{{ __('review.sc_no_counts', ['q' => $qlabel]) }}</div>
    @else
        <div class="br-scroll">
        <table class="br-t" id="sc_counts">
            <thead><tr><th>{{ __('review.reference') }}</th><th>{{ __('review.date') }}</th><th>{{ __('business.location') }}</th><th class="num">{{ __('review.sc_lines_diffs') }}</th><th class="num">{{ $in(__('review.sc_short')) }}</th><th class="num">{{ $in(__('review.sc_over')) }}</th><th class="num">{{ $in(__('review.sc_net')) }}</th></tr></thead>
            @foreach ($s['counts'] as $c)
                <tr>
                    <td>@if (empty($pdf))<a href="{{ action([\App\Http\Controllers\InventoryCountController::class, 'show'], [$c['id']]) }}">{{ $c['ref'] }}</a>@else <span style="font-size:6.6pt;white-space:nowrap;">{{ $c['ref'] }}</span> @endif
                        <div class="br-muted br-small">{{ $c['scope'] }}@if ($c['purpose'] !== 'routine') · {{ $purposes[$c['purpose']] ?? $c['purpose'] }}@endif</div></td>
                    <td><span style="white-space:nowrap;">{{ $dt($c['posted']) }}</span>@if ($c['approver'])<div class="br-muted br-small">{{ __('review.sc_approved_by', ['name' => $c['approver']]) }}</div>@endif</td>
                    <td>{{ $c['location'] }}</td>
                    <td class="num">{{ number_format($c['lines']) }} / {{ number_format($c['diffs']) }}</td>
                    <td class="num br-neg">{{ $c['shortage'] ? $n0($c['shortage']) : '—' }}</td>
                    <td class="num br-pos">{{ $c['surplus'] ? $n0($c['surplus']) : '—' }}</td>
                    <td class="num {{ $c['net'] < 0 ? 'br-neg' : '' }}">{{ $n0($c['net']) }}</td>
                </tr>
            @endforeach
            <tr class="total"><td colspan="3">{{ __('review.total') }}</td><td class="num">{{ number_format($t['lines']) }} / {{ number_format($t['diffs']) }}</td><td class="num">{{ $n0($t['shortage']) }}</td><td class="num">{{ $n0($t['surplus']) }}</td><td class="num">{{ $n0($t['net']) }}</td></tr>
        </table>
        </div>
        @if (empty($pdf))<div class="br-muted br-small" style="margin-top:4px;">{{ __('review.sc_variance_hint') }}</div>@endif
    @endif

    @if (! empty($s['open']))
        <div class="br-h">{{ __('review.sc_open') }}</div>
        <table class="br-t br-keep" id="sc_open">
            <thead><tr><th>{{ __('review.reference') }}</th><th>{{ __('review.sc_started') }}</th><th>{{ __('business.location') }}</th><th>{{ __('review.status') }}</th><th class="num">{{ __('review.sc_progress') }}</th></tr></thead>
            @foreach ($s['open'] as $c)
                <tr><td>@if (empty($pdf))<a href="{{ action([\App\Http\Controllers\InventoryCountController::class, 'show'], [$c['id']]) }}">{{ $c['ref'] }}</a>@else {{ $c['ref'] }} @endif</td>
                    <td>{{ $dt($c['started']) }}</td><td>{{ $c['location'] }}</td>
                    <td><span class="br-pill warn">{{ $statuses[$c['status']] ?? $c['status'] }}</span></td>
                    <td class="num">{{ number_format($c['lines']) }} / {{ number_format($c['lines_total']) }}</td></tr>
            @endforeach
        </table>
    @endif

    {{-- products short --}}
    @if (! empty($s['short_products']))
        <div class="br-h">{{ __('review.sc_short_products') }}</div>
        <div class="br-intro">{{ __('review.intro_sc_short') }}</div>
        <div class="br-scroll">
        <table class="br-t" id="sc_short_products">
            <thead><tr><th>#</th><th>{{ __('sale.product') }}</th><th class="num">{{ __('review.sc_times') }}</th><th class="num">{{ __('review.sc_qty_short') }}</th><th class="num">{{ __('review.sc_value_short') }}</th></tr></thead>
            @foreach ($s['short_products'] as $i => $p)
                <tr>
                    <td class="br-muted">{{ $i + 1 }}</td>
                    <td>{{ $p['name'] }} @if ($p['sku'])<span class="br-muted br-small">· {{ $p['sku'] }}</span>@endif</td>
                    <td class="num">@if ($p['times'] >= 2)<span class="br-pill bad">{{ $p['times'] }}×</span>@else {{ $p['times'] }}× @endif</td>
                    <td class="num">{{ $q($p['qty']) }} <span class="br-muted br-small">{{ $p['unit'] }}</span></td>
                    <td class="num br-neg">{{ $m($p['value']) }}</td>
                </tr>
            @endforeach
        </table>
        </div>
    @endif

    {{-- other write-offs --}}
    <div class="br-h">{{ __('review.sc_adjustments') }} <small>· {{ __('review.sc_adjustments_note') }}</small></div>
    <div class="br-intro">{{ __('review.intro_sc_adjustments') }}</div>
    @if (empty($s['adjustments']))
        <div class="br-muted" id="sc_no_adjustments">{{ __('review.sc_no_adjustments') }}</div>
    @else
        <table class="br-t" id="sc_adjustments">
            <thead><tr><th>{{ __('review.reference') }}</th><th>{{ __('review.date') }}</th><th>{{ __('business.location') }}</th><th>{{ __('review.sc_type') }}</th><th>{{ __('review.sc_reason') }}</th><th class="num">{{ __('review.amount') }}</th></tr></thead>
            @foreach (array_slice($s['adjustments'], 0, 20) as $a)
                <tr><td>{{ $a['ref'] }}</td><td style="white-space:nowrap;">{{ $dt($a['date']) }}</td><td>{{ $a['location'] }}</td><td>{{ $adj_types[$a['type']] ?? '' }}</td><td class="br-small">{{ \Illuminate\Support\Str::limit((string) $a['note'], 80) }}</td>
                    <td class="num br-neg">{{ $m($a['value']) }}@if ($a['recovered'] > 0)<div class="br-muted br-small">{{ __('review.recovered', ['amount' => $m($a['recovered'])]) }}</div>@endif</td></tr>
            @endforeach
            <tr class="total"><td colspan="5">{{ __('review.total') }} ({{ count($s['adjustments']) }})</td><td class="num">{{ $m($s['adjust_total']) }}</td></tr>
        </table>
    @endif

    {{-- at risk now --}}
    <div class="br-h">{{ __('review.sc_risk') }} <small>· {{ __('review.sc_risk_note') }}</small></div>
    <div class="br-intro">{{ __('review.intro_sc_risk') }}</div>
    <table class="br-t br-keep" id="sc_risk">
        <tr><td>{{ __('review.stock_expired', ['n' => $s['expiry']['expired_items']]) }}</td><td class="num {{ $s['expiry']['expired_value'] > 0 ? 'br-neg' : '' }}">{{ $m($s['expiry']['expired_value']) }}</td></tr>
        <tr><td>{{ __('review.stock_expiring', ['n' => $s['expiry']['soon_items']]) }}</td><td class="num">{{ $m($s['expiry']['soon_value']) }}</td></tr>
        <tr><td>{{ __('review.sc_recount_open') }}</td><td class="num">{{ number_format($s['recount']['n']) }} @if ($s['recount']['n'])<span class="br-muted br-small">({{ $m($s['recount']['value']) }})</span>@endif</td></tr>
        <tr><td>{{ __('review.sc_low_stock') }}</td><td class="num">{{ number_format($s['low_stock']) }}</td></tr>
    </table>

    <div class="br-h">{{ __('review.glossary') }}</div>
    <table class="br-t br-gloss" id="sc_glossary">
        @foreach (['gs_count', 'gs_short', 'gs_over', 'gs_net', 'gs_coverage', 'gs_recount', 'gs_writeoff', 'gs_value'] as $g)
            <tr><td class="term">{{ __('review.' . $g) }}</td><td>{{ __('review.' . $g . '_d') }}</td></tr>
        @endforeach
    </table>

    <div class="br-h">{{ __('review.notes') }}</div>
    <div class="br-note" id="br_notes_print">{{ $notes ?? '' }}</div>
    <table class="br-sign br-keep">
        <tr>
            <td><span>{{ __('review.sign_counted') }}</span></td>
            <td><span>{{ __('review.sign_prepared') }}</span></td>
            <td><span>{{ __('review.sign_client') }}</span></td>
        </tr>
    </table>
    <div class="br-foot">{{ __('review.footer', ['business' => $biz->name]) }}</div>
</div>
