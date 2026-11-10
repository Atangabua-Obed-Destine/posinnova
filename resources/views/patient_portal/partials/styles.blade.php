<style>
    body { background: #f1f5f9; -webkit-tap-highlight-color: transparent; }

    /* Stat grid: 2-up on phones, 4-up on tablets+. */
    @media (min-width: 640px) { .portal-stats { grid-template-columns: repeat(4, 1fr) !important; } }

    /* Grid/flex shims — guarantee layout even if these Tailwind utilities aren't in the build. */
    .tw-grid { display: grid; }
    .tw-grid-cols-1 { grid-template-columns: repeat(1, minmax(0,1fr)); }
    .tw-grid-cols-2 { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .tw-grid-cols-3 { grid-template-columns: repeat(3, minmax(0,1fr)); }
    .tw-grid-cols-4 { grid-template-columns: repeat(4, minmax(0,1fr)); }
    @media (min-width:640px){ .sm\:tw-grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr));} .sm\:tw-grid-cols-4{grid-template-columns:repeat(4,minmax(0,1fr));} .sm\:tw-grid-cols-5{grid-template-columns:repeat(5,minmax(0,1fr));} }
    @media (min-width:1024px){ .lg\:tw-grid-cols-3{grid-template-columns:repeat(3,minmax(0,1fr));} .lg\:tw-col-span-2{grid-column:span 2 / span 2;} }
    /* Flex + gap shims */
    .tw-flex { display: flex; } .tw-inline-flex { display: inline-flex; }
    .tw-flex-col { flex-direction: column; } .tw-flex-1 { flex: 1 1 0%; } .tw-shrink-0 { flex-shrink: 0; }
    .tw-items-center { align-items: center; } .tw-items-start { align-items: flex-start; }
    .tw-justify-between { justify-content: space-between; } .tw-justify-center { justify-content: center; } .tw-justify-around { justify-content: space-around; }
    .tw-gap-1{gap:4px;} .tw-gap-1\.5{gap:6px;} .tw-gap-2{gap:8px;} .tw-gap-3{gap:12px;} .tw-gap-4{gap:16px;}
    .tw-space-y-3 > * + *{margin-top:12px;} .tw-space-y-4 > * + *{margin-top:16px;}
    .portal-topbar { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; }

    /* Native-style cross-document page slide (Chrome/Edge MPA). No-ops elsewhere. */
    @view-transition { navigation: auto; }
    @media (prefers-reduced-motion: reduce) { @view-transition { navigation: none; } }

    /* Bottom bar clears the iPhone home indicator (safe area). */
    .portal-bottombar { padding-top: 4px; padding-bottom: calc(6px + env(safe-area-inset-bottom)); }
    .portal-bottombar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; display: flex; justify-content: space-around; background: rgba(255,255,255,.97); box-shadow: 0 -1px 0 rgba(0,0,0,.06); }
    .portal-bottombar a { flex: 1 1 0; min-width: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; padding: 6px 0; }
    .portal-bottombar a span { font-size: 11px; }
    /* Install banner */
    .portal-install { position: fixed; left: 12px; right: 12px; bottom: calc(72px + env(safe-area-inset-bottom)); z-index: 40; display: flex; align-items: center; gap: 12px; border-radius: 16px; padding: 12px 16px; background: #4f46e5; color: #fff; box-shadow: 0 10px 30px -10px rgba(79,70,229,.6); }
    @media (max-width: 767px) {
        .portal-has-bottombar { padding-bottom: calc(70px + env(safe-area-inset-bottom)); }
    }
    .portal-topbar { padding-top: calc(0.75rem + env(safe-area-inset-top)); }
    @media (min-width: 768px) { .portal-topbar { padding-top: 0.75rem; } }

    /* Tap feedback — press states feel native. */
    .portal-press { transition: transform .12s ease, background-color .15s ease, box-shadow .15s ease; }
    .portal-press:active { transform: scale(.97); }
    .portal-tab:active i { transform: scale(.85); transition: transform .1s ease; }

    /* Card press affordance on tappable cards. */
    a.portal-card, button.portal-card { transition: transform .12s ease, box-shadow .15s ease; }
    a.portal-card:active, button.portal-card:active { transform: scale(.985); }
    a.portal-card:hover { box-shadow: 0 8px 24px -8px rgba(15,23,42,.18); transform: translateY(-1px); }

    /* Soft depth for content cards. */
    .portal-page .tw-shadow-sm { box-shadow: 0 1px 3px rgba(15,23,42,.06), 0 6px 16px -10px rgba(15,23,42,.12) !important; }
    /* Decorative glow behind gradient hero/balance cards. */
    .portal-page .tw-from-indigo-600.tw-to-blue-500 { position: relative; overflow: hidden; }
    .portal-page .tw-from-indigo-600.tw-to-blue-500::after { content:''; position:absolute; top:-40px; right:-30px; width:140px; height:140px; border-radius:9999px; background:rgba(255,255,255,.12); }

    /* Page enter animation. */
    .portal-page { animation: portalFade .25s ease both; }
    @keyframes portalFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }

    /* Focus-visible rings for keyboard/a11y. */
    .portal-press:focus-visible, a.portal-card:focus-visible, .portal-tab:focus-visible {
        outline: 2px solid #6366f1; outline-offset: 2px;
    }

    /* Scroll-fade affordance for the horizontal chip nav. */
    .portal-chipnav { position: relative; display: flex; gap: 8px; }
    .portal-chipnav a { display: inline-flex; align-items: center; gap: 6px; }
    .portal-chipnav::after {
        content: ''; position: absolute; top: 0; right: 0; height: 100%; width: 28px;
        background: linear-gradient(to right, rgba(243,244,246,0), #f3f4f6); pointer-events: none;
    }
    .portal-chipnav::-webkit-scrollbar { display: none; }
    .portal-chipnav { scrollbar-width: none; }

    /* Skeleton shimmer. */
    .portal-skeleton { background: linear-gradient(90deg,#eee 25%,#f5f5f5 37%,#eee 63%); background-size: 400% 100%; animation: portalShimmer 1.4s ease infinite; border-radius: .5rem; }
    @keyframes portalShimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }

    /* Toast. */
    .portal-toast { animation: portalToastIn .3s ease both; }
    @keyframes portalToastIn { from { opacity: 0; transform: translateY(-12px); } to { opacity: 1; transform: none; } }

    /* ---- Responsive md: shims (these Tailwind variants aren't in the build) ---- */
    .tw-hidden { display: none; }
    /* Install banner respects its toggle class + hides on desktop */
    .portal-install.tw-hidden { display: none !important; }
    .portal-install:not(.tw-hidden) { display: flex; }
    @media (min-width: 768px) {
        .md\:tw-flex-row { flex-direction: row !important; }
        .md\:tw-flex { display: flex !important; }
        .md\:tw-flex-col { flex-direction: column !important; }
        .md\:tw-w-72 { width: 18rem !important; }
        .md\:tw-block { display: block !important; }
        .md\:tw-hidden { display: none !important; }
        /* Desktop: hide the mobile bottom bar + install banner, give content room */
        .portal-bottombar { display: none !important; }
        .portal-install { display: none !important; }
        .portal-has-bottombar { padding-bottom: 0 !important; }
    }
</style>
