{{-- PosInnova: global support / contact info ticker. Shown on every screen.
     Fixed to the bottom of the viewport, hidden when printing. --}}
<div class="posinnova-ticker no-print" role="region" aria-label="System support information">
    <div class="posinnova-ticker__brand">
        <i class="fas fa-headset"></i>
        <span>I-NNOVA Support</span>
    </div>
    <div class="posinnova-ticker__viewport">
        <div class="posinnova-ticker__track">
            {{-- Track is duplicated below for a perfectly seamless loop --}}
            @for ($i = 0; $i < 2; $i++)
                <span class="posinnova-ticker__item">
                    <i class="fas fa-info-circle"></i>
                    Need help or facing an issue? Our team is here for you 24/7.
                </span>
                <span class="posinnova-ticker__sep">•</span>
                <span class="posinnova-ticker__item">
                    <i class="fas fa-envelope"></i>
                    <a href="mailto:contact@i-nnovacmr.com">contact@i-nnovacmr.com</a>
                </span>
                <span class="posinnova-ticker__sep">•</span>
                <span class="posinnova-ticker__item">
                    <i class="fas fa-phone-alt"></i>
                    <a href="tel:+237671008494">+237 671 008 494</a>
                </span>
                <span class="posinnova-ticker__sep">•</span>
                <span class="posinnova-ticker__item">
                    <i class="fas fa-map-marker-alt"></i>
                    Belgocam Building, 1st Floor, Commercial Avenue, Bamenda &mdash; Behind the Camccul Building
                </span>
                <span class="posinnova-ticker__sep">•</span>
                <span class="posinnova-ticker__item">
                    <i class="fas fa-shield-alt"></i>
                    Powered by I-NNOVA &mdash; Smart POS for growing businesses.
                </span>
                <span class="posinnova-ticker__sep">•</span>
            @endfor
        </div>
    </div>
    <button type="button" class="posinnova-ticker__close" aria-label="Hide support bar"
        onclick="document.body.classList.toggle('posinnova-ticker-hidden')">
        <i class="fas fa-times"></i>
    </button>
</div>

<style>
    :root { --posinnova-ticker-h: 34px; }

    .posinnova-ticker {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        height: var(--posinnova-ticker-h);
        z-index: 1040; /* above bootstrap navbar (1030), below modals (1050) */
        display: flex;
        align-items: center;
        gap: 0;
        background: linear-gradient(90deg, #001F3E 0%, #0b3b73 60%, #0a2a52 100%);
        color: #fff;
        font-size: 12.5px;
        font-weight: 500;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.18);
        overflow: hidden;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }

    .posinnova-ticker__brand {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0 14px;
        height: 100%;
        background: rgba(255, 255, 255, 0.08);
        border-right: 1px solid rgba(255, 255, 255, 0.12);
        font-weight: 700;
        letter-spacing: .3px;
        white-space: nowrap;
        flex-shrink: 0;
    }
    .posinnova-ticker__brand i { color: #28b77b; }

    .posinnova-ticker__viewport {
        flex: 1;
        overflow: hidden;
        position: relative;
        height: 100%;
        display: flex;
        align-items: center;
    }

    .posinnova-ticker__track {
        display: inline-flex;
        align-items: center;
        gap: 14px;
        white-space: nowrap;
        padding-left: 100%; /* start fully off-screen on the right */
        animation: posinnova-ticker-scroll 55s linear infinite;
        will-change: transform;
    }
    .posinnova-ticker:hover .posinnova-ticker__track { animation-play-state: paused; }

    .posinnova-ticker__item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .posinnova-ticker__item i { color: #ffd66b; opacity: .95; }
    .posinnova-ticker__item a { color: #fff; text-decoration: underline; }
    .posinnova-ticker__item a:hover { color: #ffd66b; }

    .posinnova-ticker__sep { opacity: .35; }

    .posinnova-ticker__close {
        background: transparent;
        border: 0;
        color: rgba(255, 255, 255, 0.7);
        height: 100%;
        padding: 0 12px;
        cursor: pointer;
        flex-shrink: 0;
        transition: color .15s ease, background .15s ease;
    }
    .posinnova-ticker__close:hover { color: #fff; background: rgba(255, 255, 255, 0.08); }

    @keyframes posinnova-ticker-scroll {
        0%   { transform: translateX(0); }
        100% { transform: translateX(-50%); } /* track contains content twice */
    }

    /* Push page content up so the ticker never covers anything important. */
    body { padding-bottom: var(--posinnova-ticker-h) !important; }
    body.posinnova-ticker-hidden { padding-bottom: 0 !important; }
    body.posinnova-ticker-hidden .posinnova-ticker { display: none; }

    @media (max-width: 600px) {
        :root { --posinnova-ticker-h: 30px; }
        .posinnova-ticker { font-size: 11.5px; }
        .posinnova-ticker__brand span { display: none; }
        .posinnova-ticker__brand { padding: 0 10px; }
    }

    /* Honour user's reduced-motion preference. */
    @media (prefers-reduced-motion: reduce) {
        .posinnova-ticker__track { animation-duration: 120s; }
    }

    @media print {
        .posinnova-ticker { display: none !important; }
        body { padding-bottom: 0 !important; }
    }
</style>
