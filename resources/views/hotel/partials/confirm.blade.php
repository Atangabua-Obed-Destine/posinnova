{{--
    PosInnova hotel: "check this before it is saved", with the app's own dialog (public/js/record-confirm.js, PosConfirm).
    HtConfirm.attach(form, build): the form is not sent at once; build(form, button) gives the dialog's content
    ({title, intro, amount, amountLabel, rows: [[label, value, 'strong'|'minus'|'warn']], groups, note, yes}).
    It is sent once the user ticked the box and pressed the button. Off when Business settings turn these checks off.
--}}
@php
    $ht_cf_t = collect(['check', 'intro', 'guest', 'new_guest', 'no_guest', 'people', 'people_v', 'special', 'tax', 'paid_now', 'nothing_now', 'still', 'credit', 'id', 'others', 'late', 'short', 'short_leave', 'change'])
        ->mapWithKeys(fn ($k) => [$k => __('hotel.cf_' . $k)])->merge(['yes' => __('messages.yes')])->all();
@endphp
<script>
(function () {
    if (window.HtConfirm) return;
    var money = function (n) { n = Number(n) || 0; return window.__currency_trans_from_en ? __currency_trans_from_en(n, true) : Math.round(n).toLocaleString(); };
    var num = function (s) {
        if (s == null || String(s).trim() === '') return 0;
        var v = window.__number_uf ? __number_uf(String(s)) : parseFloat(String(s).replace(/[^\d.\-]/g, ''));
        return isFinite(v) ? Number(v) : 0;
    };
    var date = function (iso) { return iso ? iso.split('-').reverse().join('/') : ''; };
    // what a field shows: the chosen words of a list, the typed text
    var shown = function (f) {
        if (!f) return '';
        if (f.tagName === 'SELECT') { var o = f.selectedOptions && f.selectedOptions[0]; return o && f.value !== '' ? o.textContent.replace(/\s+/g, ' ').trim() : ''; }
        if (f.type === 'date') return date(f.value);
        return (f.value || '').trim();
    };
    // the filled, visible fields of a form, by their labels
    var auto = function (form) {
        var rows = [];
        form.querySelectorAll('input, select, textarea').forEach(function (f) {
            if (!f.name || f.type === 'hidden' || f.disabled || !(f.offsetWidth || f.offsetHeight)) return;
            if (f.type === 'checkbox' || f.type === 'radio') {
                if (f.checked) { var lb = f.closest('label'); if (lb) rows.push([lb.textContent.replace(/\s+/g, ' ').trim(), T.yes]); }
                return;
            }
            var v = shown(f), l = f.id && document.querySelector('label[for="' + f.id + '"]');
            if (v !== '' && l) rows.push([l.textContent.replace(/\s*\*\s*$/, '').replace(/\s+/g, ' ').trim(), v]);
        });
        return rows;
    };
    var T = {!! json_encode($ht_cf_t) !!};
    window.HtConfirm = {
        money: money, num: num, date: date, shown: shown, auto: auto, t: T,
        attach: function (form, build) {
            if (!form || form.__htcf) return;
            form.__htcf = true;
            form.addEventListener('submit', function (e) {
                if (!window.PosConfirm || !PosConfirm.enabled('hotel')) return;
                if (form.__htGo) { form.__htGo = false; return; }
                e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation();
                var by = e.submitter && e.submitter.form === form ? e.submitter : null;
                // another script may already have greyed the button: it must work again if the user goes back
                setTimeout(function () { form.querySelectorAll('button').forEach(function (b) { if (b.dataset.htOff !== '1') b.disabled = false; }); }, 0);
                var d = build(form, by) || {};
                if (!d.yes && by) d.yes = by.textContent.replace(/\s+/g, ' ').trim();
                d.kind = d.kind || 'hotel';
                d.check = d.check || T.check;
                d.intro = d.intro || T.intro;
                d.rows = (d.rows || []).filter(function (r) { return r && r[1] != null && String(r[1]).trim() !== ''; });
                PosConfirm.ask(d, function () {
                    form.__htGo = true;
                    if (form.requestSubmit) { if (by) form.requestSubmit(by); else form.requestSubmit(); }
                    else HTMLFormElement.prototype.submit.call(form);
                });
            }, true);
        },
    };
})();
</script>
