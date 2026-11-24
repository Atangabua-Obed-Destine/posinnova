@extends('layouts.app')
@section('title', __('hotel.grp_new'))

@section('content')
@include('hotel.partials.styles')
@php
    $R = \App\Http\Controllers\HotelReservationController::class;
    $G = \App\Http\Controllers\HotelGroupController::class;
@endphp
<style>
.ht-step { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #1849a9; color: #fff; font-size: 13px; font-weight: 700; margin-right: 8px; }
.ht-sugg { border: 1px solid #eaecf0; border-radius: 10px; margin-top: 4px; overflow: hidden; }
.ht-sugg button { display: block; width: 100%; text-align: left; padding: 9px 12px; border: 0; border-bottom: 1px solid #f2f4f7; background: #fff; font: inherit; cursor: pointer; }
.ht-sugg button:hover, .ht-sugg button:focus { background: #f5f8ff; }
.ht-picked { display: flex; gap: 12px; align-items: center; padding: 11px 13px; border-radius: 10px; background: #f5f8ff; border: 1px solid #d1e0ff; margin-top: 8px; }
.ht-pays { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 10px; }
.ht-pays label { display: flex; gap: 10px; padding: 13px; border-radius: 12px; border: 2px solid #eaecf0; background: #fff; cursor: pointer; margin: 0; font-weight: 400; }
.ht-pays label.on { border-color: #4f46e5; background: #f5f8ff; }
.ht-pays strong { display: block; }
.ht-gline { border: 1px solid #eaecf0; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
.ht-gline-h { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 8px; }
.ht-gline-f { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0 10px; }
.ht-gline .lv { padding: 9px 0; font-weight: 700; }
.ht-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
.ht-chip-r { width: 76px; border-radius: 10px; border: 2px solid #1d2939; background: #1d2939; color: #fff; padding: 0; overflow: hidden; font: inherit; cursor: pointer; }
.ht-chip-r .n { display: block; padding: 7px 0 5px; font-size: 17px; font-weight: 700; }
.ht-chip-r .b { display: block; font-size: 11px; font-weight: 700; padding: 3px 0; background: #32d583; color: #053321; }
.ht-chip-r .b.no { background: #fdb022; color: #3d2400; }
.ht-chip-r.on { border-color: #fff; box-shadow: 0 0 0 3px #4f46e5; }
.ht-share { background: #f9fafb; border: 1px solid #eaecf0; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-top: 10px; }
.ht-share div { display: flex; justify-content: space-between; gap: 10px; }
</style>
<section class="content ht" id="ht_grp_form">
    <div class="ht-head">
        <div>
            <a class="ht-link" href="{{ action([$R, 'index']) }}">‹ {{ __('hotel.menu_reservations') }}</a>
            <h1 style="margin-top:6px;">{{ __('hotel.grp_new') }} <span class="ht-pill ht-p-confirmed" id="ht_g_count" style="vertical-align:4px;"></span></h1>
            <p>{{ __('hotel.grp_lead') }}</p>
        </div>
    </div>

    <form method="POST" action="{{ action([$G, 'store']) }}" id="ht_grp_create" autocomplete="off">
        @csrf
        <input type="hidden" name="contact_id" id="ht_contact_id" value="{{ old('contact_id') }}">
        <div style="display:flex;flex-wrap:wrap;gap:18px;align-items:flex-start;">
            <div style="flex:999 1 560px;min-width:0;">
                <div class="ht-card">
                    <h2><span class="ht-step">1</span>{{ __('hotel.grp_who_pays') }}</h2>
                    <div class="ht-f" style="margin-bottom:4px;">
                        <label for="ht_guest_q">{{ __('hotel.grp_find_payer') }}</label>
                        <input class="ht-in" type="search" id="ht_guest_q" placeholder="{{ __('hotel.find_guest_ph') }}">
                        <div class="ht-sugg" id="ht_guest_sugg" hidden></div>
                    </div>
                    <div class="ht-picked" id="ht_guest_picked" hidden>
                        <div style="flex:1;"><strong id="ht_guest_name"></strong><div class="ht-muted ht-small" id="ht_guest_meta"></div></div>
                        <button type="button" class="ht-btn sm" id="ht_guest_clear">{{ __('hotel.change') }}</button>
                    </div>
                    <div id="ht_guest_new">
                        <p class="ht-muted ht-small" style="margin:8px 0;">{{ __('hotel.grp_or_new_payer') }}</p>
                        <div class="ht-fields">
                            <div class="ht-f"><label for="ht_name">{{ __('hotel.grp_payer_name') }}</label><input class="ht-in" type="text" name="name" id="ht_name" value="{{ old('name') }}" maxlength="150"></div>
                            <div class="ht-f"><label for="ht_mobile">{{ __('hotel.phone') }}</label><input class="ht-in" type="tel" name="mobile" id="ht_mobile" value="{{ old('mobile') }}" maxlength="40"></div>
                        </div>
                    </div>
                    <div style="margin:10px 0 6px;font-size:13px;font-weight:600;color:#344054;" id="ht_pays_lbl">{{ __('hotel.grp_how_settled') }}</div>
                    <div class="ht-pays" role="radiogroup" aria-labelledby="ht_pays_lbl">
                        <label class="{{ old('pays', 'payer') === 'payer' ? 'on' : '' }}"><input type="radio" name="pays" value="payer" @checked(old('pays', 'payer') === 'payer') style="margin-top:3px;">
                            <span><strong>{{ __('hotel.grp_pays_payer') }}</strong><span class="ht-muted ht-small">{{ __('hotel.grp_pays_payer_h') }}</span></span></label>
                        <label class="{{ old('pays') === 'each' ? 'on' : '' }}"><input type="radio" name="pays" value="each" @checked(old('pays') === 'each') style="margin-top:3px;">
                            <span><strong>{{ __('hotel.grp_pays_each') }}</strong><span class="ht-muted ht-small">{{ __('hotel.grp_pays_each_h') }}</span></span></label>
                    </div>
                </div>

                <div class="ht-card">
                    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center;margin-bottom:12px;">
                        <h2 style="margin:0;"><span class="ht-step">2</span>{{ __('hotel.grp_the_rooms') }}</h2>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                            <label style="display:flex;gap:6px;align-items:center;margin:0;font-weight:500;font-size:13px;"><input type="checkbox" id="ht_same_arrival" checked> {{ __('hotel.grp_same_arrival') }}</label>
                            <select class="ht-in" name="rate_plan" id="ht_plan_pick" style="width:auto;min-width:180px;" aria-label="{{ __('hotel.rate_plan') }}">
                                @foreach (\App\HotelRoomType::PLANS as $p)<option value="{{ $p }}" @selected(old('rate_plan', 'room_only') === $p)>{{ __('hotel.plan_' . $p) }}</option>@endforeach
                            </select>
                        </div>
                    </div>
                    <div id="ht_glines"></div>
                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                        <button type="button" class="ht-btn" id="ht_g_add" style="border-style:dashed;border-color:#4f46e5;color:#3730a3;">+ {{ __('hotel.grp_add_room') }}</button>
                        <button type="button" class="ht-btn" id="ht_g_copy">+ {{ __('hotel.grp_copy_room') }}</button>
                    </div>
                </div>

                <div class="ht-card">
                    <h2><span class="ht-step">3</span>{{ __('hotel.grp_booking') }}</h2>
                    <div class="ht-fields">
                        <div class="ht-f"><label for="ht_source">{{ __('hotel.booked_through') }}</label>
                            <select class="ht-in" name="source" id="ht_source">@foreach (\App\Utils\Hotel::SOURCES as $s)<option value="{{ $s }}" @selected(old('source', 'company') === $s)>{{ __('hotel.source_' . $s) }}</option>@endforeach</select></div>
                        <div class="ht-f"><label for="ht_status">{{ __('hotel.status') }}</label>
                            <select class="ht-in" name="status" id="ht_status"><option value="confirmed">{{ __('hotel.st_confirmed') }}</option><option value="tentative" @selected(old('status') === 'tentative')>{{ __('hotel.st_tentative') }}</option></select></div>
                        <div class="ht-f" id="ht_hold_wrap" hidden><label for="ht_hold">{{ __('hotel.hold_until') }}</label><input class="ht-in" type="datetime-local" name="hold_until" id="ht_hold" value="{{ old('hold_until') }}"></div>
                    </div>
                    <div class="ht-f"><label for="ht_notes">{{ __('hotel.notes') }}</label><input class="ht-in" type="text" name="notes" id="ht_notes" value="{{ old('notes') }}" maxlength="500"></div>
                </div>
            </div>

            <aside class="ht-card" style="flex:1 1 300px;max-width:400px;min-width:280px;position:sticky;top:12px;" aria-label="{{ __('hotel.summary') }}">
                <h2>{{ __('hotel.summary') }}</h2>
                <div class="ht-row"><span class="ht-muted">{{ __('hotel.grp_payer') }}</span><strong id="ht_sum_payer">—</strong></div>
                <div id="ht_sum_lines" style="border-top:1px solid #eaecf0;margin-top:6px;padding-top:6px;"></div>
                <div class="ht-row"><strong>{{ __('hotel.grp_total') }}</strong><strong id="ht_sum_total" style="font-size:18px;">—</strong></div>
                <div style="margin-top:14px;padding-top:12px;border-top:1px solid #eaecf0;">
                    <div class="ht-f"><label for="ht_deposit">{{ __('hotel.deposit_now') }}</label>
                        <input class="ht-in" type="text" name="deposit" id="ht_deposit" value="{{ old('deposit') }}" inputmode="decimal" data-rule="{{ $settings['deposit_rule'] }}"></div>
                    <div class="ht-f"><label for="ht_method">{{ __('hotel.paid_by') }}</label>
                        <select class="ht-in" name="method" id="ht_method">@foreach ($payment_types as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                    <div class="ht-share" id="ht_share" hidden></div>
                </div>
                <button type="submit" class="ht-btn pri" style="width:100%;margin-top:12px;" id="ht_grp_submit">{{ __('hotel.grp_confirm') }}</button>
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
        'availability' => action([\App\Http\Controllers\HotelController::class, 'availability']),
        'search' => action([\App\Http\Controllers\HotelController::class, 'guestSearch']),
        'arrival0' => $arrival, 'lang' => app()->getLocale(), 'can_rate' => $can_rate,
        'room_n' => __('hotel.grp_room_n'), 'remove' => __('hotel.grp_remove_room'), 'type' => __('hotel.room_type'), 'arrival' => __('hotel.arrival'),
        'nights' => __('hotel.nights'), 'leaves' => __('hotel.grp_leaves'), 'people' => __('hotel.adults'), 'occupant' => __('hotel.grp_occupant'),
        'room_opt' => __('hotel.grp_room_opt'), 'rate' => __('hotel.rate_override'), 'ready' => __('hotel.ready'), 'to_clean' => __('hotel.to_clean'),
        'full' => __('hotel.js_full'), 'free' => __('hotel.js_free'), 'night' => __('hotel.js_night'), 'nights_w' => __('hotel.js_nights'),
        'n_rooms' => __('hotel.grp_n_rooms'), 'one_room' => __('hotel.grp_one_room'), 'paid' => __('hotel.grp_share_paid'), 'to_pay' => __('hotel.grp_share_to_pay'),
        'share_t' => __('hotel.grp_share_title'), 'share_h' => __('hotel.grp_share_help'), 'confirm_n' => __('hotel.grp_confirm_n'),
        'stays' => __('hotel.js_stays'), 'new_payer' => __('hotel.js_new_guest'), 'none' => __('hotel.no_room_types'),
        'cf_title' => __('hotel.grp_cf_title'), 'cf_intro' => __('hotel.grp_cf_intro'), 'payer' => __('hotel.grp_payer'), 'settled' => __('hotel.grp_settled_by'),
        'pays_payer' => __('hotel.grp_pays_payer'), 'pays_each' => __('hotel.grp_pays_each'), 'total' => __('hotel.grp_total'), 'deposit' => __('hotel.deposit_now'),
        'method' => __('hotel.paid_by'), 'notes' => __('hotel.notes'), 'source' => __('hotel.booked_through'), 'status' => __('hotel.status'),
    ]) !!};
    var OLD = @json(array_values((array) old('rooms', [])));
    var $ = function (id) { return document.getElementById(id); };
    var el = function (tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; };
    var money = function (n) { return window.__currency_trans_from_en ? __currency_trans_from_en(n, true) : Math.round(n).toLocaleString(); };
    var addDays = function (iso, n) { var d = new Date(iso + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); };
    var daysBetween = function (a, b) { return Math.round((Date.parse(b + 'T00:00:00Z') - Date.parse(a + 'T00:00:00Z')) / 86400000); };
    var dayText = function (iso) { try { return new Date(iso + 'T12:00:00Z').toLocaleDateString(T.lang === 'fr' ? 'fr-FR' : 'en-GB', { weekday: 'short', day: '2-digit', month: '2-digit', timeZone: 'UTC' }); } catch (e) { return iso; } };
    var nn = function (n) { return n + ' ' + (n == 1 ? T.night : T.nights_w); };
    var plan = $('ht_plan_pick').value;
    var cache = {}, lines = [], seq = 0;

    function avail(a, d) {
        var k = a + '|' + d;
        if (!cache[k]) cache[k] = fetch(T.availability + '?arrival=' + a + '&departure=' + d, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
        return cache[k];
    }

    function field(label, input, k) { var w = el('div', 'ht-f'); var l = el('label', null, label); input.id = 'ht_gf_' + (++seq); input.setAttribute('data-k', k); l.htmlFor = input.id; w.appendChild(l); w.appendChild(input); if (k === 'type') w.style.gridColumn = 'span 2'; return w; }

    function addLine(from) {
        var L = { id: ++seq, type: from ? from.type : '', room: '', arrival: from ? from.arrival : T.arrival0, nights: from ? from.nights : 1, adults: from ? from.adults : 1, occupant: '', rate: '', data: null };
        var box = el('div', 'ht-gline'); L.box = box;
        var h = el('div', 'ht-gline-h'); L.title = el('strong'); L.sub = el('strong'); var rm = el('button', 'ht-btn sm', '×'); rm.type = 'button';
        rm.setAttribute('aria-label', T.remove); rm.addEventListener('click', function () { if (lines.length > 1) { lines.splice(lines.indexOf(L), 1); box.remove(); refresh(); } });
        var right = el('span'); right.style.display = 'flex'; right.style.gap = '10px'; right.style.alignItems = 'center'; right.appendChild(L.sub); right.appendChild(rm);
        h.appendChild(L.title); h.appendChild(right); box.appendChild(h);
        var f = el('div', 'ht-gline-f');
        L.typeSel = el('select', 'ht-in'); L.typeSel.addEventListener('change', function () { L.type = this.value; L.room = ''; draw(L); });
        L.arr = el('input', 'ht-in'); L.arr.type = 'date'; L.arr.value = L.arrival;
        L.arr.addEventListener('change', function () { if (!this.value) return; setArrival(L, this.value); if ($('ht_same_arrival').checked) lines.forEach(function (o) { if (o !== L) { setArrival(o, L.arrival); o.arr.value = L.arrival; } }); });
        L.nts = el('input', 'ht-in'); L.nts.type = 'number'; L.nts.min = 1; L.nts.max = 365; L.nts.value = L.nights; L.nts.inputMode = 'numeric';
        var nt; L.nts.addEventListener('input', function () { var n = parseInt(this.value, 10); if (!(n >= 1)) return; L.nights = Math.min(n, 365); clearTimeout(nt); nt = setTimeout(function () { load(L); }, 300); refresh(); });
        L.lv = el('div', 'lv'); L.lv.setAttribute('data-k', 'leaves');
        var lvw = el('div', 'ht-f'); lvw.appendChild(el('label', null, T.leaves)); lvw.appendChild(L.lv);
        L.ad = el('input', 'ht-in'); L.ad.type = 'number'; L.ad.min = 1; L.ad.max = 20; L.ad.value = L.adults; L.ad.addEventListener('input', function () { L.adults = this.value; });
        L.occ = el('input', 'ht-in'); L.occ.type = 'text'; L.occ.maxLength = 150; L.occ.addEventListener('input', function () { L.occupant = this.value; });
        f.appendChild(field(T.type, L.typeSel, 'type')); f.appendChild(field(T.arrival, L.arr, 'arrival')); f.appendChild(field(T.nights, L.nts, 'nights')); f.appendChild(lvw);
        f.appendChild(field(T.people, L.ad, 'adults')); f.appendChild(field(T.occupant, L.occ, 'occupant'));
        if (T.can_rate) { L.rt = el('input', 'ht-in'); L.rt.inputMode = 'decimal'; L.rt.addEventListener('input', function () { L.rate = this.value; refresh(); }); f.appendChild(field(T.rate, L.rt, 'rate')); }
        box.appendChild(f);
        var rl = el('div', 'ht-small', T.room_opt); rl.style.fontWeight = '600'; rl.style.color = '#344054'; box.appendChild(rl);
        L.chips = el('div', 'ht-chips'); L.chips.setAttribute('role', 'group'); L.chips.setAttribute('aria-label', T.room_opt); box.appendChild(L.chips);
        L.hidden = el('div'); box.appendChild(L.hidden);
        $('ht_glines').appendChild(box);
        lines.push(L);
        load(L);
        return L;
    }
    function setArrival(L, v) { L.arrival = v; load(L); }
    function departure(L) { return addDays(L.arrival, L.nights); }
    function load(L) {
        L.lv.textContent = dayText(departure(L));
        avail(L.arrival, departure(L)).then(function (j) { L.data = j; draw(L); });
    }
    function typeOf(L) { return L.data && L.data.types ? L.data.types.find(function (t) { return String(t.id) === String(L.type); }) : null; }
    function rateOf(L) {
        var t = typeOf(L); if (!t) return 0;
        var over = T.can_rate && L.rate !== '' ? (window.__number_uf ? __number_uf(L.rate) : parseFloat(L.rate)) : 0;
        var p = t.plans[plan] || t.plans.room_only;
        return (over > 0 ? over : p.rate) + (t.tax || 0);
    }
    function totalOf(L) { return rateOf(L) * L.nights; }
    function taken(L) { return lines.filter(function (o) { return o !== L && o.room; }).map(function (o) { return String(o.room); }); }
    function draw(L) {
        var types = (L.data && L.data.types) || [];
        L.typeSel.innerHTML = '';
        if (!L.type || !types.some(function (t) { return String(t.id) === String(L.type); })) { var f1 = types.find(function (t) { return t.free > 0; }); L.type = f1 ? String(f1.id) : ''; }
        types.forEach(function (t) {
            var p = t.plans[plan] || t.plans.room_only;
            var o = el('option', null, t.name + ' · ' + p.rate_f + (t.free > 0 ? ' · ' + T.free.replace(':n', t.free).replace(':m', t.rooms) : ' · ' + T.full));
            o.value = t.id; if (String(t.id) === String(L.type)) o.selected = true; L.typeSel.appendChild(o);
        });
        var t = typeOf(L), used = taken(L);
        L.chips.innerHTML = '';
        if (t) t.free_rooms.forEach(function (r) {
            if (used.indexOf(String(r.id)) >= 0) return;
            var b = el('button', 'ht-chip-r' + (String(r.id) === String(L.room) ? ' on' : '')); b.type = 'button';
            b.setAttribute('aria-pressed', String(r.id) === String(L.room) ? 'true' : 'false');
            b.appendChild(el('span', 'n', r.number)); b.appendChild(el('span', 'b' + (r.clean ? '' : ' no'), r.clean ? T.ready : T.to_clean));
            b.addEventListener('click', function () { L.room = String(L.room) === String(r.id) ? '' : String(r.id); lines.forEach(draw); });
            L.chips.appendChild(b);
        });
        if (L.room && !(t && t.free_rooms.some(function (r) { return String(r.id) === String(L.room); }))) L.room = '';
        refresh();
    }
    function refresh() {
        var total = 0;
        lines.forEach(function (L, i) {
            var t = typeOf(L), n = i;
            L.title.textContent = T.room_n.replace(':n', i + 1) + (t ? ' · ' + t.name : '');
            L.sub.textContent = t ? money(totalOf(L)) : '';
            L.lv.textContent = dayText(departure(L));
            total += totalOf(L);
            // the fields sent with the form
            L.hidden.innerHTML = '';
            var put = function (k, v) { var h = el('input'); h.type = 'hidden'; h.name = 'rooms[' + n + '][' + k + ']'; h.value = v == null ? '' : v; L.hidden.appendChild(h); };
            put('room_type_id', L.type); put('room_id', L.room); put('arrival', L.arrival); put('departure', departure(L)); put('adults', L.adults); put('occupant', L.occupant);
            if (T.can_rate) put('rate', L.rate);
        });
        $('ht_g_count').textContent = lines.length == 1 ? T.one_room : T.n_rooms.replace(':n', lines.length);
        $('ht_grp_submit').textContent = T.confirm_n.replace(':n', lines.length);
        var box = $('ht_sum_lines'); box.innerHTML = '';
        lines.forEach(function (L) {
            var t = typeOf(L), r = el('div', 'ht-row'), left = el('span');
            var room = L.room && t ? (t.free_rooms.find(function (x) { return String(x.id) === String(L.room); }) || {}).number : '';
            left.appendChild(el('strong', null, room || (t ? t.name : '—'))); left.appendChild(el('div', 'ht-muted ht-small', L.arrival.split('-').reverse().slice(0, 2).join('/') + ' → ' + departure(L).split('-').reverse().slice(0, 2).join('/') + ' · ' + nn(L.nights)));
            r.appendChild(left); r.appendChild(el('strong', null, money(totalOf(L)))); box.appendChild(r);
        });
        $('ht_sum_total').textContent = money(total);
        var dep = $('ht_deposit'), rule = dep.getAttribute('data-rule');
        if (!dep.dataset.touched) dep.value = rule === 'full' ? String(Math.round(total)) : (rule === 'first_night' ? String(Math.round(lines.reduce(function (s, L) { return s + rateOf(L); }, 0))) : '');
        share();
    }
    // the same rule as the server: rooms that leave first are paid first, the rest waits on the last one
    function share() {
        var amt = window.__number_uf ? __number_uf($('ht_deposit').value || '0') : parseFloat($('ht_deposit').value) || 0, box = $('ht_share');
        if (!(amt > 0) || lines.length < 2) { box.hidden = true; return; }
        var order = lines.map(function (L, i) { return { L: L, i: i, dep: departure(L) }; }).sort(function (a, b) { return a.dep < b.dep ? -1 : (a.dep > b.dep ? 1 : a.i - b.i); });
        var left = amt; box.innerHTML = ''; box.appendChild(el('strong', null, T.share_t.replace(':amount', money(amt))));
        order.forEach(function (o) {
            var need = totalOf(o.L), got = Math.min(left, need); left -= got;
            var t = typeOf(o.L), room = o.L.room && t ? (t.free_rooms.find(function (x) { return String(x.id) === String(o.L.room); }) || {}).number : T.room_n.replace(':n', o.i + 1);
            var r = el('div'); r.appendChild(el('span', null, room));
            var s = el('span', null, got >= need - 0.004 ? T.paid.replace(':amount', money(need)) : T.to_pay.replace(':amount', money(need - got)));
            s.style.fontWeight = '600'; s.style.color = got >= need - 0.004 ? '#067647' : '#b54708'; r.appendChild(s); box.appendChild(r);
        });
        box.appendChild(el('div', 'ht-muted', T.share_h));
        box.hidden = false;
    }

    $('ht_plan_pick').addEventListener('change', function () { plan = this.value; lines.forEach(draw); });
    $('ht_g_add').addEventListener('click', function () { var last = lines[lines.length - 1]; var L = addLine(last ? { type: '', arrival: last.arrival, nights: last.nights, adults: 1 } : null); L.typeSel.focus(); });
    $('ht_g_copy').addEventListener('click', function () { var last = lines[lines.length - 1]; addLine(last ? { type: last.type, arrival: last.arrival, nights: last.nights, adults: last.adults } : null); });
    $('ht_deposit').addEventListener('input', function () { this.dataset.touched = '1'; share(); });
    document.querySelectorAll('.ht-pays input').forEach(function (r) { r.addEventListener('change', function () { document.querySelectorAll('.ht-pays label').forEach(function (l) { l.classList.toggle('on', l.contains(document.querySelector('.ht-pays input:checked'))); }); }); });
    var hs = function () { $('ht_hold_wrap').hidden = $('ht_status').value !== 'tentative'; }; $('ht_status').addEventListener('change', hs); hs();

    // the payer
    var timer, q = $('ht_guest_q'), sugg = $('ht_guest_sugg');
    function payerName() { return $('ht_contact_id').value ? $('ht_guest_name').textContent : $('ht_name').value.trim(); }
    function pick(g) {
        $('ht_contact_id').value = g ? g.id : '';
        $('ht_guest_picked').hidden = !g; $('ht_guest_new').hidden = !!g; sugg.hidden = true;
        if (g) { $('ht_guest_name').textContent = g.name; $('ht_guest_meta').textContent = [g.mobile, g.stays ? T.stays.replace(':n', g.stays) : null].filter(Boolean).join(' · '); q.value = ''; }
        $('ht_sum_payer').textContent = payerName() || '—';
    }
    q.addEventListener('input', function () {
        clearTimeout(timer); var v = q.value.trim(); if (v.length < 2) { sugg.hidden = true; return; }
        timer = setTimeout(function () {
            fetch(T.search + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (list) {
                sugg.innerHTML = '';
                list.forEach(function (g) { var b = el('button', null, g.name + (g.mobile ? ' · ' + g.mobile : '') + (g.stays ? ' · ' + T.stays.replace(':n', g.stays) : '')); b.type = 'button'; b.addEventListener('click', function () { pick(g); }); sugg.appendChild(b); });
                var nb = el('button', null, T.new_payer.replace(':name', v)); nb.type = 'button'; nb.addEventListener('click', function () { $('ht_name').value = v; sugg.hidden = true; $('ht_sum_payer').textContent = v; $('ht_mobile').focus(); }); sugg.appendChild(nb);
                sugg.hidden = false;
            });
        }, 250);
    });
    $('ht_guest_clear').addEventListener('click', function () { pick(null); q.focus(); });
    $('ht_name').addEventListener('input', function () { $('ht_sum_payer').textContent = payerName() || '—'; });
    $('ht_grp_create').addEventListener('submit', function () { var b = $('ht_grp_submit'); setTimeout(function () { b.disabled = true; }, 0); });

    // before it is saved: every room and the money, to check with the payer
    var C = HtConfirm, X = C.t;
    C.attach($('ht_grp_create'), function () {
        var total = lines.reduce(function (s, L) { return s + totalOf(L); }, 0), dep = C.num($('ht_deposit').value), name = payerName();
        var rows = [[T.payer, name || X.no_guest, name ? '' : 'warn'], [T.settled, document.querySelector('.ht-pays input:checked').value === 'each' ? T.pays_each : T.pays_payer]];
        lines.forEach(function (L, i) {
            var t = typeOf(L), room = L.room && t ? (t.free_rooms.find(function (x) { return String(x.id) === String(L.room); }) || {}).number : '';
            rows.push([T.room_n.replace(':n', i + 1), [t ? t.name : '', room, C.date(L.arrival) + ' → ' + C.date(departure(L)), nn(L.nights), L.occupant].filter(Boolean).join(' · ') + ' · ' + C.money(totalOf(L))]);
        });
        rows.push([T.total, C.money(total), 'strong'], [T.deposit, dep > 0 ? C.money(dep) + ' · ' + C.shown($('ht_method')) : X.nothing_now], [X.still, C.money(Math.max(0, total - dep)), 'strong'],
            [T.source, C.shown($('ht_source'))], [T.status, C.shown($('ht_status'))], [T.notes, $('ht_notes').value.trim()]);
        return { title: T.cf_title, intro: T.cf_intro, rows: rows, amount: total, amountLabel: T.total };
    });

    if (OLD.length) {
        OLD.forEach(function (o) { var n = o.arrival && o.departure ? Math.max(1, daysBetween(o.arrival, o.departure)) : 1; var L = addLine({ type: o.room_type_id, arrival: o.arrival || T.arrival0, nights: n, adults: o.adults || 1 }); L.room = o.room_id || ''; L.occupant = o.occupant || ''; L.occ.value = L.occupant; });
    } else {
        addLine(null);
    }
    pick(null);
    if ($('ht_name').value) $('ht_sum_payer').textContent = $('ht_name').value;
})();
</script>
@endsection
