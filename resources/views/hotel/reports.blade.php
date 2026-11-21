@extends('layouts.app')
@section('title', __('hotel.menu_reports'))

@section('content')
@include('hotel.partials.styles')
@php
    $P = \App\Http\Controllers\HotelReportController::class;
    $R = \App\Http\Controllers\HotelReservationController::class;
    $q = fn ($t, $x = []) => action([$P, 'index'], array_merge(['tab' => $t, 'from' => $from->toDateString(), 'to' => $to->toDateString()], $x));
@endphp
<style>
.ht-bars { display: flex; align-items: flex-end; gap: 4px; height: 200px; border-bottom: 1px solid #d0d5dd; padding-top: 18px;
    background-image: linear-gradient(to top, #f2f4f7 1px, transparent 1px); background-size: 100% 25%; }
.ht-bars .b { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
.ht-bars .b i { display: block; width: 100%; max-width: 26px; background: #2e90fa; border-radius: 4px 4px 0 0; }
.ht-bars .b span { font-size: 10px; color: #475467; margin-bottom: 2px; }
.ht-xl { display: flex; gap: 4px; margin-top: 4px; } .ht-xl span { flex: 1; min-width: 0; text-align: center; font-size: 10px; color: #667085; }
</style>
<section class="content ht" id="ht_reports">
    <div class="ht-head">
        <div><h1>{{ __('hotel.menu_reports') }}</h1></div>
        @if ($tab !== 'police')
            <form method="GET" class="ht-actions" style="align-items:flex-end;">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="ht-f" style="margin:0;"><label for="ht_r_from">{{ __('hotel.from') }}</label><input class="ht-in" type="date" name="from" id="ht_r_from" value="{{ $from->toDateString() }}"></div>
                <div class="ht-f" style="margin:0;"><label for="ht_r_to">{{ __('hotel.to') }}</label><input class="ht-in" type="date" name="to" id="ht_r_to" value="{{ $to->toDateString() }}"></div>
                <button class="ht-btn">{{ __('hotel.show') }}</button>
            </form>
        @endif
    </div>
    <nav class="ht-tabs">
        <a href="{{ $q('occupancy') }}" class="{{ $tab === 'occupancy' ? 'on' : '' }}">{{ __('hotel.occupancy') }}</a>
        <a href="{{ $q('police') }}" class="{{ $tab === 'police' ? 'on' : '' }}" id="ht_tab_police">{{ __('hotel.police_register') }}</a>
        <a href="{{ $q('revenue') }}" class="{{ $tab === 'revenue' ? 'on' : '' }}">{{ __('hotel.revenue_owed') }}</a>
    </nav>

    @if ($tab === 'occupancy')
        <div class="ht-kpis">
            <div class="ht-kpi"><div class="l">{{ __('hotel.occupancy') }}</div><div class="v" id="ht_r_occ">{{ $kpi['occupancy'] }}%</div></div>
            <div class="ht-kpi"><div class="l">{{ __('hotel.nights_sold') }}</div><div class="v">{{ $kpi['sold'] }}</div></div>
            <div class="ht-kpi"><div class="l">{{ __('hotel.adr') }}</div><div class="v">{{ $fmt($kpi['adr']) }}</div></div>
            <div class="ht-kpi"><div class="l">{{ __('hotel.revpar') }}</div><div class="v">{{ $fmt($kpi['revpar']) }}</div></div>
            <div class="ht-kpi"><div class="l">{{ __('hotel.room_revenue') }}</div><div class="v">{{ $fmt($kpi['revenue']) }}</div></div>
        </div>
        @if (count($days) <= 62)
            <div class="ht-card">
                <h2>{{ __('hotel.occupancy_per_night') }}</h2>
                <div class="ht-bars" role="img" aria-label="{{ __('hotel.occupancy_per_night') }}">
                    @foreach ($days as $d)<div class="b" title="{{ $d['date']->format('d/m') }}: {{ $d['sold'] }}/{{ $d['sellable'] }}"><span>{{ $d['pct'] }}</span><i style="height: {{ max(1, min(100, $d['pct'])) }}%;"></i></div>@endforeach
                </div>
                <div class="ht-xl">@foreach ($days as $d)<span>{{ $d['date']->format('d') }}</span>@endforeach</div>
            </div>
        @endif
        <div class="ht-grid ht-g2">
            <div class="ht-card"><h2>{{ __('hotel.by_room_type') }}</h2>
                <table class="ht-t"><thead><tr><th>{{ __('hotel.room_type') }}</th><th class="num">{{ __('hotel.rooms') }}</th><th class="num">{{ __('hotel.nights') }}</th><th class="num">{{ __('hotel.occupancy') }}</th><th class="num">{{ __('hotel.room_revenue') }}</th></tr></thead>
                    <tbody>@forelse ($by_type as $t)<tr><td>{{ $t['name'] }}</td><td class="num">{{ $t['rooms'] }}</td><td class="num">{{ $t['nights'] }}</td><td class="num">{{ $t['pct'] }}%</td><td class="num">{{ $num($t['revenue']) }}</td></tr>
                    @empty<tr><td colspan="5" class="ht-muted">{{ __('hotel.nothing') }}</td></tr>@endforelse</tbody></table></div>
            <div class="ht-card"><h2>{{ __('hotel.by_source') }}</h2>
                <table class="ht-t"><thead><tr><th>{{ __('hotel.booked_through') }}</th><th class="num">{{ __('hotel.nights') }}</th></tr></thead>
                    <tbody>@forelse ($sources as $s)<tr><td>{{ __('hotel.source_' . $s['source']) }}</td><td class="num">{{ $s['nights'] }}</td></tr>
                    @empty<tr><td colspan="2" class="ht-muted">{{ __('hotel.nothing') }}</td></tr>@endforelse</tbody></table></div>
        </div>
        <p class="ht-muted ht-small">{{ __('hotel.occupancy_help') }}</p>

    @elseif ($tab === 'police')
        <form method="GET" class="ht-actions" style="align-items:flex-end;margin-bottom:12px;">
            <input type="hidden" name="tab" value="police">
            <div class="ht-f" style="margin:0;"><label for="ht_r_day">{{ __('hotel.day') }}</label><input class="ht-in" type="date" name="day" id="ht_r_day" value="{{ $day->toDateString() }}"></div>
            <button class="ht-btn">{{ __('hotel.show') }}</button>
            <a class="ht-btn" href="{{ action([$P, 'index'], ['tab' => 'police', 'day' => $day->toDateString(), 'print' => 1]) }}" target="_blank" rel="noopener">{{ __('hotel.print') }}</a>
        </form>
        <div class="ht-card" style="padding:6px 6px 2px;">
            <h2 style="padding:8px 10px 0;">{{ __('hotel.police_on', ['date' => $day->format('d/m/Y')]) }}</h2>
            @include('hotel.partials.police_table')
        </div>

    @else
        <div class="ht-grid ht-g2">
            <div class="ht-card"><h2>{{ __('hotel.revenue_by_dept') }}</h2>
                @foreach (['rooms', 'hotel_extras', 'tourist_tax', 'other_sales'] as $k)<div class="ht-row"><span>{{ __('hotel.dept_' . $k) }}</span><strong>{{ $fmt($dept[$k]) }}</strong></div>@endforeach
                <div class="ht-row"><strong>{{ __('hotel.total') }}</strong><strong>{{ $fmt(array_sum($dept)) }}</strong></div>
                @if ($discounts > 0)<div class="ht-row"><span class="ht-muted">{{ __('hotel.discounts_given') }}</span><strong>{{ $fmt($discounts) }}</strong></div>@endif
                <p class="ht-muted ht-small" style="margin:8px 0 0;">{{ __('hotel.revenue_help') }}</p></div>
            <div class="ht-card"><h2>{{ __('hotel.still_owed') }}</h2>
                @if ($owed->isEmpty())<div class="ht-empty">{{ __('hotel.nothing_owed') }}</div>
                @else
                    <table class="ht-t"><thead><tr><th>{{ __('hotel.guest') }}</th><th>{{ __('hotel.status') }}</th><th class="num">{{ __('hotel.balance') }}</th></tr></thead>
                        <tbody>@foreach ($owed as $o)<tr><td><a class="ht-link" href="{{ action([$R, 'show'], [$o['res']->id]) }}">{{ optional($o['res']->contact)->name }}</a><div class="ht-muted ht-small">{{ $o['res']->ref_no }} · {{ __('hotel.room') }} {{ optional($o['res']->room)->number }}</div></td>
                            <td><span class="ht-pill ht-p-{{ $o['res']->status }}">{{ __('hotel.st_' . $o['res']->status) }}</span></td><td class="num">{{ $num($o['balance']) }}</td></tr>@endforeach</tbody></table>
                @endif</div>
        </div>
    @endif
</section>
@endsection
