{{-- PosInnova: collapsible product browser.

     The right-hand pane (category/brand pickers + product grid) can be hidden so
     the cart takes the full width - useful for cashiers who scan or type product
     names rather than tapping tiles. #pi_browser_collapse (in pos_sidebar) hides
     it; the fixed "Products" tab below brings it back.

     Included by sale_pos/create and sale_pos/edit BEFORE the two panes, so the
     saved state is applied before they are parsed and the page never paints the
     old 60/40 split first and then jumps.

     The choice is remembered per browser in localStorage. That is a per-till
     convenience, nothing more: if storage is blocked (private window, cleared
     site data) the pane simply starts open, which is the original behaviour. --}}
<button type="button" class="pi-browser-expand no-print" id="pi_browser_expand"
    aria-controls="pi_pos_browser_pane" aria-expanded="false"
    title="@lang('lang_v1.show_product_panel_help')">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6" /></svg>
    <span>@lang('lang_v1.show_product_panel')</span>
</button>
<script>
    (function () {
        var KEY = 'pi_pos_browser_collapsed';

        function read() {
            try { return window.localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
        }
        function write(collapsed) {
            try { window.localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (e) {}
        }
        function apply(collapsed) {
            document.body.classList.toggle('pi-browser-collapsed', collapsed);
            // "expanded" describes the pane both buttons control
            ['pi_browser_collapse', 'pi_browser_expand'].forEach(function (id) {
                var el = document.getElementById(id);
                if (el) { el.setAttribute('aria-expanded', collapsed ? 'false' : 'true'); }
            });
        }

        // Now, before the panes exist, so the first paint is already right...
        apply(read());
        // ...and again once the collapse button (parsed later) is in the DOM.
        document.addEventListener('DOMContentLoaded', function () { apply(read()); });

        document.addEventListener('click', function (e) {
            var t = e.target && e.target.closest
                ? e.target.closest('#pi_browser_collapse, #pi_browser_expand') : null;
            if (!t) { return; }
            e.preventDefault();
            var collapsed = t.id === 'pi_browser_collapse';
            apply(collapsed);
            write(collapsed);
            // the control the cashier clicked has just disappeared - hand focus
            // to the one that undoes it, so a keyboard user is never stranded
            var next = document.getElementById(collapsed ? 'pi_browser_expand' : 'pi_browser_collapse');
            if (next) { next.focus(); }
        });
    })();
</script>
