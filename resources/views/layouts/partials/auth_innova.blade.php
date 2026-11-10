{{-- PosInnova sign-in / register look (navy + I-NNOVA gold), loaded after extracss_auth so it wins.
     Plain CSS: the compiled Tailwind bundle lacks most utilities these pages need. --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap" rel="stylesheet">
<style>
    :root {
        --ia-navy-1: #0b1a2e; --ia-navy-2: #13294a; --ia-navy-3: #1f3a5f;
        --ia-gold: #e8b54a; --ia-ink: #101828; --ia-ink-soft: #344054; --ia-muted: #667085;
        --ia-rule: #e4e1da; --ia-rule-strong: #d0d5dd; --ia-paper: #f7f5f0;
        --ia-serif: 'Fraunces', 'Palatino Linotype', 'Book Antiqua', Georgia, serif;
        --ia-sans: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    html, body { background: var(--ia-paper); }
    body.auth-split-body { font-family: var(--ia-sans); color: var(--ia-ink); -webkit-font-smoothing: antialiased; }

    /* ---------- the split: brand panel left, form right (phones: short brand header first) */
    .auth-split { flex-direction: column !important; }
    @media (min-width: 1024px) {
        .auth-split { display: grid !important; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); }
        .auth-split__left, .auth-split__right { width: auto !important; }
    }

    /* ---------- brand panel */
    .auth-split__left {
        position: relative; overflow: hidden; color: #fff; padding: 0 !important;
        background-color: var(--ia-navy-1) !important;
        background-image: linear-gradient(155deg, var(--ia-navy-1) 0%, var(--ia-navy-2) 45%, var(--ia-navy-3) 100%) !important;
    }
    @media (min-width: 1024px) { .auth-split__left { position: sticky; top: 0; height: 100vh; display: block !important; } }
    .ia-rule { position: absolute; inset: 0; pointer-events: none;
        background-image: linear-gradient(to bottom, rgba(255,255,255,.045) 1px, transparent 1px), linear-gradient(to right, rgba(232,181,74,.10) 1px, transparent 1px);
        background-size: 100% 34px, 100% 100%; background-position: 0 0, 72px 0; background-repeat: repeat, no-repeat; }
    .ia-glow { position: absolute; border-radius: 9999px; filter: blur(70px); pointer-events: none; }
    .ia-glow--a { width: 320px; height: 320px; right: -96px; top: -96px; background: rgba(232,181,74,.20); }
    .ia-glow--b { width: 384px; height: 384px; left: -80px; bottom: -128px; background: rgba(59,111,176,.30); }
    .ia-brand-panel { position: relative; max-width: 36rem; margin: 0 auto; padding: 28px 24px 32px; display: flex; flex-direction: column; height: 100%; }
    @media (min-width: 640px) { .ia-brand-panel { padding: 32px 40px; } }
    @media (min-width: 1024px) { .ia-brand-panel { padding: 48px 40px; } }

    .ia-brand { display: flex; align-items: center; gap: 12px; color: #fff; text-decoration: none !important; }
    .ia-brand:hover { color: #fff; }
    .ia-brand-tile { flex: none; width: 46px; height: 46px; border-radius: 12px; background: rgba(255,255,255,.96); display: flex; align-items: center; justify-content: center; box-shadow: 0 6px 18px -6px rgba(0,0,0,.45); }
    .ia-brand-tile img { width: 38px; height: 38px; object-fit: contain; }
    .ia-brand-name { display: block; font-family: var(--ia-serif); font-size: 21px; line-height: 1.15; color: #fff; }
    .ia-brand-sub { display: block; font-size: 12px; color: rgba(255,255,255,.65); margin-top: 2px; }
    .ia-brand-sub b { color: var(--ia-gold); letter-spacing: .16em; font-weight: 600; }

    .ia-hero { margin-top: 26px; }
    @media (min-width: 1024px) { .ia-hero { margin-top: 46px; } }
    .ia-hero h2 { margin: 0; font-family: var(--ia-serif); font-weight: 500; font-size: 28px; line-height: 1.15; color: #fff !important; letter-spacing: -.01em; }
    @media (min-width: 640px) { .ia-hero h2 { font-size: 38px; } }
    .ia-hero h2 span { color: var(--ia-gold) !important; }
    .ia-hero p { margin: 12px 0 0; max-width: 30rem; font-size: 15px; line-height: 1.6; color: rgba(255,255,255,.78) !important; }

    .ia-flow { margin-top: 30px; display: none; max-width: 440px; }
    @media (min-width: 640px) { .ia-flow { display: block; } }
    .ia-points { list-style: none; padding: 0; margin: 30px 0 0; display: none; }
    @media (min-width: 1024px) { .ia-points { display: block; } }
    .ia-points li { display: flex; gap: 16px; padding: 15px 16px; margin-bottom: 12px; border-radius: 16px; border: 1px solid rgba(255,255,255,.10); background: rgba(255,255,255,.06); backdrop-filter: blur(4px); }
    .ia-points .ia-ic { flex: none; width: 40px; height: 40px; border-radius: 12px; background: var(--ia-gold); color: var(--ia-navy-1); display: flex; align-items: center; justify-content: center; }
    .ia-points .ia-ic svg { width: 20px; height: 20px; }
    .ia-points b { display: block; font-size: 15px; font-weight: 600; color: #fff; }
    .ia-points span.d { display: block; margin-top: 2px; font-size: 13.5px; line-height: 1.4; color: rgba(255,255,255,.70); }
    .ia-left-foot { margin-top: auto; padding-top: 32px; font-size: 12px; color: rgba(255,255,255,.45) !important; display: none; }
    @media (min-width: 1024px) { .ia-left-foot { display: block; } }
    /* short screens: the drawing gives way so the points still fit */
    @media (min-width: 1024px) and (max-height: 860px) { .ia-flow { display: none; } }
    .ia-brand-sub .ia-nb { white-space: nowrap; }

    @keyframes ia-rise { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: none; } }
    .ia-rise { animation: ia-rise .7s ease-out both; } .ia-rise-2 { animation-delay: .12s; } .ia-rise-3 { animation-delay: .24s; }
    @media (prefers-reduced-motion: reduce) { .ia-rise { animation: none; } .ia-flow-dot { display: none; } }

    /* ---------- form side */
    .auth-split__right { background: radial-gradient(1100px 560px at 100% 0%, rgba(232,181,74,.09), transparent 60%), radial-gradient(900px 520px at 0% 100%, rgba(31,58,95,.07), transparent 60%), var(--ia-paper) !important;
        padding: 18px 16px 28px !important; display: flex; flex-direction: column; min-height: auto; }
    @media (min-width: 640px) { .auth-split__right { padding: 22px 32px 36px !important; } }
    @media (min-width: 1024px) { .auth-split__right { min-height: 100vh; } }
    .ia-topbar { display: flex; align-items: center; justify-content: flex-end; gap: 10px; flex-wrap: wrap; }
    .ia-chip { display: inline-flex; align-items: center; height: 38px; padding: 0 16px; border-radius: 999px; font-size: 14px; font-weight: 600; text-decoration: none !important; border: 1px solid var(--ia-rule-strong); color: var(--ia-ink-soft) !important; background: rgba(255,255,255,.7); }
    .ia-chip:hover { background: #fff; color: var(--ia-ink) !important; }
    .ia-chip--dark { background: var(--ia-navy-3); border-color: var(--ia-navy-3); color: #fff !important; }
    .ia-chip--dark:hover { background: var(--ia-navy-2); color: #fff !important; }
    .ia-lang details > summary { height: 38px; display: inline-flex; align-items: center; padding: 0 14px !important; margin: 0 !important; border-radius: 999px; border: 1px solid var(--ia-rule-strong); background: rgba(255,255,255,.7); color: var(--ia-ink-soft) !important; font-size: 14px !important; font-weight: 600; cursor: pointer; list-style: none; }
    .ia-lang details { margin: 0 !important; }
    .ia-lang details > summary::-webkit-details-marker { display: none; }
    .ia-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 22px 0; }
    .auth-form--sm { max-width: 28rem !important; }

    .ia-head { text-align: center; }
    .ia-head-ic { margin: 0 auto; width: 64px; height: 64px; border-radius: 18px; display: flex; align-items: center; justify-content: center; color: #fff; background: linear-gradient(145deg, var(--ia-navy-3), var(--ia-navy-1)); box-shadow: 0 12px 24px -10px rgba(31,58,95,.55); }
    .ia-head-ic svg { width: 32px; height: 32px; }
    .ia-head h1 { margin: 18px 0 0; font-family: var(--ia-serif); font-weight: 500; font-size: 30px; line-height: 1.2; color: var(--ia-ink) !important; }
    .ia-head p { margin: 8px 0 0; font-size: 14.5px; color: var(--ia-muted) !important; }

    .ia-card { margin-top: 26px; background: #fff; border: 1px solid var(--ia-rule); border-radius: 18px; padding: 24px; box-shadow: 0 20px 40px -24px rgba(11,26,46,.18); }
    @media (min-width: 640px) { .ia-card { padding: 32px; } }
    .ia-field { margin-bottom: 18px; }
    .ia-label { display: flex; align-items: center; gap: 6px; margin: 0 0 7px; font-size: 14px; font-weight: 600; color: var(--ia-ink) !important; }
    .ia-label svg { width: 16px; height: 16px; color: var(--ia-navy-3); }
    .ia-input { display: block; width: 100%; height: 48px; padding: 0 16px; border-radius: 12px; border: 1px solid var(--ia-rule-strong); background: #fff; color: var(--ia-ink); font-size: 15px; transition: border-color .15s, box-shadow .15s; }
    .ia-input::placeholder { color: #98a2b3; }
    .ia-input:focus { outline: none; border-color: var(--ia-navy-3); box-shadow: 0 0 0 3px rgba(31,58,95,.16); }
    .ia-pwd { position: relative; }
    .ia-pwd .ia-input { padding-right: 52px; }
    .ia-eye { position: absolute; top: 0; right: 0; height: 48px; width: 48px; border: 0; background: transparent; color: var(--ia-muted); display: flex; align-items: center; justify-content: center; border-radius: 0 12px 12px 0; cursor: pointer; }
    .ia-eye:hover { color: var(--ia-ink); }
    .ia-eye:focus-visible { outline: 2px solid rgba(31,58,95,.4); }
    .ia-eye svg { width: 20px; height: 20px; }
    .ia-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; margin: 2px 0 20px; font-size: 14px; }
    .ia-check { display: inline-flex; align-items: center; gap: 8px; margin: 0; font-weight: 500; color: var(--ia-ink-soft) !important; cursor: pointer; min-height: 32px; }
    .ia-check input { width: 16px; height: 16px; margin: 0; accent-color: var(--ia-navy-3); }
    .ia-link { font-weight: 600; color: var(--ia-navy-3) !important; text-decoration: none !important; display: inline-block; padding: 6px 0; }
    .ia-link:hover { text-decoration: underline !important; }
    .ia-btn { display: flex; width: 100%; align-items: center; justify-content: center; gap: 8px; height: 52px; border: 0; border-radius: 12px; color: #fff !important; font-size: 15.5px; font-weight: 600; cursor: pointer;
        background: linear-gradient(100deg, var(--ia-navy-3) 0%, var(--ia-navy-2) 100%); box-shadow: 0 12px 22px -12px rgba(31,58,95,.75); transition: filter .15s, transform .05s; }
    .ia-btn:hover { filter: brightness(1.1); }
    a.ia-btn, a.ia-btn:hover, a.ia-btn:focus { text-decoration: none !important; color: #fff !important; }
    .ia-btn:active { transform: translateY(1px); }
    .ia-btn:focus-visible { outline: 2px solid var(--ia-gold); outline-offset: 2px; }
    .ia-btn svg { width: 17px; height: 17px; transition: transform .15s; }
    .ia-btn:hover svg { transform: translateX(2px); }
    .ia-alert { display: flex; gap: 8px; margin-bottom: 18px; padding: 10px 12px; border-radius: 10px; border: 1px solid #fecdca; background: #fef3f2; color: #b42318 !important; font-size: 14px; }
    .ia-alert svg { flex: none; width: 16px; height: 16px; margin-top: 2px; }
    .ia-alert--ok { border-color: #abefc6; background: #ecfdf3; color: #067647 !important; }
    .ia-card--reg { padding: 18px; }
    @media (min-width: 640px) { .ia-card--reg { padding: 28px; } }
    .ia-card--reg .tw-bg-white { box-shadow: none !important; }
    .ia-alt { margin: 20px 0 0; text-align: center; font-size: 14px; color: var(--ia-muted) !important; }
    .ia-foot { margin-top: 18px; text-align: center; font-size: 12.5px; color: var(--ia-muted) !important; }
    .ia-foot p { margin: 4px 0 0; color: var(--ia-muted) !important; }
    .ia-foot svg { display: inline-block; width: 13px; height: 13px; vertical-align: -2px; margin-right: 4px; }
    .auth-split { background: var(--ia-paper); }

    /* register wizard (jquery-steps): navy steps and buttons instead of the stock blue / indigo */
    .wizard > .steps a, .wizard > .steps a:hover, .wizard > .steps a:active { border-radius: 12px !important; }
    .wizard > .steps .current a, .wizard > .steps .current a:hover, .wizard > .steps .current a:active { background: var(--ia-navy-3) !important; color: #fff !important; }
    .wizard > .steps .done a, .wizard > .steps .done a:hover, .wizard > .steps .done a:active { background: #f3e6c4 !important; color: var(--ia-navy-1) !important; }
    .wizard > .steps .disabled a, .wizard > .steps .disabled a:hover { background: #f0efeb !important; color: var(--ia-muted) !important; }
    .wizard > .actions a, .wizard > .actions a:hover, .wizard > .actions a:active,
    .wizard > .actions a.tw-dw-btn { background: var(--ia-navy-3) !important; border-color: var(--ia-navy-3) !important; color: #fff !important; border-radius: 12px !important; font-weight: 600; }
    .wizard > .actions a:hover { background: var(--ia-navy-2) !important; }
    .wizard > .actions .disabled a, .wizard > .actions .disabled a:hover { background: #f0efeb !important; border-color: #f0efeb !important; color: #98a2b3 !important; }
    .wizard > .actions a[href="#finish"] { background: var(--ia-gold) !important; border-color: var(--ia-gold) !important; color: var(--ia-navy-1) !important; }
    .ia-foot b { color: var(--ia-ink-soft); letter-spacing: .14em; font-weight: 600; }

    /* other pages in this layout (register form, password reset, pricing): a calm white card */
    .ia-main .auth-form-wrap > .tw-w-full > .tw-bg-white,
    .auth-form-wrap .tw-rounded-xl.tw-bg-white { border-radius: 18px !important; border: 1px solid var(--ia-rule); box-shadow: 0 20px 40px -24px rgba(11,26,46,.18) !important; }
</style>
