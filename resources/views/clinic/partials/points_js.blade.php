{{--
    PosInnova: writing in points. Any <textarea class="pi-points"> grows with what is typed, gets a
    "Points" button under it (turns the line, or the lines selected, into points and back), and Enter on
    a point starts the next one (Enter on an empty point ends the list). "- " or "* " typed at the start
    of a line becomes a point too. The text stays plain text: "• " at the start of a line.
    With class "pi-quiet" as well: the same without the button (short fields).
    Include once per page; call PiPoints.init(root) after adding fields.
--}}
@once
<style>
    textarea.pi-points { resize: vertical; overflow: hidden; min-height: 44px; line-height: 1.45; }
    .pi-pts-btn { display: inline-flex; align-items: center; gap: 6px; margin-top: 5px; min-height: 36px; padding: 5px 13px; border: 1px solid #c7d2fe; border-radius: 999px; background: #eef2ff; color: #3730a3; font-weight: 600; font-size: 13px; cursor: pointer; }
    .pi-pts-btn:hover { border-color: #6366f1; }
    .pi-pts-btn b { font-size: 16px; line-height: 1; }
</style>
<script>
window.PiPoints = (function () {
    var B = '• ', LABEL = {!! json_encode(__('clinic.pts_btn')) !!}, TITLE = {!! json_encode(__('clinic.pts_btn_help')) !!};

    function grow(ta) {
        ta.style.height = 'auto';
        if (ta.scrollHeight > 0) { ta.style.height = (ta.scrollHeight + 2) + 'px'; } // 0 = not on screen yet (a closed panel)
    }
    function changed(ta) {
        grow(ta);
        ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    function lineAt(v, pos) {
        var s = v.lastIndexOf('\n', pos - 1) + 1, e = v.indexOf('\n', pos);
        return { s: s, e: e < 0 ? v.length : e };
    }
    /** Enter inside a point: the next point (or the end of the list when the point is empty). */
    function enter(ta) {
        var v = ta.value, a = ta.selectionStart, b = ta.selectionEnd, l = lineAt(v, a), line = v.slice(l.s, l.e);
        if (line.indexOf(B) !== 0) { return false; }
        if (line.trim() === B.trim() && a === b) {
            ta.value = v.slice(0, l.s) + v.slice(l.e);
            ta.selectionStart = ta.selectionEnd = l.s;
        } else {
            ta.value = v.slice(0, a) + '\n' + B + v.slice(b);
            ta.selectionStart = ta.selectionEnd = a + 1 + B.length;
        }
        changed(ta);
        return true;
    }
    /** The button: the lines touched by the selection become points; all points already = back to plain lines. */
    function toggle(ta) {
        var v = ta.value, a = ta.selectionStart, b = ta.selectionEnd;
        var s = lineAt(v, a).s, e = lineAt(v, b).e;
        var lines = v.slice(s, e).split('\n');
        var full = lines.filter(function (x) { return x.trim() !== ''; });
        var all = full.length > 0 && full.every(function (x) { return x.indexOf(B) === 0; });
        var out = lines.map(function (x) {
            if (all) { return x.indexOf(B) === 0 ? x.slice(B.length) : x; }
            if (x.trim() === '') { return lines.length === 1 ? B : x; }
            return x.indexOf(B) === 0 ? x : B + x.replace(/^\s*[-*]\s+/, '');
        }).join('\n');
        ta.value = v.slice(0, s) + out + v.slice(e);
        ta.focus();
        ta.selectionStart = ta.selectionEnd = s + out.length;
        changed(ta);
    }
    function wire(ta) {
        if (ta.__pts) { return; }
        ta.__pts = true;
        if (!ta.classList.contains('pi-quiet')) { // pi-quiet: grows and takes "- " and Enter, without the button
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pi-pts-btn';
            btn.title = TITLE;
            btn.innerHTML = '<b aria-hidden="true">•</b> ';
            btn.appendChild(document.createTextNode(LABEL));
            btn.addEventListener('click', function () { toggle(ta); });
            ta.insertAdjacentElement('afterend', btn);
        }

        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.ctrlKey && !e.metaKey && !e.isComposing && e.keyCode !== 229) {
                if (enter(ta)) { e.preventDefault(); }
            }
        });
        // phone keyboards that do not send a usable keydown
        ta.addEventListener('beforeinput', function (e) {
            if ((e.inputType === 'insertLineBreak' || e.inputType === 'insertParagraph') && !e.isComposing) {
                if (enter(ta)) { e.preventDefault(); }
            }
        });
        ta.addEventListener('input', function (e) {
            if (e.isTrusted) { // "- " or "* " typed at the start of a line
                var v = ta.value, a = ta.selectionStart, l = lineAt(v, a), m = v.slice(l.s, a).match(/^[-*] $/);
                if (m) {
                    ta.value = v.slice(0, l.s) + B + v.slice(a);
                    ta.selectionStart = ta.selectionEnd = l.s + B.length;
                }
            }
            grow(ta);
        });
        ta.addEventListener('focus', function () { grow(ta); });
        grow(ta);
    }
    /** New fields under root get the button; every field is sized again (after a value was put in by a script). */
    function init(root) {
        [].forEach.call((root || document).querySelectorAll('textarea.pi-points'), function (ta) { wire(ta); grow(ta); });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { init(document); }); } else { init(document); }
    window.addEventListener('load', function () { [].forEach.call(document.querySelectorAll('textarea.pi-points'), grow); });

    return { init: init, toggle: toggle };
})();
</script>
@endonce
