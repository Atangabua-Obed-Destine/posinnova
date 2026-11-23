{{--
    PosInnova: what a doctor typed in the consultation form is kept on the device until it is saved, so a
    page closed by mistake (or a phone that reloads the tab) loses nothing: on coming back to the same
    patient the form is filled in again, with a line that says so and a way to start empty.
    Kept in the browser (localStorage) under the visit and the user. Never sent anywhere; removed by the
    consultation page that opens after a successful save. Photos are not part of it.
    $visit, $c_edit (the draft being continued, or null)
--}}
<script>
$(function () {
    var form = document.getElementById('consultation_form');
    if (!form) { return; }
    var store = null;
    try { store = window.localStorage; store.getItem('x'); } catch (e) { store = null; }
    if (!store) { return; }

    // (a correction to a finished consultation is kept apart: "_fix")
    var KEY = 'pi_consult_v{{ (int) $visit->id }}_u{{ (int) auth()->id() }}{{ ! empty($correcting) ? '_fix' . (int) $c_edit->id : '' }}';
    var SERVER_AT = {{ $c_edit && $c_edit->updated_at ? $c_edit->updated_at->getTimestamp() * 1000 : 0 }};
    var SKIP = { _token: 1, _method: 1, visit_id: 1, attach_token: 1, search_prescription_product: 1, bmi_display: 1, action: 1, 'exam[present]': 1, 'exam[template_id]': 1 };
    var exBody = document.getElementById('exam_form_body'), rxBody = document.getElementById('prescription_rows');
    var exSel = document.getElementById('exam_template');
    var CF = window.ConsultForm || {};

    /* ---- reading the form ---- */
    function controls(root, own) {
        var f = {}, c = [];
        [].forEach.call(root.querySelectorAll('input, textarea, select'), function (el) {
            var t = (el.type || '').toLowerCase();
            if (!el.name || SKIP[el.name] || t === 'hidden' || t === 'file' || t === 'submit' || t === 'button') { return; }
            if (own && ((exBody && exBody.contains(el)) || (rxBody && rxBody.contains(el)))) { return; }
            if (t === 'checkbox' || t === 'radio') { if (el.checked) { c.push([el.name, el.value]); } return; }
            f[el.name] = el.value;
        });
        return { f: f, c: c };
    }
    function readRx() {
        if (!rxBody) { return null; }
        var rows = [];
        $(rxBody).children('tr').each(function () {
            if (this.getAttribute('data-locked')) { return; }
            var $t = $(this), free = $t.attr('data-free') == 1;
            var g = function (n) { return $t.find('input[name$="[' + n + ']"]').val() || ''; };
            rows.push({ id: g('id') || null, free_text: free, name: free ? ($t.find('.rx_drug_name').val() || '') : $t.find('td:first strong').first().text(),
                variation_id: $t.attr('data-variation') || null, here: $t.attr('data-here') !== undefined ? $t.attr('data-here') : null, status: '',
                outside: $t.find('.rx_outside').is(':checked'), strength: g('strength'), route: g('route'), dosage: g('dosage'), frequency: g('frequency'),
                duration: g('duration'), quantity: g('quantity'), notes: g('notes') });
        });
        return rows;
    }
    function readDraws() {
        var d = {};
        if (!exBody) { return d; }
        [].forEach.call(exBody.querySelectorAll('.ex-draw'), function (el) {
            var v = el.querySelector('.ex-draw-data').value;
            if (v) { d[el.getAttribute('data-block')] = v; } // only what was drawn (or rubbed out) on this page
        });
        return d;
    }
    function read() {
        return { main: controls(form, true), rx: readRx(), exam: { tpl: exSel ? (exSel.value || '') : '', body: exBody ? controls(exBody, false) : { f: {}, c: [] }, draws: readDraws() } };
    }

    /* ---- writing it back ---- */
    function apply(root, part, own) {
        var checked = {};
        (part.c || []).forEach(function (p) { checked[p[0] + '\n' + p[1]] = 1; });
        [].forEach.call(root.querySelectorAll('input, textarea, select'), function (el) {
            var t = (el.type || '').toLowerCase();
            if (!el.name || SKIP[el.name] || t === 'hidden' || t === 'file' || t === 'submit' || t === 'button') { return; }
            if (own && ((exBody && exBody.contains(el)) || (rxBody && rxBody.contains(el)))) { return; }
            if (t === 'checkbox' || t === 'radio') { el.checked = !!checked[el.name + '\n' + el.value]; return; }
            if (!(el.name in part.f) || el.value === part.f[el.name]) { return; }
            if (el.tagName === 'SELECT' && part.f[el.name] !== '' && ![].some.call(el.options, function (o) { return o.value === part.f[el.name]; })) {
                el.appendChild(new Option(part.f[el.name], part.f[el.name])); // an entry typed on the spot
            }
            el.value = part.f[el.name];
        });
    }
    function applyExam(ex, done) {
        function fill() {
            apply(exBody, ex.body, false);
            window.ExamForm.init(exBody);
            [].forEach.call(exBody.querySelectorAll('.ex-draw'), function (el) {
                var v = ex.draws[el.getAttribute('data-block')];
                if (v) { window.ExamForm.paint(el, v); }
            });
            done();
        }
        if (!exBody || !exSel) { return done(); }
        if (String(exSel.value || '') === String(ex.tpl || '')) { return ex.tpl ? fill() : done(); }
        if (!CF.loadExam) { return done(); }
        CF.loadExam(ex.tpl, function (ok) { if (ok && ex.tpl) { fill(); } else { done(); } });
    }
    function restore(snap, done) {
        apply(form, snap.main, true);
        $('#consultation_type, #specialty, #referral_to').trigger('change.select2');
        $('.specialty_field').toggle($('#consultation_type').val() === 'specialist');
        $('.vitals-bmi').first().trigger('input');
        if (window.PiPoints) { window.PiPoints.init(form); } // the text boxes take the height of what came back
        if (snap.rx && rxBody && CF.addRx) {
            $(rxBody).children('tr').not('[data-locked]').remove();
            snap.rx.forEach(function (row) { CF.addRx(row, true); });
        }
        applyExam(snap.exam || { tpl: '', body: { f: {}, c: [] }, draws: {} }, done);
    }

    /* ---- keeping it ---- */
    var base = null, last = null, started = false;
    function write() {
        if (!started) { return; }
        var now = read(), s = JSON.stringify(now);
        if (s === base || s === last) { return; }
        var snap = { at: Date.now(), data: now };
        try {
            store.setItem(KEY, JSON.stringify(snap));
        } catch (e) { // too big (drawings): keep the words at least
            try { now.exam.draws = {}; store.setItem(KEY, JSON.stringify({ at: Date.now(), data: now })); } catch (x) { return; }
        }
        last = s;
    }
    function start(restored) {
        var s = JSON.stringify(read());
        if (restored) { last = s; base = '\u0000'; } else { base = s; }
        started = true;
        setInterval(write, 3000);
        $(form).on('change', function () { setTimeout(write, 50); });
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') { write(); } });
        window.addEventListener('pagehide', write);
        $(form).on('submit', write);
    }

    var snap = null;
    try { snap = JSON.parse(store.getItem(KEY) || 'null'); } catch (e) { snap = null; }
    if (snap && (!snap.at || !snap.data || snap.at <= SERVER_AT || Date.now() - snap.at > 7 * 86400000)) {
        try { store.removeItem(KEY); } catch (e) { /* ignore */ }
        snap = null;
    }
    if (!snap) { setTimeout(function () { start(false); }, 1500); return; }

    // let the page finish its own start (select2, the specialty's form) before filling it in again
    setTimeout(function () {
        restore(snap.data, function () {
            var box = document.getElementById('cf_restored'), d = new Date(snap.at);
            var two = function (n) { return (n < 10 ? '0' : '') + n; };
            var same = d.toDateString() === new Date().toDateString();
            box.querySelector('[data-when]').textContent = (same ? '' : two(d.getDate()) + '/' + two(d.getMonth() + 1) + ' ') + two(d.getHours()) + ':' + two(d.getMinutes());
            box.hidden = false;
            setTimeout(function () { start(true); }, 800);
        });
    }, 600);

    $('#cf_restored_drop').on('click', function () {
        if (!window.confirm($(this).data('confirm'))) { return; }
        try { store.removeItem(KEY); } catch (e) { /* ignore */ }
        started = false;
        window.onbeforeunload = null;
        window.location.reload();
    });
});
</script>
