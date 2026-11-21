@extends('layouts.app')
@section('title', $walk_in ? __('hotel.walk_in') : __('hotel.new_reservation'))

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $H = \App\Http\Controllers\HotelController::class;
@endphp
<style>
.ht-rt { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; padding: 13px 14px; border: 2px solid #eaecf0; border-radius: 12px; background: #fff; text-align: left; font: inherit; cursor: pointer; width: 100%; margin-bottom: 10px; }
.ht-rt.on { border-color: #4f46e5; background: #f5f8ff; }
.ht-rt[disabled] { opacity: .55; cursor: not-allowed; }
.ht-rt .nm { flex: 1 1 200px; } .ht-rt .fr { flex: 0 0 140px; font-size: 13px; font-weight: 600; } .ht-rt .pr { flex: 0 0 170px; text-align: right; }
.ht-step { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #1849a9; color: #fff; font-size: 13px; font-weight: 700; margin-right: 8px; }
.ht-sugg { border: 1px solid #eaecf0; border-radius: 10px; margin-top: 4px; overflow: hidden; }
.ht-sugg button { display: block; width: 100%; text-align: left; padding: 9px 12px; border: 0; border-bottom: 1px solid #f2f4f7; background: #fff; font: inherit; cursor: pointer; }
.ht-sugg button:hover, .ht-sugg button:focus { background: #f5f8ff; }
.ht-picked { display: flex; gap: 12px; align-items: center; padding: 11px 13px; border-radius: 10px; background: #f5f8ff; border: 1px solid #d1e0ff; margin-top: 8px; }
</style>
<section class="content ht" id="ht_res_form">
    <div class="ht-head">
        <div>
            <a class="ht-link" href="{{ action([$R, 'index']) }}">‹ {{ __('hotel.menu_reservations') }}</a>
            <h1 style="margin-top:6px;">{{ $walk_in ? __('hotel.walk_in') : __('hotel.new_reservation') }}</h1>
            @if ($walk_in)<p>{{ __('hotel.walk_in_lead') }}</p>@endif
        </div>
    </div>

    <form method="POST" action="{{ action([$R, 'store']) }}" id="ht_res_create" autocomplete="off">
        @csrf
        <input type="hidden" name="walk_in" value="{{ $walk_in ? 1 : 0 }}">
        <input type="hidden" name="room_type_id" id="ht_type_id" value="{{ old('room_type_id', $type_id) }}">
        <input type="hidden" name="rate_plan" id="ht_plan" value="{{ old('rate_plan', 'room_only') }}">
        <input type="hidden" name="contact_id" id="ht_contact_id" value="{{ old('contact_id') }}">

        <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
            <div style="flex:999 1 560px;min-width:0;">
                <div class="ht-card">
                    <h2><span class="ht-step">1</span>{{ __('hotel.step_when') }}</h2>
                    <div class="ht-fields">
                        <div class="ht-f"><label for="ht_arrival">{{ __('hotel.arrival') }}</label>
                            <input class="ht-in" type="date" name="arrival" id="ht_arrival" value="{{ old('arrival', $arrival) }}" @if ($walk_in) readonly @endif required></div>
                        <div class="ht-f"><label for="ht_departure">{{ __('hotel.departure') }}</label>
                            <input class="ht-in" type="date" name="departure" id="ht_departure" value="{{ old('departure', $departure) }}" required></div>
                        <div class="ht-f"><label for="ht_adults">{{ __('hotel.adults') }}</label><input class="ht-in" type="number" min="1" max="20" name="adults" id="ht_adults" value="{{ old('adults', 1) }}"></div>
                        <div class="ht-f"><label for="ht_children">{{ __('hotel.children') }}</label><input class="ht-in" type="number" min="0" max="20" name="children" id="ht_children" value="{{ old('children', 0) }}"></div>
                    </div>
                    <p class="ht-muted" style="margin:0;" id="ht_nights_text"></p>
                </div>

                <div class="ht-card">
                    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px;">
                        <h2 style="margin:0;"><span class="ht-step">2</span>{{ __('hotel.step_room') }}</h2>
                        <select class="ht-in" id="ht_plan_pick" style="width:auto;min-width:200px;" aria-label="{{ __('hotel.rate_plan') }}"></select>
                    </div>
                    <div id="ht_types"><p class="ht-muted">{{ __('hotel.loading') }}</p></div>
                    <div class="ht-fields" style="margin-top:6px;">
                        <div class="ht-f"><label for="ht_room">{{ __('hotel.room_optional') }}</label>
                            <select class="ht-in" name="room_id" id="ht_room" data-want="{{ old('room_id', $room_id) }}"><option value="">{{ __('hotel.any_free_room') }}</option></select></div>
                        @if ($can_rate)
                            <div class="ht-f"><label for="ht_rate">{{ __('hotel.rate_override') }}</label>
                                <input class="ht-in" type="text" name="rate" id="ht_rate" value="{{ old('rate') }}" placeholder="{{ __('hotel.rate_override_ph') }}" inputmode="decimal"></div>
                        @endif
                    </div>
                </div>

                <div class="ht-card">
                    <h2><span class="ht-step">3</span>{{ __('hotel.step_guest') }}</h2>
                    <div class="ht-f" style="margin-bottom:4px;">
                        <label for="ht_guest_q">{{ __('hotel.find_guest') }}</label>
                        <input class="ht-in" type="search" id="ht_guest_q" placeholder="{{ __('hotel.find_guest_ph') }}">
                        <div class="ht-sugg" id="ht_guest_sugg" hidden></div>
                    </div>
                    <div class="ht-picked" id="ht_guest_picked" hidden>
                        <div style="flex:1;"><strong id="ht_guest_name"></strong><div class="ht-muted ht-small" id="ht_guest_meta"></div></div>
                        <button type="button" class="ht-btn sm" id="ht_guest_clear">{{ __('hotel.change') }}</button>
                    </div>
                    <div id="ht_guest_new">
                        <p class="ht-muted ht-small" style="margin:8px 0;">{{ __('hotel.or_new_guest') }}</p>
                        <div class="ht-fields">
                            <div class="ht-f"><label for="ht_name">{{ __('hotel.guest_name') }}</label><input class="ht-in" type="text" name="name" id="ht_name" value="{{ old('name') }}" maxlength="150"></div>
                            <div class="ht-f"><label for="ht_mobile">{{ __('hotel.phone') }}</label><input class="ht-in" type="tel" name="mobile" id="ht_mobile" value="{{ old('mobile') }}" maxlength="40"></div>
                        </div>
                    </div>
                    <div class="ht-fields" style="margin-top:6px;">
                        @if (! $walk_in)
                            <div class="ht-f"><label for="ht_source">{{ __('hotel.booked_through') }}</label>
                                <select class="ht-in" name="source" id="ht_source">@foreach (\App\Utils\Hotel::SOURCES as $s)<option value="{{ $s }}" @selected(old('source', 'phone') === $s)>{{ __('hotel.source_' . $s) }}</option>@endforeach</select></div>
                            <div class="ht-f"><label for="ht_status">{{ __('hotel.status') }}</label>
                                <select class="ht-in" name="status" id="ht_status"><option value="confirmed">{{ __('hotel.st_confirmed') }}</option><option value="tentative" @selected(old('status') === 'tentative')>{{ __('hotel.st_tentative') }}</option></select></div>
                            <div class="ht-f" id="ht_hold_wrap" hidden><label for="ht_hold">{{ __('hotel.hold_until') }}</label><input class="ht-in" type="date" name="hold_until" id="ht_hold" value="{{ old('hold_until') }}"></div>
                        @endif
                    </div>
                    <div class="ht-f"><label for="ht_notes">{{ __('hotel.notes') }}</label><input class="ht-in" type="text" name="notes" id="ht_notes" value="{{ old('notes') }}" maxlength="500"></div>
                </div>
            </div>

            <aside class="ht-card" style="flex:1 1 300px;max-width:400px;min-width:280px;position:sticky;top:12px;" aria-label="{{ __('hotel.summary') }}">
                <h2>{{ __('hotel.summary') }}</h2>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.stay') }}</span><strong id="ht_sum_stay">—</strong></div>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.room') }}</span><strong id="ht_sum_room">—</strong></div>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.rate') }}</span><strong id="ht_sum_rate">—</strong></div>
                <div class="ht-row"><strong>{{ __('hotel.total_stay') }}</strong><strong id="ht_sum_total" style="font-size:18px;">—</strong></div>
                <p class="ht-muted ht-small" id="ht_sum_tax" style="margin:4px 0 0;"></p>
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid #eaecf0;">
                    <div class="ht-f"><label for="ht_deposit">{{ $walk_in ? __('hotel.pay_now') : __('hotel.deposit_now') }}</label>
                        <input class="ht-in" type="text" name="deposit" id="ht_deposit" value="{{ old('deposit') }}" inputmode="decimal" data-rule="{{ $settings['deposit_rule'] }}"></div>
                    <div class="ht-f"><label for="ht_method">{{ __('hotel.paid_by') }}</label>
                        <select class="ht-in" name="method" id="ht_method">@foreach ($payment_types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                </div>
                <button type="submit" class="ht-btn pri" style="width:100%;margin-top:6px;" id="ht_res_submit">{{ $walk_in ? __('hotel.continue_checkin') : __('hotel.confirm_reservation') }}</button>
            </aside>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
(function () {
    var T = {!! json_encode([
        'availability' => action([$H, 'availability']),
        'search' => action([$H, 'guestSearch']),
        'free' => __('hotel.js_free'), 'full' => __('hotel.js_full'), 'per_night' => __('hotel.js_per_night'),
        'for_n' => __('hotel.js_for_n'), 'nights' => __('hotel.js_nights'), 'any' => __('hotel.any_free_room'),
        'not_clean' => __('hotel.js_not_clean'), 'stays' => __('hotel.js_stays'), 'new_guest' => __('hotel.js_new_guest'),
        'tax' => __('hotel.js_tax'), 'none' => __('hotel.no_room_types'),
    ]) !!};
    var $ = function (id) { return document.getElementById(id); };
    var data = null, typeId = $('ht_type_id').value, plan = $('ht_plan').value || 'room_only';
    var fmt = function (n) { return (window.__currency_trans_from_en ? __currency_trans_from_en(n, true) : Math.round(n).toLocaleString()); };

    function load() {
        var a = $('ht_arrival').value, d = $('ht_departure').value;
        if (!a || !d) return;
        fetch(T.availability + '?arrival=' + a + '&departure=' + d, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (x) {
                if (!x.ok) { var box = $('ht_types'); box.innerHTML = ''; box.appendChild(el('p', 'ht-note red', x.j.error || '')); data = null; summary(); return; }
                data = x.j; render();
            });
    }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function render() {
        var box = $('ht_types'); box.innerHTML = '';
        $('ht_nights_text').textContent = data.nights + ' ' + T.nights;
        if (!data.types.length) { box.appendChild(el('p', 'ht-muted', T.none)); return; }
        if (!data.types.some(function (t) { return String(t.id) === String(typeId) && t.free > 0; })) {
            var first = data.types.find(function (t) { return t.free > 0; }); typeId = first ? String(first.id) : '';
        }
        data.types.forEach(function (t) {
            var b = el('button', 'ht-rt' + (String(t.id) === String(typeId) ? ' on' : '')); b.type = 'button';
            b.setAttribute('aria-pressed', String(t.id) === String(typeId) ? 'true' : 'false'); b.setAttribute('data-type', t.id);
            if (t.free < 1) b.disabled = true;
            var p = t.plans[plan] || t.plans.room_only;
            var nm = el('div', 'nm'); nm.appendChild(el('div', null, t.name)).style.fontWeight = '700'; if (t.description) nm.appendChild(el('div', 'ht-muted ht-small', t.description));
            var fr = el('div', 'fr', t.free > 0 ? T.free.replace(':n', t.free).replace(':m', t.rooms) : T.full); fr.style.color = t.free > 0 ? '#067647' : '#b42318';
            var pr = el('div', 'pr'); var pr1 = el('div', null, p.rate_f + ' ' + T.per_night); pr1.style.fontWeight = '700'; pr.appendChild(pr1);
            pr.appendChild(el('div', 'ht-muted ht-small', T.for_n.replace(':amount', p.total_f).replace(':n', data.nights)));
            b.appendChild(nm); b.appendChild(fr); b.appendChild(pr);
            b.addEventListener('click', function () { typeId = String(t.id); render(); });
            box.appendChild(b);
        });
        var t = current();
        var sel = $('ht_plan_pick'); sel.innerHTML = '';
        if (t) {
            if (!t.plans[plan]) plan = 'room_only';
            Object.keys(t.plans).forEach(function (k) { var o = el('option', null, t.plans[k].label); o.value = k; if (k === plan) o.selected = true; sel.appendChild(o); });
        }
        var room = $('ht_room'), want = room.getAttribute('data-want') || room.value; room.innerHTML = '';
        var any = el('option', null, T.any); any.value = ''; room.appendChild(any);
        if (t) t.free_rooms.forEach(function (r) { var o = el('option', null, r.number + (r.clean ? '' : ' · ' + T.not_clean)); o.value = r.id; if (String(r.id) === String(want)) o.selected = true; room.appendChild(o); });
        room.setAttribute('data-want', '');
        $('ht_type_id').value = typeId; $('ht_plan').value = plan;
        summary();
    }
    function current() { return data ? data.types.find(function (t) { return String(t.id) === String(typeId); }) : null; }
    function summary() {
        var t = current();
        $('ht_sum_stay').textContent = data ? ($('ht_arrival').value.split('-').reverse().slice(0, 2).join('/') + ' → ' + $('ht_departure').value.split('-').reverse().slice(0, 2).join('/') + ' · ' + data.nights + ' ' + T.nights) : '—';
        if (!t) { $('ht_sum_room').textContent = $('ht_sum_rate').textContent = $('ht_sum_total').textContent = '—'; return; }
        var p = t.plans[plan] || t.plans.room_only, room = $('ht_room').selectedOptions[0];
        $('ht_sum_room').textContent = t.name + ' · ' + (room && room.value ? room.textContent : T.any);
        $('ht_sum_rate').textContent = p.rate_f + ' (' + p.label + ')';
        $('ht_sum_total').textContent = p.total_f;
        $('ht_sum_tax').textContent = t.tax > 0 ? T.tax.replace(':amount', fmt(t.tax)) : '';
        var dep = $('ht_deposit'), rule = dep.getAttribute('data-rule');
        if (!dep.dataset.touched) { dep.value = rule === 'full' ? String(p.rate * data.nights) : (rule === 'first_night' ? String(p.rate) : ''); }
    }
    $('ht_deposit').addEventListener('input', function () { this.dataset.touched = '1'; });
    $('ht_plan_pick').addEventListener('change', function () { plan = this.value; render(); });
    $('ht_room').addEventListener('change', summary);
    ['ht_arrival', 'ht_departure'].forEach(function (id) { $(id).addEventListener('change', function () {
        if ($('ht_departure').value <= $('ht_arrival').value) { var d = new Date($('ht_arrival').value); d.setDate(d.getDate() + 1); $('ht_departure').value = d.toISOString().slice(0, 10); }
        load(); }); });
    if ($('ht_status')) { var hs = function () { $('ht_hold_wrap').hidden = $('ht_status').value !== 'tentative'; }; $('ht_status').addEventListener('change', hs); hs(); }

    // guests
    var timer, q = $('ht_guest_q'), sugg = $('ht_guest_sugg');
    function pick(g) {
        $('ht_contact_id').value = g ? g.id : '';
        $('ht_guest_picked').hidden = !g; $('ht_guest_new').hidden = !!g; sugg.hidden = true;
        if (g) { $('ht_guest_name').textContent = g.name; $('ht_guest_meta').textContent = [g.mobile, g.stays ? T.stays.replace(':n', g.stays) : null].filter(Boolean).join(' · '); q.value = ''; }
    }
    q.addEventListener('input', function () {
        clearTimeout(timer); var v = q.value.trim(); if (v.length < 2) { sugg.hidden = true; return; }
        timer = setTimeout(function () {
            fetch(T.search + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (list) {
                sugg.innerHTML = '';
                list.forEach(function (g) { var b = el('button', null, g.name + (g.mobile ? ' · ' + g.mobile : '') + (g.stays ? ' · ' + T.stays.replace(':n', g.stays) : '')); b.type = 'button'; b.addEventListener('click', function () { pick(g); }); sugg.appendChild(b); });
                var nb = el('button', null, T.new_guest.replace(':name', v)); nb.type = 'button'; nb.addEventListener('click', function () { $('ht_name').value = v; sugg.hidden = true; $('ht_mobile').focus(); }); sugg.appendChild(nb);
                sugg.hidden = false;
            });
        }, 250);
    });
    $('ht_guest_clear').addEventListener('click', function () { pick(null); q.focus(); });
    $('ht_res_create').addEventListener('submit', function () { var b = $('ht_res_submit'); setTimeout(function () { b.disabled = true; }, 0); });
    load();
})();
</script>
@endsection
