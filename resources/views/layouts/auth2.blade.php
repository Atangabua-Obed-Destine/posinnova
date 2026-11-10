<!DOCTYPE html>
<html data-theme="light" lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('app.name', 'POS') }}</title>

    @include('layouts.partials.css')
    @include('layouts.partials.extracss_auth')
    {{-- PosInnova: the sign-in look (navy + I-NNOVA gold), on top of the layout rules above --}}
    @include('layouts.partials.auth_innova')

    @if (config('constants.enable_recaptcha'))
        <script src='https://www.google.com/recaptcha/api.js'></script>
    @endif
</head>

<body class="auth-split-body">
    @inject('request', 'Illuminate\Http\Request')
    @if (session('status') && session('status.success'))
        <input type="hidden" id="status_span" data-status="{{ session('status.success') }}" data-msg="{{ session('status.msg') }}">
    @endif
    @php
        $ia_register = $request->segment(1) == 'business' && $request->segment(2) == 'register';
        $ia_name = config('app.name', 'I-NNOVA POS');
    @endphp

    <div class="auth-split">

        {{-- ═════════ The product: what it is, before anyone is asked for anything ═════════ --}}
        <aside class="auth-split__left">
            <div class="ia-rule" aria-hidden="true"></div>
            <div class="ia-glow ia-glow--a" aria-hidden="true"></div>
            <div class="ia-glow ia-glow--b" aria-hidden="true"></div>

            <div class="ia-brand-panel">
                <a href="{{ url('/') }}" class="ia-brand ia-rise">
                    <span class="ia-brand-tile"><img src="{{ asset('img/logo-small.png') }}" alt=""></span>
                    <span>
                        <span class="ia-brand-name">{{ $ia_name }}</span>
                        <span class="ia-brand-sub">@lang('lang_v1.auth_products') · <span class="ia-nb">@lang('lang_v1.auth_powered_by') <b>I-NNOVA</b></span></span>
                    </span>
                </a>

                <div class="ia-hero ia-rise ia-rise-2">
                    @if ($ia_register)
                        <h2>@lang('lang_v1.auth_reg_line_1')<br><span>@lang('lang_v1.auth_reg_line_2')</span></h2>
                        <p>@lang('lang_v1.auth_reg_lead')</p>
                    @else
                        <h2>@lang('lang_v1.auth_line_1')<br><span>@lang('lang_v1.auth_line_2')</span></h2>
                        <p>@lang('lang_v1.auth_lead')</p>
                    @endif
                </div>

                {{-- Sales, medicines, patients and stock flowing into one place --}}
                <div class="ia-flow ia-rise ia-rise-3" aria-hidden="true">
                    <svg viewBox="0 0 460 200" style="width:100%;height:auto;">
                        <defs>
                            <linearGradient id="ia-line" x1="0" x2="1">
                                <stop offset="0" stop-color="#ffffff" stop-opacity="0.08"/>
                                <stop offset="1" stop-color="#e8b54a" stop-opacity="0.55"/>
                            </linearGradient>
                            <radialGradient id="ia-hub-glow">
                                <stop offset="0" stop-color="#e8b54a" stop-opacity="0.35"/>
                                <stop offset="1" stop-color="#e8b54a" stop-opacity="0"/>
                            </radialGradient>
                        </defs>
                        @php $ia_src = [[38, 26], [38, 76], [38, 126], [38, 176]]; @endphp
                        @foreach ($ia_src as $i => [$x, $y])
                            <path id="ia-flow-{{ $i }}" d="M{{ $x + 24 }} {{ $y }} C 190 {{ $y }}, 230 100, 352 100" fill="none" stroke="url(#ia-line)" stroke-width="1.4" stroke-dasharray="3 5"/>
                        @endforeach
                        @foreach ($ia_src as $i => [$x, $y])
                            <g transform="translate({{ $x }} {{ $y }})" fill="none" stroke="#ffffff" stroke-opacity="0.85" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="-20" y="-16" width="40" height="32" rx="8" fill="#ffffff" fill-opacity="0.07" stroke-opacity="0.22" stroke-width="1"/>
                                @if ($i == 0) {{-- a sale --}}
                                    <path d="M-10 -7h3l2.5 9h9l2-6.5H-6"/><circle cx="-3" cy="6" r="1.4"/><circle cx="4" cy="6" r="1.4"/>
                                @elseif ($i == 1) {{-- a medicine --}}
                                    <g transform="rotate(-35)"><rect x="-9" y="-4" width="18" height="8" rx="4"/><path d="M0 -4v8"/></g>
                                @elseif ($i == 2) {{-- a patient --}}
                                    <path d="M-7 -8v5a5 5 0 0 0 10 0v-5"/><path d="M-2 2v2a5 5 0 0 0 10 0v-2"/><circle cx="8" cy="0" r="1.6"/>
                                @else {{-- stock --}}
                                    <path d="M-8 -4l8 -4l8 4v8l-8 4l-8 -4z"/><path d="M-8 -4l8 4l8 -4M0 0v8"/>
                                @endif
                            </g>
                            <circle class="ia-flow-dot" r="3.2" fill="#e8b54a" opacity="0">
                                <animateMotion dur="3.6s" begin="{{ $i * 0.8 }}s" repeatCount="indefinite"><mpath href="#ia-flow-{{ $i }}"/></animateMotion>
                                <animate attributeName="opacity" values="0;1;1;0" keyTimes="0;0.1;0.85;1" dur="3.6s" begin="{{ $i * 0.8 }}s" repeatCount="indefinite"/>
                            </circle>
                        @endforeach
                        <circle cx="390" cy="100" r="62" fill="url(#ia-hub-glow)"/>
                        <circle cx="390" cy="100" r="38" fill="#0b1a2e" stroke="#e8b54a" stroke-opacity="0.7" stroke-width="1.5"/>
                        <g fill="#e8b54a">
                            <rect x="374" y="104" width="7" height="12" rx="2" fill-opacity="0.55"/>
                            <rect x="386" y="94" width="7" height="22" rx="2" fill-opacity="0.8"/>
                            <rect x="398" y="84" width="7" height="32" rx="2"/>
                        </g>
                    </svg>
                </div>

                <ul class="ia-points ia-rise ia-rise-3">
                    @foreach ([
                        ['M3 7h2l2.5 10h10l2-7H7 M10 20.5h.01 M17 20.5h.01', __('lang_v1.auth_p1_t'), __('lang_v1.auth_p1_d')],
                        ['M4 8l8-4 8 4v8l-8 4-8-4z M4 8l8 4 8-4 M12 12v8', __('lang_v1.auth_p2_t'), __('lang_v1.auth_p2_d')],
                        ['M6 4v5a5 5 0 0 0 10 0V4 M11 14v2a4 4 0 0 0 8 0v-3 M19 11.5h.01', __('lang_v1.auth_p3_t'), __('lang_v1.auth_p3_d')],
                    ] as [$ia_icon, $ia_t, $ia_d])
                        <li>
                            <span class="ia-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $ia_icon }}"/></svg></span>
                            <span><b>{{ $ia_t }}</b><span class="d">{{ $ia_d }}</span></span>
                        </li>
                    @endforeach
                </ul>

                <p class="ia-left-foot">@lang('lang_v1.auth_left_foot')</p>
            </div>
        </aside>

        {{-- ═════════ The form ═════════ --}}
        <main class="auth-split__right">
            <div class="ia-topbar">
                @if (config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
                    <a class="ia-chip" href="{{ action([\Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index']) }}">@lang('repair::lang.repair_status')</a>
                @endif
                @if (! $ia_register && config('constants.allow_registration'))
                    @if (Route::has('pricing') && config('app.env') != 'demo' && $request->segment(1) != 'pricing')
                        <a class="ia-chip" href="{{ action([\Modules\Superadmin\Http\Controllers\PricingController::class, 'index']) }}">@lang('superadmin::lang.pricing')</a>
                    @endif
                    @if ($request->segment(1) == 'login')
                        <a class="ia-chip" href="{{ route('business.getRegister') }}@if (! empty(request()->lang)){{ '?lang=' . request()->lang }}@endif">{{ __('business.register') }}</a>
                    @endif
                @endif
                @if ($request->segment(1) != 'login')
                    <a class="ia-chip ia-chip--dark" href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login']) }}@if (! empty(request()->lang)){{ '?lang=' . request()->lang }}@endif">{{ __('business.sign_in') }}</a>
                @endif
                <span class="ia-lang">@include('layouts.partials.language_btn')</span>
            </div>

            <div class="ia-main">
                <div class="auth-form-wrap @yield('right-max-width', 'auth-form--md')">
                    @yield('content')
                    <div class="ia-foot">
                        @if (! $ia_register)<p><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.5a4 4 0 018 0v3"/></svg>@lang('lang_v1.auth_note')</p>@endif
                        <p>© {{ date('Y') }} {{ $ia_name }} · @lang('lang_v1.auth_powered_by_cap') <b>I-NNOVA</b></p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    @include('layouts.partials.javascripts')
    <script src="{{ asset('js/login.js?v=' . $asset_v) }}"></script>
    @yield('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('.select2_register').select2();
        });
    </script>
    <style>
        .wizard>.content { background-color: white !important; }
    </style>
    @include('layouts.partials.support_ticker')
</body>

</html>
