{{--
    PosInnova: a booking request is never approved blind. The review window: the activity widget's
    Review button, My practice, and the diary's Confirm / Decline buttons (form[data-review="<id>"])
    open it first. It shows who, when, with which doctor, and the doctor's whole day (appointments, how
    many there will be, the daily limit, overbooking / day-off / same-day warnings). Approving or
    declining (a reason is required) happens in the background (JSON), so the page — a sale in progress
    on the POS — is never left. window.piApptReview(id, declineFirst) opens it from anywhere.
    Rendered for anyone who may approve appointments (also inside wide-screen tabs).
--}}
@php
    $pi_rv = false;     // may this user approve anything (then the review window is on the page)
    try {
        $pi_apu = auth()->user();
        $pi_apmods = session('business.enabled_modules', []);
        if ($pi_apu && is_array($pi_apmods) && in_array('hospital', $pi_apmods)) {
            $pi_apbid = session('user.business_id');
            $pi_acc = \App\Utils\ClinicAppointments::access($pi_apbid, $pi_apu);
            $pi_rv = $pi_acc['manage_all'] || $pi_acc['own'] !== null;
        }
    } catch (\Throwable $e) {
        $pi_rv = false;
    }
    $pi_apl = [
        'rv_loading' => __('clinic.rv_loading'), 'rv_error' => __('clinic.rv_error'),
        'rv_new_patient' => __('clinic.rv_new_patient'), 'rv_will_register' => __('clinic.rv_will_register'),
        'rv_matches' => __('clinic.rv_matches'), 'rv_file' => __('clinic.rv_file'),
        'rv_patient' => __('clinic.rv_patient'), 'rv_when' => __('clinic.rv_when'), 'rv_doctor' => __('clinic.rv_doctor'), 'rv_reason' => __('clinic.rv_reason'),
        'rv_from' => __('clinic.rv_from'), 'rv_asked' => __('clinic.rv_asked'),
        'src_public' => __('clinic.rv_src_public'), 'src_whatsapp' => __('clinic.rv_src_whatsapp'),
        'src_portal' => __('clinic.rv_src_portal'), 'src_reception' => __('clinic.rv_src_reception'),
        'rv_day' => __('clinic.rv_day'), 'rv_after' => __('clinic.rv_after'), 'rv_after_one' => __('clinic.rv_after_one'),
        'rv_waiting' => __('clinic.rv_waiting'), 'rv_waiting_one' => __('clinic.rv_waiting_one'), 'rv_free_one' => __('clinic.rv_free_one'),
        'rv_limit' => __('clinic.rv_limit'), 'rv_no_limit' => __('clinic.rv_no_limit'), 'rv_free' => __('clinic.rv_free'),
        'rv_hours' => __('clinic.rv_hours'), 'rv_this' => __('clinic.rv_this'),
        'rv_over' => __('clinic.rv_over'), 'rv_fills' => __('clinic.rv_fills'), 'rv_day_off' => __('clinic.rv_day_off'),
        'rv_same_day' => __('clinic.rv_same_day'), 'rv_handled' => __('clinic.rv_handled'),
        'rv_confirm' => __('clinic.rv_confirm'), 'rv_confirm_anyway' => __('clinic.rv_confirm_anyway'),
        'rv_decline' => __('clinic.rv_decline'), 'rv_decline_reason' => __('clinic.rv_decline_reason'),
        'rv_reasons' => __('clinic.rv_reasons'), 'rv_reason_ph' => __('clinic.rv_reason_ph'), 'rv_reason_required' => __('clinic.rv_reason_required'),
        'rv_wa_decline_link' => __('clinic.rv_wa_decline_link'), 'rv_no_phone' => __('clinic.rv_no_phone'),
        'rv_decline_send' => __('clinic.rv_decline_send'), 'rv_back' => __('clinic.rv_back'),
        'rv_open_diary' => __('clinic.rv_open_diary'), 'rv_close' => __('clinic.rv_close'), 'rv_done' => __('clinic.rv_done'),
        'rv_wa_auto' => __('clinic.rv_wa_auto'), 'rv_wa_link' => __('clinic.rv_wa_link'), 'rv_wa_error' => __('clinic.rv_wa_error'),
        'rv_working' => __('clinic.rv_working'),
    ];
    $pi_urls = [
        'review' => url('clinic/appointments/__ID__/review'),
        'confirm' => url('clinic/appointments/__ID__/confirm'),
        'decline' => url('clinic/appointments/__ID__/decline'),
    ];
@endphp
@if ($pi_rv)
    <style>


        @media print { .pi-rv { display: none !important; } }
        /* review window */
        .pi-rv { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: flex-start; justify-content: center; padding: 4vh 12px; overflow-y: auto; }
        .pi-rv[hidden], .pi-rv [hidden] { display: none !important; }
        .pi-rv-back { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); }
        .pi-rv-box { position: relative; width: 100%; max-width: 640px; background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0, 0, 0, .3); color: #0f172a; font-size: 14.5px; line-height: 1.45; }
        .pi-rv-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
        .pi-rv-head h3 { margin: 0; font-size: 18px; font-weight: 800; }
        .pi-rv-x { width: 40px; height: 40px; border: 0; background: #f1f5f9; border-radius: 10px; font-size: 22px; line-height: 1; cursor: pointer; color: #334155; }
        .pi-rv-body { padding: 16px 20px; }
        .pi-rv-foot { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; align-items: center; padding: 14px 20px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-radius: 0 0 16px 16px; }
        .pi-rv-foot .pi-rv-spacer { flex: 1; }
        .pi-rv-b { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 44px; padding: 8px 16px; border-radius: 10px; border: 1px solid #cbd5e1; background: #fff; color: #0f172a; font-weight: 700; font-size: 14.5px; cursor: pointer; text-decoration: none !important; }
        .pi-rv-b.ok { background: #067647; border-color: #067647; color: #fff; }
        .pi-rv-b.warn { background: #b54708; border-color: #b54708; color: #fff; }
        .pi-rv-b.no { color: #b42318; border-color: #fda29b; }
        .pi-rv-b.danger { background: #b42318; border-color: #b42318; color: #fff; }
        .pi-rv-b.wa { background: #16a34a; border-color: #16a34a; color: #fff; }
        .pi-rv-b[disabled] { opacity: .6; cursor: wait; }
        .pi-rv-alert { padding: 10px 12px; border-radius: 10px; margin: 0 0 10px; font-weight: 600; }
        .pi-rv-alert.red { background: #fef3f2; color: #b42318; border: 1px solid #fda29b; }
        .pi-rv-alert.amber { background: #fffaeb; color: #b54708; border: 1px solid #fedf89; }
        .pi-rv-alert.blue { background: #eff8ff; color: #175cd3; border: 1px solid #b2ddff; }
        .pi-rv-alert.green { background: #ecfdf3; color: #067647; border: 1px solid #abefc6; }
        .pi-rv-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px; }
        @media (max-width: 560px) { .pi-rv-grid { grid-template-columns: 1fr; } }
        .pi-rv-card { border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; }
        .pi-rv-card .k { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .03em; margin-bottom: 4px; }
        .pi-rv-name { font-size: 17px; font-weight: 800; }
        .pi-rv-tag { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 700; background: #e0f2fe; color: #075985; vertical-align: middle; }
        .pi-rv-muted { color: #64748b; font-size: 13px; }
        .pi-rv-day h4 { margin: 0 0 6px; font-size: 15px; font-weight: 800; }
        .pi-rv-stats { display: flex; flex-wrap: wrap; gap: 6px 14px; color: #334155; font-size: 13px; margin-bottom: 8px; }
        .pi-rv-stats b { color: #0f172a; }
        .pi-rv-list { list-style: none; margin: 0; padding: 0; border: 1px solid #e2e8f0; border-radius: 12px; max-height: 240px; overflow-y: auto; }
        .pi-rv-list li { display: flex; gap: 10px; align-items: center; padding: 8px 12px; border-top: 1px solid #f1f5f9; }
        .pi-rv-list li:first-child { border-top: 0; }
        .pi-rv-list li.this { background: #fffaeb; font-weight: 700; }
        .pi-rv-list .t { width: 50px; font-variant-numeric: tabular-nums; font-weight: 700; }
        .pi-rv-list .n { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .pi-rv-list .s { font-size: 12px; padding: 1px 8px; border-radius: 999px; background: #f1f5f9; color: #334155; white-space: nowrap; }
        .pi-rv-list .s.requested { background: #fffaeb; color: #b54708; }
        .pi-rv-list .s.confirmed { background: #ecfdf3; color: #067647; }
        .pi-rv-reason { width: 100%; min-height: 70px; padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 10px; font: inherit; }
        .pi-rv-reason.bad { border-color: #b42318; background: #fef3f2; }
        .pi-rv-decline { width: 100%; }
        .pi-rv-decline label { display: block; font-weight: 700; margin: 0 0 8px; }
        .pi-rv-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 8px; }
        .pi-rv-chip { min-height: 36px; padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 999px; background: #fff; color: #334155; font-size: 13px; font-weight: 600; cursor: pointer; }
        .pi-rv-chip:hover { border-color: #b42318; color: #b42318; }
        .pi-rv-ferr { color: #b42318; font-weight: 700; font-size: 13.5px; margin: 6px 0 0; }
        .pi-rv-done { text-align: center; padding: 10px 0 4px; }
        .pi-rv-done .big { width: 60px; height: 60px; margin: 0 auto 10px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; }
    </style>

    <div class="pi-rv no-print" id="pi_rv" hidden role="dialog" aria-modal="true" aria-labelledby="pi_rv_title">
        <div class="pi-rv-back" data-rv-close></div>
        <div class="pi-rv-box">
            <div class="pi-rv-head"><h3 id="pi_rv_title">@lang('clinic.rv_title')</h3>
                <button type="button" class="pi-rv-x" data-rv-close aria-label="{{ __('clinic.rv_close') }}">&times;</button></div>
            <div class="pi-rv-body" id="pi_rv_body"></div>
            <div class="pi-rv-foot" id="pi_rv_foot"></div>
        </div>
    </div>

    <script>
    (function () {
        var L = @json($pi_apl);
        var U = @json($pi_urls);
        var TOKEN = @json(csrf_token());
        var $ = function (id) { return document.getElementById(id); };
        var esc = function (s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
        var fill = function (t, o) { return String(t).replace(/:(\w+)/g, function (m, k) { return o[k] !== undefined ? o[k] : m; }); };
        // the activity widget refreshes when a request was handled here
        var poll = function () { try { window.dispatchEvent(new CustomEvent('pi:activity')); } catch (e) { /* old browser */ } };
        var pl = function (key, n) { return (n === 1 && L[key + '_one']) ? L[key + '_one'] : L[key]; };

        /* ------------------------------------------------------------ review window */
        var rv = $('pi_rv');
        var rvState = { id: null, data: null, busy: false, changed: false };

        function rvFoot(html) { $('pi_rv_foot').innerHTML = html; }
        function rvClose() {
            if (! rv) { return; }
            rv.hidden = true;
            document.documentElement.classList.remove('pi-rv-open');
            document.removeEventListener('keydown', rvKey);
            // lists of requests on this page are now out of date
            if (rvState.changed && /\/clinic\/(appointments|my-practice)/.test(location.pathname)) { location.reload(); }
            rvState.changed = false;
        }
        function rvKey(e) { if (e.key === 'Escape' && ! rvState.busy) { rvClose(); } }

        function rvRender(d) {
            var w = [];
            if (d.status !== 'requested') { w.push('<div class="pi-rv-alert blue">' + esc(L.rv_handled) + '</div>'); }
            if (d.over) { w.push('<div class="pi-rv-alert red"><i class="fas fa-exclamation-triangle"></i> ' + esc(fill(L.rv_over, { n: d.after, cap: d.cap })) + '</div>'); }
            else if (d.fills) { w.push('<div class="pi-rv-alert amber">' + esc(fill(L.rv_fills, { cap: d.cap })) + '</div>'); }
            if (d.day_off) { w.push('<div class="pi-rv-alert red"><i class="fas fa-exclamation-triangle"></i> ' + esc(L.rv_day_off) + '</div>'); }
            if (d.same_day) { w.push('<div class="pi-rv-alert amber">' + esc(fill(L.rv_same_day, { name: d.patient, time: d.same_day.time, doctor: d.same_day.doctor })) + '</div>'); }

            var who = '<div class="pi-rv-name">' + esc(d.patient) + (d.guest ? ' <span class="pi-rv-tag">' + esc(L.rv_new_patient) + '</span>' : '') + '</div>'
                + '<div>' + esc(d.phone || '') + '</div>';
            if (d.file) { who += '<div class="pi-rv-muted">' + esc(fill(L.rv_file, { file: d.file })) + '</div>'; }
            if (d.guest) {
                who += '<div class="pi-rv-muted" style="margin-top:6px">' + esc(d.match ? fill(L.rv_matches, { name: d.match.name, file: d.match.file || '-' }) : L.rv_will_register) + '</div>';
            }
            var when = '<div class="pi-rv-name">' + esc(d.time) + '</div><div>' + esc(d.date_label) + '</div>'
                + '<div class="pi-rv-muted">' + esc(L.rv_doctor) + ': <b>' + esc(d.doctor) + '</b></div>';
            var extra = [];
            if (d.reason) { extra.push('<div><span class="pi-rv-muted">' + esc(L.rv_reason) + ':</span> ' + esc(d.reason) + '</div>'); }
            extra.push('<div class="pi-rv-muted">' + esc(L.rv_from) + ': ' + esc(L['src_' + d.source] || d.source) + (d.asked ? ' · ' + esc(fill(L.rv_asked, { when: d.asked })) : '') + '</div>');

            var stats = ['<span>' + fill(esc(pl('rv_after', d.after)), { n: '<b>' + d.after + '</b>' }) + '</span>'];
            if (d.pending > (d.status === 'requested' ? 1 : 0)) { stats.push('<span>' + esc(fill(pl('rv_waiting', d.pending - (d.status === 'requested' ? 1 : 0)), { n: d.pending - (d.status === 'requested' ? 1 : 0) })) + '</span>'); }
            stats.push('<span>' + (d.cap > 0 ? fill(esc(L.rv_limit), { n: '<b>' + d.cap + '</b>' }) : esc(L.rv_no_limit)) + '</span>');
            stats.push('<span>' + esc(fill(pl('rv_free', d.free), { n: d.free })) + '</span>');
            if (d.hours) { stats.push('<span>' + esc(fill(L.rv_hours, { h: d.hours })) + '</span>'); }
            var rows = d.day.map(function (a) {
                return '<li class="' + (a.this ? 'this' : '') + '"><span class="t">' + esc(a.time) + '</span><span class="n">' + esc(a.name || '')
                    + (a.guest ? ' <span class="pi-rv-tag">' + esc(L.rv_new_patient) + '</span>' : '') + (a.this ? ' · ' + esc(L.rv_this) : '')
                    + '</span><span class="s ' + esc(a.status) + '">' + esc(a.status_label) + '</span></li>';
            }).join('');

            $('pi_rv_body').innerHTML = w.join('')
                + '<div class="pi-rv-grid"><div class="pi-rv-card"><div class="k"><i class="fas fa-user"></i> ' + esc(L.rv_patient) + '</div>' + who + '</div>'
                + '<div class="pi-rv-card"><div class="k"><i class="fas fa-calendar-alt"></i> ' + esc(L.rv_when) + '</div>' + when + '</div></div>'
                + '<div style="margin:-4px 0 14px">' + extra.join('') + '</div>'
                + '<div class="pi-rv-day"><h4>' + esc(fill(L.rv_day, { doctor: d.doctor })) + '</h4><div class="pi-rv-stats">' + stats.join(' · ') + '</div>'
                + '<ul class="pi-rv-list" id="pi_rv_list">' + rows + '</ul></div>';
            var me = document.querySelector('#pi_rv_list li.this'); if (me) { me.scrollIntoView({ block: 'nearest' }); }

            if (d.status !== 'requested') {
                rvFoot('<a class="pi-rv-b" href="' + esc(d.diary_url) + '">' + esc(L.rv_open_diary) + '</a><span class="pi-rv-spacer"></span><button type="button" class="pi-rv-b" data-rv-close>' + esc(L.rv_close) + '</button>');
                return;
            }
            rvFoot('<button type="button" class="pi-rv-b no" id="pi_rv_decline">' + esc(L.rv_decline) + '</button>'
                + '<a class="pi-rv-b" href="' + esc(d.diary_url) + '">' + esc(L.rv_open_diary) + '</a><span class="pi-rv-spacer"></span>'
                + '<button type="button" class="pi-rv-b ' + (d.over || d.day_off ? 'warn' : 'ok') + '" id="pi_rv_confirm"><i class="fas fa-check"></i> ' + esc(d.over || d.day_off ? L.rv_confirm_anyway : L.rv_confirm) + '</button>');
            $('pi_rv_confirm').onclick = rvConfirm;
            $('pi_rv_decline').onclick = rvDeclineAsk;
            setTimeout(function () { var b = $('pi_rv_confirm'); if (b) { b.focus(); } }, 50);
        }

        function rvSend(kind, extra, done) {
            if (rvState.busy) { return; }
            rvState.busy = true;
            Array.prototype.forEach.call(document.querySelectorAll('#pi_rv_foot button'), function (b) { b.disabled = true; });
            var fd = new FormData(); fd.append('_token', TOKEN);
            Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
            fetch(U[kind].replace('__ID__', rvState.id), { method: 'POST', body: fd, credentials: 'same-origin',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json().catch(function () { return { ok: false, msg: L.rv_error }; }); })
                .then(function (res) { rvState.busy = false; rvState.changed = true; done(res); poll(); })
                .catch(function () { rvState.busy = false; done({ ok: false, msg: L.rv_error }); });
        }

        function rvResult(res, kind) {
            var ok = res && res.ok;
            var html = '<div class="pi-rv-done"><div class="big" style="background:' + (ok ? '#ecfdf3;color:#067647' : '#fef3f2;color:#b42318') + '">' + (ok ? '<i class="fas fa-check"></i>' : '<i class="fas fa-exclamation"></i>') + '</div>'
                + '<div style="font-size:16px;font-weight:700">' + esc((res && res.msg) || L.rv_error) + '</div>';
            if (ok && res.whatsapp_auto) { html += '<div class="pi-rv-alert green" style="margin-top:12px">' + esc(L.rv_wa_auto) + '</div>'; }
            if (ok && res.whatsapp_error) { html += '<div class="pi-rv-alert amber" style="margin-top:12px">' + esc(fill(L.rv_wa_error, { error: res.whatsapp_error })) + '</div>'; }
            html += '</div>';
            $('pi_rv_body').innerHTML = html;
            var foot = '';
            if (ok && ! res.whatsapp_auto && res.whatsapp_link) {
                foot += '<a class="pi-rv-b wa" id="pi_rv_wa" target="_blank" rel="noopener" href="' + esc(res.whatsapp_link) + '"><i class="fab fa-whatsapp"></i> '
                    + esc(kind === 'decline' ? L.rv_wa_decline_link : L.rv_wa_link) + '</a>';
            }
            if (ok && kind === 'decline' && ! res.whatsapp_link) {
                $('pi_rv_body').insertAdjacentHTML('beforeend', '<div class="pi-rv-alert amber" style="margin-top:12px">' + esc(L.rv_no_phone) + '</div>');
            }
            rvFoot(foot + '<span class="pi-rv-spacer"></span><button type="button" class="pi-rv-b ok" data-rv-close id="pi_rv_done">' + esc(L.rv_done) + '</button>');
            setTimeout(function () { var b = $('pi_rv_done'); if (b) { b.focus(); } }, 50);
        }

        function rvConfirm() {
            var b = $('pi_rv_confirm'); if (b) { b.textContent = L.rv_working; }
            rvSend('confirm', {}, function (res) { rvResult(res, 'confirm'); });
        }

        /* Decline: the reason is required (the patient is told why); quick picks or free text */
        function rvDeclineAsk() {
            var chips = (L.rv_reasons || []).map(function (t) {
                return '<button type="button" class="pi-rv-chip">' + esc(t) + '</button>';
            }).join('');
            rvFoot('<div class="pi-rv-decline">'
                + '<label for="pi_rv_reason">' + esc(L.rv_decline_reason) + '</label>'
                + '<div class="pi-rv-chips">' + chips + '</div>'
                + '<textarea id="pi_rv_reason" class="pi-rv-reason" maxlength="191" aria-describedby="pi_rv_reason_err" placeholder="' + esc(L.rv_reason_ph) + '"></textarea>'
                + '<p class="pi-rv-ferr" id="pi_rv_reason_err" role="alert" hidden></p></div>'
                + '<button type="button" class="pi-rv-b" id="pi_rv_back">' + esc(L.rv_back) + '</button><span class="pi-rv-spacer"></span>'
                + '<button type="button" class="pi-rv-b danger" id="pi_rv_decline_send"><i class="fas fa-ban"></i> ' + esc(L.rv_decline_send) + '</button>');
            var box = $('pi_rv_reason'), err = $('pi_rv_reason_err');
            var showErr = function (msg) { err.textContent = msg || ''; err.hidden = ! msg; box.classList.toggle('bad', !! msg); };
            Array.prototype.forEach.call(document.querySelectorAll('#pi_rv_foot .pi-rv-chip'), function (c) {
                c.onclick = function () { box.value = c.textContent; showErr(''); box.focus(); };
            });
            box.addEventListener('input', function () { showErr(''); });
            $('pi_rv_back').onclick = function () { rvRender(rvState.data); };
            $('pi_rv_decline_send').onclick = function () {
                var reason = box.value.trim();
                if (reason.length < 3) { showErr(L.rv_reason_required); box.focus(); return; }
                rvSend('decline', { reason: reason }, function (res) {
                    if (res && ! res.ok && res.field === 'reason') {   // the server agrees: no reason, no decline
                        Array.prototype.forEach.call(document.querySelectorAll('#pi_rv_foot button'), function (b) { b.disabled = false; });
                        showErr(res.msg); box.focus(); return;
                    }
                    rvResult(res, 'decline');
                });
            };
            setTimeout(function () { box.focus(); }, 50);
        }

        function rvOpen(id, declineFirst) {
            if (! rv) { return false; }
            rvState = { id: id, data: null, busy: false, changed: false };
            $('pi_rv_body').innerHTML = '<p class="pi-rv-muted">' + esc(L.rv_loading) + '</p>';
            rvFoot('<button type="button" class="pi-rv-b" data-rv-close>' + esc(L.rv_close) + '</button>');
            rv.hidden = false;
            document.documentElement.classList.add('pi-rv-open');
            document.addEventListener('keydown', rvKey);
            fetch(U.review.replace('__ID__', id), { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { if (! r.ok) { throw new Error(r.status); } return r.json(); })
                .then(function (d) { rvState.data = d; rvRender(d); if (declineFirst && d.status === 'requested') { rvDeclineAsk(); } })
                .catch(function () { $('pi_rv_body').innerHTML = '<div class="pi-rv-alert red">' + esc(L.rv_error) + '</div>'; });
            return true;
        }
        window.piApptReview = rvOpen;

        if (rv) {
            rv.addEventListener('click', function (e) { if (e.target.closest('[data-rv-close]') && ! rvState.busy) { rvClose(); } });
            // the diary's / upcoming list's Confirm and Decline buttons open the review window first
            document.addEventListener('submit', function (e) {
                var f = e.target;
                if (! f || ! f.getAttribute) { return; }
                var id = f.getAttribute('data-review'), dec = f.getAttribute('data-review-decline');
                // only appointment ids: other screens (ledger, payroll) use data-review for their own window
                if (! /^\d+$/.test(id || '') && ! /^\d+$/.test(dec || '')) { return; }
                if (! /^\d+$/.test(id || '')) { id = null; }
                e.preventDefault(); e.stopPropagation();   // and keep common.js from disabling its button
                rvOpen(id || dec, !! dec);
            }, true);
        }
    })();
    </script>
@endif
