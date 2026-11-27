@php
    $logo = $business->logo ? asset('uploads/business_logos/'.$business->logo) : null;
    $meta_desc = $tagline ?: ($description ? \Illuminate\Support\Str::limit(strip_tags($description), 160) : __('clinic.pub_meta_default', ['name' => $business->name]));
    $page_url = route('clinic-public.show', $slug);
    $status = session('pub_status');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $business->name }} · {{ __('clinic.pub_title_suffix') }}</title>
    <meta name="description" content="{{ $meta_desc }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $business->name }}">
    <meta property="og:description" content="{{ $meta_desc }}">
    <meta property="og:url" content="{{ $page_url }}">
    @if ($logo)<meta property="og:image" content="{{ $logo }}">@endif
    <meta name="theme-color" content="#0e7490">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --brand:#0e7490; --brand-d:#155e75; --bg:#f8fafc; --ok:#067647; }
        * { box-sizing: border-box; }
        body { margin:0; font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif; color:var(--ink); background:var(--bg); line-height:1.5; }
        a { color:inherit; }
        .wrap { max-width: 920px; margin:0 auto; padding: 0 16px; }
        .hero { background:linear-gradient(135deg,var(--brand),var(--brand-d)); color:#fff; padding: 28px 0 30px; }
        .hero .wrap { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
        .hero img { height:64px; width:64px; object-fit:contain; background:#fff; border-radius:14px; padding:6px; }
        .hero h1 { margin:0; font-size:26px; font-weight:800; }
        .hero p { margin:4px 0 0; opacity:.92; font-size:15px; }
        .grid { display:grid; grid-template-columns: 1fr; gap:16px; padding:18px 0 40px; }
        @media (min-width:760px){ .grid { grid-template-columns: 1.3fr 1fr; align-items:start; } }
        .card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:18px; }
        .card h2 { margin:0 0 12px; font-size:18px; font-weight:700; }
        .card h3 { margin:0 0 6px; font-size:14px; font-weight:600; color:var(--muted); text-transform:uppercase; letter-spacing:.03em; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:44px; padding:9px 16px; border-radius:10px; border:1px solid var(--line); background:#fff; font-weight:600; font-size:15px; cursor:pointer; text-decoration:none; }
        .btn-brand { background:var(--brand); color:#fff; border-color:var(--brand); }
        .btn-wa { background:#16a34a; color:#fff; border-color:#16a34a; }
        .btn-block { width:100%; }
        .chips { display:flex; flex-wrap:wrap; gap:8px; }
        .chip { padding:8px 12px; border:1px solid var(--line); border-radius:10px; background:#fff; cursor:pointer; font-size:14px; font-weight:600; min-height:40px; }
        .chip.sel { background:var(--brand); color:#fff; border-color:var(--brand); }
        .chip.free { font-weight:600; }
        .muted { color:var(--muted); font-size:13px; }
        .step { margin-top:14px; }
        .step[hidden] { display:none; }
        label { display:block; font-weight:600; font-size:13px; margin:10px 0 4px; }
        input[type=text], input[type=tel], textarea, select { width:100%; padding:10px 12px; border:1px solid var(--line); border-radius:10px; font:inherit; }
        .hp { position:absolute; left:-9999px; }
        .flash { padding:12px 14px; border-radius:12px; margin:0 0 14px; font-weight:600; }
        .flash.ok { background:#ecfdf3; color:var(--ok); border:1px solid #abefc6; }
        .flash.err { background:#fef3f2; color:#b42318; border:1px solid #fda29b; }
        .doc { padding:12px 0; border-top:1px solid var(--line); }
        .doc:first-child { border-top:0; }
        .doc b { font-size:15px; }
        .contact-row { display:flex; align-items:center; gap:10px; padding:8px 0; }
        .map { width:100%; height:220px; border:0; border-radius:12px; }
        footer { text-align:center; color:var(--muted); font-size:12px; padding:18px 0 30px; }
        .two { display:flex; gap:10px; flex-wrap:wrap; }
        .two > * { flex:1 1 180px; }
    </style>
</head>
<body>
    <div class="hero">
        <div class="wrap">
            @if ($logo)<img src="{{ $logo }}" alt="{{ $business->name }}">@endif
            <div>
                <h1>{{ $business->name }}</h1>
                @if ($tagline)<p>{{ $tagline }}</p>@endif
            </div>
        </div>
    </div>

    <div class="wrap">
        <div class="grid">
            {{-- Booking --}}
            <div class="card" id="book">
                <h2>{{ __('clinic.pub_book_heading') }}</h2>
                @if ($status)
                    <div class="flash {{ $status['ok'] ? 'ok' : 'err' }}">{{ $status['msg'] }}</div>
                @endif

                @if (empty($doctors))
                    <p class="muted">{{ __('clinic.pub_no_days') }}</p>
                @else
                    <h3>{{ __('clinic.pub_choose_doctor') }}</h3>
                    <div class="chips" id="docChips">
                        @foreach ($doctors as $id => $name)
                            <div class="chip {{ $selected === $id ? 'sel' : '' }}" data-doc="{{ $id }}">{{ $name }}</div>
                        @endforeach
                    </div>

                    <div class="step" id="dayStep" hidden>
                        <h3>{{ __('clinic.pub_choose_day') }}</h3>
                        <div class="chips" id="dayChips"></div>
                        <p class="muted" id="noDays" hidden>{{ __('clinic.pub_no_days') }}</p>
                    </div>

                    <div class="step" id="timeStep" hidden>
                        <h3>{{ __('clinic.pub_choose_time') }}</h3>
                        <div class="chips" id="timeChips"></div>
                        <p class="muted" id="loadingSlots" hidden>{{ __('clinic.pub_loading') }}</p>
                        <p class="muted" id="noSlots" hidden>{{ __('clinic.pub_no_slots') }}</p>
                    </div>

                    <div class="step" id="whoStep" hidden>
                        <h3 id="chosenSummary"></h3>
                        <div class="two">
                            <a class="btn" href="{{ $portal_url }}">⟳ {{ __('clinic.pub_returning') }}<br><span class="muted" style="font-weight:400">{{ __('clinic.pub_returning_sub') }}</span></a>
                            <button type="button" class="btn btn-brand" id="newBtn">+ {{ __('clinic.pub_new') }}</button>
                        </div>

                        <form method="POST" action="{{ route('clinic-public.book', $slug) }}" id="guestForm" class="step" hidden>
                            @csrf
                            <input type="hidden" name="doctor_id" id="f_doctor">
                            <input type="hidden" name="date" id="f_date">
                            <input type="hidden" name="start" id="f_start">
                            <input class="hp" type="text" name="website" tabindex="-1" autocomplete="off">
                            <label>{{ __('clinic.pub_form_name') }}</label>
                            <input type="text" name="guest_name" maxlength="150" required value="{{ old('guest_name') }}">
                            <label>{{ __('clinic.pub_form_phone') }}</label>
                            <input type="tel" name="guest_phone" maxlength="40" required value="{{ old('guest_phone') }}">
                            <label>{{ __('clinic.pub_form_reason') }}</label>
                            <textarea name="reason" rows="2" maxlength="500">{{ old('reason') }}</textarea>
                            <p class="muted">{{ __('clinic.pub_form_note') }}</p>
                            <button type="submit" class="btn btn-brand btn-block">{{ __('clinic.pub_form_submit') }}</button>
                        </form>
                    </div>
                @endif

                @if ($whatsapp)
                    <div style="margin-top:16px; border-top:1px solid var(--line); padding-top:14px;">
                        <a class="btn btn-wa btn-block" href="{{ $whatsapp }}" target="_blank" rel="noopener">✆ {{ __('clinic.pub_book_whatsapp') }}</a>
                    </div>
                @endif
            </div>

            {{-- Sidebar: about, doctors, contact, map --}}
            <div>
                @if ($description)
                    <div class="card" style="margin-bottom:16px;">
                        <h3>{{ __('clinic.pub_about') }}</h3>
                        <div>{!! nl2br(e($description)) !!}</div>
                    </div>
                @endif

                @if (! empty($schedules))
                    <div class="card" style="margin-bottom:16px;">
                        <h3>{{ __('clinic.pub_doctors_heading') }}</h3>
                        @foreach ($schedules as $id => $s)
                            <div class="doc">
                                <b>{{ $s['name'] }}</b>
                                <div class="muted">
                                    @if (! empty($s['days']))
                                        {{ __('clinic.pub_next_open') }}: {{ $s['days'][0]['label'] }}
                                    @else
                                        {{ __('clinic.pub_no_days_short') }}
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="card" style="margin-bottom:16px;">
                    <h3>{{ __('clinic.pub_contact') }}</h3>
                    @if ($address)<div class="contact-row">📍 <span>{!! $address !!}</span></div>@endif
                    @if ($location && $location->mobile)<div class="contact-row">☎ <a href="tel:{{ preg_replace('/[^\d+]/', '', $location->mobile) }}">{{ $location->mobile }}</a></div>@endif
                    @if ($whatsapp)<div class="contact-row">✆ <a href="{{ $whatsapp }}" target="_blank" rel="noopener">{{ __('clinic.pub_whatsapp') }}</a></div>@endif
                    @if ($location && $location->email)<div class="contact-row">✉ <a href="mailto:{{ $location->email }}">{{ $location->email }}</a></div>@endif
                    <a class="btn btn-block" style="margin-top:10px;" href="{{ $portal_url }}">{{ __('clinic.pub_portal_btn') }}</a>
                </div>

                @if ($map_lat && $map_lng)
                    <div class="card">
                        <h3>{{ __('clinic.pub_find_us') }}</h3>
                        <iframe class="map" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps?q={{ $map_lat }},{{ $map_lng }}&z=16&output=embed"></iframe>
                        <a class="btn btn-block" style="margin-top:10px;" target="_blank" rel="noopener"
                            href="https://www.google.com/maps/dir/?api=1&destination={{ $map_lat }},{{ $map_lng }}">{{ __('clinic.pub_directions') }}</a>
                    </div>
                @elseif ($address)
                    <div class="card">
                        <h3>{{ __('clinic.pub_find_us') }}</h3>
                        <div>{!! $address !!}</div>
                        <a class="btn btn-block" style="margin-top:10px;" target="_blank" rel="noopener"
                            href="https://www.google.com/maps/search/?api=1&query={{ urlencode($business->name.' '.strip_tags(str_replace('<br>', ' ', $address))) }}">{{ __('clinic.pub_directions') }}</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <footer>{{ $business->name }} · {{ __('clinic.pub_powered') }}</footer>

    <script>
    (function () {
        var SCHED = @json($schedules);
        var SLOTS_URL = @json(route('clinic-public.slots', $slug));
        var L = { noSlots: @json(__('clinic.pub_no_slots')), loading: @json(__('clinic.pub_loading')) };
        var state = { doc: null, date: null, start: null };
        var $ = function (id) { return document.getElementById(id); };
        var sel = @json($selected);

        function pick(container, el) {
            Array.prototype.forEach.call(container.children, function (c) { c.classList.remove('sel'); });
            if (el) { el.classList.add('sel'); }
        }
        function show(id, on) { var e = $(id); if (e) { e.hidden = ! on; } }

        function chooseDoctor(id) {
            state.doc = id; state.date = null; state.start = null;
            show('dayStep', true); show('timeStep', false); show('whoStep', false);
            var days = (SCHED[id] && SCHED[id].days) || [];
            var box = $('dayChips'); box.innerHTML = '';
            show('noDays', days.length === 0);
            days.forEach(function (d) {
                var c = document.createElement('div'); c.className = 'chip'; c.textContent = d.label; c.setAttribute('data-date', d.date);
                c.onclick = function () { pick(box, c); chooseDate(d.date); };
                box.appendChild(c);
            });
        }
        function chooseDate(date) {
            state.date = date; state.start = null;
            show('timeStep', true); show('whoStep', false);
            var box = $('timeChips'); box.innerHTML = ''; show('noSlots', false); show('loadingSlots', true);
            var x = new XMLHttpRequest();
            x.open('GET', SLOTS_URL + '?doctor_id=' + encodeURIComponent(state.doc) + '&date=' + encodeURIComponent(date));
            x.onload = function () {
                show('loadingSlots', false);
                var slots = [];
                try { slots = (JSON.parse(x.responseText).slots) || []; } catch (e) {}
                show('noSlots', slots.length === 0);
                slots.forEach(function (s) {
                    var c = document.createElement('div'); c.className = 'chip'; c.textContent = s;
                    c.onclick = function () { pick(box, c); chooseSlot(s); };
                    box.appendChild(c);
                });
            };
            x.onerror = function () { show('loadingSlots', false); show('noSlots', true); };
            x.send();
        }
        function chooseSlot(start) {
            state.start = start;
            show('whoStep', true);
            var dayLabel = '';
            var days = (SCHED[state.doc] && SCHED[state.doc].days) || [];
            days.forEach(function (d) { if (d.date === state.date) { dayLabel = d.label; } });
            $('chosenSummary').textContent = (SCHED[state.doc] ? SCHED[state.doc].name : '') + ' · ' + dayLabel + ' · ' + start;
            $('f_doctor').value = state.doc; $('f_date').value = state.date; $('f_start').value = start;
            show('guestForm', false);
        }

        Array.prototype.forEach.call(document.querySelectorAll('#docChips .chip'), function (c) {
            c.onclick = function () { pick($('docChips'), c); chooseDoctor(c.getAttribute('data-doc')); };
        });
        var nb = $('newBtn'); if (nb) { nb.onclick = function () { show('guestForm', true); }; }
        if (sel !== null && SCHED[sel]) { var c = document.querySelector('#docChips .chip[data-doc="' + sel + '"]'); if (c) { chooseDoctor(String(sel)); } }
    })();
    </script>
</body>
</html>
