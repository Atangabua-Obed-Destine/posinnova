@extends('layouts.app')
@section('title', __('hotel.menu_board'))

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $H = \App\Http\Controllers\HotelController::class;
    $S = \App\Http\Controllers\HotelSetupController::class;
    $u = auth()->user();
    $tone = ['free' => '#e5f4e4;color:#05603a', 'occ' => '#e7f1fb;color:#1849a9', 'arr' => '#f4f3ff;color:#5925dc', 'dep' => '#fdead7;color:#b93815', 'dirty' => '#fef0c7;color:#93370d', 'ooo' => '#f2f4f7;color:#475467'];
    $filters = ['all' => [__('hotel.all'), $total], 'free' => [__('hotel.state_free'), ($counts['free'] ?? 0) + ($counts['arr'] ?? 0)], 'occ' => [__('hotel.state_occ'), ($counts['occ'] ?? 0) + ($counts['dep'] ?? 0)],
        'arr' => [__('hotel.state_arr'), $counts['arr'] ?? 0], 'dep' => [__('hotel.state_dep'), $counts['dep'] ?? 0], 'dirty' => [__('hotel.state_dirty'), $counts['dirty'] ?? 0], 'ooo' => [__('hotel.state_ooo'), $counts['ooo'] ?? 0]];
@endphp
<style>
.ht-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.ht-tile { display: flex; flex-direction: column; gap: 3px; min-height: 132px; padding: 11px 12px; border-radius: 12px; border: 2px solid transparent; text-align: left; font: inherit; cursor: pointer; min-width: 0; }
.ht-tile .top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
.ht-tile .no { font-size: 20px; font-weight: 700; }
.ht-tile .st { font-size: 12px; font-weight: 700; text-align: right; }
.ht-tile .ty { font-size: 12.5px; }
.ht-tile .gu { font-size: 14px; font-weight: 700; margin-top: auto; padding-top: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 100%; }
.ht-tile .ln { font-size: 12.5px; }
.ht-tile .mo { font-size: 12.5px; font-weight: 700; }
.ht-tile .mo.due { color: #b42318; }
.ht-tile .fl { font-size: 12px; font-weight: 700; color: #93370d; }
.ht-tile.sel { border-color: #101828; }
.ht-tile.dim { opacity: .28; }
.ht-tile.st-ooo { background-image: repeating-linear-gradient(135deg, transparent 0 8px, rgba(71,84,103,.08) 8px 16px); }
.ht-panel { display: none; }
.ht-panel.on { display: block; }
</style>
<section class="content ht" id="ht_board">
    <div class="ht-head">
        <div><h1>{{ __('hotel.menu_board') }}</h1><p>{{ __('hotel.board_lead') }}</p></div>
        <div class="ht-actions">
            <a class="ht-btn" href="{{ action([$H, 'calendar']) }}">{{ __('hotel.menu_calendar') }}</a>
            @if ($u->can('hotel.front_desk'))<a class="ht-btn pri" href="{{ action([$R, 'create'], ['walk_in' => 1]) }}">{{ __('hotel.walk_in') }}</a>@endif
        </div>
    </div>

    @if ($total === 0)
        <div class="ht-card ht-empty">{{ __('hotel.no_rooms') }} @if ($u->can('hotel.setup'))<a class="ht-link" href="{{ action([$S, 'index'], ['tab' => 'rooms']) }}">{{ __('hotel.menu_setup') }}</a>@endif</div>
    @else
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;" role="group" aria-label="{{ __('hotel.show_rooms') }}">
        @foreach ($filters as $k => [$label, $n])
            <button type="button" class="ht-chip {{ $k === 'all' ? 'on' : '' }}" data-filter="{{ $k }}">{{ $label }} {{ $n }}</button>
        @endforeach
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
        <div style="flex:999 1 520px;min-width:0;">
            @foreach ($floors as $floor => $states)
                <div class="ht-card">
                    <div style="display:flex;justify-content:space-between;margin-bottom:12px;"><h2 style="margin:0;">{{ $floor !== '' ? __('hotel.floor_n', ['n' => $floor]) : __('hotel.rooms') }}</h2>
                        <span class="ht-muted ht-small">{{ __('hotel.n_of_m_occupied', ['n' => $states->whereIn('state', ['occ', 'dep'])->count(), 'm' => $states->count()]) }}</span></div>
                    <div class="ht-tiles">
                        @foreach ($states as $st)
                            @php $room = $st['room']; $res = $st['res']; @endphp
                            <button type="button" class="ht-tile st-{{ $st['state'] }}" style="background: {{ $tone[$st['state']] }}" data-room="{{ $room->id }}" data-state="{{ $st['state'] }}" id="ht_tile_{{ $room->number }}" aria-pressed="false">
                                @php $state = $st['state']; $type = $room->type; $left = $res ? $today->diffInDays($res->departure, false) : 0; @endphp
                                <span class="top"><span class="no">{{ $room->number }}</span><span class="st">{{ __('hotel.state_' . $state) }}</span></span>
                                <span class="ty">{{ optional($type)->name }}@if (optional($type)->capacity) · {{ trans_choice('hotel.sleeps_n', $type->capacity, ['n' => $type->capacity]) }}@endif</span>
                                @if (in_array($state, ['occ', 'dep']))
                                    <span class="gu">{{ optional($res->contact)->name }}</span>
                                    <span class="ln">{{ $left <= 0 ? __('hotel.tile_leaves_today') : __('hotel.tile_leaves', ['date' => $res->departure->format('d/m')]) . ' · ' . trans_choice('hotel.tile_nights_left', $left, ['n' => $left]) }}</span>
                                    @if ($st['owe'] > 0.004)<span class="mo due">{{ __('hotel.tile_collect', ['amount' => $fmt($st['owe'])]) }}</span>
                                    @else<span class="mo">{{ __('hotel.tile_paid') }}</span>@endif
                                    @if ($room->hk_status === 'dirty')<span class="fl">{{ __('hotel.to_clean') }}</span>@endif
                                @elseif ($state === 'arr')
                                    <span class="gu">{{ optional($res->contact)->name }}</span>
                                    <span class="ln">{{ $res->arrival->lt($today) ? __('hotel.tile_was_due', ['date' => $res->arrival->format('d/m')]) : __('hotel.tile_arrives') }} · {{ trans_choice('hotel.n_nights', $res->nights(), ['n' => $res->nights()]) }}</span>
                                    @if (! $room->isTidy())<span class="fl">{{ __('hotel.to_clean') }}</span>@endif
                                @elseif ($state === 'ooo')
                                    <span class="gu">{{ $room->ooo_reason ?: __('hotel.state_ooo') }}</span>
                                    <span class="ln">{{ $room->ooo_to ? __('hotel.until_date', ['date' => $room->ooo_to->format('d/m')]) : __('hotel.until_further') }}</span>
                                @else
                                    <span class="gu">{{ $state === 'dirty' ? __('hotel.to_clean') : __('hotel.ready') }}</span>
                                    @if ($type)<span class="ln">{{ __('hotel.tile_per_night', ['amount' => $fmt($type->rate_room_only)]) }}</span>@endif
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        <aside class="ht-card" style="flex:1 1 300px;max-width:380px;min-width:260px;" aria-live="polite" id="ht_board_panel">
            <div class="ht-panel on" id="ht_panel_none"><p class="ht-muted" style="margin:0;">{{ __('hotel.board_pick') }}</p></div>
            @foreach ($floors->flatten(1) as $st)
                @php $room = $st['room']; $res = $st['res']; $state = $st['state']; @endphp
                <div class="ht-panel" id="ht_panel_{{ $room->id }}">
                    <div class="ht-muted" style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;">{{ __('hotel.room') }} {{ $room->number }} · {{ optional($room->type)->name }}</div>
                    @if ($res)
                        <h2 style="margin:6px 0 2px;font-size:19px;">{{ optional($res->contact)->name }}</h2>
                        <p class="ht-muted" style="margin:0 0 12px;">{{ $res->ref_no }} · {{ $res->arrival->format('d/m') }} → {{ $res->departure->format('d/m') }} · {{ trans_choice('hotel.n_nights', $res->nights(), ['n' => $res->nights()]) }}</p>
                        <div class="ht-row"><span class="ht-muted">{{ __('hotel.rate') }}</span><strong>{{ $fmt($res->rate) }}</strong></div>
                        @if (in_array($state, ['occ', 'dep']))
                            <div class="ht-row"><span class="ht-muted">{{ __('hotel.balance') }}</span><strong>{{ $fmt(max(0, $folio->balance($res))) }}</strong></div>
                            <div class="ht-row"><span class="ht-muted">{{ __('hotel.housekeeping') }}</span><strong>{{ __('hotel.hk_' . $room->hk_status) }}</strong></div>
                        @endif
                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;">
                            @if ($state === 'arr' && $u->can('hotel.front_desk'))<a class="ht-btn pri" href="{{ action([$R, 'checkInForm'], [$res->id]) }}">{{ __('hotel.check_in') }}</a>@endif
                            @if ($state === 'dep' && $u->can('hotel.front_desk'))<a class="ht-btn pri" href="{{ action([$R, 'checkOutForm'], [$res->id]) }}">{{ __('hotel.check_out') }}</a>@endif
                            <a class="ht-btn {{ $state === 'occ' ? 'pri' : '' }}" href="{{ action([$R, 'show'], [$res->id]) }}">{{ in_array($state, ['occ', 'dep']) ? __('hotel.open_bill') : __('hotel.open_reservation') }}</a>
                        </div>
                    @elseif ($state === 'ooo')
                        <h2 style="margin:6px 0 2px;font-size:19px;">{{ __('hotel.state_ooo') }}</h2>
                        <p class="ht-muted" style="margin:0 0 12px;">{{ $room->ooo_reason }}</p>
                        <div class="ht-row"><span class="ht-muted">{{ __('hotel.blocked') }}</span><strong>{{ $room->ooo_from->format('d/m') }} → {{ $room->ooo_to ? $room->ooo_to->format('d/m') : __('hotel.until_further') }}</strong></div>
                        @if ($u->can('hotel.setup') || $u->can('hotel.front_desk'))
                            <form method="POST" action="{{ action([$S, 'outOfOrder'], [$room->id]) }}" style="margin-top:12px;">@csrf<input type="hidden" name="clear" value="1">
                                <button class="ht-btn pri" style="width:100%;">{{ __('hotel.back_in_service') }}</button></form>
                        @endif
                    @else
                        <h2 style="margin:6px 0 2px;font-size:19px;">{{ $state === 'dirty' ? __('hotel.to_clean') : __('hotel.free_clean') }}</h2>
                        <p class="ht-muted" style="margin:0 0 12px;">@if ($room->hk_at){{ __('hotel.hk_last', ['status' => __('hotel.hk_' . $room->hk_status), 'when' => $room->hk_at->format('d/m H:i')]) }}@endif</p>
                        <div class="ht-row"><span class="ht-muted">{{ __('hotel.rate') }}</span><strong>{{ $fmt(optional($room->type)->rate_room_only) }}</strong></div>
                        <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px;">
                            @if ($state === 'dirty' && ($u->can('hotel.housekeeping') || $u->can('hotel.front_desk')))
                                <form method="POST" action="{{ action([$H, 'markRoom'], [$room->id]) }}">@csrf<input type="hidden" name="hk_status" value="clean"><button class="ht-btn ok" style="width:100%;">{{ __('hotel.mark_clean') }}</button></form>
                            @endif
                            @if ($u->can('hotel.front_desk'))<a class="ht-btn {{ $state === 'free' ? 'pri' : '' }}" href="{{ action([$R, 'create'], ['walk_in' => 1, 'room_id' => $room->id]) }}">{{ __('hotel.walk_in_here') }}</a>@endif
                            @if ($u->can('hotel.reservations') || $u->can('hotel.front_desk'))<a class="ht-btn" href="{{ action([$R, 'create'], ['room_id' => $room->id]) }}">{{ __('hotel.book_later') }}</a>@endif
                        </div>
                    @endif
                </div>
            @endforeach
        </aside>
    </div>
    @endif
</section>
@endsection

@section('javascript')
<script>
(function () {
    var tiles = document.querySelectorAll('#ht_board .ht-tile');
    var groups = { all: null, free: ['free', 'arr'], occ: ['occ', 'dep'], arr: ['arr'], dep: ['dep'], dirty: ['dirty'], ooo: ['ooo'] };
    document.querySelectorAll('#ht_board [data-filter]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelectorAll('#ht_board [data-filter]').forEach(function (x) { x.classList.toggle('on', x === b); });
            var want = groups[b.getAttribute('data-filter')];
            tiles.forEach(function (t) { t.classList.toggle('dim', !!want && want.indexOf(t.getAttribute('data-state')) < 0); });
        });
    });
    tiles.forEach(function (t) {
        t.addEventListener('click', function () {
            tiles.forEach(function (x) { x.classList.toggle('sel', x === t); x.setAttribute('aria-pressed', x === t ? 'true' : 'false'); });
            document.querySelectorAll('#ht_board .ht-panel').forEach(function (p) { p.classList.remove('on'); });
            var p = document.getElementById('ht_panel_' + t.getAttribute('data-room'));
            if (p) { p.classList.add('on'); if (window.innerWidth < 900) { p.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
        });
    });
})();
</script>
@endsection
