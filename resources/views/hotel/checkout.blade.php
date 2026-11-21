@extends('layouts.app')
@section('title', __('hotel.check_out') . ' · ' . optional($res->contact)->name)

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $s = \App\Utils\Hotel::settings($res->business_id);
@endphp
<section class="content ht" id="ht_checkout">
    <div class="ht-head">
        <div>
            <a class="ht-link" href="{{ action([$R, 'show'], [$res->id]) }}">‹ {{ $res->ref_no }}</a>
            <h1 style="margin-top:6px;">{{ __('hotel.check_out') }} · {{ __('hotel.room') }} {{ optional($res->room)->number }} · {{ optional($res->contact)->name }}</h1>
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
                <h2>{{ __('hotel.to_pay_now') }}</h2>
                <div style="font-size:28px;font-weight:700;margin-bottom:12px;" id="ht_co_due" data-balance="{{ max(0, $balance) }}">{{ $fmt(max(0, $balance) + ($late_fee > 0 && ! $late_posted ? $late_fee : 0)) }}</div>
                @if ($balance < -0.004)<div class="ht-note green">{{ __('hotel.credit_left_note', ['amount' => $fmt(-$balance)]) }}</div>@endif
                <div class="ht-f"><label for="ht_co_amount">{{ __('hotel.amount_received') }}</label>
                    <input class="ht-in" name="amount" id="ht_co_amount" inputmode="decimal" value="{{ $num(max(0, $balance) + ($late_fee > 0 && ! $late_posted ? $late_fee : 0)) }}"></div>
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
<script>
(function () {
    var due = document.getElementById('ht_co_due'), amt = document.getElementById('ht_co_amount');
    var base = parseFloat(due.getAttribute('data-balance')) || 0;
    document.querySelectorAll('#ht_late input[name=late]').forEach(function (r) {
        r.addEventListener('change', function () {
            var v = base + (parseFloat(this.getAttribute('data-fee')) || 0);
            due.textContent = window.__currency_trans_from_en ? __currency_trans_from_en(v, true) : v;
            amt.value = window.__currency_trans_from_en ? __currency_trans_from_en(v, false) : v;
        });
    });
    document.getElementById('ht_checkout_form').addEventListener('submit', function () { var b = document.getElementById('ht_co_submit'); setTimeout(function () { b.disabled = true; }, 0); });
})();
</script>
@endsection
