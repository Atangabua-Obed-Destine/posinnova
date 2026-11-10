   <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
        /* ================================
           Split-screen auth layout
           ================================ */
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            background: #f6f8fb;
        }
        body.auth-split-body {
            min-height: 100vh;
            font-family: 'Source Sans Pro', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        /* Parent row: stretch children so the brand panel always matches the form's height.
           NOTE: tw-flex-col-reverse / lg:tw-w-1/2 are NOT in the compiled tailwind build,
           so we explicitly drive the responsive layout here. */
        .auth-split {
            display: flex;
            flex-direction: column-reverse; /* mobile/tablet: brand panel BELOW form */
            align-items: stretch;
            min-height: 100vh;
        }
        .auth-split__left,
        .auth-split__right { width: 100%; }
        @media (min-width: 1024px) {
            .auth-split { flex-direction: row; }
            .auth-split__left,
            .auth-split__right { width: 50%; }
        }
        /* Form widths (Tailwind max-w utilities aren't compiled in this build) */
        .auth-form-wrap { width: 100%; margin-left: auto; margin-right: auto; }
        .auth-form--sm { max-width: 24rem; }   /* 384px - login / password */
        .auth-form--md { max-width: 28rem; }   /* 448px - default */
        .auth-form--lg { max-width: 36rem; }   /* 576px */
        .auth-form--xl { max-width: 48rem; }   /* 768px - business register */
        /* Modern input look on the auth pages (overrides legacy/Tailwind gaps) */
        .auth-split__right .auth-input,
        .auth-split__right input[type="text"].auth-input,
        .auth-split__right input[type="email"].auth-input,
        .auth-split__right input[type="password"].auth-input {
            display: block;
            width: 100%;
            height: 3rem;
            padding: 0 1rem;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 0.75rem;
            color: #111827;
            font-size: 0.95rem;
            transition: border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .auth-split__right .auth-input::placeholder { color: #9ca3af; }
        .auth-split__right .auth-input:focus {
            outline: none;
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }
        /* Primary submit button (avoids reliance on uncompiled tw-gradient utilities) */
        .auth-btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 3rem;
            padding: 0 1.25rem;
            border: 0;
            border-radius: 0.75rem;
            background: linear-gradient(90deg, #4f46e5 0%, #2563eb 100%);
            color: #ffffff !important;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 4px 14px -4px rgba(37, 99, 235, 0.45);
            cursor: pointer;
            transition: filter .15s ease, box-shadow .15s ease, transform .05s ease;
        }
        .auth-btn-primary:hover { filter: brightness(1.05); box-shadow: 0 6px 18px -4px rgba(37, 99, 235, 0.55); }
        .auth-btn-primary:active { transform: translateY(1px); }
        .auth-btn-primary:focus { outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.25); }
        /* Password input + eye toggle */
        .auth-pwd-wrap { position: relative; display: block; }
        .auth-input--pwd { padding-right: 3rem; }
        .auth-pwd-toggle {
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            padding: 0;
            background: transparent;
            border: 0;
            color: #9ca3af;
            cursor: pointer;
            border-radius: 0.5rem;
        }
        .auth-pwd-toggle:hover { color: #374151; background: #f3f4f6; }
        .auth-pwd-toggle:focus { outline: none; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.18); }
        /* Left brand panel — deep blue gradient (stretches with the row, no black void) */
        .auth-split__left {
            background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 45%, #2563eb 100%);
        }
        /* Inner wrapper inside the aside — keeps content centered & sticky as the form scrolls */
        .auth-split__left-inner {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 36rem;
            margin: 0 auto;
        }
        /* Decorative blobs */
        .auth-blob {
            position: absolute;
            border-radius: 9999px;
            filter: blur(60px);
            opacity: 0.45;
            pointer-events: none;
        }
        .auth-blob--1 {
            width: 360px; height: 360px;
            background: #60a5fa;
            top: -80px; left: -80px;
        }
        .auth-blob--2 {
            width: 420px; height: 420px;
            background: #6366f1;
            bottom: -120px; right: -120px;
        }
        /* Subtle dot grid overlay */
        .auth-grid {
            position: absolute; inset: 0;
            background-image: radial-gradient(rgba(255,255,255,0.10) 1px, transparent 1px);
            background-size: 22px 22px;
            mask-image: linear-gradient(180deg, rgba(0,0,0,0.6), rgba(0,0,0,0));
            -webkit-mask-image: linear-gradient(180deg, rgba(0,0,0,0.6), rgba(0,0,0,0));
            pointer-events: none;
        }
        .auth-hero-title { letter-spacing: -0.02em; }
        .auth-feature-icon {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            box-shadow: 0 6px 20px -6px rgba(249, 115, 22, 0.55);
        }
        .auth-feature-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(8px);
            transition: background-color .2s ease, border-color .2s ease, transform .2s ease;
        }
        .auth-feature-card:hover {
            background: rgba(255, 255, 255, 0.13);
            border-color: rgba(255, 255, 255, 0.20);
            transform: translateY(-1px);
        }
        /* Big centered borderless brand logo */
        .auth-brand-logo {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin: 0 auto 2rem;
            text-align: center;
            text-decoration: none;
            color: inherit;
        }
        .auth-brand-logo:hover { color: inherit; text-decoration: none; }
        .auth-brand-logo img {
            /* Native size 120x40 — don't upscale to keep it crisp */
            width: 240px;
            height: 80px;
            max-width: 100%;
            object-fit: contain;
            background: transparent;
            border: 0;
            box-shadow: none;
            image-rendering: -webkit-optimize-contrast;
        }
        .auth-brand-name {
            font-size: 3rem;
            font-weight: 800;
            letter-spacing: -0.01em;
            color: #ffffff;
        }
        @media (max-width: 1023.98px) {
            .auth-brand-logo img { max-width: 20rem; }
            .auth-brand-name { font-size: 2rem; }
        }
        @media (max-width: 639.98px) {
            .auth-brand-logo img { max-width: 14rem; }
            .auth-brand-name { font-size: 1.5rem; }
        }
        /* On large screens, brand panel stretches with the row (no fixed height), but its inner content sticks while the form scrolls */
        @media (min-width: 1024px) {
            .auth-split { min-height: 100vh; }
            .auth-split__left { display: flex; align-items: center; }
            .auth-split__left-inner {
                position: sticky;
                top: 0;
                padding: 3.5rem 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }
        }
        /* ====== Mobile / tablet refinements (< lg) ======
           Brand panel renders BELOW the form (flex-col-reverse on parent),
           so trim it visually so it reads as a marketing footer. */
        @media (max-width: 1023.98px) {
            .auth-split__left {
                padding-top: 2.25rem;
                padding-bottom: 2.5rem;
                min-height: auto;
            }
            .auth-hero-title {
                font-size: 1.75rem !important;
                line-height: 1.2;
            }
            .auth-blob--1 { width: 220px; height: 220px; top: -60px; left: -60px; }
            .auth-blob--2 { width: 240px; height: 240px; bottom: -80px; right: -80px; }
        }
        @media (max-width: 639.98px) {
            /* On phones, hide the 3 feature cards to keep the brand panel short */
            .auth-feature-card { display: none; }
            .auth-split__left { padding: 1.75rem 1.25rem 2rem; }
            .auth-split__right { padding: 1.25rem 1rem 1.5rem; }
        }
        /* Lang button summary needs to align with the pill buttons in the top bar */
        .auth-lang-btn { display: inline-flex; }
        .auth-lang-btn details > summary { margin: 0 !important; padding: 0.5rem 0.75rem; cursor: pointer; }
        .auth-lang-btn details > summary::-webkit-details-marker { display: none; }
        /* Brand panel headings always white (overrides any global dark heading rule) */
        .auth-split__left h1,
        .auth-split__left h2,
        .auth-split__left h3,
        .auth-split__left p,
        .auth-split__left .auth-hero-title { color: #ffffff !important; }
        /* Heading colors inside form area */
        .auth-split__right h1, .auth-split__right h2 { color: #0f172a; }
        /* Language button tweaks (override navbar styling) */
        .auth-lang-btn .dropdown > a, .auth-lang-btn .btn { color: #4b5563 !important; }

        /* ============================================================
           White-on-white safeguards for the right (light) panel.
           The legacy auth markup had bits styled white for a dark bg —
           force them readable here without affecting other screens.
           ============================================================ */
        .auth-split__right { color: #1f2937; }
        .auth-split__right h1,
        .auth-split__right h2,
        .auth-split__right h3,
        .auth-split__right h4,
        .auth-split__right h5,
        .auth-split__right h6,
        .auth-split__right legend,
        .auth-split__right label,
        .auth-split__right p,
        .auth-split__right span,
        .auth-split__right small,
        .auth-split__right li,
        .auth-split__right .help-block,
        .auth-split__right .form-group label,
        .auth-split__right .input-group-addon { color: inherit; }
        .auth-split__right .help-block { color: #6b7280; }
        /* The language dropdown summary uses tw-text-white globally — fix it here */
        .auth-split__right summary { color: #4b5563 !important; }
        .auth-split__right .tw-text-white,
        .auth-split__right .text-white { color: #1f2937 !important; }
        /* …but keep gradient buttons & icon tiles white */
        .auth-split__right .tw-bg-gradient-to-r .tw-text-white,
        .auth-split__right .tw-bg-gradient-to-br .tw-text-white,
        .auth-split__right button.tw-text-white,
        .auth-split__right a.tw-text-white,
        .auth-split__right .tw-bg-gradient-to-r,
        .auth-split__right .tw-bg-gradient-to-br { color: #ffffff !important; }
        .auth-split__right .tw-bg-gradient-to-r *,
        .auth-split__right .tw-bg-gradient-to-br * { color: #ffffff; }
        /* Inputs */
        .auth-split__right input,
        .auth-split__right select,
        .auth-split__right textarea { color: #111827; }
        .auth-split__right input::placeholder,
        .auth-split__right textarea::placeholder { color: #9ca3af; }
        /* Bootstrap input-group-addon visibility */
        .auth-split__right .input-group-addon {
            background-color: #f3f4f6;
            border-color: #e5e7eb;
            color: #4b5563;
        }
        .auth-split__right .input-group-addon i,
        .auth-split__right .input-group-addon .fa,
        .auth-split__right .input-group-addon .fas,
        .auth-split__right .input-group-addon .far,
        .auth-split__right .input-group-addon .fab { color: #4b5563 !important; }

        /* Generic FontAwesome icon safety net for the light right panel.
           Any <i> not sitting inside a colored button/badge/card defaults to dark. */
        .auth-split__right i.fa,
        .auth-split__right i.fas,
        .auth-split__right i.far,
        .auth-split__right i.fab,
        .auth-split__right i.fal { color: #4b5563; }
        /* But keep icons inside colored buttons / gradient tiles white */
        .auth-split__right .btn-primary i,
        .auth-split__right .btn-success i,
        .auth-split__right .btn-info i,
        .auth-split__right .btn-warning i,
        .auth-split__right .btn-danger i,
        .auth-split__right .btn-app i,
        .auth-split__right .bg-olive i,
        .auth-split__right .bg-maroon i,
        .auth-split__right .bg-orange i,
        .auth-split__right .bg-purple i,
        .auth-split__right .bg-navy i,
        .auth-split__right .bg-red i,
        .auth-split__right .bg-blue i,
        .auth-split__right .bg-green i,
        .auth-split__right .tw-bg-gradient-to-r i,
        .auth-split__right .tw-bg-gradient-to-br i,
        .auth-split__right [class*="tw-bg-blue-"] i,
        .auth-split__right [class*="tw-bg-indigo-"] i,
        .auth-split__right [class*="tw-bg-orange-"] i { color: #ffffff !important; }
        /* SVG icons follow the same convention via currentColor — ensure parents have a color */
        .auth-split__right .control-label,
        .auth-split__right label { color: #1f2937; }
        .auth-split__right .form-control { color: #111827; }
        /* Links */
        .auth-split__right a { color: #2563eb; }
        .auth-split__right a:hover { color: #1d4ed8; }
    </style>

    <style type="text/css">
        /*
      * Pattern lock css
      * Pattern direction
      * http://ignitersworld.com/lab/patternLock.html
      */
        .patt-wrap {
            z-index: 10;
        }

        .patt-circ.hovered {
            background-color: #cde2f2;
            border: none;
        }

        .patt-circ.hovered .patt-dots {
            display: none;
        }

        .patt-circ.dir {
            background-image: url("http://pos.test/img/pattern-directionicon-arrow.png");
            background-position: center;
            background-repeat: no-repeat;
        }

        .patt-circ.e {
            -webkit-transform: rotate(0);
            transform: rotate(0);
        }

        .patt-circ.s-e {
            -webkit-transform: rotate(45deg);
            transform: rotate(45deg);
        }

        .patt-circ.s {
            -webkit-transform: rotate(90deg);
            transform: rotate(90deg);
        }

        .patt-circ.s-w {
            -webkit-transform: rotate(135deg);
            transform: rotate(135deg);
        }

        .patt-circ.w {
            -webkit-transform: rotate(180deg);
            transform: rotate(180deg);
        }

        .patt-circ.n-w {
            -webkit-transform: rotate(225deg);
            transform: rotate(225deg);
        }

        .patt-circ.n {
            -webkit-transform: rotate(270deg);
            transform: rotate(270deg);
        }

        .patt-circ.n-e {
            -webkit-transform: rotate(315deg);
            transform: rotate(315deg);
        }
    </style>
    <style>
        /* (h1 white override removed — now scoped per panel in main auth styles) */
    </style>
    <style>
        .action-link[data-v-1552a5b6] {
            cursor: pointer;
        }
    </style>
    <style>
        .action-link[data-v-397d14ca] {
            cursor: pointer;
        }
    </style>
    <style>
        .action-link[data-v-49962cc0] {
            cursor: pointer;
        }
    </style>

<link href="{{ asset('css/tailwind/app.css') }}" rel="stylesheet">
