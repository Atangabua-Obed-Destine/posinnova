@extends('layouts.app')
@section('title', __('hotel.check_in') . ' · ' . optional($res->contact)->name)

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $name = optional($res->contact)->name;
    $parts = preg_split('/\s+/', trim((string) $name), 2);
    $card0 = $last ? collect(\App\HotelGuest::FIELDS)->mapWithKeys(fn ($f) => [$f => $last->{$f} instanceof \Carbon\Carbon ? $last->{$f}->toDateString() : $last->{$f}])->all()
        : ['surname' => mb_strtoupper($parts[1] ?? $parts[0] ?? ''), 'first_names' => isset($parts[1]) ? $parts[0] : '', 'phone' => optional($res->contact)->mobile];
    $cards = old('guests', [$card0]);
    $want_room = old('room_id', $res->room_id ?: optional($rooms->first(fn ($r) => $sellable($r)))->id);
@endphp
<style>
.ht-rooms { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 10px; }
.ht-roomopt { display: flex; flex-direction: column; gap: 3px; padding: 11px 12px; border-radius: 12px; border: 2px solid #eaecf0; background: #fff; cursor: pointer; margin: 0; font-weight: 400; }
.ht-roomopt input { position: absolute; opacity: 0; }
.ht-roomopt.on { border-color: #4f46e5; background: #f5f8ff; }
.ht-roomopt:focus-within { outline: 2px solid #84adff; }
.ht-card-g { border: 1px solid #eaecf0; border-radius: 12px; padding: 14px; margin-bottom: 12px; }
</style>
<section class="content ht" id="ht_checkin">
    <div class="ht-head">
        <div>
            <a class="ht-link" href="{{ action([$R, 'show'], [$res->id]) }}">‹ {{ $res->ref_no }}</a>
            <h1 style="margin-top:6px;">{{ __('hotel.check_in') }} · {{ $name }}</h1>
            <p>{{ optional($res->roomType)->name }}, {{ __('hotel.plan_' . $res->rate_plan) }} · {{ $res->arrival->max(\App\Utils\Hotel::today())->format('d/m') }} → {{ $res->departure->format('d/m/Y') }} · {{ trans_choice('hotel.n_nights', $nights, ['n' => $nights]) }}</p>
        </div>
        @if ($balance < -0.004)<span class="ht-pill ht-p-free" style="font-size:13px;padding:6px 12px;">{{ __('hotel.deposit_received', ['amount' => $fmt(-$balance)]) }}</span>@endif
    </div>

    <form method="POST" action="{{ action([$R, 'checkIn'], [$res->id]) }}" id="ht_checkin_form" autocomplete="off">
        @csrf
        <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
            <div style="flex:999 1 560px;min-width:0;">
                <div class="ht-card">
                    <h2>1 · {{ __('hotel.the_room') }}</h2>
                    @if ($rooms->isEmpty())
                        <div class="ht-note red">{{ __('hotel.no_free_room_type') }}</div>
                    @else
                        <div class="ht-rooms" role="radiogroup" aria-label="{{ __('hotel.the_room') }}">
                            @foreach ($rooms as $room)
                                @php $clean = $sellable($room); @endphp
                                <label class="ht-roomopt {{ (string) $want_room === (string) $room->id ? 'on' : '' }}">
                                    <input type="radio" name="room_id" value="{{ $room->id }}" @checked((string) $want_room === (string) $room->id) data-clean="{{ $clean ? 1 : 0 }}" data-no="{{ $room->number }}">
                                    <strong style="font-size:18px;">{{ $room->number }}</strong>
                                    <span style="font-size:13px;font-weight:600;color:{{ $clean ? '#067647' : '#b54708' }};">{{ $clean ? __('hotel.free_clean') : __('hotel.free_dirty') }}</span>
                                    @if ($room->id == $res->room_id)<span class="ht-muted ht-small">{{ __('hotel.booked_for_guest') }}</span>@endif
                                </label>
                            @endforeach
                        </div>
                        <div class="ht-note amber" id="ht_dirty_warn" hidden style="margin-top:12px;">{{ __('hotel.dirty_warn') }}</div>
                    @endif
                </div>

                <div class="ht-card">
                    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px;">
                        <h2 style="margin:0;">2 · {{ __('hotel.register_card') }}</h2>
                        @if ($last)<span class="ht-pill ht-p-free">{{ __('hotel.filled_from_last') }}</span>@endif
                    </div>
                    <div id="ht_cards">
                        @foreach ($cards as $i => $g)
                            <div class="ht-card-g" data-i="{{ $i }}">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                    <strong>{{ $i === 0 ? __('hotel.main_guest') : __('hotel.other_guest') }}</strong>
                                    @if ($i > 0)<button type="button" class="ht-btn sm danger ht-remove">{{ __('hotel.remove') }}</button>@endif
                                </div>
                                <div class="ht-fields">
                                    @foreach ([['surname', 'text', true], ['first_names', 'text'], ['sex', 'sex'], ['dob', 'date'], ['birth_place', 'text'], ['nationality', 'text'], ['profession', 'text'],
                                        ['id_type', 'idt'], ['id_number', 'text', $i === 0 && $settings['police_card']], ['id_issued_on', 'date'], ['id_issued_at', 'text'], ['phone', 'tel'],
                                        ['address', 'text'], ['coming_from', 'text'], ['going_to', 'text'], ['purpose', 'purpose']] as $fld)
                                        @php [$f, $kind] = $fld; $req = $fld[2] ?? false; $v = $g[$f] ?? ''; $fid = 'ht_g' . $i . '_' . $f; @endphp
                                        <div class="ht-f"><label for="{{ $fid }}">{{ __('hotel.f_' . $f) }}{{ $req ? ' *' : '' }}</label>
                                            @if ($kind === 'sex')
                                                <select class="ht-in" name="guests[{{ $i }}][sex]" id="{{ $fid }}"><option value=""></option>@foreach (['M', 'F'] as $o)<option value="{{ $o }}" @selected($v === $o)>{{ __('hotel.sex_' . $o) }}</option>@endforeach</select>
                                            @elseif ($kind === 'idt')
                                                <select class="ht-in" name="guests[{{ $i }}][id_type]" id="{{ $fid }}">@foreach (['cni', 'passport', 'residence', 'other'] as $o)<option value="{{ $o }}" @selected(($v ?: 'cni') === $o)>{{ __('hotel.idt_' . $o) }}</option>@endforeach</select>
                                            @elseif ($kind === 'purpose')
                                                <select class="ht-in" name="guests[{{ $i }}][purpose]" id="{{ $fid }}"><option value=""></option>@foreach (['business', 'leisure', 'family', 'other'] as $o)<option value="{{ $o }}" @selected($v === $o)>{{ __('hotel.purpose_' . $o) }}</option>@endforeach</select>
                                            @else
                                                <input class="ht-in" type="{{ $kind }}" name="guests[{{ $i }}][{{ $f }}]" id="{{ $fid }}" value="{{ $v }}" @if ($req) required @endif maxlength="150">
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="ht-btn" id="ht_add_guest">+ {{ __('hotel.add_guest') }}</button>
                    <p class="ht-muted ht-small" style="margin:10px 0 0;">{{ __('hotel.card_help') }}</p>
                </div>
            </div>

            <aside class="ht-card" style="flex:1 1 300px;max-width:400px;min-width:280px;position:sticky;top:12px;">
                <h2>3 · {{ __('hotel.payment_keys') }}</h2>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.stay') }}</span><strong>{{ $nights }} × {{ $fmt($res->rate) }}</strong></div>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.total_stay') }}</span><strong>{{ $fmt($res->rate * $nights) }}</strong></div>
                @if ($balance < -0.004)<div class="ht-row"><span class="ht-muted">{{ __('hotel.paid_already') }}</span><strong style="color:#067647;">− {{ $fmt(-$balance) }}</strong></div>@endif
                <div class="ht-row"><strong>{{ __('hotel.left_to_pay') }}</strong><strong style="font-size:18px;">{{ $fmt(max(0, $res->rate * $nights + $balance)) }}</strong></div>
                <p class="ht-muted ht-small" style="margin:10px 0;">{{ $settings['charge_mode'] === 'checkin' ? __('hotel.mode_checkin_help') : __('hotel.mode_nightly_help') }}</p>
                <div class="ht-f"><label for="ht_ci_amount">{{ __('hotel.take_payment_now') }}</label><input class="ht-in" name="amount" id="ht_ci_amount" inputmode="decimal" value="{{ old('amount') }}"></div>
                <div class="ht-f"><label for="ht_ci_method">{{ __('hotel.paid_by') }}</label><select class="ht-in" name="method" id="ht_ci_method">@foreach ($payment_types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="ht-f"><label for="ht_ci_keys">{{ __('hotel.keys_given') }}</label><input class="ht-in" type="number" min="0" max="9" name="keys" id="ht_ci_keys" value="{{ old('keys', 1) }}"></div>
                <button class="ht-btn pri" style="width:100%;" id="ht_ci_submit" @disabled($rooms->isEmpty())>{{ __('hotel.check_in_to') }} <span id="ht_ci_room"></span></button>
                <p class="ht-muted ht-small" style="text-align:center;margin:8px 0 0;">{{ __('hotel.checkin_after') }}</p>
            </aside>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
(function () {
    var form = document.getElementById('ht_checkin_form');
    function roomSync() {
        var on = form.querySelector('input[name=room_id]:checked');
        form.querySelectorAll('.ht-roomopt').forEach(function (l) { l.classList.toggle('on', l.contains(on)); });
        var w = document.getElementById('ht_dirty_warn'); if (w) w.hidden = !on || on.getAttribute('data-clean') === '1';
        document.getElementById('ht_ci_room').textContent = on ? on.getAttribute('data-no') : '';
    }
    form.querySelectorAll('input[name=room_id]').forEach(function (r) { r.addEventListener('change', roomSync); });
    roomSync();
    var box = document.getElementById('ht_cards');
    document.getElementById('ht_add_guest').addEventListener('click', function () {
        var first = box.querySelector('.ht-card-g'), n = box.querySelectorAll('.ht-card-g').length;
        var c = first.cloneNode(true);
        c.setAttribute('data-i', n);
        c.querySelector('strong').textContent = @json(__('hotel.other_guest'));
        c.querySelectorAll('input, select').forEach(function (e) {
            e.name = e.name.replace(/guests\[\d+\]/, 'guests[' + n + ']'); e.id = e.id.replace(/ht_g\d+_/, 'ht_g' + n + '_');
            if (e.tagName === 'INPUT') e.value = ''; else e.selectedIndex = e.name.indexOf('id_type') > 0 ? 0 : 0;
            if (e.name.indexOf('[id_number]') > 0) e.required = false;
        });
        c.querySelectorAll('label').forEach(function (l) { l.htmlFor = l.htmlFor.replace(/ht_g\d+_/, 'ht_g' + n + '_'); l.textContent = l.textContent.replace(' *', ''); });
        var head = c.querySelector('div');
        if (!head.querySelector('.ht-remove')) { var b = document.createElement('button'); b.type = 'button'; b.className = 'ht-btn sm danger ht-remove'; b.textContent = @json(__('hotel.remove')); head.appendChild(b); }
        c.querySelector('[name$="[surname]"]').required = false;
        box.appendChild(c);
        c.querySelector('input').focus();
    });
    box.addEventListener('click', function (e) { if (e.target.classList.contains('ht-remove')) e.target.closest('.ht-card-g').remove(); });
    form.addEventListener('submit', function () { var b = document.getElementById('ht_ci_submit'); setTimeout(function () { b.disabled = true; }, 0); });
})();
</script>
@endsection
