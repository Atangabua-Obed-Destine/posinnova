/*
 * PosInnova: the sales lists (All sales, Sales by shift) update by themselves.
 *
 * Every few seconds the page asks the server (GET /sells/live-check) whether a sale was made
 * since the last one it knows of. When one was:
 *   - the table refreshes where it is (same page, filters and search), the new rows lit up;
 *   - a "New sale" card says what was sold, for how much, by whom (click: the sale);
 *   - a short sound plays (can be switched off: the bell on the "Live" mark);
 *   - while the screen is not looked at, the browser tab (or the workspace tab) counts them.
 * A sale changed elsewhere (a payment, a return) refreshes the table quietly.
 * Configuration: window.LIVE_SALES = { url, text: {...} } (sell/index.blade.php).
 */
(function () {
    'use strict';

    var cfg = window.LIVE_SALES || {};
    var T = cfg.text || {};
    var tx = function (k, d) { return T[k] || d; };
    var fill = function (s, v) { return String(s || '').replace(/:(\w+)/g, function (m, k) { return v[k] != null ? v[k] : m; }); };
    var VISIBLE_EVERY = 8000, HIDDEN_EVERY = 30000, MAX_BACKOFF = 60000;

    var since = '', announced = {}, sig = null, timer = null, failures = 0, unseen = 0, newIds = {}, pendingReload = false;
    var baseTitle = document.title;
    var muted = false;
    try { muted = localStorage.getItem('pi.live.muted') === '1'; } catch (e) { /* private mode */ }

    // ---- the desktop workspace: this page may be a tab of it
    var desk = null;
    try { if (window.parent !== window && window.parent.PI_DESK) desk = window.parent.PI_DESK; } catch (e) { /* not ours */ }
    var looked = function () {
        if (document.visibilityState === 'hidden') return false;
        // a workspace tab is seen only while it is the tab on screen
        if (desk && desk.isActive) { try { return desk.isActive(window); } catch (e) { return true; } }

        return true;
    };

    // ---- the "Live" mark next to the title
    var $mark = $('<span class="ls-mark" id="ls_mark" role="status" aria-live="polite">'
        + '<span class="ls-dot" aria-hidden="true"></span><span class="ls-label"></span>'
        + '<span class="ls-count" hidden></span>'
        + '<button type="button" class="ls-bell" aria-pressed="false"></button></span>');
    var setMark = function (state, label) {
        $mark.attr('data-state', state);
        $mark.find('.ls-label').text(label);
        $mark.attr('title', state === 'off' ? tx('paused', 'Connection lost, retrying…') : tx('live_title', 'This list updates by itself when a sale is made'));
    };
    var drawBell = function () {
        $mark.find('.ls-bell').attr('aria-pressed', String(!muted))
            .attr('title', muted ? tx('sound_off', 'Sound off') : tx('sound_on', 'Sound on'))
            .attr('aria-label', muted ? tx('sound_off', 'Sound off') : tx('sound_on', 'Sound on'))
            .html(muted ? '<i class="fas fa-bell-slash"></i>' : '<i class="fas fa-bell"></i>');
    };
    $mark.on('click', '.ls-bell', function () {
        muted = !muted;
        try { localStorage.setItem('pi.live.muted', muted ? '1' : '0'); } catch (e) { /* ignore */ }
        drawBell();
        if (!muted) ding();
    });
    $mark.on('click', '.ls-count', function () { clearUnseen(); scrollToTop(); });

    // ---- the cards
    var $stack = $('<div class="ls-stack" id="ls_stack" aria-live="polite"></div>');
    var card = function (s, more) {
        var meta = [s.customer, s.by ? fill(tx('by', 'by :name'), { name: s.by }) : '', s.location].filter(Boolean).join(' · ');
        var $c = $('<div class="ls-card" role="alert"></div>');
        $c.append('<div class="ls-card-ico" aria-hidden="true"><i class="fas fa-shopping-cart"></i></div>');
        var $b = $('<div class="ls-card-body"></div>');
        $b.append($('<div class="ls-card-title"></div>').text(tx('new_sale', 'New sale') + (s.pos ? ' · ' + tx('pos', 'POS') : '') + ' · ' + s.time));
        $b.append($('<div class="ls-card-main"></div>').append($('<strong></strong>').text(s.total)).append(document.createTextNode('  ' + (s.invoice_no || ''))));
        if (meta) $b.append($('<div class="ls-card-meta"></div>').text(meta));
        if (more > 0) $b.append($('<div class="ls-card-meta ls-more"></div>').text(fill(tx('and_more', '+ :n more'), { n: more })));
        $c.append($b);
        var $x = $('<button type="button" class="ls-card-x" aria-label="×">×</button>');
        $c.append($x);
        $x.on('click', function (e) { e.stopPropagation(); close($c); });
        $c.on('click', function () {
            close($c);
            if (cfg.view_url) {
                // the sale, in the same window the "View" action opens
                var $a = $('<a href="#" class="btn-modal" data-container=".view_modal"></a>').attr('data-href', cfg.view_url.replace('__ID__', s.id));
                $('body').append($a);
                $a.trigger('click');
                $a.remove();
            }
        });
        $stack.prepend($c);
        $stack.children('.ls-card').slice(3).remove();
        setTimeout(function () { close($c); }, 12000);
    };
    var close = function ($c) { $c.addClass('ls-out'); setTimeout(function () { $c.remove(); }, 250); };

    // ---- a short two-note sound, made here (no file); browsers allow it after a first click on the page
    var audio = null;
    var ding = function () {
        if (muted) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            var t = audio.currentTime;
            [[880, 0], [1320, 0.13]].forEach(function (n) {
                var o = audio.createOscillator(), g = audio.createGain();
                o.type = 'sine'; o.frequency.value = n[0];
                g.gain.setValueAtTime(0.0001, t + n[1]);
                g.gain.exponentialRampToValueAtTime(0.25, t + n[1] + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + n[1] + 0.35);
                o.connect(g); g.connect(audio.destination);
                o.start(t + n[1]); o.stop(t + n[1] + 0.4);
            });
        } catch (e) { /* no sound: the card is enough */ }
    };
    document.addEventListener('pointerdown', function () { try { if (audio && audio.state === 'suspended') audio.resume(); } catch (e) { /* ignore */ } }, true);

    // ---- counting what was not seen
    var showUnseen = function () {
        var $n = $mark.find('.ls-count');
        if (unseen > 0) {
            $n.prop('hidden', false).text(fill(unseen === 1 ? tx('one_new', '1 new') : tx('n_new', ':n new'), { n: unseen }));
            document.title = '(' + unseen + ') ' + baseTitle;
        } else {
            $n.prop('hidden', true);
            document.title = baseTitle;
        }
        if (desk && desk.badge) { try { desk.badge(window, unseen); } catch (e) { /* ignore */ } }
    };
    var clearUnseen = function () { if (unseen) { unseen = 0; showUnseen(); } };
    var scrollToTop = function () { var w = $('#sell_table').closest('.dataTables_wrapper')[0]; if (w && w.scrollIntoView) w.scrollIntoView({ block: 'start', behavior: 'smooth' }); };
    window.addEventListener('focus', function () { if (looked()) clearUnseen(); });
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') { clearUnseen(); schedule(800); }
    });

    // ---- refreshing the table without getting in the way
    var busy = function () {
        // an open "Actions" menu, a sale being opened: wait for the next round
        return $('#sell_table .open, #sell_table .dropdown-menu:visible').length > 0 || (window.jQuery && jQuery.active > 0);
    };
    var reload = function () {
        if (typeof window.sell_table === 'undefined' || !window.sell_table) return;
        if (busy()) { pendingReload = true; return; }
        pendingReload = false;
        window.PI_LIVE_V = since + '|' + sig;
        window.sell_table.ajax.reload(null, false);
    };
    $(document).on('draw.dt', '#sell_table', function () {
        if (!window.sell_table) return;
        var any = false;
        window.sell_table.rows({ page: 'current' }).every(function () {
            // the rows carry the invoice number, not the sale id
            var d = this.data() || {};
            var inv = String(d.invoice_no_text != null ? d.invoice_no_text : $('<div>').html(d.invoice_no || '').text()).trim();
            if (inv && newIds[inv]) { $(this.node()).addClass('ls-new'); any = true; }
        });
        if (any) setTimeout(function () { newIds = {}; }, 9000);
    });

    // ---- asking the server
    var schedule = function (ms) {
        clearTimeout(timer);
        timer = setTimeout(check, ms != null ? ms : (document.visibilityState === 'hidden' ? HIDDEN_EVERY : VISIBLE_EVERY));
    };
    var check = function () {
        $.ajax({ url: cfg.url, data: { since: since, sig: sig === null ? undefined : sig }, dataType: 'json', global: false, timeout: 15000 })
            .done(function (r) {
                failures = 0;
                setMark('on', tx('live', 'Live') + ' · ' + moment().format('HH:mm:ss'));
                // the sales not announced yet (the server repeats the last second, to miss none)
                var fresh = (r.sales || []).filter(function (s) { return !announced[s.id]; });
                var first = since === '';
                since = r.now || since;
                if (!first && fresh.length) {
                    fresh.forEach(function (s) { newIds[String(s.invoice_no).trim()] = true; announced[s.id] = true; });
                    var total = Math.max(fresh.length, (r.count || 0) - (r.sales.length - fresh.length));
                    card(fresh[0], total - 1);
                    ding();
                    if (!looked()) { unseen += total; showUnseen(); }
                    sig = r.sig;
                    reload();
                } else {
                    (r.sales || []).forEach(function (s) { announced[s.id] = true; });
                    var changed = sig !== null && r.sig !== sig;
                    sig = r.sig;
                    if (changed || pendingReload) reload();
                }
                schedule();
            })
            .fail(function (x) {
                if (x && (x.status === 401 || x.status === 403 || x.status === 419)) { setMark('off', tx('stopped', 'Not live')); return; } // signed out: stop
                failures++;
                setMark('off', tx('paused_short', 'Reconnecting…'));
                schedule(Math.min(MAX_BACKOFF, VISIBLE_EVERY * Math.pow(2, failures)));
            });
    };

    $(function () {
        if (!cfg.url || !$('#sell_table').length) return;
        var $h = $('.content-header h1').first();
        if ($h.length) $h.append($mark); else $('#sell_table').closest('.box, section').prepend($mark);
        $('body').append($stack);
        drawBell();
        setMark('on', tx('live', 'Live'));
        check();
    });
})();
