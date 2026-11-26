{{--
    PosInnova: a doctor never misses a patient waiting for them. On every page (not while writing a
    consultation), the patients checked in for the signed-in doctor and not called in yet:
      - nobody marked "with the doctor": a red, flashing bar with a chime, repeated every few minutes, so a
        patient seen without "Call in" on the system is noticed;
      - someone already with the doctor: the same bar, quiet (no flashing, no sound).
    Refreshed every 20 s (clinic/queue/waiting). The sound can be switched off on this device.
--}}
@php
    $pi_wa = null;
    try {
        $pi_wu = auth()->user();
        $pi_wmods = session('business.enabled_modules', []);
        if ($pi_wu && is_array($pi_wmods) && in_array('hospital', $pi_wmods)
            && ! request()->is('consultations/create') && ! request()->is('consultations/*/edit')
            && \App\Utils\ClinicQueue::isDoctor($pi_wu, session('user.business_id'))) {
            $pi_wa = \App\Utils\ClinicQueue::waitingFor(session('user.business_id'), $pi_wu->id);
        }
    } catch (\Throwable $e) {
        $pi_wa = null;
    }
    $pi_wl = [
        'one' => __('clinic.wq_one'), 'many' => __('clinic.wq_many'), 'call' => __('clinic.wq_call_in'),
        'next' => __('clinic.wq_next'), 'min' => __('clinic.wq_minutes'), 'with' => __('clinic.wq_with_you'),
        'unpaid' => __('clinic.wq_unpaid'), 'title' => __('clinic.wq_title'),
        'sound_on' => __('clinic.wq_sound_on'), 'sound_off' => __('clinic.wq_sound_off'),
    ];
@endphp
@if ($pi_wa !== null)
    <style>
        .pi-wait { margin: 10px 15px 0; padding: 10px 14px; border-radius: 12px; display: none; }
        .pi-wait.pi-on { display: block; }
        .pi-wait.pi-loud { background: #d92d20; color: #fff; border: 2px solid #912018; animation: piWaitPulse 1.2s ease-in-out infinite; box-shadow: 0 0 0 0 rgba(217, 45, 32, .55); }
        .pi-wait.pi-quiet { background: #fef3f2; color: #912018; border: 1px solid #fda29b; }
        @keyframes piWaitPulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(217, 45, 32, .55); } 50% { box-shadow: 0 0 0 10px rgba(217, 45, 32, 0); } }
        .pi-wait-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
        .pi-wait-text { flex: 1 1 240px; min-width: 0; line-height: 1.4; font-size: 15px; }
        .pi-wait-text strong { font-size: 16px; }
        .pi-wait-sub { display: block; font-size: 12.5px; opacity: .9; }
        .pi-wait-btns { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .pi-wait-call { display: inline-flex; align-items: center; gap: 6px; min-height: 42px; padding: 8px 18px; border-radius: 8px; border: none; font-weight: 800; font-size: 15px; cursor: pointer; }
        .pi-loud .pi-wait-call { background: #fff; color: #b42318; }
        .pi-quiet .pi-wait-call { background: #d92d20; color: #fff; }
        .pi-wait a.pi-wait-queue { color: inherit !important; text-decoration: underline !important; font-weight: 600; font-size: 13px; }
        .pi-wait-mute { background: transparent; border: 1px solid currentColor; color: inherit; border-radius: 8px; min-height: 34px; padding: 4px 10px; font-size: 12.5px; cursor: pointer; }
        @media (prefers-reduced-motion: reduce) { .pi-wait.pi-loud { animation: none; } }
        @media print { .pi-wait { display: none !important; } }
    </style>
    <div class="pi-wait no-print" id="pi_wait" role="alert" aria-live="assertive"
        data-url="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'waiting']) }}"
        data-call="{{ url('clinic/queue/__ID__/start') }}"
        data-token="{{ csrf_token() }}"
        data-initial='@json($pi_wa)'>
        <div class="pi-wait-row">
            <div class="pi-wait-text"><i class="fas fa-bell" aria-hidden="true"></i> <span id="pi_wait_msg"></span>
                <span class="pi-wait-sub" id="pi_wait_sub"></span></div>
            <div class="pi-wait-btns">
                <form method="POST" id="pi_wait_form" style="display:inline;">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <button type="submit" class="pi-wait-call" id="pi_wait_call"><i class="fas fa-door-open" aria-hidden="true"></i> <span></span></button>
                </form>
                <a class="pi-wait-queue" href="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'index']) }}">@lang('clinic.wq_open_queue')</a>
                <button type="button" class="pi-wait-mute" id="pi_wait_mute"></button>
            </div>
        </div>
    </div>
    <script>
    (function () {
        var box = document.getElementById('pi_wait');
        if (! box) { return; }
        var L = @json($pi_wl);
        var REMIND_MS = 180000, POLL_MS = 20000;
        var baseTitle = document.title, flash = null, audio = null;
        // what this device already announced, kept across pages: a chime for a new patient or every few minutes, not on every page
        var store = { get: function (k, d) { try { var v = sessionStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
                      set: function (k, v) { try { sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } } };
        var lastIds = store.get('pi_wait_ids', []), lastChime = store.get('pi_wait_chime', 0);
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
        var fill = function (t, o) { return t.replace(/:(\w+)/g, function (m, k) { return o[k] !== undefined ? o[k] : m; }); };
        var muted = function () { try { return localStorage.getItem('pi_wait_mute') === '1'; } catch (e) { return false; } };

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
            // phones: vibrate too (browsers allow it only once the page has been tapped)
            try { if (navigator.vibrate && (! navigator.userActivation || navigator.userActivation.hasBeenActive)) { navigator.vibrate([300, 150, 300]); } } catch (e) { /* ignore */ }
            lastChime = Date.now();
            store.set('pi_wait_chime', lastChime);
        }
        // browsers start sound only after a tap: unlock it on the first one
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
            var w = (d && d.waiting) || [], withDoc = (d && d.with_doctor) || [];
            if (! w.length) {
                box.className = 'pi-wait no-print'; titleFlash(0); lastIds = []; store.set('pi_wait_ids', []); return;
            }
            var loud = withDoc.length === 0, first = w[0];
            box.className = 'pi-wait no-print pi-on ' + (loud ? 'pi-loud' : 'pi-quiet');
            document.getElementById('pi_wait_msg').innerHTML = w.length === 1
                ? fill(esc(L.one), { name: '<strong>' + esc(first.name) + '</strong>' })
                : fill(esc(L.many), { n: '<strong>' + w.length + '</strong>' });
            var sub = [fill(L.next, { name: first.name, no: first.queue_no }) + ' · ' + fill(L.min, { n: first.minutes })];
            if (withDoc.length) { sub.push(fill(L.with, { name: withDoc.join(', ') })); }
            if (d.unpaid) { sub.push(fill(L.unpaid, { n: d.unpaid })); }
            document.getElementById('pi_wait_sub').textContent = sub.join(' · ');
            document.getElementById('pi_wait_form').action = box.getAttribute('data-call').replace('__ID__', first.id);
            document.querySelector('#pi_wait_call span').textContent = fill(L.call, { name: first.name });
            document.getElementById('pi_wait_mute').textContent = muted() ? L.sound_off : L.sound_on;
            titleFlash(loud ? w.length : 0);

            var ids = w.map(function (x) { return x.id; });
            var arrived = ids.some(function (id) { return lastIds.indexOf(id) < 0; });
            if (arrived || (loud && Date.now() - lastChime > REMIND_MS)) { chime(); }
            lastIds = ids;
            store.set('pi_wait_ids', ids);
        }

        document.getElementById('pi_wait_mute').addEventListener('click', function () {
            try { localStorage.setItem('pi_wait_mute', muted() ? '0' : '1'); } catch (e) { /* ignore */ }
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
                try { var d = JSON.parse(x.responseText); if (d && d.doctor) { render(d); } } catch (e) { /* not JSON: signed out */ }
            };
            x.send();
        }
        try { render(JSON.parse(box.getAttribute('data-initial'))); } catch (e) { /* ignore */ }
        setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', function () { if (! document.hidden) { poll(); } });
    })();
    </script>
@endif
