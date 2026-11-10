{{-- PosInnova hospital shifts: countdown-only ticker for the header shift badge
     on non-POS pages. (POS pages run their own countdown + handover JS.) --}}
<script type="text/javascript">
$(function () {
    var $badge = $('#pos_shift_badge');
    if (!$badge.length) return;

    var endsAtStr = $badge.data('ends-at');
    var serverNowStr = $badge.data('server-now');
    var extended = parseInt($badge.data('extended') || 0, 10) === 1;

    // Anchor to server time so a wrong PC clock can't skew the countdown.
    var clientOffset = serverNowStr ? (new Date(serverNowStr).getTime() - Date.now()) : 0;
    var endsAt = endsAtStr ? new Date(endsAtStr).getTime() : null;

    function serverNow() { return Date.now() + clientOffset; }
    function two(n) { return (n < 10 ? '0' : '') + n; }
    function setCountdown(text) { $('#pos_shift_countdown').text('· ' + text); }

    function tick() {
        if (!endsAt) return;
        if (extended) { setCountdown(@json(__('lang_v1.extended'))); return; }
        var remaining = Math.floor((endsAt - serverNow()) / 1000);
        if (remaining <= 0) { setCountdown(@json(__('lang_v1.shift_over'))); return; }
        var h = Math.floor(remaining / 3600);
        var m = Math.floor((remaining % 3600) / 60);
        var s = remaining % 60;
        setCountdown((h > 0 ? h + ':' : '') + two(m) + ':' + two(s));
    }

    tick();
    setInterval(tick, 1000);
});
</script>
