@extends('layouts.app')
@section('title', __('hotel.check_out') . ' · ' . optional($res->contact)->name)

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $s = \App\Utils\Hotel::settings($res->business_id);
    $gmode = $gmode ?? null;
    $late_due = $late_fee > 0 && ! $late_posted ? $late_fee : 0;
    // what is to pay here: the room's bill, or for the last room of a "payer settles everything" group, the whole group's
    $due0 = $gmode === 'last' ? max(0, $gdue) : max(0, $balance);
    // a room that may leave owing: nothing is proposed, what it owes stays on the group
    $propose = $gmode === 'owing' ? 0 : $due0 + $late_due;
@endphp
<section class="content ht" id="ht_checkout">
    <div class="ht-head">
        <div>
            <a class="ht-link" href="{{ action([$R, 'show'], [$res->id]) }}">‹ {{ $res->ref_no }}</a>
            <h1 style="margin-top:6px;">{{ __('hotel.check_out') }} · {{ __('hotel.room') }} {{ optional($res->room)->number }} · {{ $res->sleeperName() }}</h1>
            <p>{{ optional($res->roomType)->name }}, {{ __('hotel.plan_' . $res->rate_plan) }} · {{ $res->arrival->format('d/m') }} → {{ $res->departure->format('d/m/Y') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ action([$R, 'checkOut'], [$res->id]) }}" id="ht_checkout_form">
        @csrf
        @if ($late_fee > 0 && ! $late_posted)
            <div class="ht-note amber" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;" id="ht_late">
                <span>{{ __('hotel.late_ask', ['time' => now()->format('H:i'), 'limit' => $s['checkout_time'], 'amount' => $fmt($late_fee)]) }}</span>
                <span style="display:flex;gap:14px;">
                    <label style="font-weight:600;margin:0;"><input type="radio" name="late" value="add" checked data-fee="{{ $late_fee }}"> {{ __('hotel.add_fee') }}</label>
                    <label style="font-weight:600;margin:0;"><input type="radio" name="late" value="waive" data-fee="0"> {{ __('hotel.waive_fee') }}</label>
                </span>
            </div>
        @endif
        @if ($group)
            @php $glink = '<a class="ht-link" href="' . e(action([\App\Http\Controllers\HotelGroupController::class, 'show'], [$group->id])) . '">' . e($group->ref_no . ' · ' . optional($group->contact)->name) . '</a>'; @endphp
            @if ($gmode === 'owing')
                <div class="ht-note blue" id="ht_co_group" data-mode="owing">{!! __('hotel.grp_co_owing', ['group' => $glink]) !!}</div>
            @elseif ($gmode === 'last')
                <div class="ht-note amber" id="ht_co_group" data-mode="last">{!! __('hotel.grp_co_last', ['group' => $glink]) !!}
                    <div style="margin-top:8px;">
                    @foreach ($gsum['rows'] as $gr)
                        @continue($gr['balance'] <= 0.004 || in_array($gr['res']->status, ['cancelled', 'no_show']))
                        <div style="display:flex;justify-content:space-between;gap:12px;"><span>{{ __('hotel.room') }} {{ optional($gr['res']->room)->number ?: '—' }} · {{ $gr['res']->sleeperName() }}@if ($gr['res']->id !== $res->id) <span class="ht-muted">({{ __('hotel.st_' . $gr['res']->status) }})</span>@endif</span><strong>{{ $fmt($gr['balance']) }}</strong></div>
                    @endforeach
                    </div>
                </div>
            @else
                <div class="ht-note blue" id="ht_co_group" data-mode="each">{!! __('hotel.grp_co_each', ['group' => $glink]) !!}</div>
            @endif
        @endif
        @if ($early)
            <div class="ht-note blue">{{ __('hotel.early_note', ['date' => $res->departure->format('d/m')]) }}</div>
        @endif

        <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
            <div class="ht-card" style="flex:999 1 560px;min-width:0;padding:6px 6px 2px;">
                <h2 style="padding:8px 10px 0;">{{ __('hotel.the_bill') }}</h2>
                <div class="ht-scroll"><table class="ht-t">
                    <thead><tr><th>{{ __('hotel.date') }}</th><th>{{ __('hotel.what') }}</th><th class="num">{{ __('hotel.charges') }}</th><th class="num">{{ __('hotel.payments') }}</th></tr></thead>
                    <tbody>
                    @foreach ($statement['lines'] as $l)
                        @continue($l['voided'])
                        <tr><td style="white-space:nowrap;">{{ $l['night'] ? $l['night']->format('d/m') : $l['at']->format('d/m') }}</td><td>{{ $l['what'] }}</td>
                            <td class="num">{{ $l['charge'] ? $num($l['charge']) : '' }}</td><td class="num" style="color:#067647;">{{ $l['pay'] ? $num($l['pay']) : '' }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:24px;padding:12px 10px;">
                    <span>{{ __('hotel.charges') }} <strong>{{ $fmt($statement['charges']) }}</strong></span>
                    <span>{{ __('hotel.payments') }} <strong style="color:#067647;">{{ $fmt($statement['payments']) }}</strong></span>
                </div>
            </div>

            <aside class="ht-card" style="flex:1 1 320px;max-width:420px;min-width:280px;border-top:4px solid #1849a9;">
                <h2>{{ $gmode === 'last' ? __('hotel.grp_co_group_due') : ($gmode === 'owing' ? __('hotel.grp_co_room_owes') : __('hotel.to_pay_now')) }}</h2>
                <div style="font-size:28px;font-weight:700;margin-bottom:12px;" id="ht_co_due" data-balance="{{ $due0 }}">{{ $fmt($due0 + $late_due) }}</div>
                @if ($gmode === 'owing' && $due0 + $late_due > 0.004)<div class="ht-note blue" style="margin-top:-4px;">{{ __('hotel.grp_co_stays', ['amount' => $fmt($due0 + $late_due)]) }}</div>@endif
                @if ($balance < -0.004)<div class="ht-note green">{{ __('hotel.credit_left_note', ['amount' => $fmt(-$balance)]) }}</div>@endif
                <div class="ht-f"><label for="ht_co_amount">{{ __('hotel.amount_received') }}</label>
                    <input class="ht-in" name="amount" id="ht_co_amount" inputmode="decimal" value="{{ $propose > 0.004 ? $num($propose) : '' }}" data-owing="{{ $gmode === 'owing' ? 1 : 0 }}"></div>
                <div class="ht-f"><label for="ht_co_method">{{ __('hotel.paid_by') }}</label>
                    <select class="ht-in" name="method" id="ht_co_method">@foreach ($payment_types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                @if ($can_leave_balance)
                    <label style="display:flex;gap:8px;align-items:flex-start;font-weight:500;margin:4px 0 12px;"><input type="checkbox" name="leave_balance" value="1" style="margin-top:3px;"> {{ __('hotel.leave_balance') }}</label>
                @endif
                <button class="ht-btn pri" style="width:100%;" id="ht_co_submit">{{ __('hotel.pay_and_checkout') }}</button>
                <p class="ht-muted ht-small" style="text-align:center;margin:8px 0 0;">{{ __('hotel.checkout_after') }}</p>
            </aside>
        </div>
    </form>
</section>
@endsection

@section('javascript')
@include('hotel.partials.confirm')
<script>
(function () {
    var due = document.getElementById('ht_co_due'), amt = document.getElementById('ht_co_amount');
    var K = {!! json_encode([
        'title' => __('hotel.cf_title_checkout'), 'intro' => __('hotel.cf_intro_checkout'),
        'room' => optional($res->room)->number . ' · ' . optional($res->roomType)->name, 'guest' => optional($res->contact)->name,
        'stay_l' => __('hotel.stay'), 'stay' => $res->arrival->format('d/m/Y') . ' → ' . $res->departure->format('d/m/Y'),
        'charges_l' => __('hotel.charges'), 'charges' => (float) $statement['charges'], 'payments_l' => __('hotel.payments'), 'payments' => (float) $statement['payments'],
        'due_l' => __('hotel.to_pay_now'), 'received' => __('hotel.amount_received'), 'waived' => __('hotel.waive_fee'),
        'room_l' => __('hotel.room'), 'owing' => $gmode === 'owing' ? __('hotel.grp_cf_stays') : '', 'credit_left' => $balance < -0.004 ? __('hotel.credit_left_note', ['amount' => $fmt(-$balance)]) : '',
    ]) !!};
    var base = parseFloat(due.getAttribute('data-balance')) || 0;
    document.querySelectorAll('#ht_late input[name=late]').forEach(function (r) {
        r.addEventListener('change', function () {
            var v = base + (parseFloat(this.getAttribute('data-fee')) || 0);
            due.textContent = window.__currency_trans_from_en ? __currency_trans_from_en(v, true) : v;
            if (amt.getAttribute('data-owing') !== '1') amt.value = window.__currency_trans_from_en ? __currency_trans_from_en(v, false) : v;
        });
    });
    document.getElementById('ht_checkout_form').addEventListener('submit', function () { var b = document.getElementById('ht_co_submit'); setTimeout(function () { b.disabled = true; }, 0); });

    // before it is saved: the bill and the money, to check with the guest
    var C = HtConfirm, X = C.t;
    C.attach(document.getElementById('ht_checkout_form'), function () {
        var late = document.querySelector('#ht_late input[name=late]:checked'), fee = late ? parseFloat(late.getAttribute('data-fee')) || 0 : 0;
        var owe = base + fee, got = C.num(amt.value), leave = document.querySelector('input[name=leave_balance]');
        var short = owe - got, notes = [];
        if (short > 0.004) notes.push((K.owing ? K.owing : (leave && leave.checked ? X.short_leave : X.short)).replace(':amount', C.money(short)));
        if (short < -0.004) notes.push(X.credit.replace(':amount', C.money(-short)));
        if (K.credit_left) notes.push(K.credit_left);
        return { title: K.title, intro: K.intro, amount: got, amountLabel: K.received,
            rows: [
                [K.room_l, K.room], [X.guest, K.guest], [K.stay_l, K.stay],
                [K.charges_l, C.money(K.charges)], [K.payments_l, C.money(K.payments)],
                late ? [X.late, fee > 0 ? C.money(fee) : K.waived] : null,
                [K.due_l, C.money(owe), 'strong'],
                [K.received, got > 0 ? C.money(got) + ' · ' + C.shown(document.getElementById('ht_co_method')) : X.nothing_now],
                [X.still, C.money(Math.max(0, short)), short > 0.004 && !K.owing ? 'warn' : 'strong'],
            ],
            note: notes.join(' '),
        };
    });
})();
</script>
@endsection
