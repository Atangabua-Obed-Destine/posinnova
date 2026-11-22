{{--
    PosInnova: styles and script of the examination forms (consultation.exam.form / view).
    window.ExamForm.init(root): drawing pads and one-click answers inside root (call again after loading a form).
--}}
@php
    $ex_js_t = [
        'text_prompt' => __('clinic.ex_text_prompt'),
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
    .ex-draw[data-tool="eraser"] .ex-draw-canvas { cursor:cell; }
    .ex-draw[data-tool="text"] .ex-draw-canvas { cursor:text; }
    .ex-draw-tools { display:flex; flex-wrap:wrap; gap:3px; margin-top:4px; }
    .ex-draw-tools button { width:28px; height:28px; padding:0; border:1px solid #e5e7eb; background:#fff; border-radius:6px; color:#374151; font-size:12px; line-height:26px; }
    .ex-draw-tools button:hover { border-color:#6366f1; }
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
        var st = el.__exd = { tool: 'pen', color: '#dc2626', thick: false, undo: [], had: !!el.getAttribute('data-prev'), down: false, ctx: null };
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
        function snapshot() { st.undo.push(st.ctx.getImageData(0, 0, cv.width, cv.height)); if (st.undo.length > 30) { st.undo.shift(); } }
        function blank() { var d = st.ctx.getImageData(0, 0, cv.width, cv.height).data; for (var i = 3; i < d.length; i += 4) { if (d[i]) { return false; } } return true; }
        function changed() {
            var empty = blank();
            data.value = empty ? (st.had ? 'clear' : '') : cv.toDataURL('image/png');
            el.classList.toggle('ex-drawn', !empty);
            $(data).trigger('change');
        }

        // touch and pen: the picture is tapped first, then it takes the strokes (until "Done" or a touch elsewhere)
        st.live = function (on) { el.classList.toggle('ex-live', !!on); if (!on) { st.tap = null; } };
        cv.addEventListener('pointerup', function (e) {
            var t = st.tap;
            if (!t || t.id !== e.pointerId) { return; }
            st.tap = null;
            if (Math.abs(e.clientX - t.x) < 12 && Math.abs(e.clientY - t.y) < 12 && Date.now() - t.at < 700) {
                st.live(true);
                if (window.innerWidth < 768) { st.full(true); } // a phone: the picture takes the whole screen
            }
        });
        cv.addEventListener('pointercancel', function () { st.tap = null; }); // the page scrolled: not a tap

        cv.addEventListener('pointerdown', function (e) {
            if (!st.ctx || (e.pointerType === 'mouse' && e.button !== 0)) { return; }
            if (e.pointerType !== 'mouse' && !el.classList.contains('ex-live')) {
                st.tap = { id: e.pointerId, x: e.clientX, y: e.clientY, at: Date.now() };
                return; // not ours yet: the browser scrolls the page
            }
            e.preventDefault();
            var p = pt(e);
            if (st.tool === 'text') {
                var t = window.prompt(T.text_prompt);
                if (t && $.trim(t)) {
                    snapshot();
                    var c = st.ctx;
                    c.globalCompositeOperation = 'source-over';
                    c.fillStyle = st.color;
                    c.font = 'bold ' + Math.round((st.thick ? 20 : 15) * scale()) + 'px Arial, sans-serif';
                    c.textBaseline = 'middle';
                    c.fillText($.trim(t), p.x, p.y);
                    changed();
                }
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

        // full screen (finger / pen on a tablet or touch screen)
        function fit() {
            var tools = el.querySelector('.ex-draw-tools').offsetHeight + el.querySelector('.ex-label').offsetHeight + 50;
            var ratio = cv.width / Math.max(1, cv.height);
            stage.style.width = Math.floor(Math.min(window.innerWidth * 0.96, (window.innerHeight - tools) * ratio)) + 'px';
        }
        st.full = function (on) {
            el.classList.toggle('ex-full', on);
            document.body.classList.toggle('ex-full-open', on);
            var $b = $(el).find('[data-act="full"]');
            $b.find('i').toggleClass('fa-expand', !on).toggleClass('fa-compress', on);
            $b.attr('title', on ? T.done : $b.attr('aria-label'));
            if (on) { fit(); $(window).on('resize.exd', fit); } else { stage.style.width = ''; $(window).off('resize.exd'); }
            st.live(on); // full screen is for drawing; leaving it gives the picture back to scrolling
        };

        $(el).on('click', '.ex-tool', function () {
            var $t = $(this);
            st.tool = $t.data('tool');
            if ($t.data('color')) { st.color = $t.data('color'); }
            $(el).find('.ex-tool').removeClass('ex-on');
            $t.addClass('ex-on');
            el.setAttribute('data-tool', st.tool);
        });
        $(el).on('click', '.ex-act', function () {
            var act = $(this).data('act');
            if (!st.ctx) { return; }
            if (act === 'size') { st.thick = !st.thick; $(this).attr('aria-pressed', st.thick ? 'true' : 'false'); }
            if (act === 'undo' && st.undo.length) { st.ctx.putImageData(st.undo.pop(), 0, 0); changed(); }
            if (act === 'clear' && !blank()) {
                if (window.confirm(T.clear_confirm)) { snapshot(); st.ctx.clearRect(0, 0, cv.width, cv.height); changed(); }
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
            if (url === 'clear') { st.ctx.clearRect(0, 0, cv.width, cv.height); data.value = 'clear'; el.classList.remove('ex-drawn'); return; }
            var im = new Image();
            im.onload = function () { st.ctx.clearRect(0, 0, cv.width, cv.height); st.ctx.drawImage(im, 0, 0, cv.width, cv.height); data.value = url; el.classList.add('ex-drawn'); };
            im.src = url;
        })(0);
    }

    return { init: init, touched: touched, paint: paint };
})(jQuery);
</script>
