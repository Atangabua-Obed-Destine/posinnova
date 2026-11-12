/**
 * PosInnova: "Check this before it is recorded".
 *
 * Before an operation that records or moves money, a dialog shows the amount and
 * a summary, and "Yes, record this" stays disabled until the user ticks
 * "I have checked this against the document, and the figure is right."
 *
 * Two ways to use it:
 *
 *  1. A form opts in with data-record-confirm="payment" (the kind picks the title
 *     and the explanation). Inside the form:
 *       data-confirm-amount     on the field (or element) holding the amount
 *       data-confirm-label="X"  on each field to list in the summary (selects show
 *                               the chosen option, inputs their value)
 *     The form is checked by its own validation first; once confirmed it is
 *     submitted again exactly as before (same button, same handlers).
 *
 *  2. From a script: PosConfirm.ask({kind, amount, rows: [[label, value]], note}, onConfirm)
 *     (the POS uses this before finalizing a sale).
 *
 * Texts and the on/off switches come from layouts/partials/record_confirm.blade.php
 * (window.PosConfirmConfig).
 */
(function ($) {
    'use strict';

    var cfg = window.PosConfirmConfig || { enabled: true, texts: {}, kinds: {} };
    var $modal, pending = null;

    function t(key) { return (cfg.texts && cfg.texts[key]) || key; }

    /** Switched on for this kind of operation? (Business settings can turn it off.) */
    function isOn(kind) { return !!cfg.enabled && !(cfg.off && kind && cfg.off[kind]); }

    function money(n) {
        n = Number(n);
        if (!isFinite(n)) { return '-'; }
        if (typeof __currency_trans_from_en === 'function') { return __currency_trans_from_en(n, true); }
        return n.toFixed(2);
    }

    /** The amount of a field: a formatted number input, or the text of an element. */
    function readAmount(el) {
        var $el = $(el);
        if ($el.is('input, select, textarea')) {
            return typeof __read_number === 'function' ? __read_number($el) : parseFloat(String($el.val()).replace(/[^\d.-]/g, ''));
        }
        var raw = $el.data('confirm-value');
        if (raw !== undefined && raw !== '') { return parseFloat(raw); }
        return typeof __number_uf === 'function' ? __number_uf($el.text()) : parseFloat($el.text().replace(/[^\d.-]/g, ''));
    }

    /** What a field shows: the chosen option of a select, the value of an input, else its text. */
    function readValue(el) {
        var $el = $(el);
        if ($el.is('select')) {
            var txt = $el.find('option:selected').map(function () { return $.trim($(this).text()); }).get().join(', ');
            return $el.val() ? txt : '';
        }
        if ($el.is(':checkbox, :radio')) { return $el.is(':checked') ? t('yes') : t('no'); }
        if ($el.is('input, textarea')) { return $.trim($el.val()); }
        return $.trim($el.text());
    }

    /** Label/value rows; a third value 'strong' (a total), 'minus' (a deduction) or 'warn' styles the row. */
    function fillRows($table, rows, numeric) {
        $.each(rows || [], function (i, r) {
            var v = r[1] === '' || r[1] === null || r[1] === undefined ? '-' : r[1];
            $('<tr>').addClass(r[2] ? 'rc-' + r[2] : '')
                .append($('<th>').text(r[0]))
                .append($('<td>').text(v).toggleClass('rc-num', !!numeric))
                .appendTo($table);
        });
    }

    /** The dialog's content for a form that opted in. */
    function fromForm(form) {
        var $f = $(form), opts = { kind: $f.data('record-confirm') || 'default', rows: [] };
        var $amount = $f.find('[data-confirm-amount]').filter(function () { return $(this).closest('.hide, [hidden]').length === 0; }).first();
        if ($amount.length) {
            var a = readAmount($amount);
            if (isFinite(a)) { opts.amount = a; }
        }
        $f.find('[data-confirm-label]').each(function () {
            var $el = $(this);
            if ($el.closest('.hide, [hidden]').length || ($el.is('input[type=hidden]') && !$el.data('confirm-hidden-ok'))) { return; }
            var v = readValue(this);
            if (v !== '' && v !== null) { opts.rows.push([$el.data('confirm-label'), v]); }
        });
        var note = $f.data('confirm-note');
        if (note) { opts.note = note; }
        return opts;
    }

    function build() {
        if ($modal) { return $modal; }
        $modal = $('#record_confirm_modal');
        $modal.on('change', '#rc_checkbox', function () {
            $modal.find('.rc-yes').prop('disabled', !this.checked);
        });
        $modal.on('click', '.rc-yes', function () {
            if (!$('#rc_checkbox').is(':checked') || !pending) { return; }
            var go = pending;
            pending = null;
            $(this).prop('disabled', true);
            $modal.data('confirmed', true).modal('hide');
            go();
        });
        $modal.on('click', '.rc-back', function () { $modal.modal('hide'); });
        // focus the tick box but open at the top: a long preview starts with its title and total in view
        $modal.on('shown.bs.modal', function () {
            var box = document.getElementById('rc_checkbox');
            try { box.focus({ preventScroll: true }); } catch (e) { box.focus(); }
            $modal.scrollTop(0);
        });
        // over another dialog: its own backdrop must sit above that dialog, not under it
        $modal.on('show.bs.modal', function () {
            setTimeout(function () { $('.modal-backdrop').last().addClass('rc-backdrop'); }, 0);
        });
        // a second dialog over a payment dialog: give the page back its scroll lock
        $modal.on('hidden.bs.modal', function () {
            if ($('.modal.in').length) { $('body').addClass('modal-open'); }
            if (!$modal.data('confirmed') && pending && pending.cancel) { pending.cancel(); }
            pending = null;
            $modal.data('confirmed', false);
        });
        // Enter in the dialog never submits anything behind it
        $modal.on('keydown', function (e) {
            if (e.key === 'Enter' && !$(e.target).is('button')) { e.preventDefault(); }
        });
        return $modal;
    }

    /**
     * Opens the dialog. opts: {kind, amount, rows: [[label, value]], note, title, intro}.
     * onConfirm runs once the box is ticked and "Yes, record this" pressed;
     * onCancel (optional) when the user goes back.
     */
    function ask(opts, onConfirm, onCancel) {
        opts = opts || {};
        if (!isOn(opts.kind) || !$('#record_confirm_modal').length) { onConfirm(); return; }
        var m = build(), kind = cfg.kinds[opts.kind] || cfg.kinds['default'] || {};
        m.find('.rc-title-text').text(opts.title || kind.title || t('title'));
        m.find('.rc-intro').text(opts.intro || kind.intro || t('intro'));
        if (opts.amount !== undefined && opts.amount !== null && isFinite(opts.amount)) {
            m.find('.rc-amount-box').show();
            m.find('.rc-amount-label').text(opts.amountLabel || kind.amount || t('amount'));
            m.find('.rc-amount').text(money(opts.amount));
        } else {
            m.find('.rc-amount-box').hide();
        }
        if (opts.amountText) { m.find('.rc-amount').text(opts.amountText); }
        m.toggleClass('rc-wide', !!opts.wide);
        m.find('.rc-rows-title').text(opts.rowsTitle || '');
        fillRows(m.find('.rc-rows.rc-main').empty(), opts.rows);
        // a table of lines (the products of a purchase)
        var it = opts.items, $iw = m.find('.rc-items-wrap');
        if (it && it.rows && it.rows.length) {
            var $tb = m.find('.rc-items').empty(), num = {};
            var $hr = $('<tr>');
            $.each(it.head || [], function (i, h) {
                h = typeof h === 'object' ? h : { text: h };
                if (h.num) { num[i] = true; }
                $('<th>').text(h.text).toggleClass('rc-num', !!h.num).appendTo($hr);
            });
            $('<thead>').append($hr).appendTo($tb);
            var $body = $('<tbody>').appendTo($tb);
            $.each(it.rows, function (ri, row) {
                var $tr = $('<tr>');
                $.each(row, function (ci, cell) {
                    var $td = $('<td>').toggleClass('rc-num', !!num[ci]);
                    cell = (cell !== null && typeof cell === 'object') ? cell : { text: cell };
                    $td.append(document.createTextNode(cell.text === undefined || cell.text === null || cell.text === '' ? '-' : String(cell.text)));
                    $.each(cell.sub || [], function (k, s) { if (s) { $('<span class="rc-sub">').text(s).appendTo($td); } });
                    if (cell.strong) { $td.addClass('rc-strong'); }
                    $tr.append($td);
                });
                $body.append($tr);
            });
            m.find('.rc-items-title').text(it.title || '');
            m.find('.rc-items-foot').text(it.foot || '');
            $iw.show();
        } else {
            $iw.hide();
        }
        // titled groups side by side (totals, payment)
        var $groups = m.find('.rc-groups').empty();
        $.each(opts.groups || [], function (i, g) {
            if (!g || !g.rows || !g.rows.length) { return; }
            var $g = $('<div class="rc-group">').appendTo($groups);
            $('<div class="rc-sec-title">').text(g.title || '').appendTo($g);
            fillRows($('<table class="rc-rows">').appendTo($g), g.rows, g.num);
        });
        m.find('.rc-note').text(opts.note || '').toggle(!!opts.note);
        m.find('.rc-check-text').text(opts.check || kind.check || t('check'));
        m.find('.rc-yes-text').text(opts.yes || kind.yes || t('yes_record'));
        $('#rc_checkbox').prop('checked', false);
        m.find('.rc-yes').prop('disabled', true);
        pending = onConfirm;
        pending.cancel = onCancel;
        m.data('confirmed', false).modal({ backdrop: 'static', keyboard: true, show: true });
    }

    // forms that opted in: stop the first submit, ask, then submit again for real
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || !form.matches || !form.matches('form[data-record-confirm]') || !isOn(form.getAttribute('data-record-confirm'))) { return; }
        if (form.getAttribute('data-record-confirmed') === '1') {
            form.removeAttribute('data-record-confirmed');
            return;
        }
        // the form's own checks first: an invalid form shows its errors as usual
        if ($(form).data('validator') && !$(form).valid()) { return; }
        var opts = fromForm(form);
        // nothing to check when no money moves (e.g. a new account with no opening balance)
        if (form.hasAttribute('data-confirm-skip-zero') && !(Math.abs(opts.amount || 0) > 0)) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        var submitter = e.submitter && e.submitter.form === form ? e.submitter : null;
        ask(opts, function () {
            form.setAttribute('data-record-confirmed', '1');
            if (typeof form.requestSubmit === 'function') {
                if (submitter) { form.requestSubmit(submitter); } else { form.requestSubmit(); }
            } else {
                $(form).trigger('submit');
            }
        });
    }, true);

    window.PosConfirm = { ask: ask, fromForm: fromForm, enabled: isOn, label: t, money: money };
})(jQuery);
