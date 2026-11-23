{{--
    PosInnova: styles and script of the examination forms (consultation.exam.form / view).
    window.ExamForm.init(root): drawing pads and one-click answers inside root (call again after loading a form).
--}}
@php
    $ex_js_t = [
        'text_prompt' => __('clinic.ex_text_prompt'),
        'text_ph' => __('clinic.ex_text_ph'),
        'clear_confirm' => __('clinic.ex_clear_confirm'),
        'done' => __('clinic.ex_done'),
    ];
@endphp
<style>
    .ex-grid { display:flex; flex-wrap:wrap; margin:0 -8px; }
    .ex-cell { padding:0 8px 14px; box-sizing:border-box; width:100%; }
    @media (min-width:768px) {
        .ex-cols-12 { width:100%; } .ex-cols-9 { width:75%; } .ex-cols-8 { width:66.666%; } .ex-cols-6 { width:50%; } .ex-cols-4 { width:33.333%; } .ex-cols-3 { width:25%; }
    }
    @media (max-width:767px) { .ex-cols-3 { width:50%; } }
    .ex-heading { flex-basis:100%; margin:6px 8px 10px; padding-bottom:4px; border-bottom:2px solid #e0e7ff; color:#3730a3; font-weight:700; font-size:16px; }
    .ex-label { font-weight:600; font-size:13px; margin-bottom:4px; color:#1f2937; }
    .ex-sides { display:flex; flex-wrap:wrap; gap:6px 18px; }
    .ex-side { flex:1 1 260px; min-width:0; display:flex; gap:6px; align-items:flex-start; }
    .ex-side > .ex-chips, .ex-side > .form-control, .ex-side > .ex-number, .ex-side > textarea { flex:1 1 auto; min-width:0; }
    .ex-side-tag { flex:0 0 auto; font-size:11px; font-weight:700; border-radius:6px; padding:2px 7px; margin-top:3px; }
    .ex-side-R { background:#fee2e2; color:#991b1b; } .ex-side-L { background:#dbeafe; color:#1e40af; }
    .ex-chips { display:flex; flex-wrap:wrap; gap:5px; align-items:center; }
    .ex-chip { position:relative; margin:0; font-weight:normal; cursor:pointer; }
    .ex-chip input { position:absolute; opacity:0; width:1px; height:1px; }
    .ex-chip span { display:inline-block; border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:999px; padding:2px 10px; font-size:12px; line-height:1.6; }
    .ex-chip:hover span { border-color:#6366f1; color:#4338ca; }
    .ex-chip input:focus-visible + span { outline:2px solid #6366f1; outline-offset:1px; }
    .ex-chip input:checked + span { background:#eef2ff; border-color:#6366f1; color:#3730a3; font-weight:600; }
    .ex-chip input:checked + span::before { content:"\2713  "; }
    .ex-other { width:150px !important; flex:0 1 150px; height:26px; padding:2px 8px; font-size:12px; border-radius:999px; border-style:dashed; }
    .ex-other:not(:placeholder-shown) { border-style:solid; border-color:#6366f1; background:#eef2ff; }

    .ex-draw-stage { position:relative; border:1px solid #e5e7eb; border-radius:8px; background:#fff; overflow:hidden; }
    .ex-draw-base { display:block; width:100%; height:auto; user-select:none; -webkit-user-drag:none; pointer-events:none; }
    .ex-draw-blank { width:100%; aspect-ratio:3/2; background:#fff; }
    /* A finger (or pen) on a picture scrolls the page like anywhere else: the picture is tapped first to draw
       on it (class ex-live), so nobody draws by accident while scrolling. The mouse always draws. */
    .ex-draw-canvas { position:absolute; inset:0; width:100%; height:100%; touch-action:manipulation; cursor:crosshair; }
    .ex-draw.ex-live .ex-draw-canvas { touch-action:none; }
    .ex-draw.ex-live .ex-draw-stage { border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,.3); }
    .ex-draw-tap { display:none; position:absolute; left:50%; bottom:8px; transform:translateX(-50%); padding:5px 12px; border-radius:999px; background:rgba(30,27,75,.88); color:#fff; font-size:12.5px; font-weight:600; white-space:nowrap; pointer-events:none; }
    .ex-draw-tools .ex-done-btn { display:none; width:auto; padding:0 12px; background:#4f46e5; border-color:#4f46e5; color:#fff; font-weight:700; }
    .ex-draw.ex-live .ex-draw-tools .ex-done-btn { display:inline-block; width:auto; }
    @media (any-pointer:coarse) {
        .ex-draw:not(.ex-live) .ex-draw-tap { display:block; }
        .ex-draw-tools { gap:6px; margin-top:6px; }
        .ex-draw-tools button { width:38px; height:38px; font-size:15px; line-height:36px; }
        .ex-draw-tools .ex-dot { width:16px; height:16px; }
    }
    /* words written on a picture: typed in place, and movable until the consultation is saved (then part of the drawing) */
    .ex-txt { position:absolute; transform:translateY(-50%); font:bold 15px/1 Arial, sans-serif; white-space:pre; cursor:move; user-select:none; -webkit-user-select:none;
        touch-action:manipulation; border-radius:3px; outline:1px dashed transparent; outline-offset:3px; }
    .ex-txt:hover, .ex-draw.ex-live .ex-txt { outline-color:rgba(79,70,229,.6); }
    .ex-draw.ex-live .ex-txt { touch-action:none; }
    .ex-txt.ex-drag { outline:2px solid #4f46e5; background:rgba(238,242,255,.75); }
    .ex-draw[data-tool="eraser"] .ex-txt { cursor:cell; }
    .ex-txt-edit { position:absolute; transform:translateY(-50%); z-index:3; height:36px; width:190px; max-width:92%; padding:2px 8px; border:2px solid #4f46e5; border-radius:6px; background:#fff; color:#111827;
        font:bold 16px Arial, sans-serif; box-shadow:0 2px 10px rgba(0,0,0,.2); }
    .ex-draw-hint { display:none; margin:5px 0 0; font-size:12px; line-height:1.4; color:#4338ca; }
    .ex-draw[data-tool="text"] .ex-draw-hint { display:block; }
    /* full screen: its room is kept even when hidden, so the picture does not jump when "A" is switched on or off */
    .ex-draw.ex-full .ex-draw-hint { display:block; visibility:hidden; font-size:13px; text-align:center; max-width:96vw; }
    .ex-draw.ex-full[data-tool="text"] .ex-draw-hint { visibility:visible; }
    .ex-draw[data-tool="eraser"] .ex-draw-canvas { cursor:cell; }
    .ex-draw[data-tool="text"] .ex-draw-canvas { cursor:text; }
    .ex-draw-tools { display:flex; flex-wrap:wrap; gap:3px; margin-top:4px; }
    .ex-draw-tools button { width:28px; height:28px; padding:0; border:1px solid #e5e7eb; background:#fff; border-radius:6px; color:#374151; font-size:12px; line-height:26px; }
    @media (hover:hover) { .ex-draw-tools button:hover { border-color:#6366f1; } } /* (a finger leaves no "hover" behind: only the tool in use is outlined) */
    .ex-draw-tools button.ex-on, .ex-draw-tools button[aria-pressed="true"] { border-color:#4f46e5; background:#eef2ff; color:#3730a3; box-shadow:inset 0 0 0 1px #4f46e5; }
    .ex-dot { display:inline-block; width:12px; height:12px; border-radius:50%; vertical-align:middle; }
    .ex-draw.ex-drawn .ex-label::after { content:" \270E"; color:#4f46e5; }
    /* full screen, for drawing with a finger or a pen */
    .ex-draw.ex-full { position:fixed; inset:0; z-index:2050; background:rgba(248,250,252,.98); display:flex; flex-direction:column; align-items:center; justify-content:center; padding:10px; }
    .ex-draw.ex-full .ex-label { font-size:16px; }
    .ex-draw.ex-full .ex-draw-tools button { width:40px; height:40px; font-size:16px; line-height:38px; }
    .ex-draw.ex-full .ex-dot { width:18px; height:18px; }
    body.ex-full-open { overflow:hidden; }

    /* read-only */
    .ex-view-sec { margin-bottom:12px; page-break-inside:avoid; }
    .ex-view-sec h4 { margin:0 0 6px; font-size:14px; font-weight:700; color:#3730a3; border-bottom:1px solid #e0e7ff; padding-bottom:3px; }
    .ex-view-draws { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:6px; }
    .ex-view-draw { margin:0; width:180px; }
    .ex-view-draw.ex-w-6, .ex-view-draw.ex-w-8, .ex-view-draw.ex-w-9, .ex-view-draw.ex-w-12 { width:240px; }
    .ex-view-draw figcaption { font-size:11.5px; color:#475569; text-align:center; margin-top:2px; }
    .ex-view-draw .ex-draw-stage { border-color:#cbd5e1; }
    .ex-draw-over { position:absolute; inset:0; width:100%; height:100%; }
    .ex-view-rows { margin:0; }
    .ex-view-rows div { display:flex; gap:8px; padding:2px 0; font-size:13px; border-bottom:1px dotted #e5e7eb; }
    .ex-view-rows dt { flex:0 0 38%; font-weight:600; color:#334155; }
    .ex-view-rows dd { flex:1 1 auto; margin:0; white-space:pre-line; }
    .ex-view-mini .ex-view-draw { width:96px; }
    .ex-view-mini .ex-view-draw figcaption { font-size:10px; }
</style>
<script>
window.ExamForm = (function ($) {
    var T = @json($ex_js_t);
    var W = 1000; // drawing resolution (width); the height follows the picture

    function pad(el) {
        if (el.__exd) { return; }
        var stage = el.querySelector('.ex-draw-stage'), base = el.querySelector('.ex-draw-base');
        var cv = el.querySelector('.ex-draw-canvas'), data = el.querySelector('.ex-draw-data');
        var st = el.__exd = { tool: 'pen', color: '#dc2626', thick: false, undo: [], had: !!el.getAttribute('data-prev'), down: false, ctx: null, labels: [], editing: null };
        el.setAttribute('data-tool', 'pen');

        function setup(nw, nh) {
            if (st.ctx) { return; }
            cv.width = W; cv.height = Math.max(50, Math.round(W * nh / nw));
            st.ctx = cv.getContext('2d');
            var prev = el.getAttribute('data-prev');
            if (prev) {
                var im = new Image();
                im.onload = function () { if (st.painted) { return; } st.ctx.drawImage(im, 0, 0, cv.width, cv.height); el.classList.add('ex-drawn'); };
                im.src = prev;
            }
        }
        if (base) {
            if (base.complete && base.naturalWidth) { setup(base.naturalWidth, base.naturalHeight); }
            base.addEventListener('load', function () { setup(base.naturalWidth || 3, base.naturalHeight || 2); });
            base.addEventListener('error', function () { setup(3, 2); });
        } else {
            setup(3, 2);
        }

        function pt(e) { var r = cv.getBoundingClientRect(); return { x: (e.clientX - r.left) * cv.width / r.width, y: (e.clientY - r.top) * cv.height / r.height }; }
        function scale() { var r = cv.getBoundingClientRect(); return cv.width / Math.max(1, r.width); }
        function width(ev) {
            var w = (st.tool === 'eraser' ? 16 : (st.thick ? 5 : 2.4)) * scale();
            if (ev && ev.pointerType === 'pen' && ev.pressure > 0) { w *= (0.45 + ev.pressure); }
            return w;
        }
        function seg(a, b, ev) {
            var c = st.ctx;
            c.globalCompositeOperation = st.tool === 'eraser' ? 'destination-out' : 'source-over';
            c.strokeStyle = st.color; c.lineWidth = width(ev); c.lineCap = 'round'; c.lineJoin = 'round';
            c.beginPath(); c.moveTo(a.x, a.y); c.lineTo(b.x, b.y); c.stroke();
        }
        // one step back: the strokes (not copied when only a word changed) and the words
        function snapshot(wordsOnly) {
            st.undo.push({ img: wordsOnly ? null : st.ctx.getImageData(0, 0, cv.width, cv.height), labels: JSON.parse(JSON.stringify(st.labels)) });
            if (st.undo.length > 30) { st.undo.shift(); }
        }
        function inkless() { var d = st.ctx.getImageData(0, 0, cv.width, cv.height).data; for (var i = 3; i < d.length; i += 4) { if (d[i]) { return false; } } return true; }
        function blank() { return !st.labels.length && inkless(); }
        function font(l) { return 'bold ' + l.s + 'px Arial, sans-serif'; }
        /** What is saved: the strokes with the words written over them. */
        function image() {
            if (!st.labels.length) { return cv.toDataURL('image/png'); }
            var off = document.createElement('canvas'), c = off.getContext('2d');
            off.width = cv.width; off.height = cv.height;
            c.drawImage(cv, 0, 0);
            st.labels.forEach(function (l) { c.fillStyle = l.c; c.font = font(l); c.textBaseline = 'middle'; c.fillText(l.t, l.x, l.y); });
            return off.toDataURL('image/png');
        }
        function changed() {
            var empty = blank();
            data.value = empty ? (st.had ? 'clear' : '') : image();
            el.classList.toggle('ex-drawn', !empty);
            $(data).trigger('change');
        }

        /* ---- words: typed where the picture is touched, dragged to move, touched again to change ---- */
        function place(node, l) {
            node.style.left = (l.x / cv.width * 100) + '%';
            node.style.top = (l.y / cv.height * 100) + '%';
        }
        function layout() { // the words keep their size next to the strokes when the picture grows (full screen) or shrinks
            var k = scale();
            [].forEach.call(stage.querySelectorAll('.ex-txt'), function (n) { n.style.fontSize = (n.__l.s / k) + 'px'; });
        }
        function render() {
            [].forEach.call(stage.querySelectorAll('.ex-txt'), function (n) { n.parentNode.removeChild(n); });
            st.labels.forEach(function (l) {
                var n = document.createElement('span');
                n.className = 'ex-txt'; n.textContent = l.t; n.style.color = l.c; n.__l = l;
                place(n, l);
                stage.appendChild(n);
                grip(n, l);
            });
            layout();
        }
        function grip(n, l) {
            var d = null;
            n.addEventListener('pointerdown', function (e) {
                if (e.pointerType === 'mouse' && e.button !== 0) { return; }
                if (e.pointerType !== 'mouse' && !el.classList.contains('ex-live')) { return; } // the picture is tapped first
                e.preventDefault();
                if (st.editing) { commit(); return; }
                if (st.tool === 'eraser') { snapshot(true); st.labels.splice(st.labels.indexOf(l), 1); render(); changed(); return; }
                try { n.setPointerCapture(e.pointerId); } catch (x) { /* older browsers */ }
                d = { id: e.pointerId, x: e.clientX, y: e.clientY, ox: l.x, oy: l.y, moved: false };
            });
            n.addEventListener('pointermove', function (e) {
                if (!d || d.id !== e.pointerId) { return; }
                e.preventDefault();
                var dx = e.clientX - d.x, dy = e.clientY - d.y;
                if (!d.moved) {
                    if (Math.abs(dx) < 5 && Math.abs(dy) < 5) { return; }
                    d.moved = true; snapshot(true); n.classList.add('ex-drag');
                }
                var k = scale();
                l.x = Math.max(0, Math.min(cv.width - 30, d.ox + dx * k));
                l.y = Math.max(l.s / 2, Math.min(cv.height - l.s / 2, d.oy + dy * k));
                place(n, l);
            });
            function end(e) {
                if (!d || (e && e.pointerId !== undefined && d.id !== e.pointerId)) { return; }
                var moved = d.moved;
                d = null;
                n.classList.remove('ex-drag');
                if (moved) { changed(); } else if (e && e.type === 'pointerup') { edit(l, null); } // touched without moving: its text is changed
            }
            ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (ev) { n.addEventListener(ev, end); });
        }
        /** The box to type in: at the point touched (p), or over the word being changed (l). */
        function edit(l, p) {
            if (st.editing) { commit(); }
            var at = l || p, inp = document.createElement('input'), r = cv.getBoundingClientRect();
            inp.type = 'text'; inp.className = 'ex-txt-edit'; inp.maxLength = 60; inp.value = l ? l.t : '';
            inp.placeholder = T.text_ph; inp.setAttribute('aria-label', T.text_prompt); inp.setAttribute('autocomplete', 'off'); inp.setAttribute('enterkeyhint', 'done');
            inp.style.color = l ? l.c : st.color;
            stage.appendChild(inp);
            // where it was touched, but never out of the picture
            inp.style.left = Math.max(2, Math.min(at.x / cv.width * r.width, r.width - inp.offsetWidth - 2)) + 'px';
            inp.style.top = Math.max(20, Math.min(at.y / cv.height * r.height, r.height - 20)) + 'px';
            st.editing = { inp: inp, l: l, p: p };
            if (l) { [].forEach.call(stage.querySelectorAll('.ex-txt'), function (n) { if (n.__l === l) { n.style.visibility = 'hidden'; } }); }
            inp.addEventListener('keydown', function (e) {
                if (e.which === 13) { e.preventDefault(); e.stopPropagation(); commit(); } // (Enter must not send the consultation form)
                if (e.which === 27) { e.preventDefault(); e.stopPropagation(); commit(true); }
            });
            inp.addEventListener('blur', function () { setTimeout(function () { if (st.editing && st.editing.inp === inp) { commit(); } }, 0); });
            ['pointerdown', 'click'].forEach(function (ev) { inp.addEventListener(ev, function (e) { e.stopPropagation(); }); });
            inp.focus();
            if (l) { inp.select(); }
        }
        /** Ends the typing: the word is written (or changed; emptied: removed). cancel: left as it was. */
        function commit(cancel) {
            var ed = st.editing;
            if (!ed) { return; }
            st.editing = null;
            var t = $.trim(ed.inp.value.replace(/\s+/g, ' '));
            if (ed.inp.parentNode) { ed.inp.parentNode.removeChild(ed.inp); }
            if (!cancel && ed.l && t !== ed.l.t) {
                snapshot(true);
                if (t) { ed.l.t = t; } else { st.labels.splice(st.labels.indexOf(ed.l), 1); }
            } else if (!cancel && !ed.l && t) {
                snapshot(true);
                st.labels.push({ x: Math.min(ed.p.x, cv.width - 30), y: ed.p.y, t: t, c: st.color, s: st.thick ? 56 : 42 });
            } else {
                render();
                return;
            }
            render();
            changed();
        }
        st.commit = commit;
        st.render = render;
        if (window.ResizeObserver) { new ResizeObserver(layout).observe(stage); } else { $(window).on('resize', layout); }
        $(el).closest('form').on('submit', function () { commit(); }); // a word still being typed is not lost

        // touch and pen: the picture is tapped first, then it takes the strokes (until "Done" or a touch elsewhere)
        st.live = function (on) { el.classList.toggle('ex-live', !!on); if (!on) { st.tap = null; commit(); } };
        // (listened for on the whole picture: the tap may land on a word written on it)
        stage.addEventListener('pointerdown', function (e) {
            if (st.ctx && e.pointerType !== 'mouse' && !el.classList.contains('ex-live')) {
                st.tap = { id: e.pointerId, x: e.clientX, y: e.clientY, at: Date.now() };
            }
        });
        stage.addEventListener('pointerup', function (e) {
            var t = st.tap;
            if (!t || t.id !== e.pointerId) { return; }
            st.tap = null;
            if (Math.abs(e.clientX - t.x) < 12 && Math.abs(e.clientY - t.y) < 12 && Date.now() - t.at < 700) {
                st.live(true);
                if (window.innerWidth < 768) { st.full(true); } // a phone: the picture takes the whole screen
            }
        });
        stage.addEventListener('pointercancel', function () { st.tap = null; }); // the page scrolled: not a tap

        cv.addEventListener('pointerdown', function (e) {
            if (!st.ctx || (e.pointerType === 'mouse' && e.button !== 0)) { return; }
            if (e.pointerType !== 'mouse' && !el.classList.contains('ex-live')) {
                return; // not ours yet: the browser scrolls the page
            }
            e.preventDefault();
            var p = pt(e);
            if (st.editing) { commit(); return; } // a word was being typed: touching the picture ends it, nothing more
            if (st.tool === 'text') {
                // no browser pop-up: the word is typed on the picture itself, once the finger (or the mouse) is lifted there
                st.word = { id: e.pointerId, x: e.clientX, y: e.clientY, p: p };
                return;
            }
            snapshot();
            try { cv.setPointerCapture(e.pointerId); } catch (x) { /* older browsers */ }
            st.down = true; st.last = p;
            seg(p, { x: p.x + 0.01, y: p.y + 0.01 }, e);
        });
        cv.addEventListener('pointermove', function (e) {
            if (!st.down) { return; }
            e.preventDefault();
            var evs = (e.getCoalescedEvents && e.getCoalescedEvents()) || [];
            if (!evs.length) { evs = [e]; }
            evs.forEach(function (ev) { var p = pt(ev); seg(st.last, p, ev); st.last = p; });
        });
        ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (n) {
            cv.addEventListener(n, function () { if (st.down) { st.down = false; changed(); } });
        });
        cv.addEventListener('pointerup', function (e) {
            var w = st.word;
            st.word = null;
            if (w && w.id === e.pointerId && st.tool === 'text' && Math.abs(e.clientX - w.x) < 14 && Math.abs(e.clientY - w.y) < 14) { edit(null, w.p); }
        });
        cv.addEventListener('pointercancel', function () { st.word = null; });

        // full screen (finger / pen on a tablet or touch screen)
        function fit() {
            var hint = el.querySelector('.ex-draw-hint');
            var tools = el.querySelector('.ex-draw-tools').offsetHeight + el.querySelector('.ex-label').offsetHeight + (hint ? hint.offsetHeight : 0) + 50;
            var ratio = cv.width / Math.max(1, cv.height);
            stage.style.width = Math.floor(Math.min(window.innerWidth * 0.96, (window.innerHeight - tools) * ratio)) + 'px';
        }
        st.full = function (on) {
            el.classList.toggle('ex-full', on);
            document.body.classList.toggle('ex-full-open', on);
            var $b = $(el).find('[data-act="full"]');
            $b.find('i').toggleClass('fa-expand', !on).toggleClass('fa-compress', on);
            $b.attr('title', on ? T.done : $b.attr('aria-label'));
            if (on) { fit(); $(window).on('resize.exd', fit); } else { commit(); stage.style.width = ''; $(window).off('resize.exd'); }
            st.live(on); // full screen is for drawing; leaving it gives the picture back to scrolling
            layout();
        };

        $(el).on('click', '.ex-tool', function () {
            var $t = $(this);
            commit();
            // the "A" pressed again turns the writing off: back to the pen used before
            if ($t.data('tool') === 'text' && st.tool === 'text') { $t = $(st.pen || el.querySelector('.ex-tool[data-tool="pen"]')); }
            st.tool = $t.data('tool');
            if ($t.data('color')) { st.color = $t.data('color'); }
            if (st.tool === 'pen') { st.pen = $t[0]; }
            $(el).find('.ex-tool').removeClass('ex-on');
            $t.addClass('ex-on');
            el.setAttribute('data-tool', st.tool);
        });
        $(el).on('click', '.ex-act', function () {
            var act = $(this).data('act');
            if (!st.ctx) { return; }
            commit();
            if (act === 'size') { st.thick = !st.thick; $(this).attr('aria-pressed', st.thick ? 'true' : 'false'); }
            if (act === 'undo' && st.undo.length) {
                var u = st.undo.pop();
                if (u.img) { st.ctx.putImageData(u.img, 0, 0); }
                st.labels = u.labels; render(); changed();
            }
            if (act === 'clear' && !blank()) {
                if (window.confirm(T.clear_confirm)) { snapshot(); st.ctx.clearRect(0, 0, cv.width, cv.height); st.labels = []; render(); changed(); }
            }
            if (act === 'full') { st.full(!el.classList.contains('ex-full')); }
            if (act === 'lock') { if (el.classList.contains('ex-full')) { st.full(false); } else { st.live(false); } }
        });
    }

    // a touch anywhere else ends the drawing: the next swipe over the picture scrolls the page again
    document.addEventListener('pointerdown', function (e) {
        [].forEach.call(document.querySelectorAll('.ex-draw.ex-live'), function (el) {
            if (!el.contains(e.target) && !el.classList.contains('ex-full') && el.__exd) { el.__exd.live(false); }
        });
    }, true);

    $(document).on('keydown', function (e) {
        if (e.which === 27) { $('.ex-draw.ex-full').each(function () { this.__exd && this.__exd.full(false); }); }
    });

    // one answer: clicking the chosen one again takes it back; typing "other" replaces it
    $(document).on('click', '.ex-chip input[type=radio]', function () {
        var $r = $(this);
        if ($r.data('on')) { this.checked = false; $r.data('on', false); $r.trigger('change'); return; }
        $r.closest('.ex-chips').find('input[type=radio]').data('on', false);
        $r.data('on', true);
        $r.closest('.ex-chips').find('.ex-other').val('');
    });
    $(document).on('input', '.ex-other', function () {
        var $c = $(this).closest('.ex-chips');
        if ($.trim(this.value) !== '') { $c.find('input[type=radio]').prop('checked', false).data('on', false); }
    });

    function init(root) {
        $(root || document).find('.ex-draw').each(function () { pad(this); });
        $(root || document).find('.ex-chip input[type=radio]:checked').data('on', true);
    }
    /** Something was answered or drawn in root. */
    function touched(root) {
        var $r = $(root);
        return $r.find('.ex-chip input:checked').length > 0
            || $r.find('.ex-other, input[type=text], input[type=number], textarea').filter(function () { return $.trim(this.value) !== ''; }).length > 0
            || $r.find('.ex-draw.ex-drawn').length > 0;
    }
    $(function () { init(document); });

    /** Puts a drawing kept from an earlier, unsaved page back on its picture (url: a PNG data URL, or 'clear'). */
    function paint(el, url) {
        var st = el.__exd;
        if (!st) { return; }
        st.painted = true;
        (function wait(n) {
            if (!st.ctx) { if (n < 80) { setTimeout(function () { wait(n + 1); }, 100); } return; }
            var cv = el.querySelector('.ex-draw-canvas'), data = el.querySelector('.ex-draw-data');
            st.labels = []; st.render(); // (the words of the page before are in the image brought back)
            if (url === 'clear') { st.ctx.clearRect(0, 0, cv.width, cv.height); data.value = 'clear'; el.classList.remove('ex-drawn'); return; }
            var im = new Image();
            im.onload = function () { st.ctx.clearRect(0, 0, cv.width, cv.height); st.ctx.drawImage(im, 0, 0, cv.width, cv.height); data.value = url; el.classList.add('ex-drawn'); };
            im.src = url;
        })(0);
    }

    return { init: init, touched: touched, paint: paint };
})(jQuery);
</script>
