@extends('layouts.app')
@section('title', __('hotel.menu_home'))

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $H = \App\Http\Controllers\HotelController::class;
    $counts = collect($board)->countBy('state');
    $occ_pct = $sellable ? round($expected / $sellable * 100) : 0;
    $hour = (int) now()->format('H');
    $greet = $hour < 12 ? 'greet_morning' : ($hour < 18 ? 'greet_afternoon' : 'greet_evening');
    $arr_due = $arrivals->count();
    $dep_open = $departures->where('status', 'checked_in')->count();
    $dirty = collect($board)->where('state', 'dirty')->count() + collect($board)->filter(fn ($s) => in_array($s['state'], ['occ', 'dep']) && $s['room']->hk_status === 'dirty')->count();
@endphp
<section class="content ht" id="ht_home">
    {{-- Home of a hotel: this page; the usual sales dashboard is its second tab --}}
    @if ($sales_tab)
        <nav class="ht-tabs" id="ht_home_tabs">
            <a class="on" href="{{ action([\App\Http\Controllers\HomeController::class, 'index']) }}" aria-current="page">{{ __('hotel.dash_tab_hotel') }}</a>
            <a href="{{ action([\App\Http\Controllers\HomeController::class, 'index'], ['view' => 'sales']) }}" id="ht_tab_sales">{{ __('hotel.dash_tab_sales') }}</a>
        </nav>
    @endif
    <div class="ht-head">
        <div>
            <h1>{{ __('hotel.' . $greet, ['name' => auth()->user()->first_name]) }}</h1>
            <p>{{ $today->translatedFormat('l j F Y') }} ·
                @if ($last_audit)
                    {{ __('hotel.last_audit', ['date' => $last_audit->audit_date->format('d/m'), 'time' => $last_audit->ran_at->format('H:i')]) }}
                @else
                    {{ __('hotel.no_audit_yet') }}
                @endif
            </p>
        </div>
        <div class="ht-actions">
            <a class="ht-btn" href="{{ action([$H, 'board']) }}">{{ __('hotel.menu_board') }}</a>
            @if (auth()->user()->can('hotel.front_desk'))
                <a class="ht-btn" href="{{ action([$R, 'create'], ['walk_in' => 1]) }}" id="ht_walkin">{{ __('hotel.walk_in') }}</a>
            @endif
            @if (auth()->user()->can('hotel.reservations') || auth()->user()->can('hotel.front_desk'))
                <a class="ht-btn pri" href="{{ action([$R, 'create']) }}">+ {{ __('hotel.new_reservation') }}</a>
            @endif
        </div>
    </div>

    @include('hotel.partials.eride_inbox')

    <div class="ht-kpis">
        <div class="ht-kpi" id="ht_kpi_occ"><div class="l">{{ __('hotel.occupancy_tonight') }}</div>
            <div class="v">{{ $expected }} / {{ $sellable }} <span style="font-size:16px;color:#1849a9;">{{ $occ_pct }}%</span></div>
            <div class="ht-bar"><span style="width: {{ min(100, $occ_pct) }}%"></span></div></div>
        <div class="ht-kpi"><div class="l">{{ __('hotel.arrivals_today') }}</div><div class="v">{{ $arr_due }}</div><div class="s">{{ __('hotel.still_expected') }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('hotel.departures_today') }}</div><div class="v">{{ $dep_open }}</div><div class="s">{{ __('hotel.n_left_today', ['n' => $departures->where('status', 'checked_out')->count()]) }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('hotel.rooms_to_clean') }}</div><div class="v" style="color:#b54708;">{{ $dirty }}</div><div class="s">{{ __('hotel.n_out_of_order', ['n' => $counts->get('ooo', 0)]) }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('hotel.owed_in_house') }}</div><div class="v" id="ht_kpi_owed">{{ $num($owed) }}</div><div class="s">{{ session('currency')['symbol'] ?? '' }} · {{ trans_choice('hotel.n_guests_in', $in_house->count(), ['n' => $in_house->count()]) }}</div></div>
    </div>

    <div class="ht-grid ht-g2">
        <div class="ht-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;"><h2 style="margin:0;">{{ __('hotel.arrivals_today') }}</h2>
                <a class="ht-link" href="{{ action([$R, 'index']) }}">{{ __('hotel.all_reservations') }}</a></div>
            @if ($arrivals->isEmpty())
                <div class="ht-empty">{{ __('hotel.no_arrivals') }}</div>
            @else
                <div class="ht-scroll"><table class="ht-t" id="ht_arrivals">
                    <thead><tr><th>{{ __('hotel.guest') }}</th><th>{{ __('hotel.room') }}</th><th>{{ __('hotel.nights') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($arrivals as $a)
                        <tr>
                            <td><a class="ht-link" href="{{ action([$R, 'show'], [$a->id]) }}">{{ optional($a->contact)->name }}</a>
                                <div class="ht-muted ht-small">{{ $a->ref_no }} · {{ __('hotel.source_' . $a->source) }}@if ($a->arrival->lt($today)) · <span style="color:#b42318;">{{ __('hotel.late_since', ['date' => $a->arrival->format('d/m')]) }}</span>@endif</div></td>
                            <td>{{ optional($a->roomType)->name }} · {{ optional($a->room)->number ?: __('hotel.room_not_set') }}</td>
                            <td>{{ $a->nights() }}</td>
                            <td style="text-align:right;">@if (auth()->user()->can('hotel.front_desk'))<a class="ht-btn pri sm" href="{{ action([$R, 'checkInForm'], [$a->id]) }}">{{ __('hotel.check_in') }}</a>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>

        <div class="ht-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;"><h2 style="margin:0;">{{ __('hotel.departures_today') }}</h2>
                <a class="ht-link" href="{{ action([$R, 'index'], ['status' => 'in_house']) }}">{{ __('hotel.in_house') }}</a></div>
            @if ($departures->isEmpty())
                <div class="ht-empty">{{ __('hotel.no_departures') }}</div>
            @else
                <div class="ht-scroll"><table class="ht-t" id="ht_departures">
                    <thead><tr><th>{{ __('hotel.room') }}</th><th>{{ __('hotel.guest') }}</th><th class="num">{{ __('hotel.balance') }}</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($departures as $d)
                        @php $bal = $folio->balance($d); @endphp
                        <tr>
                            <td>{{ optional($d->room)->number }}</td>
                            <td><a class="ht-link" href="{{ action([$R, 'show'], [$d->id]) }}">{{ optional($d->contact)->name }}</a></td>
                            <td class="num" style="font-weight:600;">{{ $d->status === 'checked_out' ? __('hotel.left') : $fmt(max(0, $bal)) }}</td>
                            <td style="text-align:right;">@if ($d->status === 'checked_in' && auth()->user()->can('hotel.front_desk'))<a class="ht-btn pri sm" href="{{ action([$R, 'checkOutForm'], [$d->id]) }}">{{ __('hotel.check_out') }}</a>@else<span class="ht-pill ht-p-checked_out">{{ __('hotel.left_at', ['time' => optional($d->checked_out_at)->format('H:i')]) }}</span>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>

    <div class="ht-grid ht-g3">
        <div class="ht-card">
            <h2>{{ __('hotel.rooms_now') }}</h2>
            @php $tot = max(1, count($board)); $seg = ['occ' => ['#2e90fa', $counts->get('occ', 0) + $counts->get('dep', 0)], 'free' => ['#12b76a', $counts->get('free', 0) + $counts->get('arr', 0)], 'dirty' => ['#f79009', $counts->get('dirty', 0)], 'ooo' => ['#98a2b3', $counts->get('ooo', 0)]]; @endphp
            <div style="display:flex;height:14px;border-radius:99px;overflow:hidden;background:#eaecf0;" role="img" aria-label="{{ __('hotel.rooms_now') }}">
                @foreach ($seg as $k => [$c, $n])<div style="width: {{ $n / $tot * 100 }}%; background: {{ $c }};"></div>@endforeach
            </div>
            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:14px;font-size:13px;">
                @foreach ($seg as $k => [$c, $n])<div><span class="ht-sw" style="background: {{ $c }}"></span>{{ __('hotel.state_' . $k) }} <strong>{{ $n }}</strong></div>@endforeach
            </div>
        </div>
        <div class="ht-card">
            <h2>{{ __('hotel.revenue_today') }}</h2>
            @foreach (['rooms', 'hotel_extras', 'tourist_tax', 'other_sales'] as $k)
                @if ($k !== 'tourist_tax' || $summary['departments'][$k] > 0)
                    <div class="ht-row"><span>{{ __('hotel.dept_' . $k) }}</span><strong>{{ $fmt($summary['departments'][$k]) }}</strong></div>
                @endif
            @endforeach
            <div class="ht-row"><strong>{{ __('hotel.total') }}</strong><strong>{{ $fmt($summary['revenue_total']) }}</strong></div>
            <p class="ht-muted ht-small" style="margin:8px 0 0;">{{ __('hotel.revenue_today_help') }}</p>
        </div>
        <div class="ht-card">
            <h2>{{ __('hotel.needs_attention') }}</h2>
            @foreach ($unpaid as $u)
                <a class="ht-note amber ht-unpaid" href="{{ action([$R, 'show'], [$u['res']->id]) }}">{{ __('hotel.att_unpaid', ['room' => optional($u['res']->room)->number ?: '—', 'name' => optional($u['res']->contact)->name, 'amount' => $fmt($u['amount'])]) }}</a>
            @endforeach
            @forelse ($attention as $at)
                <a class="ht-note {{ $at['tone'] }}" href="{{ $at['url'] }}">{{ $at['text'] }}</a>
            @empty
                @if ($unpaid->isEmpty())<div class="ht-empty">{{ __('hotel.all_good') }}</div>@endif
            @endforelse
        </div>
    </div>
</section>
@endsection
