@extends('layouts.app')
@section('title', __('hotel.menu_audit'))

@section('content')
@include('hotel.partials.styles')
@php
    $A = \App\Http\Controllers\HotelAuditController::class;
    $R = \App\Http\Controllers\HotelReservationController::class;
@endphp
<section class="content ht" id="ht_audit">
    <div class="ht-head">
        <div><h1>{{ __('hotel.menu_audit') }} · {{ $next->translatedFormat('l j F') }}</h1>
            <p>{{ __('hotel.audit_lead', ['time' => $settings['audit_time']]) }}</p></div>
    </div>

    @if (! $can_run)
        <div class="ht-note green">{{ __('hotel.audit_up_to_date', ['date' => $next->copy()->subDay()->format('d/m/Y')]) }}</div>
    @elseif ($too_early)
        <div class="ht-note blue" id="ht_audit_early">{{ __('hotel.audit_too_early', ['hour' => $earliest, 'n' => $check['in_house']->count()]) }}</div>
    @elseif ($can_audit)
        <form method="POST" action="{{ action([$A, 'run']) }}" class="ht-card" style="max-width:960px;" id="ht_audit_form">
            @csrf
            <input type="hidden" name="date" value="{{ $next->toDateString() }}">
            <h2>{{ __('hotel.before_running') }}</h2>
            <div class="ht-row" style="padding:12px 0;"><span><span class="ht-pill ht-p-free">✓</span> <strong>{{ trans_choice('hotel.chk_in_house', $check['in_house']->count(), ['n' => $check['in_house']->count()]) }}</strong>
                <div class="ht-muted ht-small">{{ __('hotel.chk_to_post', ['n' => $check['to_post']->count(), 'amount' => $fmt($check['to_post_amount'])]) }}</div></span></div>

            @foreach ($check['not_arrived'] as $na)
                <div class="ht-row" style="padding:12px 0;flex-wrap:wrap;">
                    <span><span class="ht-pill ht-p-dirty">!</span> <strong>{{ __('hotel.chk_not_arrived', ['name' => optional($na->contact)->name]) }}</strong>
                        <div class="ht-muted ht-small">{{ $na->ref_no }} · {{ optional($na->roomType)->name }} · {{ __('hotel.expected_on', ['date' => $na->arrival->format('d/m')]) }}{{ (float) $na->credit > 0 ? ' · ' . __('hotel.deposit_received', ['amount' => $fmt($na->credit)]) : '' }}</div></span>
                    <select class="ht-in" name="decision[{{ $na->id }}]" style="width:auto;" aria-label="{{ __('hotel.what_to_do', ['name' => optional($na->contact)->name]) }}">
                        <option value="no_show">{{ __('hotel.dec_no_show') }}</option><option value="keep">{{ __('hotel.dec_keep') }}</option><option value="cancel">{{ __('hotel.dec_cancel') }}</option>
                    </select>
                </div>
            @endforeach
            @foreach ($check['lapsed'] as $lp)
                <div class="ht-row" style="padding:12px 0;"><span><span class="ht-pill ht-p-dirty">!</span> <strong>{{ __('hotel.chk_lapsed', ['name' => optional($lp->contact)->name]) }}</strong>
                    <div class="ht-muted ht-small">{{ $lp->ref_no }} · {{ __('hotel.will_cancel') }}</div></span></div>
            @endforeach
            @foreach ($check['not_left'] as $nl)
                <div class="ht-row" style="padding:12px 0;flex-wrap:wrap;"><span><span class="ht-pill ht-p-dirty">!</span> <strong>{{ __('hotel.chk_not_left', ['room' => optional($nl->room)->number, 'name' => optional($nl->contact)->name]) }}</strong>
                    <div class="ht-muted ht-small">{{ __('hotel.chk_not_left_help') }}</div></span>
                    <a class="ht-btn sm" href="{{ action([$R, 'checkOutForm'], [$nl->id]) }}">{{ __('hotel.check_out') }}</a></div>
            @endforeach
            @if ($check['open_registers']->isNotEmpty())
                <div class="ht-row" style="padding:12px 0;"><span><span class="ht-pill ht-p-dirty">!</span> <strong>{{ trans_choice('hotel.chk_registers', $check['open_registers']->count(), ['n' => $check['open_registers']->count()]) }}</strong>
                    <div class="ht-muted ht-small">{{ $check['open_registers']->implode(', ') }} · {{ __('hotel.chk_registers_help') }}</div></span></div>
            @endif
            <div style="display:flex;justify-content:flex-end;margin-top:12px;">
                <button class="ht-btn pri" id="ht_audit_run">{{ __('hotel.run_audit_for', ['date' => $next->format('d/m/Y')]) }}</button>
            </div>
        </form>
    @else
        <div class="ht-note amber">{{ __('hotel.audit_pending', ['date' => $next->format('d/m/Y')]) }}</div>
    @endif

    <div class="ht-card" style="padding:6px 6px 2px;">
        <h2 style="padding:8px 10px 0;">{{ __('hotel.audit_history') }}</h2>
        @if ($history->isEmpty())
            <div class="ht-empty" style="padding:10px;">{{ __('hotel.no_audit_yet') }}</div>
        @else
            <div class="ht-scroll"><table class="ht-t" id="ht_audit_history">
                <thead><tr><th>{{ __('hotel.day') }}</th><th class="num">{{ __('hotel.occupancy') }}</th><th class="num">{{ __('hotel.nights_posted') }}</th><th class="num">{{ __('hotel.room_revenue') }}</th><th class="num">{{ __('hotel.total_revenue') }}</th><th class="num">{{ __('hotel.no_shows') }}</th><th>{{ __('hotel.closed') }}</th><th></th></tr></thead>
                <tbody>@foreach ($history as $h)
                    <tr><td>{{ $h->audit_date->translatedFormat('D d/m/Y') }}</td><td class="num">{{ $h->summary['occupancy'] ?? 0 }}%</td><td class="num">{{ $h->nights_posted }}</td>
                        <td class="num">{{ $fmt($h->summary['room_revenue'] ?? $h->room_revenue) }}</td><td class="num">{{ $fmt($h->summary['revenue_total'] ?? 0) }}</td><td class="num">{{ $h->no_shows }}</td>
                        <td>{{ $h->ran_at->format('d/m H:i') }} · {{ $h->ran_by ? __('hotel.by_hand') : __('hotel.automatic') }}</td>
                        <td style="text-align:right;"><a class="ht-btn sm" href="{{ action([$A, 'show'], [$h->id]) }}">{{ __('hotel.daily_report') }}</a></td></tr>
                @endforeach</tbody>
            </table></div>
        @endif
    </div>
</section>
@endsection

@section('javascript')
<script>
(function () { var f = document.getElementById('ht_audit_form'); if (f) f.addEventListener('submit', function () { var b = document.getElementById('ht_audit_run'); setTimeout(function () { b.disabled = true; }, 0); }); })();
</script>
@endsection
