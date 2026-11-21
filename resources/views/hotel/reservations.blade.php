@extends('layouts.app')
@section('title', __('hotel.menu_reservations'))

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $u = auth()->user();
    $tabs = ['upcoming' => __('hotel.tab_upcoming'), 'in_house' => __('hotel.in_house'), 'tentative' => __('hotel.st_tentative'), 'checked_out' => __('hotel.st_checked_out'),
        'cancelled' => __('hotel.st_cancelled'), 'no_show' => __('hotel.st_no_show'), 'all' => __('hotel.all')];
@endphp
<section class="content ht" id="ht_reservations">
    <div class="ht-head">
        <div><h1>{{ __('hotel.menu_reservations') }}</h1></div>
        <div class="ht-actions">
            <form method="GET" style="display:flex;gap:8px;">
                <input type="hidden" name="status" value="{{ $status }}">
                <input class="ht-in" type="search" name="q" value="{{ $q }}" placeholder="{{ __('hotel.search_res') }}" aria-label="{{ __('hotel.search_res') }}" style="min-width:240px;">
                <button class="ht-btn">{{ __('hotel.search') }}</button>
            </form>
            @if ($u->can('hotel.reservations') || $u->can('hotel.front_desk'))<a class="ht-btn pri" href="{{ action([$R, 'create']) }}">+ {{ __('hotel.new_reservation') }}</a>@endif
        </div>
    </div>
    <nav class="ht-tabs" aria-label="{{ __('hotel.status') }}">
        @foreach ($tabs as $k => $label)<a href="{{ action([$R, 'index'], ['status' => $k, 'q' => $q ?: null]) }}" class="{{ $status === $k ? 'on' : '' }}">{{ $label }}</a>@endforeach
    </nav>
    <div class="ht-card" style="padding:6px 6px 2px;">
        @if ($list->isEmpty())
            <div class="ht-empty" style="padding:16px;">{{ __('hotel.no_reservations') }}</div>
        @else
            <div class="ht-scroll"><table class="ht-t" id="ht_res_table">
                <thead><tr><th>{{ __('hotel.ref') }}</th><th>{{ __('hotel.guest') }}</th><th>{{ __('hotel.room') }}</th><th>{{ __('hotel.stay') }}</th><th>{{ __('hotel.status') }}</th><th class="num">{{ __('hotel.balance') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($list as $r)
                    @php $bal = $folio->balance($r); @endphp
                    <tr>
                        <td><a class="ht-link" href="{{ action([$R, 'show'], [$r->id]) }}">{{ $r->ref_no }}</a><div class="ht-muted ht-small">{{ __('hotel.source_' . $r->source) }}</div></td>
                        <td><strong>{{ optional($r->contact)->name }}</strong><div class="ht-muted ht-small">{{ optional($r->contact)->mobile }}</div></td>
                        <td>{{ optional($r->roomType)->name }} · {{ optional($r->room)->number ?: '—' }}</td>
                        <td style="white-space:nowrap;">{{ $r->arrival->format('d/m/Y') }} → {{ $r->departure->format('d/m') }}<div class="ht-muted ht-small">{{ trans_choice('hotel.n_nights', $r->nights(), ['n' => $r->nights()]) }}</div></td>
                        <td><span class="ht-pill ht-p-{{ $r->status }}">{{ __('hotel.st_' . $r->status) }}</span></td>
                        <td class="num">{{ abs($bal) > 0.004 ? ($bal < 0 ? __('hotel.in_credit', ['amount' => $fmt(-$bal)]) : $fmt($bal)) : '—' }}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            @if (in_array($r->status, ['tentative', 'confirmed']) && $r->arrival->lte(\App\Utils\Hotel::today()) && $u->can('hotel.front_desk'))
                                <a class="ht-btn pri sm" href="{{ action([$R, 'checkInForm'], [$r->id]) }}">{{ __('hotel.check_in') }}</a>
                            @elseif ($r->status === 'checked_in' && $u->can('hotel.front_desk'))
                                <a class="ht-btn sm" href="{{ action([$R, 'checkOutForm'], [$r->id]) }}">{{ __('hotel.check_out') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div style="padding:8px 10px;">{{ $list->links() }}</div>
        @endif
    </div>
</section>
@endsection
