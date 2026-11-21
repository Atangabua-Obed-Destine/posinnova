@extends('layouts.app')
@section('title', __('bulk.menu'))

@section('content')
@php
    $qf = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ','), '0'), '.');
    $dt = fn ($d) => $d ? $d->format(session('business.date_format', 'd/m/Y') . ' H:i') : '—';
    $who = fn ($u) => $u ? trim($u->first_name . ' ' . $u->last_name) : '—';
    $url = fn ($t, $extra = []) => action([\App\Http\Controllers\BulkItemController::class, 'index'], array_merge(['tab' => $t, 'location_id' => $location_id], $extra));
@endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('bulk.menu') }}
        <small class="tw-text-gray-600">{{ __('bulk.lead') }}</small>
    </h1>
</section>

<section class="content">
    @include('bulk_items.partials.styles')

    <ul class="nav nav-tabs bk-tabs" role="tablist">
        <li class="{{ $tab === 'in_use' ? 'active' : '' }}"><a href="{{ $url('in_use') }}" id="bk_tab_in_use">{{ __('bulk.tab_in_use') }}</a></li>
        <li class="{{ $tab === 'history' ? 'active' : '' }}"><a href="{{ $url('history') }}" id="bk_tab_history">{{ __('bulk.tab_history') }}</a></li>
        @if ($can_setup)
            <li class="{{ $tab === 'setup' ? 'active' : '' }}"><a href="{{ $url('setup') }}" id="bk_tab_setup">{{ __('bulk.tab_setup') }}</a></li>
        @endif
    </ul>

    <div class="bk-panel">
    @if ($tab === 'in_use')
        <div class="bk-intro">{{ __('bulk.intro_in_use') }}</div>
        @if (count($locations) > 1)
            <form method="GET" class="bk-filters">
                <input type="hidden" name="tab" value="in_use">
                <label for="bk_location">{{ __('business.location') }}</label>
                <select name="location_id" id="bk_location" class="form-control" onchange="this.form.submit()">
                    @foreach ($locations as $id => $name)
                        <option value="{{ $id }}" {{ (int) $id === (int) $location_id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <div id="bk_cards_wrap">
            @include('bulk_items.partials.cards', ['current' => $current, 'location_id' => $location_id, 'can_tap' => $can_tap])
        </div>
        @if ($can_setup && $items->isEmpty())
            <a href="{{ $url('setup') }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="margin-top:10px;"><i class="fas fa-cog"></i> {{ __('bulk.go_setup') }}</a>
        @endif

    @elseif ($tab === 'history')
        <div class="bk-intro">{{ __('bulk.intro_history') }}</div>
        <form method="GET" class="bk-filters" id="bk_history_filters">
            <input type="hidden" name="tab" value="history">
            <input type="hidden" name="location_id" value="{{ $location_id }}">
            <div><label for="bk_item">{{ __('bulk.h_item') }}</label>
                <select name="bulk_item_id" id="bk_item" class="form-control">
                    <option value="">{{ __('lang_v1.all') }}</option>
                    @foreach ($items as $it)<option value="{{ $it->id }}" {{ (string) $filters['bulk_item_id'] === (string) $it->id ? 'selected' : '' }}>{{ $it->name() }}</option>@endforeach
                </select></div>
            @if (count($locations) > 1)
                <div><label for="bk_h_location">{{ __('business.location') }}</label>
                    <select name="h_location_id" id="bk_h_location" class="form-control">
                        <option value="">{{ __('lang_v1.all') }}</option>
                        @foreach ($locations as $id => $name)<option value="{{ $id }}" {{ (string) $filters['location_id'] === (string) $id ? 'selected' : '' }}>{{ $name }}</option>@endforeach
                    </select></div>
            @endif
            <div><label for="bk_from">{{ __('bulk.from') }}</label><input type="date" name="from" id="bk_from" class="form-control" value="{{ $filters['from'] }}"></div>
            <div><label for="bk_to">{{ __('bulk.to') }}</label><input type="date" name="to" id="bk_to" class="form-control" value="{{ $filters['to'] }}"></div>
            <div><label for="bk_flag">{{ __('bulk.show') }}</label>
                <select name="flag" id="bk_flag" class="form-control">
                    <option value="">{{ __('bulk.show_all') }}</option>
                    <option value="low" {{ $filters['flag'] === 'low' ? 'selected' : '' }}>{{ __('bulk.show_low') }}</option>
                </select></div>
            <div><button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" style="height:36px;">{{ __('bulk.filter') }}</button></div>
        </form>

        @if (! empty($summary['items']))
            <div class="row" id="bk_summary">
                <div class="col-md-7">
                    <h4 class="bk-h">{{ __('bulk.h_per_item') }}</h4>
                    <table class="table table-condensed bk-table">
                        <thead><tr><th>{{ __('bulk.h_item') }}</th><th class="text-right">{{ __('bulk.h_finished_n') }}</th><th class="text-right">{{ __('bulk.h_avg_yield') }}</th><th class="text-right">{{ __('bulk.h_low_n') }}</th><th class="text-right">{{ __('bulk.h_lost') }}</th></tr></thead>
                        @foreach ($summary['items'] as $s)
                            <tr><td>{{ $s['name'] }}</td><td class="text-right">{{ $qf($s['containers']) }} {{ __('bulk.label_' . $s['label']) }}</td>
                                <td class="text-right">{{ $s['avg_yield'] !== null ? number_format($s['avg_yield'], 0) . '%' : '—' }}</td>
                                <td class="text-right">@if ($s['low'])<span class="bk-pill bad">{{ $s['low'] }}</span>@else 0 @endif</td>
                                <td class="text-right">{{ $tu->num_f($s['lost'], true) }}</td></tr>
                        @endforeach
                    </table>
                </div>
                <div class="col-md-5">
                    <h4 class="bk-h">{{ __('bulk.h_per_seller') }}</h4>
                    <table class="table table-condensed bk-table" id="bk_sellers">
                        <thead><tr><th>{{ __('bulk.h_seller') }}</th><th class="text-right">{{ __('bulk.h_sold') }}</th><th class="text-right" title="{{ __('bulk.h_in_low_title') }}">{{ __('bulk.h_in_low') }}</th></tr></thead>
                        @foreach (array_slice($summary['people'], 0, 10) as $pp)
                            <tr><td>{{ $pp['name'] }}</td><td class="text-right">{{ $qf($pp['sold']) }}</td>
                                <td class="text-right">{{ $pp['sold'] > 0 ? number_format($pp['low_sold'] / $pp['sold'] * 100, 0) . '%' : '—' }}</td></tr>
                        @endforeach
                    </table>
                    <div class="bk-muted small">{{ __('bulk.sellers_note') }}</div>
                </div>
            </div>
        @endif

        <h4 class="bk-h">{{ __('bulk.h_containers') }}</h4>
        <div class="table-responsive">
        <table class="table table-striped bk-table" id="bk_history">
            <thead><tr><th>{{ __('bulk.h_item') }}</th><th>{{ __('business.location') }}</th><th>{{ __('bulk.h_opened') }}</th><th>{{ __('bulk.h_finished') }}</th><th>{{ __('bulk.h_sold_from') }}</th><th class="text-right">{{ __('bulk.h_yield') }}</th><th class="text-right">{{ __('bulk.h_diff') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($history as $c)
                @php $flag = $c->item ? \App\Utils\BulkItems::flag($c, $c->item) : null; $label = $c->item ? __('bulk.label_' . $c->item->container_label) : ''; @endphp
                <tr class="{{ $flag === 'low' ? 'bk-row-low' : '' }}">
                    <td><strong>{{ $c->item ? $c->item->name() : '—' }}</strong>@if ($c->containers > 1)<div class="small bk-muted">{{ __('bulk.n_at_once', ['n' => $qf($c->containers), 'label' => $label]) }}</div>@endif</td>
                    <td>{{ optional($c->location)->name }}</td>
                    <td class="small">{{ $dt($c->opened_at) }}<br><span class="bk-muted">{{ $who($c->opener) }}</span></td>
                    <td class="small">{{ $dt($c->closed_at) }}<br><span class="bk-muted">{{ $who($c->closer) }}</span>
                        @if ($c->opened_at && $c->closed_at)<br><span class="bk-muted">{{ __('bulk.lasted', ['d' => $c->opened_at->diffForHumans($c->closed_at, true)]) }}</span>@endif</td>
                    <td class="small">{{ collect((array) ($c->breakdown['items'] ?? []))->map(fn ($i) => $qf($i['sold']) . ' × ' . $i['name'])->implode(', ') ?: '—' }}</td>
                    <td class="text-right"><span class="bk-pill {{ $flag === 'low' ? 'bad' : ($flag === 'high' ? 'warn' : 'ok') }}">{{ number_format((float) $c->yield_pct, 0) }}%</span></td>
                    <td class="text-right {{ $c->value > 0.5 ? 'text-danger' : '' }}">{{ $c->value >= 0 ? '− ' : '+ ' }}{{ $tu->num_f(abs($c->value), true) }}</td>
                    <td><a href="#" class="btn-modal btn btn-xs btn-default" data-container=".view_modal" data-href="{{ action([\App\Http\Controllers\BulkItemController::class, 'cycle'], [$c->id]) }}"><i class="fas fa-eye"></i> {{ __('messages.view') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center bk-muted">{{ __('bulk.no_history') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div class="bk-muted small">{{ __('bulk.diff_note') }}</div>

    @elseif ($tab === 'setup' && $can_setup)
        <div class="bk-intro">{!! nl2br(e(__('bulk.intro_setup'))) !!}</div>
        <div class="bk-add" id="bk_add">
            <h4 class="bk-h">{{ __('bulk.add_title') }}</h4>
            <form id="bk_add_form" class="bk-filters">
                <div style="min-width:280px;flex:1;"><label for="bk_product">{{ __('bulk.f_product') }}</label>
                    <select id="bk_product" name="variation_id" style="width:100%"></select></div>
                <div><label for="bk_label">{{ __('bulk.f_label') }}</label>
                    <select id="bk_label" name="container_label" class="form-control">
                        @foreach (\App\BulkItem::LABELS as $l)<option value="{{ $l }}">{{ __('bulk.label_' . $l) }}</option>@endforeach
                    </select></div>
                <div><label for="bk_size">{{ __('bulk.f_size') }} <span id="bk_size_unit" class="bk-muted"></span></label>
                    <input type="number" step="any" min="0.0001" id="bk_size" name="container_size" class="form-control" value="1" style="width:110px;"></div>
                <div><label for="bk_low">{{ __('bulk.f_low') }}</label><input type="number" id="bk_low" name="low_pct" class="form-control" value="85" min="10" max="100" style="width:80px;"></div>
                <div><label for="bk_high">{{ __('bulk.f_high') }}</label><input type="number" id="bk_high" name="high_pct" class="form-control" value="115" min="100" max="300" style="width:80px;"></div>
                <div><button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" style="height:36px;" id="bk_add_btn"><i class="fas fa-plus"></i> {{ __('bulk.add_btn') }}</button></div>
            </form>
            <div class="bk-muted small">{{ __('bulk.size_help') }}</div>
        </div>

        <h4 class="bk-h">{{ __('bulk.h_tracked') }}</h4>
        <div class="table-responsive">
        <table class="table bk-table" id="bk_setup_table">
            <thead><tr><th>{{ __('bulk.h_item') }}</th><th>{{ __('bulk.f_label') }} / {{ __('bulk.f_size') }}</th><th>{{ __('bulk.h_used_by') }}</th><th>{{ __('bulk.f_low') }} / {{ __('bulk.f_high') }}</th><th>{{ __('bulk.h_status') }}</th><th></th></tr></thead>
            @forelse ($items as $it)
                @php $unit = optional(optional($it->product)->unit)->actual_name; @endphp
                <tr data-id="{{ $it->id }}">
                    <td><strong>{{ $it->name() }}</strong></td>
                    <td>
                        <select class="form-control input-sm bk-e-label" aria-label="{{ __('bulk.f_label') }}" style="width:110px;display:inline-block;">
                            @foreach (\App\BulkItem::LABELS as $l)<option value="{{ $l }}" {{ $it->container_label === $l ? 'selected' : '' }}>{{ __('bulk.label_' . $l) }}</option>@endforeach
                        </select>
                        = <input type="number" step="any" min="0.0001" class="form-control input-sm bk-e-size" value="{{ $qf($it->container_size) }}" aria-label="{{ __('bulk.f_size') }}" style="width:90px;display:inline-block;"> {{ $unit }}
                    </td>
                    <td class="small">
                        @forelse ($users[$it->id] as $u)
                            <div>{{ $u['name'] }}: {{ $qf($u['per_sale']) }} {{ $unit }} @if ($u['per_container']) <span class="bk-muted">→ {{ __('bulk.per_container', ['n' => $qf(round($u['per_container'], 1)), 'label' => __('bulk.label_' . $it->container_label)]) }}</span>@endif</div>
                        @empty
                            <span class="bk-pill warn">{{ __('bulk.no_users') }}</span>
                        @endforelse
                    </td>
                    <td><input type="number" class="form-control input-sm bk-e-low" value="{{ $it->low_pct }}" min="10" max="100" aria-label="{{ __('bulk.f_low') }}" style="width:70px;display:inline-block;"> /
                        <input type="number" class="form-control input-sm bk-e-high" value="{{ $it->high_pct }}" min="100" max="300" aria-label="{{ __('bulk.f_high') }}" style="width:70px;display:inline-block;"> %</td>
                    <td>@if ($it->is_active)<span class="bk-pill ok">{{ __('bulk.active') }}</span>@else<span class="bk-pill">{{ __('bulk.inactive') }}</span>@endif</td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-xs btn-primary bk-save"><i class="fas fa-save"></i> {{ __('messages.save') }}</button>
                        <button type="button" class="btn btn-xs btn-default bk-toggle">{{ $it->is_active ? __('bulk.deactivate') : __('bulk.activate') }}</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="bk-muted text-center">{{ __('bulk.no_items_setup') }}</td></tr>
            @endforelse
        </table>
        </div>
    @endif
    </div>
</section>
@endsection

@section('javascript')
    @include('bulk_items.partials.script')
    <script>
        $(function () {
            var base = @json(url('bulk-items'));
            $('#bk_product').select2({
                placeholder: @json(__('bulk.f_product_ph')), minimumInputLength: 1, width: '100%',
                ajax: { url: base + '/products', dataType: 'json', delay: 250, data: function (p) { return { q: p.term }; }, processResults: function (d) { return d; } }
            }).on('select2:select', function (e) { $('#bk_size_unit').text(e.params.data.unit ? '(' + e.params.data.unit + ')' : ''); });
            $('#bk_add_form').on('submit', function (e) {
                e.preventDefault();
                $.post(base, $(this).serialize() + '&_token=' + encodeURIComponent($('meta[name=csrf-token]').attr('content')))
                    .done(function (r) { toastr.success(r.msg); location.reload(); })
                    .fail(function (x) { toastr.error((x.responseJSON && (x.responseJSON.msg || x.responseJSON.message)) || LANG.something_went_wrong); });
            });
            $(document).on('click', '#bk_setup_table .bk-save', function () {
                var $tr = $(this).closest('tr');
                $.post(base + '/' + $tr.data('id'), {
                    _token: $('meta[name=csrf-token]').attr('content'), container_label: $tr.find('.bk-e-label').val(), container_size: $tr.find('.bk-e-size').val(),
                    low_pct: $tr.find('.bk-e-low').val(), high_pct: $tr.find('.bk-e-high').val()
                }).done(function (r) { toastr.success(r.msg); location.reload(); })
                  .fail(function (x) { toastr.error((x.responseJSON && (x.responseJSON.msg || x.responseJSON.message)) || LANG.something_went_wrong); });
            });
            $(document).on('click', '#bk_setup_table .bk-toggle', function () {
                $.post(base + '/' + $(this).closest('tr').data('id') + '/toggle', { _token: $('meta[name=csrf-token]').attr('content') })
                    .done(function (r) { toastr.success(r.msg); location.reload(); });
            });
        });
    </script>
@endsection
