@extends('layouts.app')
@section('title', __('channel.menu_products'))

@section('content')
@include('channel.partials.styles')
@php
    $C = \App\Http\Controllers\ChannelController::class;
    $util = new \App\Utils\Util();
    $catNames = $cats->pluck('name', 'id');
    $filters = ['show' => $show, 'category' => $category ?: null, 'q' => $search !== '' ? $search : null, 'location' => $loc_id];
@endphp
<section class="content ch" id="ch_products">
    <div class="ch-head">
        <div>
            <h1>{{ __('channel.menu_products') }}</h1>
            <p>{{ __('channel.products_lead') }}</p>
        </div>
        <a class="ch-btn" href="{{ action([$C, 'index']) }}">&larr; {{ __('channel.menu_link') }}</a>
    </div>

    @if ($links->isEmpty())
        <div class="ch-msg warn">{{ __('channel.no_link_yet') }} <a href="{{ action([$C, 'index']) }}">{{ __('channel.menu_link') }}</a></div>
    @endif

    <div class="ch-card">
        <div class="ch-facts" style="margin-top:0;">
            <div><span class="k">{{ __('channel.f_on') }}</span><span class="v" id="ch_count_on">{{ $counts['on'] }}</span></div>
            <div><span class="k">{{ __('channel.f_never') }}</span><span class="v">{{ $counts['never'] }}</span></div>
            <div><span class="k">{{ __('channel.f_blocked') }}</span><span class="v">{{ count($blocked) }}</span></div>
        </div>
        <div class="ch-hint" style="margin-top:8px;">{{ __('channel.products_help') }}</div>
    </div>

    <form method="GET" action="{{ action([$C, 'products']) }}" class="ch-card ch-filter" id="ch_filter">
        <div style="flex:1 1 220px;">
            <label class="l" for="ch_q">{{ __('channel.l_search') }}</label>
            <input class="ch-in" type="search" name="q" id="ch_q" value="{{ $search }}" placeholder="{{ __('channel.search_ph') }}">
        </div>
        <div>
            <label class="l" for="ch_show">{{ __('channel.l_show') }}</label>
            <select class="ch-in" name="show" id="ch_show">
                @foreach (['all', 'on', 'off', 'never'] as $s)
                    <option value="{{ $s }}" @selected($show === $s)>{{ __('channel.show_'.$s) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="l" for="ch_cat">{{ __('channel.l_category') }}</label>
            <select class="ch-in" name="category" id="ch_cat">
                <option value="">{{ __('channel.all_categories') }}</option>
                @foreach ($cats as $c)
                    <option value="{{ $c->id }}" @selected($category == $c->id)>{{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        @if ($links->count() > 1)
            <div>
                <label class="l" for="ch_loc">{{ __('channel.l_stock_at') }}</label>
                <select class="ch-in" name="location" id="ch_loc">
                    @foreach ($links as $l)
                        <option value="{{ $l->location_id }}" @selected($loc_id == $l->location_id)>{{ optional($l->location)->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div><button class="ch-btn pri" type="submit">{{ __('channel.b_filter') }}</button></div>
    </form>

    <form method="POST" action="{{ action([$C, 'bulk']) }}" class="ch-card" id="ch_bulk_form">
        @csrf
        @foreach (['show' => $show, 'category' => $category, 'q' => $search] as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <input type="hidden" name="scope" value="ticked" id="ch_scope">
        <div class="ch-bulk">
            <span class="ch-muted">{{ __('channel.bulk_ticked') }}</span>
            <button class="ch-btn sm ok" type="submit" name="action" value="on">{{ __('channel.b_put_on') }}</button>
            <button class="ch-btn sm" type="submit" name="action" value="off">{{ __('channel.b_take_off') }}</button>
            <button class="ch-btn sm danger" type="submit" name="action" value="never">{{ __('channel.b_never') }}</button>
            <span style="flex:1"></span>
            <button class="ch-btn sm ok" type="button" id="ch_all_on" data-n="{{ $matching }}">{{ __('channel.b_all_on', ['n' => $matching]) }}</button>
        </div>

        <div style="overflow-x:auto;">
        <table class="ch-table" id="ch_table">
            <thead><tr>
                <th><input type="checkbox" id="ch_tick_all" aria-label="{{ __('channel.tick_all') }}"></th>
                <th>{{ __('channel.c_product') }}</th>
                <th>{{ __('channel.c_category') }}</th>
                <th class="num">{{ __('channel.c_price') }}</th>
                <th class="num">{{ __('channel.c_stock') }}</th>
                <th>{{ __('channel.c_on') }}</th>
                <th>{{ __('channel.c_never') }}</th>
                <th>{{ __('channel.c_own_price') }}</th>
                <th>{{ __('channel.c_unit') }}</th>
                <th>{{ __('channel.c_low') }}</th>
            </tr></thead>
            <tbody>
            @forelse ($products as $p)
                @php
                    $pr = $prices[$p->id] ?? null;
                    $price = $pr ? ($pr->lo == $pr->hi ? $util->num_f($pr->lo) : $util->num_f($pr->lo).' – '.$util->num_f($pr->hi)) : '—';
                    $sub = $util->getSubUnits(session('user.business_id'), $p->unit_id, true, $p->id);
                    $unit = $units[$p->unit_id] ?? null;
                    $in_blocked = in_array((int) $p->category_id, $blocked, true);
                @endphp
                <tr data-id="{{ $p->id }}" class="{{ $p->publish && ! $p->never ? 'is-on' : '' }} {{ $p->never ? 'is-never' : '' }}">
                    <td><input type="checkbox" name="ids[]" value="{{ $p->id }}" class="ch-tick" aria-label="{{ $p->name }}"></td>
                    <td data-l="{{ __('channel.c_product') }}"><b>{{ $p->name }}</b><div class="ch-muted ch-small">{{ $p->sku }}@if ($p->type === 'variable') · {{ __('channel.variable') }}@endif</div></td>
                    <td data-l="{{ __('channel.c_category') }}">{{ $catNames[$p->category_id] ?? '—' }}@if ($in_blocked) <span class="ch-pill bad">{{ __('channel.blocked') }}</span>@endif</td>
                    <td class="num" data-l="{{ __('channel.c_price') }}">{{ $price }}</td>
                    <td class="num" data-l="{{ __('channel.c_stock') }}">@if ($p->enable_stock){{ $util->num_f($stock[$p->id] ?? 0) }} <span class="ch-muted ch-small">{{ $unit->short_name ?? '' }}</span>@else<span class="ch-muted">{{ __('channel.no_stock_tracking') }}</span>@endif</td>
                    <td data-l="{{ __('channel.c_on') }}"><label class="ch-sw"><input type="checkbox" class="ch-f" data-f="publish" @checked($p->publish && ! $p->never) @disabled($p->never)> <span class="sr-only">{{ __('channel.c_on') }}</span></label></td>
                    <td data-l="{{ __('channel.c_never') }}"><label class="ch-sw"><input type="checkbox" class="ch-f" data-f="never" @checked($p->never)> <span class="sr-only">{{ __('channel.c_never') }}</span></label></td>
                    <td data-l="{{ __('channel.c_own_price') }}">
                        @if ($p->type === 'single')
                            <input class="ch-in w-m ch-f input_number" data-f="price" inputmode="decimal" value="{{ $p->own_price !== null ? $util->num_f($p->own_price) : '' }}" placeholder="{{ __('channel.same_price') }}">
                        @else
                            <span class="ch-muted ch-small">{{ __('channel.pos_prices') }}</span>
                        @endif
                    </td>
                    <td data-l="{{ __('channel.c_unit') }}">
                        @if (count($sub) > 1)
                            <select class="ch-in w-m ch-f" data-f="unit_id">
                                @foreach ($sub as $uid => $u)
                                    <option value="{{ $uid }}" @selected(($p->pub_unit_id ?: $p->unit_id) == $uid)>{{ $u['name'] }}</option>
                                @endforeach
                            </select>
                        @else
                            {{ $unit->actual_name ?? '—' }}
                        @endif
                    </td>
                    <td data-l="{{ __('channel.c_low') }}">
                        @if ($p->enable_stock)
                            <input class="ch-in w-s ch-f input_number" data-f="low_stock" inputmode="decimal" value="{{ $p->low_stock !== null ? $util->num_f($p->low_stock) : '' }}" placeholder="{{ $p->alert_quantity !== null ? $util->num_f($p->alert_quantity) : '0' }}">
                        @endif
                        <span class="ch-saving" aria-live="polite"></span>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="ch-muted">{{ __('channel.no_products') }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div style="margin-top:10px;">{{ $products->links() }}</div>
    </form>

    <form method="POST" action="{{ action([$C, 'categories']) }}" class="ch-card" id="ch_cats_form">
        @csrf
        <h2>{{ __('channel.cats_t') }}</h2>
        <div class="ch-hint" style="margin-bottom:10px;">{{ __('channel.cats_h') }}</div>
        <div class="ch-cats">
            @foreach ($cats as $c)
                <label><input type="checkbox" name="blocked[]" value="{{ $c->id }}" @checked(in_array((int) $c->id, $blocked, true))> {{ $c->parent_id ? '— ' : '' }}{{ $c->name }}</label>
            @endforeach
        </div>
        <div class="ch-actions"><button class="ch-btn pri" type="submit">{{ __('channel.b_save_cats') }}</button></div>
    </form>
</section>
@endsection

@section('javascript')
<script>
(function () {
    var URL_BASE = @json(url('eride/products'));
    var T = { saved: @json(__('channel.saved')), saving: @json(__('channel.saving')), err: @json(__('channel.save_failed')),
        all_confirm: @json(__('channel.all_on_confirm')) };
    var table = document.getElementById('ch_table');
    if (!table) { return; }

    function save(tr, data, done) {
        var st = tr.querySelector('.ch-saving');
        if (st) { st.textContent = T.saving; }
        data._token = @json(csrf_token());
        $.ajax({ method: 'POST', url: URL_BASE + '/' + tr.getAttribute('data-id'), data: data, dataType: 'json' })
            .done(function (r) {
                if (st) { st.textContent = T.saved; setTimeout(function () { st.textContent = ''; }, 1500); }
                if (r && typeof r.publish !== 'undefined') {
                    var pub = tr.querySelector('[data-f=publish]'), nev = tr.querySelector('[data-f=never]');
                    pub.checked = !!r.publish && !r.never; pub.disabled = !!r.never; nev.checked = !!r.never;
                    tr.classList.toggle('is-on', !!r.publish && !r.never);
                    tr.classList.toggle('is-never', !!r.never);
                }
                if (done) { done(); }
            })
            .fail(function (x) {
                var msg = (x.responseJSON && x.responseJSON.msg) || T.err;
                if (st) { st.textContent = ''; }
                if (typeof toastr !== 'undefined') { toastr.error(msg); } else { alert(msg); }
            });
    }

    table.addEventListener('change', function (e) {
        var el = e.target.closest('.ch-f');
        if (!el) { return; }
        var tr = el.closest('tr'), f = el.getAttribute('data-f'), d = {};
        d[f] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
        save(tr, d);
    });

    var all = document.getElementById('ch_tick_all');
    if (all) {
        all.addEventListener('change', function () {
            table.querySelectorAll('.ch-tick').forEach(function (c) { c.checked = all.checked; });
        });
    }

    var btn = document.getElementById('ch_all_on');
    if (btn) {
        btn.addEventListener('click', function () {
            if (!confirm(T.all_confirm.replace(':n', btn.getAttribute('data-n')))) { return; }
            var f = document.getElementById('ch_bulk_form');
            document.getElementById('ch_scope').value = 'matching';
            var a = document.createElement('input');
            a.type = 'hidden'; a.name = 'action'; a.value = 'on';
            f.appendChild(a);
            f.submit();
        });
    }
})();
</script>
@endsection
