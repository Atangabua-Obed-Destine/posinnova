{{--
    PosInnova hotel <-> E-Ride Hotels: E-Ride requests on the hotel home, with a ring.
    Keep a room (a tentative reservation until a time, or a firm one), Decline, a short Reply.
    The conversation itself stays in E-Ride ("Open the chat").
--}}
@php
    $er_link = \App\Utils\ChannelHotel::link(session('user.business_id'));
    $er_can = auth()->user()->can('hotel.reservations') || auth()->user()->can('hotel.front_desk');
@endphp
@if ($er_link && $er_can)
@php
    $E = \App\Http\Controllers\HotelErideController::class;
    $er_T = collect(['inbox_t', 'inbox_none', 'new_one', 'new_many', 'keep', 'decline', 'reply', 'open_chat', 'st_open', 'st_answered', 'st_kept', 'st_booked', 'st_arrived', 'st_closed',
        'nights', 'guests', 'rooms', 'arrives', 'type', 'type_pick', 'hold_until', 'firm', 'firm_h', 'send', 'cancel', 'reason_full', 'reason_other',
        'reply_ph', 'decline_ph', 'offline', 'revoked', 'not_mapped', 'unread', 'kept_as', 'free_n', 'rooms_l'])
        ->mapWithKeys(fn ($k) => [$k => __('hotel.er_'.$k)])->all();
    $er_R = \App\Http\Controllers\HotelReservationController::class;
@endphp
<style>
#ht_er { border: 2px solid #eaecf0; }
#ht_er.ring { border-color: #f04438; box-shadow: 0 0 0 3px rgba(240,68,56,.15); }
#ht_er .er-h { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
#ht_er .er-n { background: #475467; color: #fff; border-radius: 999px; min-width: 26px; height: 26px; padding: 0 8px; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
#ht_er.ring .er-n { background: #f04438; }
#ht_er .er-q { border: 1px solid #eaecf0; border-radius: 10px; padding: 12px; margin-bottom: 10px; }
#ht_er .er-q-h { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px; align-items: baseline; }
#ht_er .er-q-h b { font-size: 16px; }
#ht_er .er-facts { display: flex; flex-wrap: wrap; gap: 6px 14px; margin: 6px 0; color: #344054; }
#ht_er .er-msg { background: #f9fafb; border-radius: 8px; padding: 8px 10px; margin: 6px 0; }
#ht_er .er-acts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
#ht_er .er-form { border-top: 1px solid #eaecf0; margin-top: 10px; padding-top: 10px; display: grid; gap: 8px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items: end; }
#ht_er .er-form .full { grid-column: 1 / -1; }
#ht_er .er-pill { display: inline-block; border-radius: 999px; padding: 2px 9px; font-size: 12px; font-weight: 600; background: #f2f4f7; color: #344054; }
#ht_er .er-pill.kept { background: #fef0c7; color: #93370d; } #ht_er .er-pill.arrived { background: #dcfae6; color: #067647; } #ht_er .er-pill.unread { background: #f04438; color: #fff; }
</style>
<div class="ht-card" id="ht_er" aria-live="polite">
    <div class="er-h"><h2 style="margin:0;">{{ __('hotel.er_inbox_t') }}</h2><span class="er-n" id="ht_er_n">0</span></div>
    <div id="ht_er_list"><div class="ht-empty">{{ __('hotel.er_inbox_none') }}</div></div>
</div>
<script>
// jQuery and toastr load at the end of the page: start once they are there.
document.addEventListener('DOMContentLoaded', function () {
    var URL_INBOX = @json(action([$E, 'inbox']));
    var URL_KEEP = @json(url('hotel/eride/keep'));
    var URL_DECLINE = @json(url('hotel/eride/decline'));
    var URL_REPLY = @json(url('hotel/eride/reply'));
    var URL_RES = @json(url('hotel/reservations'));
    var TOKEN = @json(csrf_token());
    var T = @json($er_T);
    var st = { known: {}, last: null, open: null, beep: 0 };

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function dmy(s) { if (!s) { return ''; } var p = String(s).substr(0, 10).split('-'); return p[2] + '/' + p[1]; }
    function chime() {
        try {
            var C = window.AudioContext || window.webkitAudioContext; if (!C) { return; }
            var ctx = chime.ctx || (chime.ctx = new C());
            [0, 0.3].forEach(function (t, i) {
                var o = ctx.createOscillator(), g = ctx.createGain();
                o.frequency.value = i ? 660 : 990; g.gain.setValueAtTime(0.0001, ctx.currentTime + t);
                g.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + t + 0.02); g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + t + 0.26);
                o.connect(g); g.connect(ctx.destination); o.start(ctx.currentTime + t); o.stop(ctx.currentTime + t + 0.28);
            });
        } catch (e) { /* no sound */ }
    }
    function holdDefault(q, time) {
        return String(q.check_in).substr(0, 10) + 'T' + (time || '18:00');
    }

    function render(r) {
        var box = document.getElementById('ht_er'), list = document.getElementById('ht_er_list');
        var qs = r.inquiries || [];
        var unread = qs.filter(function (q) { return (q.unread || 0) > 0 || q.status === 'open'; }).length;
        document.getElementById('ht_er_n').textContent = qs.length;
        box.classList.toggle('ring', unread > 0);
        if (st.open && document.getElementById('ht_er_f_' + st.open)) { return; } // a form is being filled: do not redraw under the receptionist
        var h = '';
        if (r.error === 'revoked') { h += '<div class="ht-note red">' + esc(T.revoked) + '</div>'; }
        else if (r.error === 'offline') { h += '<div class="ht-note amber">' + esc(T.offline) + '</div>'; }
        if (!qs.length) { h += '<div class="ht-empty">' + esc(T.inbox_none) + '</div>'; }
        qs.forEach(function (q) {
            var stc = q.status === 'kept' ? 'kept' : (q.status === 'arrived' || q.status === 'booked' ? 'arrived' : '');
            var type = q.room_type || {};
            var mapped = type.pos_type_id ? (r.types.filter(function (t) { return t.id == type.pos_type_id; })[0] || null) : null;
            h += '<div class="er-q" data-id="' + esc(q.inquiry_id) + '"><div class="er-q-h"><div><b>' + esc(q.number) + ' · ' + esc((q.guest || {}).name) + '</b>' +
                ((q.guest || {}).phone ? ' · <a href="tel:' + esc(q.guest.phone) + '">' + esc(q.guest.phone) + '</a>' : '') + '</div>' +
                '<span class="er-pill ' + stc + '">' + esc(T['st_' + q.status] || q.status) + '</span>' +
                ((q.unread || 0) > 0 ? ' <span class="er-pill unread">' + esc(T.unread.replace(':n', q.unread)) + '</span>' : '') + '</div>' +
                '<div class="er-facts"><span><b>' + esc(dmy(q.check_in)) + ' → ' + esc(dmy(q.check_out)) + '</b> · ' + esc(T.nights.replace(':n', q.nights)) + '</span>' +
                '<span>' + esc(T.guests.replace(':n', q.guests)) + ' · ' + esc(T.rooms.replace(':n', q.rooms || 1)) + '</span>' +
                '<span>' + esc(T.type) + ': ' + esc(mapped ? mapped.name : (type.name || '—')) + (mapped ? '' : ' <em>(' + esc(T.not_mapped) + ')</em>') + '</span>' +
                (q.arrival ? '<span>' + esc(T.arrives) + ': ' + esc(q.arrival) + '</span>' : '') + '</div>' +
                (q.question ? '<div class="er-msg">“' + esc(q.question) + '”</div>' : '') +
                (q.last_guest_message && q.last_guest_message.text && q.last_guest_message.text !== q.question ? '<div class="er-msg">' + esc(q.last_guest_message.text) + '</div>' : '') +
                ((q.pos || []).length ? '<div class="ht-small">' + esc(T.kept_as) + ' ' + q.pos.map(function (p) {
                    return '<a href="' + URL_RES + '/' + p.id + '">' + esc(p.ref_no) + '</a>' + (p.hold_until ? ' (' + esc(p.hold_until.substr(11, 5)) + ')' : '');
                }).join(', ') + '</div>' : '') +
                '<div class="er-acts">' +
                (q.status === 'arrived' ? '' : '<button type="button" class="ht-btn pri sm er-keep">' + esc(T.keep) + '</button>') +
                '<button type="button" class="ht-btn sm er-reply">' + esc(T.reply) + '</button>' +
                (q.status === 'arrived' || (q.pos || []).length ? '' : '<button type="button" class="ht-btn sm er-decline">' + esc(T.decline) + '</button>') +
                (q.eride_url ? '<a class="ht-btn sm" target="_blank" rel="noopener" href="' + esc(q.eride_url) + '">' + esc(T.open_chat) + ' ↗</a>' : '') +
                '</div></div>';
        });
        list.innerHTML = h;
        st.last = r;
    }

    function poll() {
        $.getJSON(URL_INBOX).done(function (r) {
            if (!r || !r.linked) { return; }
            var fresh = false;
            (r.inquiries || []).forEach(function (q) {
                var sig = q.status + '/' + (q.unread || 0) + '/' + ((q.last_guest_message || {}).at || '');
                if (st.known[q.inquiry_id] !== sig) { if (q.status === 'open' || (q.unread || 0) > 0) { fresh = true; } st.known[q.inquiry_id] = sig; }
            });
            var waiting = (r.inquiries || []).filter(function (q) { return q.status === 'open' || (q.unread || 0) > 0; }).length;
            if (waiting && (fresh || Date.now() - st.beep > 60000)) {
                st.beep = Date.now(); chime();
                if (fresh && typeof toastr !== 'undefined') { toastr.info(waiting === 1 ? T.new_one : T.new_many.replace(':n', waiting)); }
            }
            render(r);
        });
    }

    function form(card, kind) {
        var id = card.getAttribute('data-id');
        var q = (st.last.inquiries || []).filter(function (x) { return x.inquiry_id === id; })[0];
        var old = card.querySelector('.er-form'); if (old) { old.remove(); }
        st.open = id;
        var h = '<form class="er-form" id="ht_er_f_' + esc(id) + '" data-kind="' + kind + '">';
        if (kind === 'keep') {
            var pos = (q.room_type || {}).pos_type_id;
            h += '<div><label>' + esc(T.type_pick) + '</label><select class="ht-in" name="room_type_id" required>' + st.last.types.map(function (t) {
                return '<option value="' + t.id + '"' + (t.id == pos ? ' selected' : '') + '>' + esc(t.name) + '</option>'; }).join('') + '</select></div>' +
                '<div><label>' + esc(T.rooms_l) + '</label><input class="ht-in" type="number" min="1" max="10" name="rooms" value="' + (q.rooms || 1) + '"></div>' +
                '<div class="er-until"><label>' + esc(T.hold_until) + '</label><input class="ht-in" type="datetime-local" name="until" value="' + holdDefault(q, st.last.hold_time) + '"></div>' +
                '<label class="full" style="font-weight:500;display:flex;gap:8px;align-items:center;"><input type="checkbox" name="firm" value="1"> ' + esc(T.firm) + ' <span class="ht-hint">' + esc(T.firm_h) + '</span></label>';
        } else if (kind === 'decline') {
            h += '<div><select class="ht-in" name="reason"><option value="full">' + esc(T.reason_full) + '</option><option value="other">' + esc(T.reason_other) + '</option></select></div>' +
                '<div class="full"><textarea class="ht-in" name="text" maxlength="500" rows="2" placeholder="' + esc(T.decline_ph) + '"></textarea></div>';
        } else {
            h += '<div class="full"><textarea class="ht-in" name="text" maxlength="500" rows="3" required placeholder="' + esc(T.reply_ph) + '"></textarea></div>';
        }
        h += '<div class="full" style="display:flex;gap:8px;"><button type="submit" class="ht-btn pri sm">' + esc(kind === 'keep' ? T.keep : (kind === 'decline' ? T.decline : T.send)) + '</button>' +
            '<button type="button" class="ht-btn sm er-cancel">' + esc(T.cancel) + '</button></div></form>';
        card.insertAdjacentHTML('beforeend', h);
        var f = card.querySelector('.er-form');
        var firm = f.querySelector('[name=firm]');
        if (firm) { firm.addEventListener('change', function () { f.querySelector('.er-until').hidden = firm.checked; }); }
        var first = f.querySelector('textarea, select'); if (first) { first.focus(); }
    }

    var list = document.getElementById('ht_er_list');
    list.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) { return; }
        var card = b.closest('.er-q');
        if (b.classList.contains('er-keep')) { form(card, 'keep'); }
        else if (b.classList.contains('er-decline')) { form(card, 'decline'); }
        else if (b.classList.contains('er-reply')) { form(card, 'reply'); }
        else if (b.classList.contains('er-cancel')) { b.closest('.er-form').remove(); st.open = null; }
    });
    list.addEventListener('submit', function (e) {
        e.preventDefault();
        var f = e.target, kind = f.getAttribute('data-kind'), id = f.closest('.er-q').getAttribute('data-id');
        var btn = f.querySelector('button[type=submit]'); btn.disabled = true;
        var data = { _token: TOKEN };
        if (kind === 'keep') {
            data.room_type_id = f.room_type_id.value; data.rooms = f.rooms.value;
            data.status = f.firm.checked ? 'confirmed' : 'tentative'; data.until = f.firm.checked ? '' : f.until.value;
        } else { data.text = f.text.value; if (f.reason) { data.reason = f.reason.value; } }
        var url = (kind === 'keep' ? URL_KEEP : (kind === 'decline' ? URL_DECLINE : URL_REPLY)) + '/' + encodeURIComponent(id);
        $.post(url, data).done(function (r) {
            if (r && r.ok) { toastr.success(r.message); st.open = null; poll(); }
            else { toastr.error((r && r.message) || T.offline); btn.disabled = false; }
        }).fail(function (x) { toastr.error((x.responseJSON && x.responseJSON.message) || T.offline); btn.disabled = false; });
    });

    poll();
    setInterval(poll, 20000);
});
</script>
@endif
