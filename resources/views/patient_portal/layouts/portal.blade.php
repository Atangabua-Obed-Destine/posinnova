<!DOCTYPE html>
<html data-theme="light" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    {{-- Pinch-zoom restored (no user-scalable=no); viewport-fit=cover enables safe-area insets --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('lang_v1.patient_portal')) · {{ session('business.name', config('app.name')) }}</title>

    {{-- PWA --}}
    <link rel="manifest" href="{{ route('portal.manifest') }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ __('lang_v1.patient_portal') }}">
    <link rel="apple-touch-icon" href="{{ asset('img/pwa-icon-192.png') }}">

    <link href="{{ asset('css/tailwind/app.css?v='.$asset_v) }}" rel="stylesheet">
    <link href="{{ asset('css/tailwind/portal.css?v='.$asset_v) }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/vendor.css?v=' . $vendor_v) }}">
    <link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">
    @include('patient_portal.partials.styles')
</head>
<body>
@php
    $p = $portal_patient ?? null;
    $pd = optional($p)->patientDetails;
    $currentGroup = $portal_group ?? 'home';
    $nav = [
        ['route' => 'portal.dashboard', 'icon' => 'fa-home', 'label' => __('lang_v1.portal_home'), 'group' => 'home'],
        ['route' => 'portal.visits', 'icon' => 'fa-stethoscope', 'label' => __('lang_v1.my_visits'), 'group' => 'records'],
        ['route' => 'portal.prescriptions', 'icon' => 'fa-prescription', 'label' => __('lang_v1.my_prescriptions'), 'group' => 'records'],
        ['route' => 'portal.lab-results', 'icon' => 'fa-vial', 'label' => __('lang_v1.my_lab_results'), 'group' => 'records'],
        ['route' => 'portal.imaging', 'icon' => 'fa-x-ray', 'label' => __('lang_v1.my_imaging'), 'group' => 'records'],
        ['route' => 'portal.admissions', 'icon' => 'fa-procedures', 'label' => __('lang_v1.my_admissions'), 'group' => 'records'],
        ['route' => 'portal.bills', 'icon' => 'fa-file-invoice-dollar', 'label' => __('lang_v1.my_bills'), 'group' => 'bills'],
        ['route' => 'portal.profile', 'icon' => 'fa-user', 'label' => __('lang_v1.my_profile'), 'group' => 'profile'],
    ];
    // Bottom tab bar: four primary destinations; "Records" stays active across all record sub-pages.
    $tabs = [
        ['route' => 'portal.dashboard', 'icon' => 'fa-home', 'label' => __('lang_v1.portal_home'), 'group' => 'home'],
        ['route' => 'portal.visits', 'icon' => 'fa-notes-medical', 'label' => __('lang_v1.records'), 'group' => 'records'],
        ['route' => 'portal.bills', 'icon' => 'fa-file-invoice-dollar', 'label' => __('lang_v1.bills'), 'group' => 'bills'],
        ['route' => 'portal.profile', 'icon' => 'fa-user', 'label' => __('lang_v1.profile'), 'group' => 'profile'],
    ];
    // sections the clinic switched off are not offered to patients either
    if ($p) {
        $off = array_filter(['portal.imaging' => 'imaging', 'portal.admissions' => 'wards', 'portal.maternity' => 'maternity'], fn ($sec) => ! \App\Utils\ClinicSettings::sectionOn($p->business_id, $sec));
        $nav = array_values(array_filter($nav, fn ($i) => ! isset($off[$i['route']])));
    }
    // clinic appointments: a tab when the clinic uses them
    if ($p && \App\Utils\ClinicAppointments::enabled($p->business_id)) {
        array_splice($nav, 1, 0, [['route' => 'portal.appointments', 'icon' => 'fa-calendar-check', 'label' => __('clinic.appt_portal_title'), 'group' => 'appointments']]);
        array_splice($tabs, 1, 0, [['route' => 'portal.appointments', 'icon' => 'fa-calendar-check', 'label' => __('clinic.appt_portal_tab'), 'group' => 'appointments']]);
    }
    $businessLogo = session('business.logo');
@endphp

{{-- Currency formatting inputs (read by functions.js) --}}
<input type="hidden" id="__symbol" value="{{ session('currency')['symbol'] ?? '' }}">
<input type="hidden" id="__thousand" value="{{ session('currency')['thousand_separator'] ?? ',' }}">
<input type="hidden" id="__decimal" value="{{ session('currency')['decimal_separator'] ?? '.' }}">
<input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement', 'before') }}">
<input type="hidden" id="__precision" value="{{ session('business.currency_precision', 2) }}">
<input type="hidden" id="__quantity_precision" value="{{ session('business.quantity_precision', 2) }}">

<div class="tw-min-h-screen tw-flex tw-flex-col md:tw-flex-row portal-has-bottombar">

    {{-- Pull-to-refresh indicator --}}
    <div id="portal-ptr" class="tw-fixed tw-top-0 tw-inset-x-0 tw-z-40 tw-flex tw-justify-center tw-pt-2 tw-pointer-events-none" style="opacity:0;transition:transform .2s,opacity .2s;">
        <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-9 tw-h-9 tw-rounded-full tw-bg-white tw-shadow tw-text-indigo-600"><i class="fas fa-sync-alt"></i></span>
    </div>

    {{-- Desktop sidebar --}}
    <aside class="tw-hidden md:tw-flex md:tw-flex-col md:tw-w-72 tw-bg-white tw-ring-1 tw-ring-gray-200 tw-shrink-0">
        <div class="tw-p-5 tw-border-b tw-border-gray-100 tw-flex tw-items-center tw-gap-3">
            @if($businessLogo)
                <img src="{{ asset('uploads/business_logos/'.$businessLogo) }}" alt="" class="tw-w-10 tw-h-10 tw-rounded-lg tw-object-contain">
            @else
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-lg tw-bg-indigo-600 tw-text-white"><i class="fas fa-hospital-user"></i></span>
            @endif
            <div class="tw-min-w-0">
                <p class="tw-text-base tw-font-bold tw-text-gray-900 tw-truncate">{{ session('business.name', config('app.name')) }}</p>
                <p class="tw-text-xs tw-text-gray-400">@lang('lang_v1.patient_portal')</p>
            </div>
        </div>

        {{-- Patient mini-card --}}
        @if($p)
            <div class="tw-mx-3 tw-mt-3 tw-rounded-xl tw-bg-indigo-50 tw-p-3 tw-flex tw-items-center tw-gap-3">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-full tw-bg-indigo-600 tw-text-white tw-font-semibold">{{ strtoupper(mb_substr($p->name, 0, 1)) }}</span>
                <div class="tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-gray-800 tw-truncate">{{ $p->name }}</p>
                    <p class="tw-text-xs tw-text-indigo-500">{{ optional($pd)->file_number }}</p>
                </div>
            </div>
        @endif

        <nav class="tw-flex-1 tw-p-3 tw-space-y-1 tw-overflow-y-auto">
            @foreach($nav as $item)
                <a href="{{ route($item['route']) }}"
                   class="portal-press tw-flex tw-items-center tw-gap-3 tw-px-3 tw-py-2.5 tw-rounded-xl tw-text-sm tw-transition-colors {{ $currentGroup === $item['group'] && request()->routeIs($item['route']) ? 'tw-bg-indigo-600 tw-text-white tw-font-semibold tw-shadow-sm' : 'tw-text-gray-600 hover:tw-bg-gray-50' }}">
                    <i class="fas {{ $item['icon'] }} tw-w-4 tw-text-center"></i> {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="tw-p-3 tw-border-t tw-border-gray-100 tw-space-y-2">
            @include('patient_portal.partials.lang_switch', ['variant' => 'sidebar'])
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit" aria-label="@lang('lang_v1.logout')" class="portal-press tw-w-full tw-flex tw-items-center tw-gap-2 tw-px-3 tw-py-2.5 tw-rounded-xl tw-text-sm tw-text-red-600 hover:tw-bg-red-50">
                    <i class="fas fa-sign-out-alt tw-w-4 tw-text-center"></i> @lang('lang_v1.logout')
                </button>
            </form>
        </div>
    </aside>

    <div class="tw-flex-1 tw-flex tw-flex-col tw-min-w-0">
        {{-- Top app bar --}}
        <header class="portal-topbar tw-sticky tw-top-0 tw-z-20 tw-bg-white/90 tw-backdrop-blur tw-ring-1 tw-ring-gray-200 tw-px-4 tw-py-3 tw-flex tw-items-center tw-justify-between">
            <div class="tw-flex tw-items-center tw-gap-2 tw-min-w-0">
                @hasSection('back')
                    @yield('back')
                @else
                    <span class="md:tw-hidden tw-inline-flex tw-items-center tw-gap-2">
                        @if($businessLogo)
                            <img src="{{ asset('uploads/business_logos/'.$businessLogo) }}" alt="" class="tw-w-7 tw-h-7 tw-rounded-md tw-object-contain">
                        @endif
                        <span class="tw-text-base tw-font-bold tw-text-indigo-700 tw-truncate">@yield('heading', session('business.name', config('app.name')))</span>
                    </span>
                @endif
                <h1 class="tw-hidden md:tw-block tw-text-lg tw-font-semibold tw-text-gray-800 tw-truncate">@yield('heading', '')</h1>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                <span class="md:tw-hidden">@include('patient_portal.partials.lang_switch', ['variant' => 'compact'])</span>
                @php
                    $bid = optional($p)->business_id;
                    $alerts = 0;
                    if ($p) {
                        $alerts += \App\LabRequest::forBusiness($bid)->where('contact_id', $p->id)->whereIn('status', ['resulted', 'validated'])->whereDate('updated_at', '>=', now()->subDays(7))->count();
                        $alerts += \App\ImagingRequest::forBusiness($bid)->where('contact_id', $p->id)->where('status', 'done')->whereDate('updated_at', '>=', now()->subDays(7))->count();
                    }
                @endphp
                <a href="{{ route('portal.lab-results') }}" aria-label="@lang('lang_v1.notifications')" class="tw-relative tw-w-9 tw-h-9 tw-inline-flex tw-items-center tw-justify-center tw-rounded-full tw-text-gray-500 hover:tw-bg-gray-100">
                    <i class="fas fa-bell"></i>
                    @if($alerts > 0)<span class="tw-absolute tw-top-1 tw-right-1 tw-min-w-[16px] tw-h-4 tw-px-1 tw-rounded-full tw-bg-red-500 tw-text-white tw-text-[10px] tw-font-bold tw-flex tw-items-center tw-justify-center">{{ $alerts > 9 ? '9+' : $alerts }}</span>@endif
                </a>
                <span class="tw-hidden sm:tw-inline tw-text-sm tw-text-gray-600 tw-truncate tw-max-w-[140px]">{{ optional($p)->name }}</span>
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-9 tw-h-9 tw-rounded-full tw-bg-indigo-100 tw-text-indigo-600 tw-font-semibold tw-shrink-0">
                    {{ strtoupper(mb_substr(optional($p)->name ?? '?', 0, 1)) }}
                </span>
                <form method="POST" action="{{ route('portal.logout') }}" class="md:tw-hidden">
                    @csrf
                    <button type="submit" aria-label="@lang('lang_v1.logout')" class="tw-text-red-500 tw-w-9 tw-h-9 tw-inline-flex tw-items-center tw-justify-center"><i class="fas fa-sign-out-alt"></i></button>
                </form>
            </div>
        </header>

        <main class="portal-page tw-flex-1 tw-p-4 sm:tw-p-6 tw-max-w-5xl tw-w-full tw-mx-auto">
            @yield('content')
        </main>
    </div>
</div>

{{-- Install (A2HS) banner --}}
<div id="portal-install" class="portal-install tw-hidden md:tw-hidden">
    <i class="fas fa-mobile-alt tw-text-lg"></i>
    <span class="tw-flex-1 tw-text-sm tw-font-medium">@lang('lang_v1.portal_install_app')</span>
    <button id="portal-install-btn" class="portal-press tw-rounded-lg tw-px-3 tw-py-1.5 tw-text-xs tw-font-bold" style="background:#fff;color:#4f46e5;">@lang('lang_v1.install')</button>
    <button id="portal-install-x" aria-label="close" style="color:rgba(255,255,255,.8);padding:0 4px;">&times;</button>
</div>

{{-- Mobile bottom tab bar --}}
<nav class="portal-bottombar md:tw-hidden tw-fixed tw-bottom-0 tw-inset-x-0 tw-z-30 tw-bg-white/95 tw-backdrop-blur tw-ring-1 tw-ring-gray-200 tw-flex tw-justify-around">
    @foreach($tabs as $tab)
        @php $active = $currentGroup === $tab['group']; @endphp
        <a href="{{ route($tab['route']) }}" aria-label="{{ $tab['label'] }}"
           class="portal-tab tw-relative tw-flex tw-flex-col tw-items-center tw-justify-center tw-flex-1 tw-py-2 tw-gap-0.5 {{ $active ? 'tw-text-indigo-600' : 'tw-text-gray-400' }}">
            @if($active)<span class="tw-absolute tw-top-0 tw-w-8 tw-h-1 tw-rounded-full tw-bg-indigo-600"></span>@endif
            <i class="fas {{ $tab['icon'] }} tw-text-base"></i>
            <span class="tw-text-[10px] tw-font-medium">{{ $tab['label'] }}</span>
        </a>
    @endforeach
</nav>

@include('patient_portal.partials.toast')

<script src="{{ asset('js/vendor.js?v=' . $vendor_v) }}"></script>
<script src="{{ asset('js/functions.js?v=' . $asset_v) }}"></script>
<script>
    // Initialise currency globals from the hidden inputs (lighter than loading common.js).
    var __currency_symbol = $('#__symbol').val() || '';
    var __currency_thousand_separator = $('#__thousand').val() || ',';
    var __currency_decimal_separator = $('#__decimal').val() || '.';
    var __currency_symbol_placement = $('#__symbol_placement').val() || 'before';
    var __currency_precision = parseInt($('#__precision').val() || 2, 10);
    var __quantity_precision = parseInt($('#__quantity_precision').val() || 2, 10);
    $(function () {
        if (typeof __currency_convert_recursively === 'function') {
            __currency_convert_recursively($('body'));
        }
    });
</script>
@yield('javascript')
{{-- Infinite scroll, install prompt, pull-to-refresh --}}
<script>
(function () {
    // ---- Infinite scroll ----
    var inf = document.querySelector('.portal-infinite');
    var list = document.querySelector('[data-list]');
    var loading = false;
    function loadMore() {
        if (loading || !inf || !list) return;
        var url = inf.getAttribute('data-next');
        if (!url) return;
        loading = true;
        var skel = inf.querySelector('.portal-skel-row');
        if (skel) skel.classList.remove('tw-hidden');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var newList = doc.querySelector('[data-list]');
                if (newList) { list.insertAdjacentHTML('beforeend', newList.innerHTML); }
                var newInf = doc.querySelector('.portal-infinite');
                if (newInf) { inf.setAttribute('data-next', newInf.getAttribute('data-next')); }
                else { inf.parentNode.removeChild(inf); inf = null; }
                if (typeof __currency_convert_recursively === 'function') __currency_convert_recursively($(list));
                loading = false; if (skel) skel.classList.add('tw-hidden');
            })
            .catch(function () { loading = false; if (skel) skel.classList.add('tw-hidden'); });
    }
    if (inf) {
        var btn = inf.querySelector('.portal-loadmore');
        if (btn) btn.addEventListener('click', loadMore);
        if ('IntersectionObserver' in window) {
            var io = new IntersectionObserver(function (e) { if (e[0].isIntersecting) loadMore(); }, { rootMargin: '300px' });
            io.observe(inf);
        }
    }

    // ---- Install (A2HS) banner ----
    var deferred;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault(); deferred = e;
        if (sessionStorage.getItem('portal_a2hs') === 'no') return;
        var bar = document.getElementById('portal-install');
        if (bar) bar.classList.remove('tw-hidden');
    });
    var ib = document.getElementById('portal-install-btn');
    if (ib) ib.addEventListener('click', function () { if (deferred) { deferred.prompt(); deferred = null; document.getElementById('portal-install').classList.add('tw-hidden'); } });
    var ix = document.getElementById('portal-install-x');
    if (ix) ix.addEventListener('click', function () { sessionStorage.setItem('portal_a2hs', 'no'); document.getElementById('portal-install').classList.add('tw-hidden'); });

    // ---- Pull to refresh ----
    var sy = 0, pull = 0, pulling = false, ind = document.getElementById('portal-ptr');
    window.addEventListener('touchstart', function (e) { if (window.scrollY === 0) sy = e.touches[0].clientY; }, { passive: true });
    window.addEventListener('touchmove', function (e) {
        if (sy && window.scrollY === 0) { pull = e.touches[0].clientY - sy; if (pull > 0 && ind) { pulling = true; ind.style.transform = 'translateY(' + Math.min(pull, 80) + 'px)'; ind.style.opacity = Math.min(pull / 80, 1); } }
    }, { passive: true });
    window.addEventListener('touchend', function () { if (pulling && pull > 70) { location.reload(); } else if (ind) { ind.style.transform = ''; ind.style.opacity = 0; } sy = 0; pull = 0; pulling = false; });
})();
</script>
{{-- Service worker registration --}}
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '{{ url('/portal') }}/' }).catch(function () {});
        });
    }
</script>
</body>
</html>
