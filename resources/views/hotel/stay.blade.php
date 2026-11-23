@extends('layouts.app')
@section('title', $res->ref_no . ' · ' . optional($res->contact)->name)

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $open = in_array($res->status, ['tentative', 'confirmed']);
    $in = $res->status === 'checked_in';
    $today = \App\Utils\Hotel::today();
    $posted_nights = collect($statement['lines'])->where('kind', 'night')->where('voided', false)->count();
@endphp
<style>
.ht-line-void td { color: #98a2b3; text-decoration: line-through; }
.ht-line-void td.keep { text-decoration: none; }
.ht-kind { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: #f2f4f7; color: #475467; }
details.ht-panel-d { background: #fff; border: 1px solid #a4bcfd; border-radius: 12px; padding: 14px 16px; margin-bottom: 14px; }
details.ht-panel-d > summary { font-weight: 700; }
.ht-mini { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; margin-top: 8px; }
.ht-mini .ht-in { width: auto; min-width: 120px; }
</style>
<section class="content ht" id="ht_stay" data-status="{{ $res->status }}">
    <div class="ht-head">
        <div>
            <a class="ht-link" href="{{ action([$R, 'index'], ['status' => $in ? 'in_house' : 'upcoming']) }}">‹ {{ __('hotel.menu_reservations') }}</a>
            <h1 style="margin-top:6px;">{{ $res->room ? __('hotel.room') . ' ' . $res->room->number . ' · ' : '' }}{{ optional($res->contact)->name }}
                <span class="ht-pill ht-p-{{ $res->status }}" style="vertical-align:4px;" id="ht_status_pill">{{ __('hotel.st_' . $res->status) }}</span></h1>
            <p>{{ $res->ref_no }} · {{ optional($res->roomType)->name }}, {{ __('hotel.plan_' . $res->rate_plan) }} · {{ $res->arrival->format('d/m/Y') }} → {{ $res->departure->format('d/m/Y') }},
                {{ trans_choice('hotel.n_nights', $res->nights(), ['n' => $res->nights()]) }} · {{ trans_choice('hotel.n_adults', $res->adults, ['n' => $res->adults]) }}@if ($res->children), {{ trans_choice('hotel.n_children', $res->children, ['n' => $res->children]) }}@endif</p>
        </div>
        <div class="ht-actions">
            @if ($open && $can['desk'] && $res->arrival->lte($today))<a class="ht-btn pri" href="{{ action([$R, 'checkInForm'], [$res->id]) }}" id="ht_btn_checkin">{{ __('hotel.check_in') }}</a>@endif
            @if ($res->status === 'tentative' && ($can['res'] || $can['desk']))
                <form method="POST" action="{{ action([$R, 'confirm'], [$res->id]) }}">@csrf<button class="ht-btn">{{ __('hotel.confirm') }}</button></form>
            @endif
            @if ($in && $can['desk'])<a class="ht-btn pri" href="{{ action([$R, 'checkOutForm'], [$res->id]) }}" id="ht_btn_checkout">{{ __('hotel.check_out') }}</a>@endif
            <a class="ht-btn" href="{{ action([$R, 'printFolio'], [$res->id]) }}" target="_blank" rel="noopener" id="ht_btn_print">{{ __('hotel.print_bill') }}</a>
        </div>
    </div>

    @if ($res->notes)<div class="ht-note blue">{{ $res->notes }}</div>@endif
    @if ($cover)
        @if ($cover['to_collect'] > 0.004)
            <div class="ht-note amber" id="ht_cover" data-collect="{{ $cover['to_collect'] }}" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;">
                <span>{!! __('hotel.cover_due', ['amount' => '<strong>' . e($fmt($cover['to_collect'])) . '</strong>']) !!}</span>
                @if ($can['desk'] || $can['res'])<button type="button" class="ht-btn sm" id="ht_cover_pay">{{ __('hotel.take_payment') }}</button>@endif
            </div>
        @else
            <div class="ht-note green" id="ht_cover" data-collect="0" data-until="{{ $cover['paid_until'] }}">{{ $cover['whole_stay'] ? __('hotel.cover_whole', ['date' => $res->departure->format('d/m')]) : __('hotel.cover_until', ['date' => \Carbon\Carbon::parse($cover['paid_until'])->format('d/m'), 'next' => \Carbon\Carbon::parse($cover['paid_until'])->addDay()->format('d/m')]) }}</div>
        @endif
    @endif
    @if (in_array($res->status, ['cancelled', 'no_show']))
        <div class="ht-note red">{{ __('hotel.st_' . $res->status) }} · {{ optional($res->cancelled_at)->format('d/m/Y H:i') }}{{ $res->cancel_reason ? ' · ' . $res->cancel_reason : '' }}</div>
    @endif

    <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
        <div style="flex:999 1 560px;min-width:0;">
            {{-- actions on the bill --}}
            @if ($in && $can['desk'])
                <details class="ht-panel-d" id="ht_d_charge" @if (old('item')) open @endif>
                    <summary>+ {{ __('hotel.post_charge') }}</summary>
                    <form method="POST" action="{{ action([$R, 'charge'], [$res->id]) }}" class="ht-mini" id="ht_charge_form">@csrf
                        <div class="ht-f" style="margin:0;"><label for="ht_item">{{ __('hotel.what') }}</label>
                            <select class="ht-in" name="item" id="ht_item">@foreach (\App\Utils\Hotel::EXTRAS as $x)<option value="{{ $x }}">{{ __('hotel.item_' . $x) }}</option>@endforeach</select></div>
                        <div class="ht-f" style="margin:0;"><label for="ht_c_amount">{{ __('hotel.amount') }}</label><input class="ht-in" name="amount" id="ht_c_amount" inputmode="decimal" required></div>
                        <div class="ht-f" style="margin:0;flex:1;"><label for="ht_c_note">{{ __('hotel.note') }}</label><input class="ht-in" name="note" id="ht_c_note" maxlength="150" style="width:100%;"></div>
                        <button class="ht-btn pri">{{ __('hotel.add_to_bill') }}</button>
                    </form>
                </details>
            @endif
            @if (($in && $can['desk']) || ($open && ($can['res'] || $can['desk'])))
                <details class="ht-panel-d" id="ht_d_pay">
                    <summary>{{ $open ? __('hotel.take_deposit') : __('hotel.take_payment') }}</summary>
                    <form method="POST" action="{{ action([$R, 'payment'], [$res->id]) }}" class="ht-mini" id="ht_pay_form">@csrf
                        <div class="ht-f" style="margin:0;"><label for="ht_p_amount">{{ __('hotel.amount') }}</label><input class="ht-in" name="amount" id="ht_p_amount" inputmode="decimal" value="{{ $cover && $cover['to_collect'] > 0.004 ? $num($cover['to_collect']) : ($balance > 0 ? $num($balance) : '') }}" required></div>
                        <div class="ht-f" style="margin:0;"><label for="ht_p_method">{{ __('hotel.paid_by') }}</label>
                            <select class="ht-in" name="method" id="ht_p_method">@foreach ($payment_types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                        <div class="ht-f" style="margin:0;flex:1;"><label for="ht_p_note">{{ __('hotel.note') }}</label><input class="ht-in" name="note" id="ht_p_note" maxlength="150" style="width:100%;"></div>
                        <button class="ht-btn pri">{{ __('hotel.record_payment') }}</button>
                    </form>
                </details>
            @endif

            <div class="ht-card" style="padding:6px 6px 2px;">
                <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 10px;"><h2 style="margin:0;">{{ __('hotel.the_bill') }}</h2>
                    <span class="ht-muted ht-small">{{ __('hotel.nights_on_bill', ['n' => $posted_nights, 'm' => $res->nights()]) }}</span></div>
                @if (empty($statement['lines']))
                    <div class="ht-empty" style="padding:10px;">{{ __('hotel.bill_empty') }}</div>
                @else
                    <div class="ht-scroll"><table class="ht-t" id="ht_bill">
                        <thead><tr><th>{{ __('hotel.date') }}</th><th>{{ __('hotel.what') }}</th><th class="num">{{ __('hotel.charges') }}</th><th class="num">{{ __('hotel.payments') }}</th><th class="num">{{ __('hotel.balance') }}</th>@if ($can['manager'] && $in)<th></th>@endif</tr></thead>
                        <tbody>
                        @foreach ($statement['lines'] as $l)
                            <tr class="{{ $l['voided'] ? 'ht-line-void' : '' }}" data-kind="{{ $l['kind'] }}">
                                <td style="white-space:nowrap;">{{ $l['night'] ? $l['night']->format('d/m') : $l['at']->format('d/m') }}<div class="ht-muted ht-small">{{ $l['night'] ? __('hotel.night') : $l['at']->format('H:i') }}</div></td>
                                <td><strong>{{ $l['what'] }}</strong>
                                    <div class="ht-muted ht-small">
                                        @if (! empty($l['invoice_no'])){{ __('hotel.invoice') }} {{ $l['invoice_no'] }}@endif
                                        @if (! empty($l['payment_ref'])){{ $l['payment_ref'] }}@endif
                                        @if (! empty($l['discount']) && $l['discount'] > 0) · <span style="color:#067647;">{{ __('hotel.discounted', ['amount' => $fmt($l['discount'])]) }}</span>@endif
                                        @if ($l['voided']) <span class="keep">· {{ __('hotel.voided') }}: {{ $l['void_reason'] }}</span>@endif
                                    </div></td>
                                <td class="num">{{ $l['charge'] ? $num($l['charge']) : ($l['voided'] ? $num($l['original']) : '') }}</td>
                                <td class="num" style="color:#067647;">{{ $l['pay'] ? $num($l['pay']) : '' }}</td>
                                <td class="num keep" style="font-weight:600;">{{ $num($l['balance']) }}</td>
                                @if ($can['manager'] && $in)
                                    <td class="keep" style="text-align:right;">
                                        @if (! $l['voided'] && in_array($l['kind'], ['night', 'charge', 'late_checkout']) && $l['transaction_id'])
                                            <details class="ht-more"><summary class="ht-btn sm" aria-label="{{ __('hotel.correct') }}">{{ __('hotel.correct') }}</summary>
                                                <div style="text-align:left;padding:10px;border:1px solid #eaecf0;border-radius:10px;margin-top:6px;background:#fcfcfd;min-width:260px;">
                                                    <form method="POST" action="{{ action([$R, 'discount'], [$res->id]) }}">@csrf<input type="hidden" name="line_id" value="{{ $l['id'] }}">
                                                        <div class="ht-f"><label for="ht_dis_a{{ $l['id'] }}">{{ __('hotel.discount_amount') }}</label><input class="ht-in" name="amount" id="ht_dis_a{{ $l['id'] }}" inputmode="decimal" required></div>
                                                        <div class="ht-f"><label for="ht_dis_r{{ $l['id'] }}">{{ __('hotel.reason') }}</label><input class="ht-in" name="reason" id="ht_dis_r{{ $l['id'] }}" maxlength="150" required></div>
                                                        <button class="ht-btn sm">{{ __('hotel.give_discount') }}</button></form>
                                                    @if ($l['paid'] <= 0.004)
                                                        <form method="POST" action="{{ action([$R, 'void'], [$res->id]) }}" style="margin-top:10px;border-top:1px solid #eaecf0;padding-top:10px;">@csrf<input type="hidden" name="line_id" value="{{ $l['id'] }}">
                                                            <div class="ht-f"><label for="ht_void_r{{ $l['id'] }}">{{ __('hotel.void_reason') }}</label><input class="ht-in" name="reason" id="ht_void_r{{ $l['id'] }}" maxlength="150" required></div>
                                                            <button class="ht-btn sm danger">{{ __('hotel.void_charge') }}</button></form>
                                                    @endif
                                                </div></details>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
                <div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:24px;padding:12px 10px;font-size:15px;" id="ht_totals">
                    <span>{{ __('hotel.charges') }} <strong>{{ $fmt($statement['charges']) }}</strong></span>
                    <span>{{ __('hotel.payments') }} <strong style="color:#067647;">{{ $fmt($statement['payments']) }}</strong></span>
                    @if ($balance < -0.004)
                        <span>{{ __('hotel.credit') }} <strong style="font-size:18px;color:#067647;" id="ht_balance" data-value="{{ $balance }}">{{ $fmt(-$balance) }}</strong></span>
                    @else
                        <span>{{ __('hotel.balance_due') }} <strong style="font-size:18px;" id="ht_balance" data-value="{{ $balance }}">{{ $fmt(max(0, $balance)) }}</strong></span>
                    @endif
                </div>
            </div>
        </div>

        <aside style="flex:1 1 280px;max-width:380px;min-width:260px;">
            <div class="ht-card">
                <h2>{{ __('hotel.guest') }}</h2>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.name') }}</span><strong>{{ optional($res->contact)->name }}</strong></div>
                @if (optional($res->contact)->mobile)<div class="ht-row"><span class="ht-muted">{{ __('hotel.phone') }}</span><strong>{{ $res->contact->mobile }}</strong></div>@endif
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.booked_through') }}</span><strong>{{ __('hotel.source_' . $res->source) }}</strong></div>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.rate') }}</span><strong>{{ $fmt($res->rate) }}</strong></div>
                @if ($res->checked_in_at)<div class="ht-row"><span class="ht-muted">{{ __('hotel.checked_in_at') }}</span><strong>{{ $res->checked_in_at->format('d/m H:i') }}</strong></div>@endif
                @if ($res->checked_out_at)<div class="ht-row"><span class="ht-muted">{{ __('hotel.checked_out_at') }}</span><strong>{{ $res->checked_out_at->format('d/m H:i') }}</strong></div>@endif
                @if ($res->keys_given !== null)<div class="ht-row"><span class="ht-muted">{{ __('hotel.keys') }}</span><strong>{{ $res->keys_given }}</strong></div>@endif
                @if ($res->hold_until)<div class="ht-row"><span class="ht-muted">{{ __('hotel.hold_until') }}</span><strong>{{ $res->hold_until->format('d/m/Y') }}</strong></div>@endif
            </div>

            @if ($guests->isNotEmpty())
                <div class="ht-card">
                    <h2>{{ __('hotel.register_cards') }}</h2>
                    @foreach ($guests as $g)
                        <div class="ht-row"><span><strong>{{ $g->fullName() }}</strong><div class="ht-muted ht-small">{{ $g->nationality }}{{ $g->id_number ? ' · ' . __('hotel.idt_' . ($g->id_type ?: 'other')) . ' ' . $g->id_number : '' }}</div></span></div>
                    @endforeach
                </div>
            @endif

            @if ($in && $can['desk'])
                <details class="ht-card" id="ht_d_move" style="display:block;">
                    <summary style="cursor:pointer;font-weight:700;">{{ __('hotel.move_room') }}</summary>
                    <form method="POST" action="{{ action([$R, 'move'], [$res->id]) }}" style="margin-top:10px;" id="ht_move_form">@csrf
                        <div class="ht-f"><label for="ht_m_room">{{ __('hotel.to_room') }}</label>
                            <select class="ht-in" name="room_id" id="ht_m_room" required><option value="">—</option>
                                @foreach ($move_rooms as $mr)<option value="{{ $mr->id }}">{{ $mr->number }} · {{ optional($mr->type)->name }}{{ \App\Utils\Hotel::sellable($mr) ? '' : ' · ' . __('hotel.hk_dirty') }}</option>@endforeach</select></div>
                        @if ($can['manager'])<div class="ht-f"><label for="ht_m_rate">{{ __('hotel.new_rate_optional') }}</label><input class="ht-in" name="rate" id="ht_m_rate" inputmode="decimal"></div>@endif
                        <div class="ht-f"><label for="ht_m_reason">{{ __('hotel.reason') }}</label><input class="ht-in" name="reason" id="ht_m_reason" maxlength="150"></div>
                        <button class="ht-btn pri" style="width:100%;">{{ __('hotel.move') }}</button>
                    </form>
                </details>
            @endif

            @if (($open && ($can['res'] || $can['desk'])) || ($in && $can['desk']))
                <details class="ht-card" id="ht_d_edit" style="display:block;">
                    <summary style="cursor:pointer;font-weight:700;">{{ $in ? __('hotel.extend_shorten') : __('hotel.change_booking') }}</summary>
                    <form method="POST" action="{{ action([$R, 'update'], [$res->id]) }}" style="margin-top:10px;" id="ht_edit_form">@csrf
                        @if (! $in)<div class="ht-f"><label for="ht_e_arr">{{ __('hotel.arrival') }}</label><input class="ht-in" type="date" name="arrival" id="ht_e_arr" value="{{ $res->arrival->toDateString() }}"></div>@endif
                        @if ($in)
                            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;" id="ht_e_quick">
                                @foreach ([1, 2, 3] as $n)<button type="button" class="ht-chip" data-add="{{ $n }}">{{ trans_choice('hotel.plus_nights', $n, ['n' => $n]) }}</button>@endforeach
                            </div>
                        @endif
                        <div class="ht-f"><label for="ht_e_dep">{{ $in ? __('hotel.new_departure') : __('hotel.departure') }}</label><input class="ht-in" type="date" name="departure" id="ht_e_dep" value="{{ $res->departure->toDateString() }}"
                            @if ($in) min="{{ $today->toDateString() }}" data-current="{{ $res->departure->toDateString() }}" data-from="{{ $res->departure->max($today)->toDateString() }}" data-cost="{{ $night_cost }}" data-collect="{{ $cover ? $cover['to_collect'] : 0 }}" data-counted="{{ $cover ? $cover['counted_until'] : $res->departure->copy()->subDay()->toDateString() }}" @endif></div>
                        @if ($in)
                            <div class="ht-note blue" id="ht_e_calc" hidden data-more1="{{ __('hotel.ext_more_1') }}" data-more="{{ __('hotel.ext_more_n') }}" data-less1="{{ __('hotel.ext_less_1') }}" data-less="{{ __('hotel.ext_less_n') }}"></div>
                            <div id="ht_e_money" hidden>
                                <div class="ht-f"><label for="ht_e_amount">{{ __('hotel.ext_pay_now') }}</label><input class="ht-in" name="amount" id="ht_e_amount" inputmode="decimal"><span class="ht-muted ht-small">{{ __('hotel.ext_pay_hint') }}</span></div>
                                <div class="ht-f"><label for="ht_e_method">{{ __('hotel.paid_by') }}</label><select class="ht-in" name="method" id="ht_e_method">@foreach ($payment_types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                            </div>
                        @endif
                        @if (! $in)
                            <div class="ht-f"><label for="ht_e_type">{{ __('hotel.room_type') }}</label><select class="ht-in" name="room_type_id" id="ht_e_type">@foreach ($types as $t)<option value="{{ $t->id }}" @selected($t->id == $res->room_type_id)>{{ $t->name }}</option>@endforeach</select></div>
                            <div class="ht-f"><label for="ht_e_room">{{ __('hotel.room') }}</label><select class="ht-in" name="room_id" id="ht_e_room"><option value="">{{ __('hotel.any_free_room') }}</option>
                                @if ($res->room)<option value="{{ $res->room_id }}" selected>{{ $res->room->number }}</option>@endif
                                @foreach ($free_rooms as $fr)@if ($fr->id != $res->room_id)<option value="{{ $fr->id }}">{{ $fr->number }}</option>@endif @endforeach</select></div>
                            <div class="ht-f"><label for="ht_e_plan">{{ __('hotel.rate_plan') }}</label><select class="ht-in" name="rate_plan" id="ht_e_plan">@foreach (optional($res->roomType)->plans() ?? [] as $p)<option value="{{ $p }}" @selected($p === $res->rate_plan)>{{ __('hotel.plan_' . $p) }}</option>@endforeach</select></div>
                            <div class="ht-fields"><div class="ht-f"><label for="ht_e_ad">{{ __('hotel.adults') }}</label><input class="ht-in" type="number" min="1" name="adults" id="ht_e_ad" value="{{ $res->adults }}"></div>
                                <div class="ht-f"><label for="ht_e_ch">{{ __('hotel.children') }}</label><input class="ht-in" type="number" min="0" name="children" id="ht_e_ch" value="{{ $res->children }}"></div></div>
                        @endif
                        @if ($can['manager'])<div class="ht-f"><label for="ht_e_rate">{{ __('hotel.rate') }}</label><input class="ht-in" name="rate" id="ht_e_rate" inputmode="decimal" value="{{ $num($res->rate) }}"></div>@endif
                        <div class="ht-f"><label for="ht_e_notes">{{ __('hotel.notes') }}</label><input class="ht-in" name="notes" id="ht_e_notes" value="{{ $res->notes }}" maxlength="500"></div>
                        <button class="ht-btn pri" style="width:100%;">{{ __('hotel.save') }}</button>
                    </form>
                </details>
            @endif

            @if ($open && ($can['res'] || $can['desk']))
                <details class="ht-card" id="ht_d_cancel" style="display:block;">
                    <summary style="cursor:pointer;font-weight:700;color:#b42318;">{{ __('hotel.cancel_booking') }}</summary>
                    <form method="POST" action="{{ action([$R, 'cancel'], [$res->id]) }}" style="margin-top:10px;" id="ht_cancel_form">@csrf
                        <div class="ht-f"><label for="ht_x_reason">{{ __('hotel.reason') }}</label><input class="ht-in" name="reason" id="ht_x_reason" maxlength="150" required></div>
                        <label style="display:flex;gap:8px;align-items:center;font-weight:500;margin-bottom:10px;"><input type="checkbox" name="as" value="no_show"> {{ __('hotel.mark_no_show') }}</label>
                        <button class="ht-btn danger" style="width:100%;">{{ __('hotel.cancel_booking') }}</button>
                        @if ((float) $res->credit > 0)<p class="ht-muted ht-small" style="margin:8px 0 0;">{{ __('hotel.cancel_credit_help', ['amount' => $fmt($res->credit)]) }}</p>@endif
                    </form>
                </details>
            @endif

            @if ($moves->isNotEmpty())
                <div class="ht-card"><h2>{{ __('hotel.room_moves') }}</h2>
                    @foreach ($moves as $m)<div class="ht-row"><span>{{ $m->from_no ?: '—' }} → {{ $m->to_no }}<div class="ht-muted ht-small">{{ \Carbon\Carbon::parse($m->created_at)->format('d/m H:i') }}{{ $m->reason ? ' · ' . $m->reason : '' }}</div></span></div>@endforeach
                </div>
            @endif
        </aside>
    </div>
</section>
@endsection

@section('javascript')
@include('hotel.partials.confirm')
<script>
// before it is saved: what the form will do, to check with the guest
(function () {
    var C = HtConfirm, X = C.t, $ = function (id) { return document.getElementById(id); };
    var K = {!! json_encode([
        'guest' => optional($res->contact)->name . ' · ' . $res->ref_no . ($res->room ? ' · ' . __('hotel.room') . ' ' . $res->room->number : ''),
        'payment' => __('hotel.cf_title_payment'), 'charge' => __('hotel.cf_title_charge'), 'move' => __('hotel.cf_title_move'),
        'dates' => __('hotel.cf_title_dates'), 'cancel' => __('hotel.cf_title_cancel'), 'amount' => __('hotel.amount'),
        'cancel_credit' => (float) $res->credit > 0 ? __('hotel.cancel_credit_help', ['amount' => $fmt($res->credit)]) : '',
    ]) !!};
    var simple = function (title, amountField, extra) {
        return function (form) {
            var rows = [[X.guest, K.guest]].concat(C.auto(form));
            var a = amountField ? C.num(amountField.value) : null;
            var d = { title: title, rows: rows, note: '' };
            if (a !== null) { d.amount = a; d.amountLabel = K.amount; }
            if (extra) extra(d, form);
            return d;
        };
    };
    C.attach($('ht_pay_form'), simple(K.payment, $('ht_p_amount')));
    C.attach($('ht_charge_form'), simple(K.charge, $('ht_c_amount')));
    C.attach($('ht_move_form'), simple(K.move));
    C.attach($('ht_edit_form'), simple(K.dates, null, function (d) {
        var calc = $('ht_e_calc'), amt = $('ht_e_amount');
        if (calc && !calc.hidden && calc.textContent.trim()) d.rows.splice(1, 0, [X.change, calc.textContent.trim(), 'strong']);
        if (amt && !$('ht_e_money').hidden) { d.amount = C.num(amt.value); d.amountLabel = X.paid_now; }
    }));
    C.attach($('ht_cancel_form'), simple(K.cancel, null, function (d) { d.danger = true; d.note = K.cancel_credit; }));
})();
</script>
<script>
(function () {
    var cover = document.getElementById('ht_cover_pay'), pay = document.getElementById('ht_d_pay');
    if (cover && pay) {
        cover.addEventListener('click', function () { pay.open = true; pay.scrollIntoView({ block: 'center' }); var a = document.getElementById('ht_p_amount'); if (a) a.focus(); });
    }
    var dep = document.getElementById('ht_e_dep'), calc = document.getElementById('ht_e_calc'), money = document.getElementById('ht_e_money'), amount = document.getElementById('ht_e_amount');
    if (!dep || !calc || !dep.dataset.current) return;
    var cost = parseFloat(dep.dataset.cost) || 0, owed = parseFloat(dep.dataset.collect) || 0, touched = false;
    var fmt = function (n, sym) { return window.__currency_trans_from_en ? __currency_trans_from_en(n, sym) : String(Math.round(n)); };
    var day = function (s) { var p = s.split('-'); return Date.UTC(+p[0], +p[1] - 1, +p[2]) / 86400000; };
    var iso = function (d) { return new Date(d * 86400000).toISOString().slice(0, 10); };
    var show = function () {
        var n = dep.value ? day(dep.value) - day(dep.dataset.current) : 0;
        calc.hidden = n === 0;
        money.hidden = n <= 0;
        if (n > 0) {
            var total = n * cost;
            calc.textContent = (n === 1 ? calc.dataset.more1 : calc.dataset.more).replace(':n', n).replace(':cost', fmt(cost, false)).replace(':amount', fmt(total, true));
            // proposed: everything the guest owes up to the new departure (what is missing today, and the nights not paid yet)
            var unpaid = Math.max(0, day(dep.value) - 1 - day(dep.dataset.counted));
            if (!touched) amount.value = fmt(owed + unpaid * cost, false);
        } else {
            if (n < 0) calc.textContent = (n === -1 ? calc.dataset.less1 : calc.dataset.less).replace(':n', -n);
            if (!touched) amount.value = '';
        }
    };
    amount.addEventListener('input', function () { touched = true; });
    dep.addEventListener('change', show);
    dep.addEventListener('input', show);
    [].forEach.call(document.querySelectorAll('#ht_e_quick [data-add]'), function (b) {
        b.addEventListener('click', function () {
            dep.value = iso(day(dep.dataset.from) + parseInt(b.dataset.add, 10));
            [].forEach.call(document.querySelectorAll('#ht_e_quick .ht-chip'), function (x) { x.classList.toggle('on', x === b); });
            show();
        });
    });
})();
</script>
@if (session('hotel_print'))
<script>window.open(@json(action([$R, 'printFolio'], [$res->id])), '_blank');</script>
@endif
@endsection
