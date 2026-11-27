{{--
    PosInnova: a booking request never goes unseen. On every page (not while writing a consultation),
    the appointment requests the signed-in user should approve — reception sees every doctor's,
    a doctor sees their own, following the clinic's "who approves" setting (appointments_approval).
    A red, flashing bar with a chime, repeated every few minutes, with Approve / Decline on the bar.
    Refreshed every 20 s (clinic/appointments/pending). The sound can be switched off on this device.
    Namespaced pi-appt / pi_appt_* so it never clashes with the doctor "patient waiting" bar (pi-wait).
--}}
@php
    $pi_ap = null;
    try {
        $pi_apu = auth()->user();
        $pi_apmods = session('business.enabled_modules', []);
        if ($pi_apu && is_array($pi_apmods) && in_array('hospital', $pi_apmods)
            && ! request()->is('consultations/create') && ! request()->is('consultations/*/edit')) {
            $pi_apd = \App\Utils\ClinicAppointments::pendingFor(session('user.business_id'), $pi_apu);
            if (! empty($pi_apd['can'])) {
                $pi_ap = $pi_apd;
            }
        }
    } catch (\Throwable $e) {
        $pi_ap = null;
    }
    $pi_apl = [
        'one' => __('clinic.pr_one'), 'many' => __('clinic.pr_many'), 'sub' => __('clinic.pr_sub'),
        'ago' => __('clinic.pr_ago'), 'approve' => __('clinic.pr_approve'), 'decline' => __('clinic.pr_decline'),
        'title' => __('clinic.pr_title'), 'open' => __('clinic.pr_open'),
        'sound_on' => __('clinic.pr_sound_on'), 'sound_off' => __('clinic.pr_sound_off'),
        'reason' => __('clinic.appt_decline_prompt'),
    ];
@endphp
@if ($pi_ap !== null)
    <style>
        .pi-appt { margin: 10px 15px 0; padding: 10px 14px; border-radius: 12px; display: none; background: #d92d20; color: #fff; border: 2px solid #912018; }
        .pi-appt.pi-appt-on { display: block; animation: piApptPulse 1.2s ease-in-out infinite; box-shadow: 0 0 0 0 rgba(217, 45, 32, .55); }
        @keyframes piApptPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(217, 45, 32, .55); } 50% { box-shadow: 0 0 0 10px rgba(217, 45, 32, 0); } }
        .pi-appt-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
        .pi-appt-text { flex: 1 1 240px; min-width: 0; line-height: 1.4; font-size: 15px; }
        .pi-appt-text strong { font-size: 16px; }
        .pi-appt-sub { display: block; font-size: 12.5px; opacity: .9; }
        .pi-appt-btns { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pi-appt-btn { display: inline-flex; align-items: center; gap: 6px; min-height: 42px; padding: 8px 18px; border-radius: 8px; border: none; font-weight: 800; font-size: 15px; cursor: pointer; }
        .pi-appt-ok { background: #fff; color: #067647; }
        .pi-appt-no { background: transparent; color: #fff; border: 1px solid #fff; font-weight: 700; }
        .pi-appt a.pi-appt-link { color: inherit !important; text-decoration: underline !important; font-weight: 600; font-size: 13px; }
        .pi-appt-mute { background: transparent; border: 1px solid currentColor; color: inherit; border-radius: 8px; min-height: 34px; padding: 4px 10px; font-size: 12.5px; cursor: pointer; }
        @media (prefers-reduced-motion: reduce) { .pi-appt.pi-appt-on { animation: none; } }
        @media print { .pi-appt { display: none !important; } }
    </style>
    <div class="pi-appt no-print" id="pi_appt" role="alert" aria-live="assertive"
        data-url="{{ action([\App\Http\Controllers\ClinicAppointmentController::class, 'pending']) }}"
        data-confirm="{{ url('clinic/appointments/__ID__/confirm') }}"
        data-decline="{{ url('clinic/appointments/__ID__/decline') }}"
        data-token="{{ csrf_token() }}"
        data-initial='@json($pi_ap)'>
        <div class="pi-appt-row">
            <div class="pi-appt-text"><i class="fas fa-calendar-check" aria-hidden="true"></i> <span id="pi_appt_msg"></span>
                <span class="pi-appt-sub" id="pi_appt_sub"></span></div>
            <div class="pi-appt-btns">
                <form method="POST" id="pi_appt_ok_form" style="display:inline;">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="pi-appt-btn pi-appt-ok" id="pi_appt_ok"><i class="fas fa-check" aria-hidden="true"></i> <span></span></button>
                </form>
                <form method="POST" id="pi_appt_no_form" style="display:inline;">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="reason" id="pi_appt_reason">
                    <button type="submit" class="pi-appt-btn pi-appt-no" id="pi_appt_no"><i class="fas fa-ban" aria-hidden="true"></i> <span></span></button>
                </form>
                <a class="pi-appt-link" href="{{ action([\App\Http\Controllers\ClinicAppointmentController::class, 'index']) }}">@lang('clinic.pr_open')</a>
                <button type="button" class="pi-appt-mute" id="pi_appt_mute"></button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var box = document.getElementById('pi_appt');
        if (! box) { return; }
        var L = @json($pi_apl);
        var REMIND_MS = 180000, POLL_MS = 20000;
        var baseTitle = document.title, flash = null, audio = null;
        var store = { get: function (k, d) { try { var v = sessionStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
                      set: function (k, v) { try { sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } } };
        var lastIds = store.get('pi_appt_ids', []), lastChime = store.get('pi_appt_chime', 0);
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
        var fill = function (t, o) { return t.replace(/:(\w+)/g, function (m, k) { return o[k] !== undefined ? o[k] : m; }); };
        var muted = function () { try { return localStorage.getItem('pi_appt_mute') === '1'; } catch (e) { return false; } };

        function chime() {
            if (muted()) { return; }
            try {
                audio = audio || new (window.AudioContext || window.webkitAudioContext)();
                if (audio.state === 'suspended') { audio.resume(); }
                [0, 0.35, 0.9, 1.25].forEach(function (at, i) {
                    var o = audio.createOscillator(), g = audio.createGain(), t = audio.currentTime + at;
                    o.type = 'sine'; o.frequency.value = i % 2 ? 660 : 880;
                    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.6, t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.3);
                    o.connect(g); g.connect(audio.destination); o.start(t); o.stop(t + 0.32);
                });
            } catch (e) { /* no sound on this browser */ }
            try { if (navigator.vibrate && (! navigator.userActivation || navigator.userActivation.hasBeenActive)) { navigator.vibrate([300, 150, 300]); } } catch (e) { /* ignore */ }
            lastChime = Date.now();
            store.set('pi_appt_chime', lastChime);
        }
        ['click', 'keydown', 'touchstart'].forEach(function (ev) {
            document.addEventListener(ev, function () {
                try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); if (audio.state === 'suspended') { audio.resume(); } } catch (e) { /* ignore */ }
            }, { once: true, passive: true });
        });

        function titleFlash(n) {
            clearInterval(flash); flash = null; document.title = baseTitle;
            if (! n) { return; }
            var on = false, t = fill(L.title, { n: n });
            flash = setInterval(function () { on = ! on; document.title = on ? t : baseTitle; }, 1000);
        }

        function render(d) {
            var w = (d && d.requests) || [];
            if (! w.length) {
                box.className = 'pi-appt no-print'; titleFlash(0); lastIds = []; store.set('pi_appt_ids', []); return;
            }
            var first = w[0];
            box.className = 'pi-appt no-print pi-appt-on';
            document.getElementById('pi_appt_msg').innerHTML = w.length === 1
                ? fill(esc(L.one), { name: '<strong>' + esc(first.patient) + '</strong>' })
                : fill(esc(L.many), { n: '<strong>' + w.length + '</strong>' });
            var sub = fill(L.sub, { doctor: first.doctor, date: first.date, time: first.start });
            sub += ' · ' + fill(L.ago, { n: first.minutes_ago });
            document.getElementById('pi_appt_sub').textContent = sub;
            document.getElementById('pi_appt_ok_form').action = box.getAttribute('data-confirm').replace('__ID__', first.id);
            document.getElementById('pi_appt_no_form').action = box.getAttribute('data-decline').replace('__ID__', first.id);
            document.querySelector('#pi_appt_ok span').textContent = L.approve;
            document.querySelector('#pi_appt_no span').textContent = L.decline;
            document.getElementById('pi_appt_mute').textContent = muted() ? L.sound_off : L.sound_on;
            titleFlash(w.length);

            var ids = w.map(function (x) { return x.id; });
            var arrived = ids.some(function (id) { return lastIds.indexOf(id) < 0; });
            if (arrived || Date.now() - lastChime > REMIND_MS) { chime(); }
            lastIds = ids;
            store.set('pi_appt_ids', ids);
        }

        // Decline asks for a reason the patient will see, then submits
        document.getElementById('pi_appt_no_form').addEventListener('submit', function (e) {
            var why = window.prompt(L.reason, '');
            if (why === null) { e.preventDefault(); return; }
            document.getElementById('pi_appt_reason').value = why;
        });

        document.getElementById('pi_appt_mute').addEventListener('click', function () {
            try { localStorage.setItem('pi_appt_mute', muted() ? '0' : '1'); } catch (e) { /* ignore */ }
            this.textContent = muted() ? L.sound_off : L.sound_on;
            if (! muted()) { chime(); }
        });

        function poll() {
            if (document.hidden) { return; }
            var x = new XMLHttpRequest();
            x.open('GET', box.getAttribute('data-url'));
            x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            x.setRequestHeader('Accept', 'application/json');
            x.onload = function () {
                if (x.status !== 200) { return; }
                try { var d = JSON.parse(x.responseText); if (d && d.can) { render(d); } else { render({ requests: [] }); } } catch (e) { /* ignore */ }
            };
            x.send();
        }
        try { render(JSON.parse(box.getAttribute('data-initial'))); } catch (e) { /* ignore */ }
        setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', function () { if (! document.hidden) { poll(); } });
    })();
    </script>
@endif
