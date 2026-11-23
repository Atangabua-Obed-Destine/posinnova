{{--
    PosInnova online channels: E-Ride orders on the POS screen.
    Waiting orders are shown loudly (badge + sound). "Ring up" fills the normal cart (products,
    E-Ride prices, unit, customer, delivery, note); the salesperson checks it and finishes the sale
    herself. Nothing is recorded until she does. Included by sale_pos/create when a location is linked.
--}}
@php
    $ch_T = collect([
        'btn', 'title', 'none', 'new_one', 'new_many', 'ring', 'paid_shop', 'paid_eride', 'unlinked', 'cancelled_t', 'cancelled_d',
        'cancelled_deleted', 'seen', 'open_sale', 'cart_busy', 'loading', 'banner', 'finish_shop', 'finish_eride', 'not_added',
        'total_diff', 'draft_hint', 'status_cancelled', 'status_rung', 'sent', 'queued', 'mismatch', 'offline', 'revoked',
        'way_delivery', 'way_collect', 'way_eat_in', 'way_room', 'paid_room', 'finish_room', 'room_refused', 'cancelled_room', 'unbill', 'drop', 'cancel_toast', 'no_pay_method', 'no_credit_button', 'added', 'customer', 'paid_at',
    ])->mapWithKeys(fn ($k) => [$k => __('channel.pos_'.$k)])->all();
@endphp
<style>
#ch_pos_btn { position: fixed; right: 16px; bottom: 92px; z-index: 1035; display: inline-flex; align-items: center; gap: 8px; border: 0; border-radius: 999px;
    padding: 10px 16px; background: #1d2939; color: #fff; font-weight: 700; box-shadow: 0 6px 16px rgba(16,24,40,.25); cursor: pointer; }
#ch_pos_btn .n { background: #f04438; color: #fff; border-radius: 999px; min-width: 24px; height: 24px; padding: 0 7px; display: inline-flex; align-items: center; justify-content: center; }
#ch_pos_btn .n.zero { background: #475467; }
#ch_pos_btn.ring { animation: chPulse 1.2s ease-in-out infinite; background: #b42318; }
@keyframes chPulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.07); } }
@media (prefers-reduced-motion: reduce) { #ch_pos_btn.ring { animation: none; } }
#ch_pos_modal .ch-o { border: 1px solid #eaecf0; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
#ch_pos_modal .ch-o-h { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; align-items: center; }
#ch_pos_modal .ch-o-h b { font-size: 16px; }
#ch_pos_modal .ch-o ul { margin: 8px 0; padding-left: 18px; }
#ch_pos_modal .ch-o li.un { color: #b42318; }
#ch_pos_modal .ch-pill { display: inline-block; border-radius: 999px; padding: 2px 9px; font-size: 12px; font-weight: 600; background: #f2f4f7; color: #344054; }
#ch_pos_modal .ch-pill.shop { background: #dcfae6; color: #067647; } #ch_pos_modal .ch-pill.eride { background: #fef0c7; color: #93370d; }
#ch_pos_modal .ch-c { border: 1px solid #fda29b; background: #fffbfa; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
#ch_pos_modal .ch-btn-r { min-height: 40px; font-weight: 700; }
#ch_pos_banner { border: 2px solid #1849a9; background: #eff4ff; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px; }
#ch_pos_banner .t { font-weight: 700; font-size: 15px; }
#ch_pos_banner .miss { color: #b42318; margin-top: 6px; }
#ch_pos_banner .diff { color: #93370d; margin-top: 6px; font-weight: 600; }
#ch_pos_banner .acts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
</style>

<button type="button" id="ch_pos_btn" hidden aria-haspopup="dialog">
    <span>{{ __('channel.pos_btn') }}</span><span class="n zero" id="ch_pos_n">0</span>
</button>

<div class="modal fade" id="ch_pos_modal" tabindex="-1" role="dialog" aria-labelledby="ch_pos_title">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="ch_pos_title">{{ __('channel.pos_title') }}</h4>
            </div>
            <div class="modal-body" id="ch_pos_list"></div>
        </div>
    </div>
</div>

<script>
(function () {
    var LINKED = @json(array_map('intval', $channel_locations ?? []));
    var URL_PENDING = @json(url('eride/pos/pending'));
    var URL_ORDER = @json(url('eride/pos/order'));
    var URL_SEEN = @json(url('eride/pos/cancel-seen'));
    var URL_UNBILL = @json(url('eride/pos/unbill'));
    var TOKEN = @json(csrf_token());
    var T = @json($ch_T);
    var POLL_MS = 20000;
    var state = { orders: [], cancelled: [], known: {}, lastBeep: 0, busy: false, current: null };

    function esc(s) { return $('<div>').text(s == null ? '' : String(s)).html(); }
    function money(n) { return typeof __currency_trans_from_en === 'function' ? __currency_trans_from_en(n, true) : String(n); }
    function loc() { return parseInt($('input#location_id').val(), 10) || 0; }
    function linkedHere() { return LINKED.indexOf(loc()) !== -1; }

    // A short two-tone chime, made in the browser (no sound file to load).
    function beep() {
        try {
            var C = window.AudioContext || window.webkitAudioContext;
            if (!C) { return; }
            var ctx = beep.ctx || (beep.ctx = new C());
            [0, 0.28, 0.56].forEach(function (t, i) {
                var o = ctx.createOscillator(), g = ctx.createGain();
                o.type = 'sine'; o.frequency.value = i === 1 ? 660 : 880;
                g.gain.setValueAtTime(0.0001, ctx.currentTime + t);
                g.gain.exponentialRampToValueAtTime(0.35, ctx.currentTime + t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.24);
                o.connect(g); g.connect(ctx.destination);
                o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.26);
            });
        } catch (e) { /* no sound on this device */ }
    }

    function paint() {
        var n = state.orders.length + state.cancelled.length;
        var $b = $('#ch_pos_btn');
        $b.prop('hidden', !linkedHere());
        $('#ch_pos_n').text(n).toggleClass('zero', n === 0);
        $b.toggleClass('ring', n > 0);
        $b.attr('aria-label', T.btn + ': ' + n);

        var h = '';
        if (state.error === 'revoked') { h += '<div class="alert alert-danger">' + esc(T.revoked) + '</div>'; }
        else if (state.error === 'offline') { h += '<div class="alert alert-warning">' + esc(T.offline) + '</div>'; }
        state.cancelled.forEach(function (c) {
            var d = c.sale_deleted ? T.cancelled_deleted : (c.room ? T.cancelled_room : T.cancelled_d);
            d = d.replace(':number', c.number).replace(':invoice', c.invoice_no || '—').replace(':room', c.room || '');
            h += '<div class="ch-c" data-order="' + esc(c.order_id) + '"><b>' + esc(T.cancelled_t.replace(':number', c.number)) + '</b>' +
                '<div>' + esc(d) + (c.reason ? ' (' + esc(c.reason) + ')' : '') + '</div><div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;">' +
                (c.sale_id ? '<a class="btn btn-default btn-sm" target="_blank" href="' + @json(url('sells')) + '/' + c.sale_id + '">' + esc(T.open_sale) + '</a>' : '') +
                (c.room ? '<button type="button" class="btn btn-primary btn-sm ch-unbill">' + esc(T.unbill.replace(':room', c.room)) + '</button>' : '') +
                '<button type="button" class="btn btn-danger btn-sm ch-seen" data-number="' + esc(c.number) + '">' + esc(T.seen) + '</button></div></div>';
        });
        if (!state.orders.length && !state.cancelled.length) {
            h += '<p class="text-muted" style="margin:0;">' + esc(T.none) + '</p>';
        }
        state.orders.forEach(function (o) {
            var lines = o.lines.map(function (l) {
                return '<li class="' + (l.linked ? '' : 'un') + '">' + esc(l.qty) + ' × ' + esc(l.name) + (l.unit && String(l.name).indexOf(l.unit) === -1 ? ' (' + esc(l.unit) + ')' : '') +
                    ' — ' + esc(money(l.total)) + (l.linked ? '' : ' · ' + esc(T.unlinked)) + '</li>';
            }).join('');
            var way = o.way === 'delivery' ? T.way_delivery : (o.way === 'eat_in' ? T.way_eat_in : (o.way === 'room' ? T.way_room : T.way_collect));
            h += '<div class="ch-o" data-order="' + esc(o.order_id) + '"><div class="ch-o-h"><div><b>#' + esc(o.number) + '</b> · ' + esc(o.customer) +
                (o.phone ? ' · ' + esc(o.phone) : '') + '<div class="text-muted small">' + esc(way) + (o.paid_at ? ' · ' + esc(T.paid_at) + ' ' + esc(moment(o.paid_at).format('LT')) : '') + '</div></div>' +
                '<span class="ch-pill ' + (o.paid_to === 'shop' ? 'shop' : 'eride') + '">' + esc(o.paid_to === 'eride' ? T.paid_eride : (o.paid_to === 'room_bill' ? T.paid_room : T.paid_shop)) + '</span></div>' +
                '<ul>' + lines + '</ul>' + (o.note ? '<div class="text-muted">“' + esc(o.note) + '”</div>' : '') +
                '<div class="ch-o-h" style="margin-top:6px;"><b>' + esc(money(o.total)) + '</b>' +
                '<button type="button" class="btn btn-primary ch-btn-r ch-ring">' + esc(T.ring) + '</button></div></div>';
        });
        $('#ch_pos_list').html(h);
    }

    function poll() {
        if (!linkedHere()) { paint(); return; }
        $.getJSON(URL_PENDING, { location_id: loc() }).done(function (r) {
            if (!r || !r.linked) { state.orders = []; state.cancelled = []; paint(); return; }
            state.error = r.error;
            state.orders = r.orders || [];
            state.cancelled = r.cancelled || [];
            var fresh = false, freshCancel = false;
            state.orders.forEach(function (o) {
                if (!state.known[o.order_id]) { state.known[o.order_id] = 1; fresh = true; }
            });
            state.cancelled.forEach(function (o) {
                if (state.known[o.order_id] !== 'c') { state.known[o.order_id] = 'c'; freshCancel = true; }
            });
            var n = state.orders.length + state.cancelled.length;
            var now = Date.now();
            if (n > 0 && (fresh || freshCancel || now - state.lastBeep > 60000)) {
                state.lastBeep = now;
                beep();
                if (fresh && typeof toastr !== 'undefined') {
                    toastr.info(state.orders.length === 1 ? T.new_one : T.new_many.replace(':n', state.orders.length));
                }
                if (freshCancel && typeof toastr !== 'undefined') {
                    toastr.warning(T.cancel_toast);
                }
            }
            paint();
        });
    }

    function cartEmpty() { return $('table#pos_table tbody tr.product_row').length === 0; }

    function setField(name, value) {
        var $f = $('form#add_pos_sell_form');
        var $i = $f.find('input[name="' + name + '"]');
        if (!$i.length) { $i = $('<input type="hidden">').attr('name', name).appendTo($f); }
        $i.val(value);
    }

    function clearRing() {
        state.current = null;
        ['channel_order_id', 'channel_order_number', 'channel_paid_to'].forEach(function (n) { setField(n, ''); });
        $('#ch_pos_banner').remove();
    }

    function ringUp(order_id) {
        if (state.busy) { return; }
        if (!cartEmpty() && !(state.current && state.current.order_id === order_id)) {
            toastr.warning(T.cart_busy);
            return;
        }
        state.busy = true;
        $.getJSON(URL_ORDER + '/' + encodeURIComponent(order_id), { location_id: loc() }).done(function (r) {
            if (!r || r.status === 'error') { state.busy = false; toastr.error((r && r.message) || T.offline); return; }
            if (r.status === 'cancelled') { state.busy = false; toastr.warning(T.status_cancelled); poll(); return; }
            if (r.status === 'rung_up') { state.busy = false; toastr.info(T.status_rung.replace(':invoice', r.invoice_no || '')); poll(); return; }
            $('#ch_pos_modal').modal('hide');
            fill(r.cart);
        }).fail(function () { state.busy = false; toastr.error(T.offline); });
    }

    function fill(cart) {
        clearRing();
        state.current = cart;
        setField('channel_order_id', cart.order_id);
        setField('channel_order_number', cart.number);
        setField('channel_paid_to', cart.paid_to);
        toastr.info(T.loading.replace(':n', cart.number));

        // The customer first: the price a line comes at can depend on the customer.
        if (cart.contact_id) {
            var $c = $('select#customer_id');
            if (!$c.find('option[value="' + cart.contact_id + '"]').length) {
                $c.append(new Option(cart.contact_text || ('#' + cart.contact_id), cart.contact_id, true, true));
            }
            $c.val(String(cart.contact_id)).trigger('change');
        }

        var header = loc();
        var price_group = $('#price_group').length ? $('#price_group').val() : '';
        var skipped = (cart.missing || []).slice();
        var i = 0;
        var next = function () {
            if (i >= cart.lines.length) { return done(); }
            var line = cart.lines[i++];
            $.ajax({
                method: 'GET', url: '/sells/pos/get_product_row/' + line.variation_id + '/' + header, dataType: 'json',
                data: { product_row: $('input#product_row_count').val(), customer_id: $('select#customer_id').val(), is_direct_sell: false,
                    price_group: price_group, quantity: line.quantity, line_location_id: header }
            }).done(function (res) {
                if (!res || !res.success || !res.html_content) { skipped.push(line); return; }
                pos_insert_product_row(res);
                var $row = $('table#pos_table tbody tr.product_row').last();
                if (line.sub_unit_id) {
                    var $s = $row.find('select.sub_unit');
                    if ($s.length && $s.find('option[value="' + line.sub_unit_id + '"]').length) { $s.val(String(line.sub_unit_id)).trigger('change'); }
                }
                var $q = $row.find('input.pos_quantity');
                if ($q.length && __read_number($q) !== line.quantity) { __write_number($q, line.quantity); $q.change(); }
                // What the customer paid on E-Ride, tax included, per unit.
                var $p = $row.find('input.pos_unit_price_inc_tax');
                if ($p.length) { __write_number($p, line.unit_price_inc_tax); $p.change(); }
            }).fail(function () { skipped.push(line); }).always(next);
        };

        var done = function () {
            state.busy = false;
            // Order discount, as a fixed amount (never the business's default percentage).
            $('input#discount_type').val('fixed');
            __write_number($('input#discount_amount'), cart.discount || 0);
            if ($('#discount_type_modal').length) { $('#discount_type_modal').val('fixed'); __write_number($('#discount_amount_modal'), cart.discount || 0); }
            // Delivery: the native shipping fields (shown in Sell › Shipments).
            $('#shipping_details_modal').val(cart.shipping_details);
            $('#shipping_address_modal').val(cart.shipping_address);
            $('#delivered_to_modal').val(cart.delivered_to);
            if ($('#shipping_status_modal option[value="ordered"]').length) { $('#shipping_status_modal').val('ordered').trigger('change'); }
            __write_number($('input#shipping_charges_modal'), cart.shipping_charges || 0);
            if ($('#posShippingModalUpdate').length) { $('#posShippingModalUpdate').trigger('click'); }
            else {
                $('input#shipping_details').val(cart.shipping_details); $('input#shipping_address').val(cart.shipping_address);
                $('input#delivered_to').val(cart.delivered_to); $('input#shipping_status').val('ordered');
                __write_number($('input#shipping_charges'), cart.shipping_charges || 0);
            }
            $('textarea#sale_note').val(cart.sale_note);
            if (typeof pos_total_row === 'function') { pos_total_row(); }
            banner(cart, skipped);
        };
        next();
    }

    function banner(cart, skipped) {
        $('#ch_pos_banner').remove();
        var method_label = cart.pay_method ? ($('#method_0 option[value="' + cart.pay_method + '"]').text() || cart.pay_method) : '';
        var h = '<div id="ch_pos_banner" role="status"><div class="t">' + esc(T.banner.replace(':n', cart.number)) + '</div>' +
            '<div class="small">' + esc(T.customer) + ': ' + esc(cart.delivered_to) + (cart.shipping_address ? ' · ' + esc(cart.shipping_address.split('\n')[0]) : '') + '</div>';
        if (skipped.length) {
            h += '<div class="miss"><b>' + esc(T.not_added) + '</b> ' + skipped.map(function (l) {
                return esc((l.qty || l.quantity) + ' × ' + l.name + ' — ' + money(l.total));
            }).join('; ') + '</div>';
        }
        h += '<div class="diff" id="ch_pos_diff" hidden></div><div class="small text-muted" style="margin-top:4px;">' + esc(T.draft_hint) + '</div><div class="acts">';
        if (cart.paid_to === 'room_bill' && cart.room_charge_error) {
            h += '<div class="miss"><b>' + esc(T.room_refused) + '</b> ' + esc(cart.room_charge_error) + '</div>';
        }
        if (cart.paid_to === 'room_bill' && cart.room_charge) {
            h += '<button type="button" class="btn btn-primary" id="ch_finish">' + esc(T.finish_room.replace(':room', cart.room_charge.room || '—')) + '</button>';
        } else if (cart.paid_to === 'eride') {
            h += '<button type="button" class="btn btn-warning" id="ch_finish">' + esc(T.finish_eride) + '</button>';
        } else {
            h += '<button type="button" class="btn btn-success" id="ch_finish">' + esc(T.finish_shop.replace(':method', method_label || '—')) + '</button>';
        }
        h += '<button type="button" class="btn btn-default" id="ch_drop">' + esc(T.drop) + '</button></div></div>';
        $('form#add_pos_sell_form').prepend(h);
        checkTotal();
    }

    function checkTotal() {
        if (!state.current) { return; }
        var pos = Math.round(__read_number($('input#final_total_input')) || 0);
        var want = state.current.expected_total;
        var $d = $('#ch_pos_diff');
        if (Math.abs(pos - want) >= 1) {
            $d.text(T.total_diff.replace(':pos', money(pos)).replace(':order', money(want))).prop('hidden', false);
        } else {
            $d.prop('hidden', true);
        }
    }

    $(document).on('click', '#ch_finish', function () {
        var cart = state.current;
        if (!cart) { return; }
        if (cart.paid_to === 'room_bill' && cart.room_charge && window.hotelRoomCharge) {
            window.hotelRoomCharge.finish({ id: cart.room_charge.reservation_id, contact_id: cart.contact_id, room: cart.room_charge.room, guest: cart.room_charge.guest });
            return;
        }
        if (cart.paid_to === 'eride') {
            var $credit = $('button.pos-express-finalize[data-pay_method="credit_sale"]').first();
            if ($credit.length && !$credit.hasClass('hide')) { $credit.trigger('click'); return; }
            // No "Credit sale" button on this till: the payment window with nothing received.
            __write_number($('#amount_0'), 0);
            $('#amount_0').change();
            toastr.info(T.no_credit_button);
            $('button#pos-finalize').first().trigger('click');
            return;
        }
        var $m = $('#method_0');
        if (cart.pay_method && $m.find('option[value="' + cart.pay_method + '"]').length) {
            $m.val(cart.pay_method).change();
        } else {
            toastr.warning(T.no_pay_method);
        }
        __write_number($('#amount_0'), __read_number($('input#final_total_input')));
        $('#amount_0').change();
        $('button#pos-finalize').first().trigger('click');
    });

    $(document).on('click', '#ch_drop', function () {
        clearRing();
        $('#pos-cancel').trigger('click');
    });

    $(document).on('click', '#ch_pos_btn', function () { paint(); $('#ch_pos_modal').modal('show'); poll(); });
    $(document).on('click', '#ch_pos_list .ch-ring', function () { ringUp($(this).closest('[data-order]').attr('data-order')); });
    $(document).on('click', '#ch_pos_list .ch-unbill', function () {
        var $c = $(this).closest('[data-order]'), $b = $(this).prop('disabled', true);
        $.post(URL_UNBILL, { _token: TOKEN, location_id: loc(), order_id: $c.attr('data-order') })
            .done(function (r) { if (r && r.ok) { toastr.success(r.message); $c.remove(); poll(); } else { toastr.error((r && r.message) || T.offline); $b.prop('disabled', false); } })
            .fail(function () { toastr.error(T.offline); $b.prop('disabled', false); });
    });
    $(document).on('click', '#ch_pos_list .ch-seen', function () {
        var $c = $(this).closest('[data-order]');
        $.post(URL_SEEN, { _token: TOKEN, location_id: loc(), order_id: $c.attr('data-order'), number: $(this).attr('data-number') })
            .always(function () { $c.remove(); poll(); });
    });

    // The sale was saved: tell the salesperson what E-Ride said, and start afresh.
    $(document).ajaxSuccess(function (e, xhr, settings) {
        var r = xhr && xhr.responseJSON;
        if (!r || !r.channel || (settings.type || '').toUpperCase() !== 'POST') { return; }
        if (r.channel.warning) { toastr.error(r.channel.warning, '', { timeOut: 0, extendedTimeOut: 0, closeButton: true }); }
        else if (r.channel.rung_up) {
            toastr.success(r.channel.delivered ? T.sent : T.queued);
            if (r.channel.matches === false) { toastr.warning(T.mismatch, '', { timeOut: 0, extendedTimeOut: 0, closeButton: true }); }
        }
        clearRing();
        setTimeout(poll, 500);
    });

    // Clearing the POS form (after a sale, or Cancel) also forgets the order.
    if (typeof window.reset_pos_form === 'function') {
        var orig = window.reset_pos_form;
        window.reset_pos_form = function () { clearRing(); return orig.apply(this, arguments); };
    }
    $(document).on('change', 'table#pos_table, input#final_total_input', function () { setTimeout(checkTotal, 0); });
    $(document).on('click', '#posShippingModalUpdate, #posEditDiscountModalUpdate', function () { setTimeout(checkTotal, 0); });

    $(function () {
        poll();
        setInterval(poll, POLL_MS);
        // The POS location can change on screen.
        $(document).on('change', 'select#select_location_id', function () { clearRing(); state.known = {}; setTimeout(poll, 300); });
    });
})();
</script>
