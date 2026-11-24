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
#ht_rooms_pick { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); margin-top: 4px; }
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
                        <div class="ht-f"><label for="ht_nights_in">{{ __('hotel.nights') }}</label>
                            <input class="ht-in" type="number" min="1" max="365" step="1" id="ht_nights_in" inputmode="numeric" @if ($walk_in) autofocus @endif></div>
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
                    <div style="margin:6px 0 12px;">
                        <div id="ht_room_lbl" style="font-size:13px;font-weight:600;color:#344054;margin-bottom:2px;">{{ __('hotel.room_optional') }}</div>
                        <p class="ht-muted ht-small" style="margin:0 0 6px;">{{ __('hotel.room_unpick_hint') }}</p>
                        <div class="ht-rooms" id="ht_rooms_pick" role="radiogroup" aria-labelledby="ht_room_lbl" data-want="{{ old('room_id', $room_id) }}"></div>
                        @if ($walk_in)<div class="ht-note amber" id="ht_dirty_warn" hidden style="margin-top:12px;">{{ __('hotel.dirty_warn') }}</div>@endif
                    </div>
                    <div class="ht-fields" style="margin-top:6px;">
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
                            <div class="ht-f" id="ht_hold_wrap" hidden><label for="ht_hold">{{ __('hotel.hold_until') }}</label><input class="ht-in" type="datetime-local" name="hold_until" id="ht_hold" value="{{ old('hold_until') }}"></div>
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
@include('hotel.partials.confirm')
<script>
(function () {
    var T = {!! json_encode([
        'cf_title' => $walk_in ? __('hotel.cf_title_walkin') : __('hotel.cf_title_res'), 'cf_intro' => $walk_in ? __('hotel.cf_intro_walkin') : __('hotel.cf_intro_res'),
        'l_stay' => __('hotel.stay'), 'l_type' => __('hotel.room_type'), 'l_room' => __('hotel.room'), 'l_plan' => __('hotel.rate_plan'), 'l_rate' => __('hotel.rate'),
        'l_total' => __('hotel.total_stay'), 'l_deposit' => $walk_in ? __('hotel.pay_now') : __('hotel.deposit_now'), 'l_method' => __('hotel.paid_by'),
        'l_source' => __('hotel.booked_through'), 'l_status' => __('hotel.status'), 'l_hold' => __('hotel.hold_until'), 'l_notes' => __('hotel.notes'), 'l_phone' => __('hotel.phone'),
        'availability' => action([$H, 'availability']),
        'search' => action([$H, 'guestSearch']),
        'free' => __('hotel.js_free'), 'full' => __('hotel.js_full'), 'per_night' => __('hotel.js_per_night'),
        'for_n' => __('hotel.js_for_n'), 'for_1' => __('hotel.js_for_1'), 'nights' => __('hotel.js_nights'), 'night' => __('hotel.js_night'), 'any' => __('hotel.any_free_room'),
        'not_clean' => __('hotel.js_not_clean'), 'stays' => __('hotel.js_stays'), 'new_guest' => __('hotel.js_new_guest'),
        'tax' => __('hotel.js_tax'), 'none' => __('hotel.no_room_types'),
        'tile' => __('hotel.tile_per_night'), 'ready' => __('hotel.ready'), 'to_clean' => __('hotel.to_clean'),
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
    function nn(n) { return n + ' ' + (n == 1 ? T.night : T.nights); }
    function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
    function render() {
        var box = $('ht_types'); box.innerHTML = '';
        $('ht_nights_text').textContent = nn(data.nights);
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
            pr.appendChild(el('div', 'ht-muted ht-small', (data.nights == 1 ? T.for_1 : T.for_n).replace(':amount', p.total_f).replace(':n', data.nights)));
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
        rooms(t);
        $('ht_type_id').value = typeId; $('ht_plan').value = plan;
        summary();
    }
    // the free rooms of the chosen type, as cards. None picked = any free room (chosen at check-in);
    // a picked card tapped again is unpicked.
    function rooms(t) {
        var box = $('ht_rooms_pick'), on = picked(), want = box.getAttribute('data-want') || (on ? on.value : '');
        box.innerHTML = ''; box.setAttribute('data-want', '');
        if (!t) return;
        var p = t.plans[plan] || t.plans.room_only;
        t.free_rooms.forEach(function (r) {
            var lab = el('label', 'ht-roomopt'), inp = el('input'), inn = el('span', 'in'), top = el('span', 'top');
            inp.type = 'radio'; inp.name = 'room_id'; inp.value = r.id;
            inp.setAttribute('data-no', r.number); inp.setAttribute('data-clean', r.clean ? 1 : 0);
            if (String(r.id) === String(want)) { inp.checked = true; }
            var tick = el('span', 'tick', '✓'); tick.setAttribute('aria-hidden', 'true');
            top.appendChild(el('span', 'no', r.number)); top.appendChild(tick);
            inn.appendChild(top);
            inn.appendChild(el('span', 'ty', t.name));
            var sub = [r.floor, t.sleeps].filter(Boolean).join(' · ');
            if (sub) inn.appendChild(el('span', 'sub', sub));
            inn.appendChild(el('span', 'pr', T.tile.replace(':amount', p.rate_f)));
            lab.appendChild(inp); lab.appendChild(inn);
            lab.appendChild(el('span', 'bar ' + (r.clean ? 'ok' : 'no'), r.clean ? T.ready : T.to_clean));
            inp.addEventListener('change', roomPicked);
            inp.addEventListener('click', function () { if (inp.getAttribute('data-was') === '1') { inp.checked = false; roomPicked(); } });
            box.appendChild(lab);
        });
        roomPicked();
    }
    function picked() { return document.querySelector('#ht_rooms_pick input[name=room_id]:checked'); }
    function roomPicked() {
        var on = picked();
        document.querySelectorAll('#ht_rooms_pick input[name=room_id]').forEach(function (i) { i.setAttribute('data-was', i.checked ? '1' : '0'); });
        document.querySelectorAll('#ht_rooms_pick .ht-roomopt').forEach(function (l) { l.classList.toggle('on', !!on && l.contains(on)); });
        if ($('ht_dirty_warn')) $('ht_dirty_warn').hidden = !(on && on.value && on.getAttribute('data-clean') === '0');
        summary();
    }
    // the room as text for the summary and the check page
    function roomText() {
        var on = picked();
        return on && on.value ? on.getAttribute('data-no') + (on.getAttribute('data-clean') === '0' ? ' · ' + T.not_clean : '') : T.any;
    }
    function current() { return data ? data.types.find(function (t) { return String(t.id) === String(typeId); }) : null; }
    function summary() {
        var t = current();
        $('ht_sum_stay').textContent = data ? ($('ht_arrival').value.split('-').reverse().slice(0, 2).join('/') + ' → ' + $('ht_departure').value.split('-').reverse().slice(0, 2).join('/') + ' · ' + nn(data.nights)) : '—';
        if (!t) { $('ht_sum_room').textContent = $('ht_sum_rate').textContent = $('ht_sum_total').textContent = '—'; return; }
        var p = t.plans[plan] || t.plans.room_only;
        $('ht_sum_room').textContent = t.name + ' · ' + roomText();
        $('ht_sum_rate').textContent = p.rate_f + ' (' + p.label + ')';
        $('ht_sum_total').textContent = p.total_f;
        $('ht_sum_tax').textContent = t.tax > 0 ? T.tax.replace(':amount', fmt(t.tax)) : '';
        var dep = $('ht_deposit'), rule = dep.getAttribute('data-rule');
        if (!dep.dataset.touched) { dep.value = rule === 'full' ? String(p.rate * data.nights) : (rule === 'first_night' ? String(p.rate) : ''); }
    }
    $('ht_deposit').addEventListener('input', function () { this.dataset.touched = '1'; });
    $('ht_plan_pick').addEventListener('change', function () { plan = this.value; render(); });
    // arrival + nights = departure, both ways: typing the nights sets the departure, picking a departure sets the nights
    function addDays(iso, n) { var d = new Date(iso + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); }
    function daysBetween(a, b) { return Math.round((Date.parse(b + 'T00:00:00Z') - Date.parse(a + 'T00:00:00Z')) / 86400000); }
    function nightsTyped() { var n = parseInt($('ht_nights_in').value, 10); return n >= 1 ? Math.min(n, 365) : 0; }
    function nightsFromDates() { var a = $('ht_arrival').value, d = $('ht_departure').value; if (a && d) $('ht_nights_in').value = Math.max(1, daysBetween(a, d)); }
    var ntimer;
    $('ht_nights_in').addEventListener('input', function () {
        var n = nightsTyped(); if (!n || !$('ht_arrival').value) return;
        $('ht_departure').value = addDays($('ht_arrival').value, n);
        clearTimeout(ntimer); ntimer = setTimeout(load, 300);
    });
    $('ht_nights_in').addEventListener('change', function () { if (!nightsTyped()) nightsFromDates(); });
    $('ht_arrival').addEventListener('change', function () {
        if (!this.value) return;
        $('ht_departure').value = addDays(this.value, nightsTyped() || 1); nightsFromDates(); load();
    });
    $('ht_departure').addEventListener('change', function () {
        if (!this.value || this.value <= $('ht_arrival').value) this.value = addDays($('ht_arrival').value, 1);
        nightsFromDates(); load();
    });
    nightsFromDates();
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

    // before it is saved: everything on one page, to check with the guest
    var C = HtConfirm, X = C.t;
    C.attach($('ht_res_create'), function () {
        var t = current(), p = t ? (t.plans[plan] || t.plans.room_only) : null, n = data ? data.nights : 0;
        var over = $('ht_rate') ? C.num($('ht_rate').value) : 0, rate = over > 0 ? over : (p ? p.rate : 0);
        var total = rate * n, tax = t ? t.tax * n : 0, dep = C.num($('ht_deposit').value);
        var guest = $('ht_contact_id').value ? $('ht_guest_name').textContent : ($('ht_name').value.trim() ? X.new_guest.replace(':name', $('ht_name').value.trim()) : '');
        var phone = $('ht_contact_id').value ? $('ht_guest_meta').textContent : $('ht_mobile').value.trim();
        var rows = [
            [X.guest, guest || X.no_guest, guest ? '' : 'warn'],
            [T.l_phone, phone],
            [T.l_stay, C.date($('ht_arrival').value) + ' → ' + C.date($('ht_departure').value) + (n ? ' · ' + nn(n) : '')],
            [X.people, X.people_v.replace(':a', $('ht_adults').value || 0).replace(':c', $('ht_children').value || 0)],
            [T.l_type, t ? t.name : ''],
            [T.l_room, roomText()],
            [T.l_plan, p ? p.label : ''],
            [T.l_rate, over > 0 ? X.special.replace(':amount', C.money(over)) : C.money(rate), over > 0 ? 'warn' : ''],
            tax > 0 ? [X.tax + ' (' + C.money(t.tax) + ' × ' + n + ')', C.money(tax)] : null,
            [T.l_deposit, dep > 0 ? C.money(dep) : X.nothing_now],
            dep > 0 ? [T.l_method, C.shown($('ht_method'))] : null,
            [X.still, C.money(Math.max(0, total + tax - dep)), 'strong'],
        ];
        if ($('ht_source')) {
            rows.push([T.l_source, C.shown($('ht_source'))], [T.l_status, C.shown($('ht_status'))]);
            if ($('ht_status').value === 'tentative') rows.push([T.l_hold, C.date($('ht_hold').value)]);
        }
        rows.push([T.l_notes, $('ht_notes').value.trim()]);
        return { title: T.cf_title, intro: T.cf_intro, rows: rows, amount: total, amountLabel: T.l_total,
            note: dep > total + tax + 0.004 ? X.credit.replace(':amount', C.money(dep - total - tax)) : '' };
    });
    load();
})();
</script>
@endsection
