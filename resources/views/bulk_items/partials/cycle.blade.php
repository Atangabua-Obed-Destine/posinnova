{{-- PosInnova bulk items: one finished container, in detail (in .view_modal) --}}
@php
    $item = $c->item;
    $label = __('bulk.label_' . $item->container_label);
    $unit = optional(optional($item->product)->unit)->actual_name;
    $qf = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $dt = fn ($d) => $d ? $d->format(session('business.date_format', 'd/m/Y') . ' H:i') : '—';
    $flag = \App\Utils\BulkItems::flag($c, $item);
    $who = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : '—';
@endphp
<div class="modal-dialog" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">{{ $item->name() }} · {{ __('bulk.cycle_title', ['label' => $label, 'n' => $c->id]) }}</h4>
        </div>
        <div class="modal-body" id="bk_cycle_detail">
            <table class="table table-condensed">
                <tr><th style="width:40%;">{{ __('business.location') }}</th><td>{{ optional($c->location)->name }}</td></tr>
                <tr><th>{{ __('bulk.h_opened') }}</th><td>{{ $dt($c->opened_at) }} · {{ $who($c->opener) }}</td></tr>
                <tr><th>{{ __('bulk.h_finished') }}</th><td>{{ $dt($c->closed_at) }} · {{ $who($c->closer) }}@if ($c->containers > 1) · <strong>{{ __('bulk.n_at_once', ['n' => $qf($c->containers), 'label' => $label]) }}</strong>@endif</td></tr>
                @php $one = abs($item->container_size - 1) < 0.00005; @endphp
                <tr><th>{{ __('bulk.h_expected') }}</th><td>{{ __('bulk.of_label', ['share' => $qf($c->expected_usage / max(0.0001, $item->container_size)), 'label' => $label]) }}@if (! $one) <span class="text-muted">({{ $qf($c->expected_usage) }} {{ $unit }})</span>@endif</td></tr>
                <tr><th>{{ __('bulk.h_used') }}</th><td>{{ $qf($c->containers) }} {{ $label }}@if (! $one) <span class="text-muted">({{ $qf($c->actual_usage) }} {{ $unit }})</span>@endif</td></tr>
                <tr><th>{{ __('bulk.h_yield') }}</th><td><span class="bk-pill {{ $flag === 'low' ? 'bad' : ($flag === 'high' ? 'warn' : 'ok') }}">{{ number_format((float) $c->yield_pct, 0) }}%</span>
                    <span class="text-muted">{{ __('bulk.range', ['low' => $item->low_pct, 'high' => $item->high_pct]) }}</span></td></tr>
                <tr><th>{{ $c->value >= 0 ? __('bulk.h_lost') : __('bulk.h_extra') }}</th><td>{{ $tu->num_f(abs($c->value), true) }}
                    <div class="text-muted small">{{ $c->value >= 0 ? __('bulk.lost_note') : __('bulk.extra_note') }}</div></td></tr>
                @if ($c->note)
                    <tr><th>{{ __('bulk.h_note') }}</th><td>{{ $c->note }}</td></tr>
                @endif
            </table>
            <h5><strong>{{ __('bulk.h_sold_from') }}</strong></h5>
            <table class="table table-condensed table-striped">
                <thead><tr><th>{{ __('bulk.h_menu_item') }}</th><th class="text-right">{{ __('bulk.h_sold') }}</th><th class="text-right">{{ __('bulk.h_share') }}</th></tr></thead>
                @forelse ((array) ($c->breakdown['items'] ?? []) as $i)
                    <tr><td>{{ $i['name'] }}</td><td class="text-right">{{ $qf($i['sold']) }}</td><td class="text-right">{{ $qf($i['used'] / max(0.0001, $item->container_size)) }} {{ $label }}</td></tr>
                @empty
                    <tr><td colspan="3" class="text-muted">{{ __('bulk.none_sold') }}</td></tr>
                @endforelse
            </table>
            <h5><strong>{{ __('bulk.h_sellers') }}</strong></h5>
            <table class="table table-condensed table-striped">
                <thead><tr><th>{{ __('bulk.h_seller') }}</th><th class="text-right">{{ __('bulk.h_sold') }}</th></tr></thead>
                @forelse ((array) ($c->breakdown['sellers'] ?? []) as $s)
                    <tr><td>{{ $s['name'] ?: '—' }}</td><td class="text-right">{{ $qf($s['sold']) }}</td></tr>
                @empty
                    <tr><td colspan="2" class="text-muted">—</td></tr>
                @endforelse
            </table>
        </div>
        <div class="modal-footer">
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">{{ __('messages.close') }}</button>
        </div>
    </div>
</div>
