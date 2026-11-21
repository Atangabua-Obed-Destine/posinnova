@extends('layouts.app')
@section('title', __('hotel.menu_calendar'))

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $H = \App\Http\Controllers\HotelController::class;
    $u = auth()->user();
    $book = $u->can('hotel.reservations') || $u->can('hotel.front_desk');
@endphp
<style>
.ht-cal { min-width: 1000px; }
.ht-cal .row1 { display: flex; align-items: stretch; border-bottom: 1px solid #f2f4f7; margin: 0; }
.ht-cal .lab { flex: 0 0 120px; padding: 8px 12px; border-right: 1px solid #eaecf0; box-sizing: border-box; }
.ht-cal .days { flex: 1; display: grid; grid-template-columns: repeat({{ $days }}, minmax(0, 1fr)); align-items: center; padding: 5px 0;
    background-image: linear-gradient(to right, #eaecf0 1px, transparent 1px); background-size: calc(100% / {{ $days }}) 100%; position: relative; min-height: 44px; }
.ht-cal .dh { text-align: center; font-size: 12px; color: #667085; padding: 6px 0; }
.ht-cal .dh strong { display: block; font-size: 14px; color: #101828; }
.ht-cal .dh.today { background: #eff4ff; border-radius: 8px; }
.ht-cal .dh.today strong { color: #1849a9; }
.ht-cal .free { display: block; font-size: 11px; color: #067647; }
.ht-cal .free.none { color: #b42318; }
.ht-cal .cell { grid-row: 1; align-self: stretch; margin: -5px 0; display: block; }
.ht-cal .cell:hover { background: rgba(46,144,250,.10); }
.ht-cal .bar { grid-row: 1; margin: 0 3px; padding: 6px 9px; border-radius: 8px; font-size: 12px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none !important; position: relative; z-index: 1; min-height: 30px; box-sizing: border-box; }
.ht-cal .b-in { background: #2e90fa; color: #fff !important; }
.ht-cal .b-conf { background: #7a5af8; color: #fff !important; }
.ht-cal .b-tent { background: #fff; color: #5925dc !important; border: 2px dashed #7a5af8; }
.ht-cal .b-out { background: #e4e7ec; color: #344054 !important; }
.ht-cal .b-ooo { background: #f2f4f7; color: #475467; background-image: repeating-linear-gradient(135deg, transparent 0 6px, rgba(71,84,103,.12) 6px 12px); }
</style>
<section class="content ht" id="ht_calendar">
    <div class="ht-head">
        <div><h1>{{ __('hotel.menu_calendar') }}</h1><p>{{ __('hotel.calendar_lead') }}</p></div>
        <div class="ht-actions" style="align-items:center;">
            <a class="ht-btn" href="{{ action([$H, 'calendar'], ['start' => $start->copy()->subDays(7)->toDateString()]) }}" aria-label="{{ __('hotel.earlier') }}">‹</a>
            <strong>{{ $start->format('d/m') }} – {{ $start->copy()->addDays($days - 1)->format('d/m/Y') }}</strong>
            <a class="ht-btn" href="{{ action([$H, 'calendar'], ['start' => $start->copy()->addDays(7)->toDateString()]) }}" aria-label="{{ __('hotel.later') }}">›</a>
            <a class="ht-btn" href="{{ action([$H, 'calendar']) }}">{{ __('hotel.today') }}</a>
            @if ($book)<a class="ht-btn pri" href="{{ action([$R, 'create']) }}">+ {{ __('hotel.new_reservation') }}</a>@endif
        </div>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:16px;margin-bottom:12px;font-size:13px;">
        <span><span class="ht-sw" style="background:#2e90fa;width:20px;"></span>{{ __('hotel.st_checked_in') }}</span>
        <span><span class="ht-sw" style="background:#7a5af8;width:20px;"></span>{{ __('hotel.st_confirmed') }}</span>
        <span><span class="ht-sw" style="background:#fff;border:2px dashed #7a5af8;width:20px;"></span>{{ __('hotel.st_tentative') }}</span>
        <span><span class="ht-sw" style="background:#e4e7ec;width:20px;"></span>{{ __('hotel.st_checked_out') }}</span>
        <span><span class="ht-sw" style="background:#d0d5dd;width:20px;"></span>{{ __('hotel.state_ooo') }}</span>
    </div>

    <div class="ht-card" style="padding:0;overflow:hidden;">
        <div class="ht-scroll"><div class="ht-cal">
            <div class="row1" style="background:#fcfcfd;">
                <div class="lab ht-muted" style="font-size:12px;font-weight:600;">{{ __('hotel.room') }}</div>
                <div class="days" style="background-image:none;">
                    @for ($i = 0; $i < $days; $i++)
                        @php $d = $start->copy()->addDays($i); $free = collect($avail)->sum(fn ($a) => max(0, $a['by_night'][$d->toDateString()] ?? 0)); @endphp
                        <div class="dh {{ $d->isSameDay($today) ? 'today' : '' }}">{{ $d->translatedFormat('D') }}<strong>{{ $d->format('d') }}</strong><span class="free {{ $free ? '' : 'none' }}">{{ __('hotel.n_free', ['n' => $free]) }}</span></div>
                    @endfor
                </div>
            </div>
            @forelse ($rooms as $room)
                <div class="row1">
                    <div class="lab"><strong>{{ $room->number }}</strong><div class="ht-muted ht-small">{{ optional($room->type)->name }}</div></div>
                    <div class="days">
                        @if ($book)
                            @for ($i = 0; $i < $days; $i++)
                                @php $d = $start->copy()->addDays($i); @endphp
                                @if ($d->gte($today))
                                    <a class="cell" style="grid-column: {{ $i + 1 }} / span 1;" href="{{ action([$R, 'create'], ['room_id' => $room->id, 'arrival' => $d->toDateString(), 'departure' => $d->copy()->addDay()->toDateString()]) }}" aria-label="{{ __('hotel.book_room_on', ['room' => $room->number, 'date' => $d->format('d/m')]) }}"></a>
                                @endif
                            @endfor
                        @endif
                        @if (isset($ooo[$room->id]))
                            <div class="bar b-ooo" style="grid-column: {{ $ooo[$room->id]['col'] }} / span {{ $ooo[$room->id]['span'] }};" title="{{ $ooo[$room->id]['reason'] }}">{{ __('hotel.state_ooo') }}{{ $ooo[$room->id]['reason'] ? ' · ' . $ooo[$room->id]['reason'] : '' }}</div>
                        @endif
                        @foreach ($bars[$room->id] ?? [] as $b)
                            <a class="bar b-{{ $b['kind'] }}" style="grid-column: {{ $b['col'] }} / span {{ $b['span'] }};" href="{{ action([$R, 'show'], [$b['res']->id]) }}"
                               title="{{ optional($b['res']->contact)->name }} · {{ $b['res']->arrival->format('d/m') }} → {{ $b['res']->departure->format('d/m') }} · {{ $b['res']->ref_no }}">{{ optional($b['res']->contact)->name }}</a>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="ht-empty" style="padding:16px;">{{ __('hotel.no_rooms') }}</div>
            @endforelse
        </div></div>
    </div>

    @if ($unassigned->isNotEmpty())
        <div class="ht-card">
            <h2>{{ __('hotel.not_in_room_yet') }}</h2>
            <div class="ht-scroll"><table class="ht-t">
                <thead><tr><th>{{ __('hotel.guest') }}</th><th>{{ __('hotel.room_type') }}</th><th>{{ __('hotel.stay') }}</th><th>{{ __('hotel.status') }}</th></tr></thead>
                <tbody>@foreach ($unassigned as $r)
                    <tr><td><a class="ht-link" href="{{ action([$R, 'show'], [$r->id]) }}">{{ optional($r->contact)->name }}</a> <span class="ht-muted ht-small">{{ $r->ref_no }}</span></td>
                        <td>{{ optional($r->roomType)->name }}</td><td>{{ $r->arrival->format('d/m') }} → {{ $r->departure->format('d/m') }}</td>
                        <td><span class="ht-pill ht-p-{{ $r->status }}">{{ __('hotel.st_' . $r->status) }}</span></td></tr>
                @endforeach</tbody>
            </table></div>
        </div>
    @endif
</section>
@endsection
