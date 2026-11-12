/* ==========================================================================
 * PosInnova: unfinished-purchase recovery (Add Purchase), like the POS cart.
 *
 * A purchase being typed in lives only in the page. A reload, a closed tab or a
 * crash loses the supplier, every line with its costs, lots and expiry dates,
 * and the payment. This keeps a copy in this browser and offers it back on the
 * next visit to Add Purchase.
 *
 *   1. It never restores by itself: the user sees what was found and presses
 *      "Put it back" (or "Discard").
 *   2. Each line is fetched again from the server, exactly as picking the
 *      product does, then the values the user typed (quantity, unit, costs,
 *      discount, tax, selling price, lot, dates...) are put back on it and the
 *      totals are worked out again by the page's own code.
 *   3. Saving does not throw the copy away blindly: the purchase list forgets it
 *      only when it reports the purchase as saved. If saving failed, it is kept.
 *
 * The copy belongs to one user in one business; another user on the same PC
 * never sees it. It is offered for 12 hours. Files (the attached document)
 * cannot be kept by a browser; the user is reminded to attach it again.
 * ========================================================================== */
(function ($) {
    'use strict';

    var KEY = 'posinnova_purchase_snapshot_v1';
    var restoring = false;   // no snapshot writes while lines are going back in
    var settled = false;     // an empty form must not delete the copy before it was offered
    var save_timer = null;

    function cfg() {
        var $el = $('#purchase_recovery');
        if (!$el.length) { return null; }
        var a = function (n) { var v = $el.attr('data-' + n); return typeof v === 'undefined' ? '' : String(v); };
        return {
            page: a('page'),
            user_id: a('user-id'),
            business_id: a('business-id'),
            max_age_mins: parseInt(a('max-age-mins'), 10) || 720,
            has_errors: a('has-errors') === '1',
            lang: {
                heading: a('lang-heading'), help: a('lang-help'), help_saving: a('lang-help-saving'),
                restore: a('lang-restore'), discard: a('lang-discard'), restoring: a('lang-restoring'),
                restored: a('lang-restored'), discarded: a('lang-discarded'), skipped: a('lang-skipped'),
                document: a('lang-document'), kept: a('lang-kept'), no_supplier: a('lang-no-supplier')
            }
        };
    }

    function $form() { return $('form#add_purchase_form'); }

    function read() {
        try {
            var raw = localStorage.getItem(KEY);
            return raw ? JSON.parse(raw) : null;
        } catch (e) { return null; }
    }
    function write(snap) { try { localStorage.setItem(KEY, JSON.stringify(snap)); } catch (e) { /* best effort */ } }
    function clear() { try { localStorage.removeItem(KEY); } catch (e) { /* best effort */ } }

    /** Ours to use here: same user, same business, not too old. */
    function mine(snap, c) {
        if (!snap || snap.v !== 1 || !snap.lines) { return false; }
        if (String(snap.user_id) !== c.user_id || String(snap.business_id) !== c.business_id) { return false; }
        var age = (Date.now() - (parseInt(snap.ts, 10) || 0)) / 60000;
        return age >= 0 && age <= c.max_age_mins;
    }

    // ---- taking the copy -------------------------------------------------------------------

    var SKIP_HEADER = /^(_token|_method|document|contact_id|location_id|final_total|total_before_tax|tax_amount|advance_balance|purchase_order_ids\[\]|purchase_requisition_ids\[\])$/;

    function collect_lines() {
        var lines = [];
        $('#purchase_entry_table tbody tr').each(function () {
            var $tr = $(this);
            var $q = $tr.find('input.purchase_quantity');
            if (!$q.length) { return; }
            var m = /^purchases\[(\d+)\]/.exec($q.attr('name') || '');
            if (!m) { return; }
            var prefix = 'purchases[' + m[1] + ']';
            var fields = {};
            $tr.find('input[name], select[name], textarea[name]').each(function () {
                var name = this.name;
                if (name.indexOf(prefix) !== 0) { return; }
                fields[name.substring(prefix.length)] = $(this).val();
            });
            if (!fields['[variation_id]'] || !fields['[product_id]']) { return; }
            var $n = $tr.children('td').eq(1).clone();
            $n.find('small, a, .label, input, select, br').remove();
            lines.push({
                product_id: String(fields['[product_id]']),
                variation_id: String(fields['[variation_id]']),
                target_location_id: String(fields['[location_id]'] || $tr.attr('data-target_location_id') || ''),
                po_id: String($tr.attr('data-purchase_order_id') || ''),
                req_id: String($tr.attr('data-purchase_requisition_id') || ''),
                name: $.trim($n.text().replace(/\s+/g, ' ')).substring(0, 80),
                fields: fields
            });
        });
        return lines;
    }

    function collect_header() {
        var out = [];
        $form().find('input[name], select[name], textarea[name]').each(function () {
            if ($(this).closest('#purchase_entry_table').length) { return; }
            var name = this.name, type = (this.type || '').toLowerCase();
            if (SKIP_HEADER.test(name) || type === 'file' || type === 'button' || type === 'submit') { return; }
            if (type === 'checkbox' || type === 'radio') {
                out.push({ n: name, k: 'chk', val: this.value, on: this.checked });
            } else {
                out.push({ n: name, k: 'val', v: $(this).val() });
            }
        });
        return out;
    }

    function multi(sel) {
        var $s = $(sel);
        if (!$s.length) { return null; }
        return {
            ids: $s.val() || [],
            opts: $s.find('option:selected').map(function () { return [[this.value, $(this).text()]]; }).get()
        };
    }

    function take() {
        var c = cfg();
        if (!c || c.page !== 'create' || restoring || !$form().length) { return; }
        var lines = collect_lines();
        if (!lines.length) {
            // nothing worth keeping, once the page has had its chance to offer the old copy
            if (settled) {
                var old = read();
                if (old && mine(old, c) && !old.submitted) { clear(); }
            }
            return;
        }
        var $sup = $('#supplier_id');
        var sdata = null;
        try { sdata = $sup.data('select2') ? ($sup.select2('data') || [])[0] : null; } catch (e) { sdata = null; }
        var supplier = $sup.val() ? {
            id: String($sup.val()),
            text: $.trim($sup.find('option:selected').text()),
            data: sdata ? {
                id: sdata.id, text: sdata.text, business_name: sdata.business_name, supplier_business_name: sdata.supplier_business_name,
                name: sdata.name, contact_id: sdata.contact_id, pay_term_number: sdata.pay_term_number, pay_term_type: sdata.pay_term_type,
                balance: sdata.balance, address_line_1: sdata.address_line_1, address_line_2: sdata.address_line_2,
                city: sdata.city, state: sdata.state, country: sdata.country, zip_code: sdata.zip_code
            } : null
        } : null;
        var doc = $form().find('input[type=file][name=document]').get(0);
        write({
            v: 1,
            ts: Date.now(),
            user_id: c.user_id,
            business_id: c.business_id,
            submitted: false,
            location_id: String($('#location_id').val() || ''),
            supplier: supplier,
            po: multi('#purchase_order_ids'),
            req: multi('#purchase_requisition_ids'),
            header: collect_header(),
            pay_user_edited: !!$form().find('.payment_row input.payment-amount').first().data('user_edited'),
            doc_name: doc && doc.files && doc.files.length ? doc.files[0].name : '',
            lines: lines
        });
    }

    function schedule() {
        if (restoring) { return; }
        // the user started a new purchase instead: that is their answer to the offer
        if ($('#purchase_recovery_banner').length && $('#purchase_entry_table tbody tr .purchase_quantity').length) {
            $('#purchase_recovery_banner').remove();
            settled = true;
        }
        clearTimeout(save_timer);
        save_timer = setTimeout(take, 400);
    }

    // ---- offering it back -------------------------------------------------------------------

    function offer() {
        var c = cfg();
        if (!c || c.page !== 'create' || !$form().length) { settled = true; return; }
        if ($('#purchase_recovery_banner').length) { return; }
        var snap = read();
        if (!mine(snap, c)) {
            if (snap && String(snap.user_id) === c.user_id && String(snap.business_id) === c.business_id) { clear(); }
            settled = true;
            return;
        }
        // lines already on the page (a requisition or order loaded into it): not a recovery case
        if ($('#purchase_entry_table tbody tr .purchase_quantity').length) { settled = true; return; }
        // came back here because the server refused the form: it was not saved
        if (snap.submitted && c.has_errors) { snap.submitted = false; write(snap); }

        var when = '';
        try { when = typeof moment !== 'undefined' ? moment(snap.ts).format('LT') : new Date(snap.ts).toLocaleTimeString(); } catch (e) { when = ''; }
        var sup = snap.supplier && snap.supplier.text ? snap.supplier.text : c.lang.no_supplier;
        var help = (snap.submitted ? c.lang.help_saving : c.lang.help)
            .replace('__COUNT__', snap.lines.length).replace('__SUPPLIER__', sup).replace('__TIME__', when);

        var $b = $(
            '<div class="alert" id="purchase_recovery_banner" role="alert" style="margin-bottom:12px;">' +
                '<i class="fa fa-history"></i> <strong class="prr-heading"></strong> <span class="prr-help"></span>' +
                '<ol class="prr-lines" style="margin:6px 0 0 18px;padding:0;font-size:13px;"></ol>' +
                '<div style="margin-top:8px;">' +
                    '<button type="button" class="btn btn-primary btn-sm" id="purchase_recovery_restore"></button> ' +
                    '<button type="button" class="btn btn-default btn-sm" id="purchase_recovery_discard"></button> ' +
                    '<span class="prr-status text-muted" style="margin-left:8px;"></span>' +
                '</div>' +
            '</div>'
        );
        $b.addClass(snap.submitted ? 'alert-danger' : 'alert-warning');
        $b.find('.prr-heading').text(c.lang.heading);
        $b.find('.prr-help').text(help);
        $.each(snap.lines.slice(0, 8), function (i, l) {
            var q = l.fields && l.fields['[quantity]'] ? ' × ' + l.fields['[quantity]'] : '';
            $('<li>').text((l.name || l.variation_id) + q).appendTo($b.find('.prr-lines'));
        });
        if (snap.lines.length > 8) { $('<li>').text('…').appendTo($b.find('.prr-lines')); }
        $b.find('#purchase_recovery_restore').text(c.lang.restore);
        $b.find('#purchase_recovery_discard').text(c.lang.discard);
        $b.data('snap', snap);
        $form().prepend($b);
    }

    // ---- putting it back --------------------------------------------------------------------

    /** Wait until the page's own AJAX calls (supplier orders, requisitions) are done. */
    function when_idle(cb) {
        var tries = 0;
        (function poll() {
            if ($.active === 0 || tries++ > 60) { cb(); return; }
            setTimeout(poll, 100);
        })();
    }

    function set_select2(sel, saved) {
        var $s = $(sel);
        if (!$s.length || !saved || !saved.ids || !saved.ids.length) { return; }
        $.each(saved.opts || [], function (i, o) {
            if (!$s.find('option[value="' + o[0] + '"]').length) { $s.append(new Option(o[1], o[0], false, false)); }
        });
        $s.val(saved.ids).trigger('change.select2');   // no select2:select: that would load the lines a second time
    }

    function set_date($el, v) {
        $el.val(v);
        try { if ($el.data('datepicker')) { $el.datepicker('update', v); } } catch (e) { /* plain input */ }
    }

    /** The user's values back onto a freshly fetched row, in the order the page's handlers expect. */
    function apply_line($tr, line) {
        var m = /^purchases\[(\d+)\]/.exec($tr.find('input.purchase_quantity').attr('name') || '');
        if (!m) { return; }
        var prefix = 'purchases[' + m[1] + ']';
        var f = line.fields || {};
        var el = function (suffix) { return $tr.find('[name="' + prefix + suffix + '"]'); };
        var has = function (suffix) { return Object.prototype.hasOwnProperty.call(f, suffix) && f[suffix] !== null; };

        // 1. the unit first: changing it rescales cost and selling price from the base unit
        if (has('[sub_unit_id]')) {
            var $su = el('[sub_unit_id]');
            if ($su.find('option[value="' + f['[sub_unit_id]'] + '"]').length && $su.val() !== f['[sub_unit_id]']) {
                $su.val(f['[sub_unit_id]']).trigger('change');
            }
        }
        // 2. the values the cost calculation reads, then the selling price (the profit % is worked out from it)
        if (has('[quantity]')) { el('[quantity]').val(f['[quantity]']); }
        if (has('[purchase_line_tax_id]')) {
            var $tx = el('[purchase_line_tax_id]');
            if ($tx.find('option[value="' + f['[purchase_line_tax_id]'] + '"]').length) { $tx.val(f['[purchase_line_tax_id]']); }
        }
        if (has('[discount_percent]')) { el('[discount_percent]').val(f['[discount_percent]']); }
        if (has('[default_sell_price]')) { el('[default_sell_price]').val(f['[default_sell_price]']); }
        // 3. the cost: its handler works out cost before/after tax, the line totals and the page totals
        if (has('[pp_without_discount]')) { el('[pp_without_discount]').val(f['[pp_without_discount]']).trigger('change'); }
        el('[quantity]').trigger('change');
        if (has('[profit_percent]')) { el('[profit_percent]').val(f['[profit_percent]']); }
        // 4. dates (no change event: a new MFG date would recompute the expiry the user may have typed)
        if (has('[mfg_date]')) { set_date(el('[mfg_date]'), f['[mfg_date]']); }
        if (has('[exp_date]')) { set_date(el('[exp_date]'), f['[exp_date]']); }
        // 5. everything else the row holds (lot, second-unit quantity, per-unit prices, links), as typed
        var computed = /^\[(item_tax|purchase_price|purchase_price_inc_tax|quantity|pp_without_discount|discount_percent|default_sell_price|profit_percent|sub_unit_id|purchase_line_tax_id|mfg_date|exp_date|product_id|variation_id)\]$/;
        $.each(f, function (suffix, v) {
            if (computed.test(suffix) || v === null) { return; }
            var $e = el(suffix);
            if ($e.length) {
                if ($e.val() !== v) {
                    $e.val(v);
                    if ($e.closest('.per_unit_prices_wrapper').length) { $e.trigger('change'); }
                }
            } else if (/^\[(purchase_order_line_id|purchase_requisition_line_id)\]$/.test(suffix) && v) {
                // the link to the purchase order / requisition line this came from
                $('<input type="hidden">').attr('name', prefix + suffix).val(v).appendTo($tr.children('td').eq(1));
            }
        });
        if (line.po_id) { $tr.attr('data-purchase_order_id', line.po_id).data('purchase_order_id', line.po_id); }
        if (line.req_id) { $tr.attr('data-purchase_requisition_id', line.req_id).data('purchase_requisition_id', line.req_id); }
        if (typeof update_purchase_per_base_hint === 'function') { update_purchase_per_base_hint($tr); }
    }

    function restore_header(snap) {
        var $f = $form();
        var set_all = function () {
            $.each(snap.header || [], function (i, h) {
                var $e = $f.find('[name="' + h.n.replace(/"/g, '\\"') + '"]').filter(function () { return $(this).closest('#purchase_entry_table').length === 0; });
                if (!$e.length) { return; }
                if (h.k === 'chk') {
                    $e.filter(function () { return this.value === h.val; }).prop('checked', !!h.on);
                } else if ($e.is('select')) {
                    var vals = $.isArray(h.v) ? h.v : [h.v];
                    var ok = $.grep(vals, function (v) { return v === '' || v === null || $e.find('option[value="' + v + '"]').length; });
                    if (ok.length === vals.length) { $e.val(h.v); if ($e.data('select2')) { $e.trigger('change.select2'); } }
                } else {
                    $e.val(h.v);
                }
            });
        };
        set_all();
        // the payment method shows its own fields and picks a default account: let it, then put the user's back
        $f.find('.payment_types_dropdown').trigger('change');
        set_all();
        // open the folded sections that hold something, so it can be seen
        var num = function (sel) { return __read_number($f.find(sel), true) || 0; };
        if ($f.find('select[name="discount_type"]').val() && num('[name="discount_amount"]')) { $('#pi_purchase_discount_collapse').collapse('show'); }
        if ($f.find('select[name="tax_id"]').val()) { $('#pi_purchase_discount_collapse').collapse('show'); }
        if (num('[name="shipping_charges"]') || $.trim($f.find('[name="shipping_details"]').val() || '')) { $('#pi_purchase_shipping_collapse').collapse('show'); }
        for (var k = 1; k <= 4; k++) {
            if (num('#additional_expense_value_' + k) || $.trim($('#additional_expense_key_' + k).val() || '')) { $('#additional_expenses_div').show(); }
        }
        // the payment amount: kept as typed when the user had changed it, otherwise it follows the total
        var $pay = $f.find('.payment_row input.payment-amount').first();
        var saved_pay = null;
        $.each(snap.header || [], function (i, h) { if ($pay.length && h.n === $pay.attr('name')) { saved_pay = h.v; } });
        if ($pay.length) { $pay.data('user_edited', !!snap.pay_user_edited); }
        update_table_total();
        update_grand_total();
        if ($pay.length && snap.pay_user_edited && saved_pay !== null) {
            $pay.val(saved_pay);
            var due = (__read_number($('input#grand_total_hidden'), true) || 0) - (__read_number($pay, true) || 0);
            $('#payment_due').text(__currency_trans_from_en(due, true, true));
        }
    }

    function restore(snap) {
        var c = cfg();
        if (!c || !snap) { return; }
        var $b = $('#purchase_recovery_banner');
        $b.find('button').prop('disabled', true);
        $b.find('.prr-status').text(c.lang.restoring);
        restoring = true;
        var skipped = [];

        // 1. location (changing it empties the table and loads its requisitions)
        if (snap.location_id && $('#location_id').find('option[value="' + snap.location_id + '"]').length && $('#location_id').val() !== snap.location_id) {
            $('#location_id').val(snap.location_id).trigger('change');
        }
        // 2. supplier (loads its open purchase orders); address, pay term and advance balance as when it was picked
        if (snap.supplier && snap.supplier.id) {
            var $sup = $('#supplier_id');
            if (!$sup.find('option[value="' + snap.supplier.id + '"]').length) {
                $sup.append(new Option(snap.supplier.text, snap.supplier.id, true, true));
            }
            $sup.val(snap.supplier.id).trigger('change');
            if (snap.supplier.data) {
                if (typeof set_supplier_address === 'function') { set_supplier_address(snap.supplier.data); }
                if (snap.supplier.data.balance !== undefined) {
                    $('#advance_balance_text').text(__currency_trans_from_en(snap.supplier.data.balance, true));
                    $('#advance_balance').val(snap.supplier.data.balance);
                }
            }
        }
        // the exchange rate before the lines: a new line is converted with it
        $.each(snap.header || [], function (i, h) { if (h.n === 'exchange_rate') { $('input#exchange_rate').val(h.v); } });

        when_idle(function () {
            set_select2('#purchase_order_ids', snap.po);
            set_select2('#purchase_requisition_ids', snap.req);
            $('#purchase_entry_table tbody').html('');

            var add = function (i) {
                if (i >= snap.lines.length) { finish(); return; }
                var line = snap.lines[i];
                var row_count = $('#row_count').val();
                var data = {
                    product_id: line.product_id, variation_id: line.variation_id, row_count: row_count,
                    location_id: $('#location_id').val(), supplier_id: $('#supplier_id').val()
                };
                if (line.target_location_id) { data.target_location_id = line.target_location_id; }
                $.ajax({ method: 'POST', url: '/purchases/get_purchase_entry_row', dataType: 'html', data: data })
                    .done(function (html) {
                        var before = $('#purchase_entry_table tbody tr').length;
                        if (html && $(html).find('.purchase_quantity').length) {
                            append_purchase_lines(html, row_count);
                        }
                        var $rows = $('#purchase_entry_table tbody tr');
                        if ($rows.length > before) {
                            apply_line($rows.last(), line);
                        } else {
                            skipped.push(line.name || line.variation_id);
                        }
                    })
                    .fail(function () { skipped.push(line.name || line.variation_id); })
                    .always(function () { add(i + 1); });
            };

            var finish = function () {
                restore_header(snap);
                update_table_sr_number();
                restoring = false;
                settled = true;
                $('#purchase_recovery_banner').remove();
                var put = $('#purchase_entry_table tbody tr .purchase_quantity').length;
                clear();
                take();
                if (put) { toastr.success(c.lang.restored.replace('__COUNT__', put)); }
                if (skipped.length) { toastr.warning(c.lang.skipped.replace('__PRODUCTS__', skipped.join(', '))); }
                if (snap.doc_name) { toastr.info(c.lang.document.replace('__NAME__', snap.doc_name)); }
            };

            add(0);
        });
    }

    // ---- wiring -------------------------------------------------------------------------------

    $(function () {
        var c = cfg();
        if (!c) { return; }

        if (c.page === 'list') {
            // back on the list after saving: forget the copy if it was saved, keep it if saving failed
            var snap = read();
            if (snap && snap.submitted && String(snap.user_id) === c.user_id && String(snap.business_id) === c.business_id) {
                var st = String($('#status_span').attr('data-status') || '');
                if (st === '1') {
                    clear();
                } else if (st !== '') {
                    snap.submitted = false;
                    write(snap);
                    toastr.info(c.lang.kept);
                }
            }
            return;
        }

        if (c.page !== 'create' || !$form().length) { return; }

        $(document).on('change input', '#add_purchase_form', schedule);
        var tbody = document.querySelector('#purchase_entry_table tbody');
        if (tbody && window.MutationObserver) {
            new MutationObserver(schedule).observe(tbody, { childList: true });
        }
        // on its way to the server: mark it, so a copy is never mistaken for an unsaved purchase
        $(document).on('submit', '#add_purchase_form', function () {
            clearTimeout(save_timer);
            if (restoring) { return; }
            take();
            var s = read();
            if (s) { s.submitted = true; s.ts = Date.now(); write(s); }
        });

        $(document).on('click', '#purchase_recovery_restore', function (e) {
            e.preventDefault();
            var snap = $('#purchase_recovery_banner').data('snap');
            if (snap) { restore(snap); }
        });
        $(document).on('click', '#purchase_recovery_discard', function (e) {
            e.preventDefault();
            settled = true;
            clear();
            $('#purchase_recovery_banner').remove();
            toastr.info(c.lang.discarded);
        });

        // after select2, the sole-supplier pick and the payment defaults have settled
        setTimeout(offer, 600);
    });
})(jQuery);
