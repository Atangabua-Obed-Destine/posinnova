@extends('layouts.app')
@section('title', __('hotel.menu_housekeeping'))

@section('content')
@include('hotel.partials.styles')
@php
    $H = \App\Http\Controllers\HotelController::class;
    $tags = ['departure' => ['ht-p-dep', __('hotel.hkg_departure')], 'stayover' => ['ht-p-occ', __('hotel.hkg_stayover')], 'leaving' => ['ht-p-ooo', __('hotel.hkg_leaving')],
        'inspect' => ['ht-p-arr', __('hotel.hkg_inspect')], 'ooo' => ['ht-p-ooo', __('hotel.state_ooo')], 'ready' => ['ht-p-free', __('hotel.hkg_ready')]];
    $u = auth()->user();
    $total = $list->whereIn('group', ['departure', 'stayover', 'inspect'])->count();
@endphp
<style>
.ht-hk { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.ht-hk-item { background: #fff; border: 1px solid #eaecf0; border-radius: 14px; padding: 14px; display: flex; flex-direction: column; gap: 8px; }
.ht-hk-item .no { font-size: 20px; font-weight: 700; }
.ht-hk-item .ht-btn { min-height: 48px; font-size: 15px; width: 100%; }
.ht-hk-item.urgent { border: 2px solid #f04438; }
</style>
<section class="content ht" id="ht_housekeeping">
    <div class="ht-head">
        <div><h1>{{ __('hotel.menu_housekeeping') }}</h1><p>{{ __('hotel.hk_lead') }}</p></div>
        <div style="min-width:220px;"><strong>{{ __('hotel.hk_progress', ['n' => $todo]) }}</strong>
            <div class="ht-bar"><span style="width: {{ $list->count() ? round(($list->count() - $todo) / $list->count() * 100) : 100 }}%; background:#12b76a;"></span></div></div>
    </div>
    @if ($list->isEmpty())
        <div class="ht-card ht-empty">{{ __('hotel.no_rooms') }}</div>
    @endif
    <div class="ht-hk">
        @foreach ($list as $st)
            @php $room = $st['room']; [$cls, $tag] = $tags[$st['group']]; @endphp
            <div class="ht-hk-item {{ $st['urgent'] ? 'urgent' : '' }}" id="ht_hk_{{ $room->number }}" data-group="{{ $st['group'] }}">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span class="no">{{ $room->number }} <span class="ht-muted" style="font-size:13px;font-weight:500;">{{ optional($room->type)->name }}</span></span>
                    <span class="ht-pill {{ $st['urgent'] ? 'ht-p-bad' : $cls }}">{{ $st['urgent'] ? __('hotel.hk_urgent') : $tag }}</span>
                </div>
                <div class="ht-muted ht-small">
                    @if ($st['group'] === 'ooo'){{ $room->ooo_reason ?: __('hotel.state_ooo') }}
                    @elseif ($st['res']){{ optional($st['res']->contact)->name }} · {{ __('hotel.until_date', ['date' => $st['res']->departure->format('d/m')]) }}
                    @elseif ($room->hk_at){{ __('hotel.hk_last', ['status' => __('hotel.hk_' . $room->hk_status), 'when' => $room->hk_at->format('d/m H:i')]) }}@endif
                </div>
                @if (in_array($st['group'], ['departure', 'stayover']))
                    <form method="POST" action="{{ action([$H, 'markRoom'], [$room->id]) }}">@csrf<input type="hidden" name="hk_status" value="clean"><button class="ht-btn ok">{{ __('hotel.mark_clean') }}</button></form>
                @elseif ($st['group'] === 'leaving')
                    <button class="ht-btn" disabled>{{ __('hotel.hk_guest_in') }}</button>
                @elseif ($st['group'] === 'inspect' && ($u->can('hotel.manager') || $u->can('hotel.housekeeping')))
                    <form method="POST" action="{{ action([$H, 'markRoom'], [$room->id]) }}">@csrf<input type="hidden" name="hk_status" value="inspected"><button class="ht-btn pri">{{ __('hotel.mark_inspected') }}</button></form>
                @elseif ($st['group'] === 'ready')
                    <form method="POST" action="{{ action([$H, 'markRoom'], [$room->id]) }}">@csrf<input type="hidden" name="hk_status" value="dirty"><button class="ht-btn sm" style="min-height:36px;font-size:13px;">{{ __('hotel.mark_dirty') }}</button></form>
                @endif
            </div>
        @endforeach
    </div>
    @if ($require_inspection)<p class="ht-muted ht-small" style="margin-top:14px;">{{ __('hotel.hk_inspect_note') }}</p>@endif
</section>
@endsection
