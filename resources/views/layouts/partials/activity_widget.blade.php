{{--
    PosInnova: everything that needs the signed-in user, in one floating widget reachable from every screen
    (the POS included). It replaced the separate bars (patient waiting, booking requests, continue
    consultation). Modelled on the E-Ride app's "Your activity" bubble:
      - collapsed: a round button that floats over the page; the badge counts what needs you now (red) or
        what is in progress (grey); a pulse while something needs you. Drag it anywhere: it snaps to the
        nearest side and stays where you put it (this device).
      - expanded: a panel beside it (a bottom sheet on a phone), grouped NEEDS YOU NOW / NOT FINISHED /
        IN PROGRESS, each row with its action: Review (the review window), Call in, Continue.
      - something new that needs you: a chime (one for all), vibration on phones, the tab title flashing and
        a short "peek" card beside the button; a reminder every 3 min while it waits.
    The list comes from App\Utils\ClinicActivity (GET clinic/activity, every 5 s). Inside a wide-screen tab
    (iframe) it does not run: the top window shows it.
--}}
@php
    $pi_act = null;
    $pi_src = null;
    try {
        $pi_acu = auth()->user();
        $pi_acb = session('user.business_id');
        $pi_src = \App\Utils\ClinicActivity::sources($pi_acu, $pi_acb);
        if ($pi_src !== null) {
            $pi_act = \App\Utils\ClinicActivity::build($pi_acu, $pi_acb, $pi_src);
        }
    } catch (\Throwable $e) {
        $pi_act = null;
    }
    $pi_pos = ! empty($pos);
    $pi_actl = [
        'title' => __('clinic.act_title'), 'empty' => __('clinic.act_empty'),
        'g_now' => __('clinic.act_g_now'), 'g_unfinished' => __('clinic.act_g_unfinished'), 'g_progress' => __('clinic.act_g_progress'),
        'btn' => __('clinic.act_btn'), 'btn_needs' => __('clinic.act_btn_needs'), 'btn_total' => __('clinic.act_btn_total'),
        'sound_on' => __('clinic.pr_sound_on'), 'sound_off' => __('clinic.pr_sound_off'),
        'flash' => __('clinic.act_title_flash'), 'diary' => url('clinic/appointments/upcoming'),
    ];
    $pi_links = [];
    if ($pi_src) {
        if (! empty($pi_src['waiting'])) {
            $pi_links[] = [url('clinic/my-practice'), 'fa-clipboard-list', __('clinic.mp_title')];
            $pi_links[] = [url('clinic/queue'), 'fa-users', __('clinic.wq_open_queue')];
        }
        if (! empty($pi_src['requests'])) {
            $pi_links[] = [url('clinic/appointments/upcoming'), 'fa-calendar-alt', __('clinic.act_appointments')];
        }
    }
@endphp
@if ($pi_act !== null)
    <style>
        .pi-act { position: fixed; z-index: 1039; width: 68px; height: 68px; top: 70%; right: 4px; display: flex; align-items: center; justify-content: center; touch-action: none;
            background: transparent !important; border: 0 !important; box-shadow: none !important; padding: 0 !important; margin: 0 !important; }
        .pi-act[hidden], .pi-act-panel[hidden], .pi-act-back[hidden], .pi-act-peek[hidden] { display: none !important; }
        .pi-act-btn { position: relative; width: 56px; height: 56px; border-radius: 50%; border: 0; padding: 0; cursor: pointer; background: #0e7490; color: #fff;
            box-shadow: 0 5px 14px rgba(0, 0, 0, .28); display: flex; align-items: center; justify-content: center; font-size: 24px; touch-action: none; transition: opacity .15s, transform .15s; }
        .pi-act-btn:hover { transform: scale(1.04); }
        .pi-act-btn:focus-visible { outline: 3px solid #f59e0b; outline-offset: 3px; }
        .pi-act.pi-act-drag .pi-act-btn { opacity: .85; cursor: grabbing; transform: none; }
        .pi-act.pi-act-hot .pi-act-btn { background: #d92d20; }
        .pi-act.pi-act-hot::before { content: ''; position: absolute; width: 56px; height: 56px; border-radius: 50%; background: #d92d20; opacity: .35; animation: piActPulse 1.4s ease-out infinite; }
        @keyframes piActPulse { from { transform: scale(1); opacity: .45; } to { transform: scale(1.45); opacity: 0; } }
        .pi-act-badge { position: absolute; top: -4px; right: -4px; min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px; border: 2px solid #fff;
            background: #3f3f46; color: #fff; font-size: 11.5px; font-weight: 800; line-height: 18px; text-align: center; font-family: inherit; }
        .pi-act-badge.hot { background: #b42318; }
        .pi-act-badge[hidden] { display: none; }
        .pi-act-peek { position: absolute; top: 50%; right: 72px; transform: translateY(-50%); width: max-content; max-width: min(300px, calc(100vw - 96px)); background: #1c1c1e; color: #fff;
            border-radius: 14px; padding: 9px 12px; box-shadow: 0 10px 24px rgba(0, 0, 0, .28); cursor: pointer; font-size: 13px; line-height: 1.35; animation: piActIn .25s ease-out; }
        .pi-act-peek b { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: #fda29b; }
        .pi-act-left .pi-act-peek { right: auto; left: 72px; }
        @keyframes piActIn { from { opacity: 0; transform: translateY(-50%) scale(.95); } to { opacity: 1; transform: translateY(-50%) scale(1); } }

        .pi-act-back { position: fixed; inset: 0; z-index: 1044; background: rgba(15, 23, 42, .45); }
        .pi-act-panel { position: fixed; z-index: 1045; width: 360px; max-width: calc(100vw - 24px); max-height: min(72vh, 600px); display: flex; flex-direction: column;
            background: #fff; color: #0f172a; border-radius: 18px; box-shadow: 0 18px 50px rgba(15, 23, 42, .3); font-size: 14px; line-height: 1.4; overflow: hidden; }
        .pi-act-grab { display: none; width: 42px; height: 4px; border-radius: 4px; background: rgba(100, 116, 139, .35); margin: 10px auto 2px; }
        .pi-act-head { display: flex; align-items: center; gap: 8px; padding: 14px 12px 4px 18px; }
        .pi-act-head h3 { flex: 1; margin: 0; font-size: 17px; font-weight: 800; }
        .pi-act-mute { border: 1px solid #cbd5e1; background: #fff; color: #334155; border-radius: 999px; min-height: 32px; padding: 3px 10px; font-size: 12px; font-weight: 600; cursor: pointer; }
        .pi-act-x { width: 36px; height: 36px; border: 0; border-radius: 10px; background: #f1f5f9; color: #334155; font-size: 20px; line-height: 1; cursor: pointer; }
        .pi-act-list { overflow-y: auto; padding: 4px 12px 10px; -webkit-overflow-scrolling: touch; }
        .pi-act-g { margin: 10px 6px 6px; font-size: 10.5px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: #64748b; }
        .pi-act-g.now { color: #b42318; }
        .pi-act-row { display: flex; align-items: center; gap: 10px; padding: 10px; margin-bottom: 8px; border-radius: 14px; background: #f8fafc; }
        .pi-act-row.now { background: #fef3f2; }
        .pi-act-ic { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex: none; font-size: 16px; }
        .pi-act-tx { flex: 1; min-width: 0; }
        .pi-act-tx b { display: block; font-size: 14px; font-weight: 700; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        .pi-act-tx small { display: block; color: #64748b; font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pi-act-tag { display: inline-block; padding: 0 7px; border-radius: 999px; background: #e0f2fe; color: #075985; font-size: 11px; font-weight: 700; margin-left: 4px; vertical-align: 1px; }
        .pi-act-go { flex: none; display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 6px 12px; border-radius: 999px; border: 1px solid #cbd5e1;
            background: #fff; color: #0f172a !important; font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none !important; white-space: nowrap; }
        .pi-act-row.now .pi-act-go:not(.alt) { background: #d92d20; border-color: #d92d20; color: #fff !important; }
        .pi-act-empty { text-align: center; color: #64748b; padding: 22px 10px 26px; }
        .pi-act-empty i { display: block; font-size: 26px; color: #12b76a; margin-bottom: 8px; }
        .pi-act-foot { display: flex; flex-wrap: wrap; gap: 6px; padding: 10px 12px 12px; border-top: 1px solid #e2e8f0; background: #f8fafc; }
        .pi-act-foot a { display: inline-flex; align-items: center; gap: 6px; min-height: 34px; padding: 4px 10px; border-radius: 10px; border: 1px solid #e2e8f0; background: #fff;
            color: #334155 !important; font-size: 12.5px; font-weight: 600; text-decoration: none !important; }
        @media (max-width: 767px) {
            .pi-act-panel { left: 0 !important; right: 0 !important; top: auto !important; bottom: 0 !important; width: 100%; max-width: 100%; max-height: 80vh;
                border-radius: 22px 22px 0 0; padding-bottom: env(safe-area-inset-bottom); }
            .pi-act-grab { display: block; }
            .pi-act-go { min-height: 40px; padding: 8px 14px; }
            .pi-act-head { padding-top: 6px; }
        }
        body.modal-open .pi-act, body.swal2-shown .pi-act, html.pi-rv-open .pi-act,
        body.modal-open .pi-act-panel, html.pi-rv-open .pi-act-panel, html.pi-rv-open .pi-act-back { display: none !important; }
        @media (prefers-reduced-motion: reduce) { .pi-act.pi-act-hot::before, .pi-act-peek { animation: none; } }
        @media print { .pi-act, .pi-act-panel, .pi-act-back { display: none !important; } }
    </style>

    <div class="pi-act no-print" id="pi_act" hidden>
        <button type="button" class="pi-act-btn" id="pi_act_btn" aria-expanded="false" aria-controls="pi_act_panel" aria-label="{{ __('clinic.act_btn') }}">
            <i class="fas fa-clipboard-list" aria-hidden="true"></i><span class="pi-act-badge" id="pi_act_badge" hidden></span>
        </button>
        <div class="pi-act-peek" id="pi_act_peek" hidden role="status" aria-live="assertive"><b></b><span></span></div>
    </div>
    <div class="pi-act-back no-print" id="pi_act_back" hidden></div>
    <section class="pi-act-panel no-print" id="pi_act_panel" hidden role="dialog" aria-labelledby="pi_act_title">
        <div class="pi-act-grab" aria-hidden="true"></div>
        <div class="pi-act-head">
            <h3 id="pi_act_title">@lang('clinic.act_title')</h3>
            <button type="button" class="pi-act-mute" id="pi_act_mute"></button>
            <button type="button" class="pi-act-x" id="pi_act_close" aria-label="{{ __('clinic.rv_close') }}">&times;</button>
        </div>
        <div class="pi-act-list" id="pi_act_list"></div>
        @if (count($pi_links))
            <div class="pi-act-foot">
                @foreach ($pi_links as [$href, $icon, $text])
                    <a href="{{ $href }}"><i class="fas {{ $icon }}" aria-hidden="true"></i> {{ $text }}</a>
                @endforeach
            </div>
        @endif
    </section>
    <form method="POST" id="pi_act_call" hidden>@csrf</form>

    <script>
    (function () {
        var root = document.getElementById('pi_act');
        if (! root) { return; }
        // a wide-screen tab (iframe): the top window already shows it, chimes and polls
        try { if (window.self !== window.top) { root.remove(); return; } } catch (e) { root.remove(); return; }
        var D = @json($pi_act);
        var L = @json($pi_actl);
        var URL_LIST = @json(url('clinic/activity'));
        var POS = @json($pi_pos);
        var POLL_MS = 5000, REMIND_MS = 180000, BOX = 68, PEEK_MS = 6000;
        var $ = function (id) { return document.getElementById(id); };
        var btn = $('pi_act_btn'), badge = $('pi_act_badge'), panel = $('pi_act_panel'), back = $('pi_act_back'), list = $('pi_act_list'), peek = $('pi_act_peek');
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
        var fill = function (t, o) { return String(t).replace(/:(\w+)/g, function (m, k) { return o[k] !== undefined ? o[k] : m; }); };
        var ls = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } } };
        var ss = { get: function (k, d) { try { var v = sessionStorage.getItem(k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
                   set: function (k, v) { try { sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) { /* private mode */ } } };

        /* ------------------------------------------------ sound (one for everything) */
        if (ls.get('pi_act_mute') === null && (ls.get('pi_appt_mute') === '1' || ls.get('pi_wait_mute') === '1')) { ls.set('pi_act_mute', '1'); }
        var muted = function () { return ls.get('pi_act_mute') === '1'; };
        var audio = null, lastChime = ss.get('pi_act_chime', 0);
        function chime() {
            lastChime = Date.now(); ss.set('pi_act_chime', lastChime);
            root.setAttribute('data-chimed', String(lastChime));
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
        }
        ['click', 'keydown', 'touchstart'].forEach(function (ev) {
            document.addEventListener(ev, function () {
                try { audio = audio || new (window.AudioContext || window.webkitAudioContext)(); if (audio.state === 'suspended') { audio.resume(); } } catch (e) { /* ignore */ }
            }, { once: true, passive: true });
        });
        function muteLabel() { $('pi_act_mute').textContent = muted() ? L.sound_off : L.sound_on; }
        $('pi_act_mute').addEventListener('click', function () { ls.set('pi_act_mute', muted() ? '0' : '1'); muteLabel(); if (! muted()) { chime(); } });

        var baseTitle = document.title, flash = null, flashN = 0;
        function titleFlash(n) {
            if (n === flashN) { return; }
            flashN = n; clearInterval(flash); flash = null; document.title = baseTitle;
            if (! n) { return; }
            var on = false, t = fill(L.flash, { n: n });
            flash = setInterval(function () { on = ! on; document.title = on ? t : baseTitle; }, 1000);
        }

        /* ------------------------------------------------ where it sits (drag, snap, remembered) */
        // the POS screen keeps its own spot: by default the empty top-right corner of its header, clear of the cart and the products
        var K = POS ? { r: 'pi_act_pos_right', y: 'pi_act_pos_y' } : { r: 'pi_act_right', y: 'pi_act_y' };
        var pos = { right: ls.get(K.r) !== '0', y: parseFloat(ls.get(K.y)) };
        if (! (pos.y >= 0 && pos.y <= 0.95)) { pos.y = POS ? 0 : 0.72; }
        function bounds() {
            var cs = getComputedStyle(document.documentElement);
            var ticker = parseInt(cs.getPropertyValue('--posinnova-ticker-h'), 10) || 0;
            var st = document.getElementById('pi-status'); var sh = st && st.offsetParent !== null ? st.offsetHeight : 0;
            return { top: 8, bottom: Math.max(8, window.innerHeight - BOX - 8 - ticker - sh) };
        }
        var clamp = function (v, a, b) { return Math.min(Math.max(v, a), b); };
        function place() {
            var b = bounds();
            root.style.top = clamp(pos.y * window.innerHeight, b.top, b.bottom) + 'px';
            var edge = window.innerWidth < 768 ? 4 : 18;   // off the page's scrollbar on a computer
            root.style.left = (pos.right ? window.innerWidth - BOX - edge : edge) + 'px';
            root.style.right = 'auto';
            root.classList.toggle('pi-act-left', ! pos.right);
            if (! panel.hidden) { placePanel(); }
        }
        window.addEventListener('resize', place);

        var drag = null, dragged = false;
        btn.addEventListener('pointerdown', function (e) {
            if (e.button) { return; }
            drag = { x: e.clientX, y: e.clientY, l: root.offsetLeft, t: root.offsetTop, moved: false };
            try { btn.setPointerCapture(e.pointerId); } catch (er) { /* ignore */ }
        });
        btn.addEventListener('pointermove', function (e) {
            if (! drag) { return; }
            var dx = e.clientX - drag.x, dy = e.clientY - drag.y;
            if (! drag.moved && Math.sqrt(dx * dx + dy * dy) < 6) { return; }
            drag.moved = true; root.classList.add('pi-act-drag'); hidePeek();
            var b = bounds();
            root.style.left = clamp(drag.l + dx, 0, window.innerWidth - BOX) + 'px';
            root.style.top = clamp(drag.t + dy, b.top, b.bottom) + 'px';
        });
        function endDrag() {
            if (! drag) { return; }
            if (drag.moved) {
                pos.right = root.offsetLeft + BOX / 2 > window.innerWidth / 2;
                pos.y = clamp(root.offsetTop / window.innerHeight, 0, 0.95);
                ls.set(K.r, pos.right ? '1' : '0'); ls.set(K.y, pos.y.toFixed(4));
                dragged = true; root.classList.remove('pi-act-drag'); place();
            }
            drag = null;
        }
        btn.addEventListener('pointerup', endDrag);
        btn.addEventListener('pointercancel', endDrag);
        btn.addEventListener('click', function () {
            if (dragged) { dragged = false; return; }
            if (panel.hidden) { openPanel(); } else { closePanel(); }
        });

        /* ------------------------------------------------ the panel */
        var phone = function () { return window.innerWidth < 768; };
        function placePanel() {
            if (phone()) { panel.style.left = panel.style.right = panel.style.top = panel.style.bottom = ''; return; }
            var w = Math.min(360, window.innerWidth - 24), h = panel.offsetHeight || 420;
            var r = root.getBoundingClientRect();
            var left = pos.right ? r.left - w - 6 : r.right + 6;
            left = clamp(left, 12, window.innerWidth - w - 12);
            var top = clamp(r.top + r.height / 2 - h / 2, 12, window.innerHeight - h - 12);
            panel.style.left = left + 'px'; panel.style.top = top + 'px'; panel.style.right = 'auto'; panel.style.bottom = 'auto';
        }
        function openPanel() {
            hidePeek();
            panel.hidden = false; back.hidden = ! phone();
            btn.setAttribute('aria-expanded', 'true');
            muteLabel(); placePanel();
            setTimeout(function () { var f = panel.querySelector('.pi-act-go') || $('pi_act_close'); if (f) { f.focus(); } }, 30);
            poll(true);
        }
        function closePanel(focusBack) {
            if (panel.hidden) { return; }
            panel.hidden = true; back.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            if (focusBack !== false) { btn.focus(); }
        }
        $('pi_act_close').addEventListener('click', function () { closePanel(); });
        back.addEventListener('click', function () { closePanel(false); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && ! panel.hidden && ! document.documentElement.classList.contains('pi-rv-open')) { closePanel(); } });
        document.addEventListener('pointerdown', function (e) {
            if (panel.hidden || panel.contains(e.target) || root.contains(e.target)) { return; }
            if (e.target.closest && e.target.closest('#pi_rv')) { return; }
            closePanel(false);
        }, true);

        /* ------------------------------------------------ the list */
        var GROUPS = ['now', 'unfinished', 'progress'];
        var here = location.pathname + location.search;
        var samePage = function (u) { try { var x = new URL(u, location.href); return x.pathname + x.search === here; } catch (e) { return false; } };
        var items = [];

        function rowHtml(it, i) {
            var act = '';
            if (it.action === 'review') {
                act = '<button type="button" class="pi-act-go" data-i="' + i + '">' + esc(it.label) + '</button>';
            } else if (it.action === 'call') {
                act = '<button type="button" class="pi-act-go" data-i="' + i + '" data-url="' + esc(it.url) + '"><i class="fas fa-door-open" aria-hidden="true"></i>&nbsp;' + esc(it.label) + '</button>';
            } else if (it.url) {
                act = '<a class="pi-act-go' + (it.kind === 'waiting' ? ' alt' : '') + '" href="' + esc(it.url) + '">' + esc(it.label) + '</a>';
            }
            return '<div class="pi-act-row ' + esc(it.group) + '" data-key="' + esc(it.key) + '" data-kind="' + esc(it.kind) + '">'
                + '<span class="pi-act-ic" style="background:' + esc(it.color) + '22;color:' + esc(it.color) + '"><i class="fas ' + esc(it.icon) + '" aria-hidden="true"></i></span>'
                + '<span class="pi-act-tx"><b>' + esc(it.title) + (it.tag ? '<span class="pi-act-tag">' + esc(it.tag) + '</span>' : '') + '</b><small>' + esc(it.sub) + '</small></span>'
                + act + '</div>';
        }

        function doAction(it) {
            if (it.action === 'review') {
                closePanel(false);
                if (window.piApptReview && document.getElementById('pi_rv') && window.piApptReview(it.id, false)) { return; }
                location.href = L.diary;
            } else if (it.action === 'call') {
                var f = $('pi_act_call'); f.action = it.url; f.submit();
            }
        }
        list.addEventListener('click', function (e) {
            var b = e.target.closest('button.pi-act-go');
            if (b) { doAction(items[+b.getAttribute('data-i')]); }
        });

        var seen = ss.get('pi_act_seen', []), peekTimer = null;
        function hidePeek() { clearTimeout(peekTimer); peek.hidden = true; }
        function showPeek(it) {
            if (! panel.hidden) { return; }
            peek.querySelector('b').textContent = it.peek || L.title;
            peek.querySelector('span').textContent = it.title;
            peek.hidden = false;
            clearTimeout(peekTimer); peekTimer = setTimeout(hidePeek, PEEK_MS);
        }
        peek.addEventListener('click', function () { hidePeek(); openPanel(); });

        function render(d) {
            if (! d || d.on === false) { root.hidden = true; closePanel(false); titleFlash(0); return; }
            items = (d.items || []).filter(function (it) { return ! (it.kind === 'resume' && it.url && samePage(it.url)); });
            var now = items.filter(function (it) { return it.group === 'now'; });
            var n = now.length, total = items.length;
            root.hidden = false;
            root.classList.toggle('pi-act-hot', n > 0);
            badge.hidden = total === 0;
            badge.textContent = n > 0 ? n : total;
            badge.classList.toggle('hot', n > 0);
            root.setAttribute('data-needs', String(n)); root.setAttribute('data-total', String(total));
            btn.setAttribute('aria-label', L.btn + (n > 0 ? ' · ' + fill(L.btn_needs, { n: n }) : (total ? ' · ' + fill(L.btn_total, { n: total }) : '')));

            if (! total) {
                list.innerHTML = '<div class="pi-act-empty"><i class="fas fa-check-circle" aria-hidden="true"></i>' + esc(L.empty) + '</div>';
            } else {
                var html = '';
                GROUPS.forEach(function (g) {
                    var rows = '';
                    items.forEach(function (it, i) { if (it.group === g) { rows += rowHtml(it, i); } });
                    if (rows) { html += '<div class="pi-act-g ' + g + '">' + esc(L['g_' + g]) + '</div>' + rows; }
                });
                list.innerHTML = html;
            }
            if (! panel.hidden) { placePanel(); }

            // something new that needs you: chime + peek; a reminder while it waits
            var keys = now.map(function (it) { return it.key; });
            var fresh = now.filter(function (it) { return seen.indexOf(it.key) < 0; });
            if (fresh.length) { chime(); showPeek(fresh[0]); }
            else if (n && Date.now() - lastChime > REMIND_MS) { chime(); }
            seen = keys; ss.set('pi_act_seen', keys);
            titleFlash(n);
        }

        var inflight = false, lastPoll = 0;
        function poll(force) {
            if (document.hidden || inflight) { return; }
            if (! force && Date.now() - lastPoll < 2000) { return; }
            inflight = true; lastPoll = Date.now();
            var x = new XMLHttpRequest();
            x.open('GET', URL_LIST);
            x.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            x.setRequestHeader('Accept', 'application/json');
            x.onload = function () {
                inflight = false;
                if (x.status !== 200) { return; }
                try { render(JSON.parse(x.responseText)); } catch (e) { /* not JSON: signed out */ }
            };
            x.onerror = function () { inflight = false; };
            x.send();
        }
        window.piActivityRefresh = function () { poll(true); };
        window.addEventListener('pi:activity', function () { poll(true); });

        place();
        render(D);
        setInterval(poll, POLL_MS);
        document.addEventListener('visibilitychange', function () { if (! document.hidden) { poll(true); } });
        window.addEventListener('focus', function () { poll(); });
    })();
    </script>
@endif
