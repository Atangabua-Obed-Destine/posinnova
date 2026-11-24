@extends('layouts.app')
@section('title', $group->ref_no . ' · ' . optional($group->contact)->name)

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $G = \App\Http\Controllers\HotelGroupController::class;
    $rows = $sum['rows'];
    $first = collect($rows)->first();
    $from = collect($rows)->min(fn ($r) => $r['res']->arrival);
    $to = collect($rows)->max(fn ($r) => $r['res']->departure);
    $open = collect($rows)->filter(fn ($r) => in_array($r['res']->status, \App\HotelReservation::HOLDING, true));
    $last_dep = $open->max(fn ($r) => $r['res']->departure);
    $propose = max(0, $sum['total_left']);
@endphp
<style>
.ht-kpi3 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 16px; }
.ht-kpi3 > div { background: #fff; border: 1px solid #eaecf0; border-radius: 14px; padding: 14px 16px; }
.ht-kpi3 .v { font-size: 24px; font-weight: 700; margin-top: 2px; }
.ht-groom { display: grid; grid-template-columns: 90px minmax(0, 1fr) 150px 130px 130px; gap: 14px; align-items: center; padding: 12px 4px; border-bottom: 1px solid #f2f4f7; }
.ht-groom:last-child { border-bottom: 0; }
.ht-gno { border-radius: 10px; background: #1d2939; color: #fff; overflow: hidden; text-align: center; }
.ht-gno .n { padding: 8px 0 5px; font-size: 19px; font-weight: 700; }
.ht-gno .b { font-size: 11px; font-weight: 700; padding: 3px 0; background: #32d583; color: #053321; }
.ht-gno .b.wait { background: #a4bcfd; color: #102a56; }
.ht-gno .b.gone { background: #98a2b3; color: #101828; }
@media (max-width: 760px) { .ht-groom { grid-template-columns: 76px minmax(0, 1fr); } .ht-groom .wide { grid-column: 2; } }
</style>
<section class="content ht" id="ht_group" data-group="{{ $group->id }}">
    <div class="ht-head">
        <div>
            <a class="ht-link" href="{{ action([$R, 'index']) }}">‹ {{ __('hotel.menu_reservations') }}</a>
            <h1 style="margin-top:6px;">{{ $group->ref_no }} · {{ optional($group->contact)->name }}
                <span class="ht-pill ht-p-confirmed" style="vertical-align:4px;" id="ht_g_pays">{{ $group->pays === 'each' ? __('hotel.grp_pays_each') : __('hotel.grp_pays_payer') }}</span></h1>
            <p>{{ trans_choice('hotel.grp_rooms_count', count($rows), ['n' => count($rows)]) }}@if ($from) · {{ $from->format('d/m') }} → {{ $to->format('d/m/Y') }}@endif{{ optional($group->contact)->mobile ? ' · ' . $group->contact->mobile : '' }}</p>
        </div>
        <div class="ht-actions">
            <a class="ht-btn" href="{{ action([$G, 'printBill'], [$group->id]) }}" target="_blank" rel="noopener" id="ht_g_print">{{ __('hotel.grp_print') }}</a>
        </div>
    </div>
    @if ($group->notes)<div class="ht-note blue">{{ $group->notes }}</div>@endif

    <div class="ht-kpi3" id="ht_g_kpis">
        <div><div class="ht-muted ht-small">{{ __('hotel.grp_billed') }}</div><div class="v" id="ht_g_billed">{{ $fmt($sum['charges']) }}</div>
            @if ($sum['to_come'] > 0.004)<div class="ht-muted ht-small">{{ __('hotel.grp_to_come', ['amount' => $fmt($sum['to_come'])]) }}</div>@endif</div>
        <div><div class="ht-muted ht-small">{{ __('hotel.payments') }}</div><div class="v" style="color:#067647;" id="ht_g_paid">{{ $fmt($sum['payments']) }}</div></div>
        <div style="{{ $sum['total_left'] > 0.004 ? 'border:2px solid #fdb022;' : '' }}"><div class="ht-muted ht-small">{{ __('hotel.grp_still_to_pay') }}</div>
            <div class="v" style="color:{{ $sum['total_left'] > 0.004 ? '#b54708' : '#067647' }};" id="ht_g_left" data-left="{{ $sum['total_left'] }}">{{ $fmt(max(0, $sum['total_left'])) }}</div>
            @if ($sum['total_left'] < -0.004)<div class="ht-muted ht-small">{{ __('hotel.in_credit', ['amount' => $fmt(-$sum['total_left'])]) }}</div>
            @elseif ($group->pays === 'payer' && $last_dep && $sum['total_left'] > 0.004)<div class="ht-muted ht-small">{{ __('hotel.grp_settle_by', ['date' => $last_dep->format('d/m')]) }}</div>@endif</div>
    </div>

    <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
        <div class="ht-card" style="flex:999 1 560px;min-width:0;">
            <h2>{{ __('hotel.grp_the_rooms') }}</h2>
            <div id="ht_g_rooms">
            @foreach ($rows as $row)
                @php
                    $r = $row['res']; $st = $r->status;
                    $bar = $st === 'checked_in' ? ['', __('hotel.in_house')] : (in_array($st, ['tentative', 'confirmed']) ? ['wait', __('hotel.grp_expected')] : ['gone', __('hotel.st_' . $st)]);
                @endphp
                <div class="ht-groom" data-res="{{ $r->id }}" data-status="{{ $st }}">
                    <div class="ht-gno"><div class="n">{{ optional($r->room)->number ?: '—' }}</div><div class="b {{ $bar[0] }}">{{ $bar[1] }}</div></div>
                    <div style="min-width:0;">
                        <strong>{{ $r->sleeperName() }}</strong>
                        <div class="ht-muted ht-small">{{ optional($r->roomType)->name }} · {{ $r->arrival->format('d/m') }} → {{ $r->departure->format('d/m') }} · {{ trans_choice('hotel.n_nights', $r->nights(), ['n' => $r->nights()]) }} · {{ $r->ref_no }}</div>
                        <a class="ht-link ht-small" href="{{ action([$R, 'show'], [$r->id]) }}">{{ __('hotel.grp_open_stay') }}</a>
                    </div>
                    <div class="wide">
                        @if (in_array($st, ['tentative', 'confirmed']) && $r->arrival->lte($today) && $can['desk'])
                            <a class="ht-btn pri sm" href="{{ action([$R, 'checkInForm'], [$r->id]) }}">{{ __('hotel.check_in') }}</a>
                        @elseif ($st === 'checked_in' && $can['desk'])
                            <a class="ht-btn sm" href="{{ action([$R, 'checkOutForm'], [$r->id]) }}">{{ __('hotel.check_out') }}</a>
                        @elseif ($st === 'checked_out')
                            <span class="ht-muted ht-small">{{ __('hotel.grp_left_on', ['date' => optional($r->checked_out_at)->format('d/m H:i')]) }}</span>
                        @endif
                    </div>
                    <div class="wide" style="text-align:right;"><div class="ht-muted ht-small">{{ __('hotel.grp_bill') }}</div><strong>{{ $fmt($row['charges']) }}</strong>
                        @if ($row['to_come'] > 0.004)<div class="ht-muted ht-small">+ {{ $num($row['to_come']) }}</div>@endif</div>
                    <div class="wide" style="text-align:right;"><div class="ht-muted ht-small">{{ __('hotel.grp_owes') }}</div>
                        <strong class="owes" style="color:{{ $row['balance'] > 0.004 ? '#b54708' : '#067647' }};">{{ $row['balance'] < -0.004 ? __('hotel.in_credit', ['amount' => $num(-$row['balance'])]) : $fmt(max(0, $row['balance'])) }}</strong>
                        @if ($st === 'checked_out' && $row['balance'] > 0.004)<div class="ht-muted ht-small">{{ __('hotel.grp_on_group') }}</div>@endif</div>
                </div>
            @endforeach
            </div>

            @if ($can['res'] || $can['desk'])
                <details class="ht-more" style="margin-top:12px;border-top:1px solid #eaecf0;padding-top:12px;" id="ht_g_add" @if (old('room_type_id')) open @endif>
                    <summary class="ht-btn" style="display:inline-flex;">+ {{ __('hotel.grp_add_room') }}</summary>
                    <form method="POST" action="{{ action([$G, 'addRoom'], [$group->id]) }}" style="margin-top:12px;">
                        @csrf
                        <div class="ht-fields">
                            <div class="ht-f"><label for="ht_ga_type">{{ __('hotel.room_type') }}</label>
                                <select class="ht-in" name="room_type_id" id="ht_ga_type">@foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                            <div class="ht-f"><label for="ht_ga_arr">{{ __('hotel.arrival') }}</label><input class="ht-in" type="date" name="arrival" id="ht_ga_arr" value="{{ old('arrival', $today->copy()->max($from ?: $today)->toDateString()) }}" required></div>
                            <div class="ht-f"><label for="ht_ga_dep">{{ __('hotel.departure') }}</label><input class="ht-in" type="date" name="departure" id="ht_ga_dep" value="{{ old('departure', ($last_dep ?: $today->copy()->addDay())->toDateString()) }}" required></div>
                            <div class="ht-f"><label for="ht_ga_ad">{{ __('hotel.adults') }}</label><input class="ht-in" type="number" min="1" max="20" name="adults" id="ht_ga_ad" value="{{ old('adults', 1) }}"></div>
                            <div class="ht-f"><label for="ht_ga_occ">{{ __('hotel.grp_occupant') }}</label><input class="ht-in" type="text" name="occupant" id="ht_ga_occ" maxlength="150" value="{{ old('occupant') }}"></div>
                        </div>
                        <button class="ht-btn pri">{{ __('hotel.grp_add_room') }}</button>
                        <span class="ht-muted ht-small" style="margin-left:8px;">{{ __('hotel.grp_add_room_h') }}</span>
                    </form>
                </details>
            @endif
        </div>

        @if ($can['desk'] || $can['res'])
        <aside class="ht-card" style="flex:1 1 300px;max-width:400px;min-width:280px;" aria-label="{{ __('hotel.take_payment') }}">
            <h2>{{ __('hotel.grp_take_payment') }}</h2>
            <form method="POST" action="{{ action([$G, 'payment'], [$group->id]) }}" id="ht_g_pay_form">
                @csrf
                <div class="ht-f"><label for="ht_g_amount">{{ __('hotel.amount_received') }}</label>
                    <input class="ht-in" name="amount" id="ht_g_amount" inputmode="decimal" value="{{ $propose > 0.004 ? $num($propose) : '' }}" required></div>
                <div class="ht-f"><label for="ht_g_method">{{ __('hotel.paid_by') }}</label>
                    <select class="ht-in" name="method" id="ht_g_method">@foreach ($payment_types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="ht-f"><label for="ht_g_note">{{ __('hotel.note') }}</label><input class="ht-in" name="note" id="ht_g_note" maxlength="180"></div>
                <p class="ht-muted ht-small" style="margin:0 0 10px;">{{ __('hotel.grp_pay_rule') }}</p>
                <button class="ht-btn pri" style="width:100%;" id="ht_g_pay">{{ __('hotel.grp_take') }}</button>
                <p class="ht-muted ht-small" style="margin:8px 0 0;">{{ __('hotel.grp_one_room_pay') }}</p>
            </form>
        </aside>
        @endif
    </div>
</section>
@endsection

@section('javascript')
@include('hotel.partials.confirm')
<script>
(function () {
    var f = document.getElementById('ht_g_pay_form'); if (!f || !window.HtConfirm) return;
    var C = HtConfirm, X = C.t, left = parseFloat(document.getElementById('ht_g_left').getAttribute('data-left')) || 0;
    var K = {!! json_encode(['title' => __('hotel.grp_cf_pay_title'), 'intro' => __('hotel.grp_cf_pay_intro'), 'group' => $group->ref_no . ' · ' . optional($group->contact)->name,
        'group_l' => __('hotel.grp_group'), 'left_l' => __('hotel.grp_still_to_pay'), 'got' => __('hotel.amount_received'), 'rule' => __('hotel.grp_pay_rule')]) !!};
    C.attach(f, function () {
        var got = C.num(document.getElementById('ht_g_amount').value);
        return { title: K.title, intro: K.intro, amount: got, amountLabel: K.got,
            rows: [[K.group_l, K.group], [K.left_l, C.money(Math.max(0, left))], [K.got, C.money(got) + ' · ' + C.shown(document.getElementById('ht_g_method')), 'strong'],
                [X.still, C.money(Math.max(0, left - got)), 'strong']],
            note: (got > left + 0.004 ? X.credit.replace(':amount', C.money(got - Math.max(0, left))) + ' ' : '') + K.rule };
    });
})();
</script>
@endsection
