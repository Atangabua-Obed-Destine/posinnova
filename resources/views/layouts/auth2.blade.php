<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!-- Tell the browser to be responsive to screen width -->
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') - {{ config('app.name', 'POS') }}</title>

    @include('layouts.partials.css')

    @include('layouts.partials.extracss_auth')

    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <script src='https://www.google.com/recaptcha/api.js'></script>

</head>

<body class="auth-split-body pace-done" data-new-gr-c-s-check-loaded="14.1172.0" data-gr-ext-installed="" cz-shortcut-listen="true">
    @inject('request', 'Illuminate\Http\Request')
    @if (session('status') && session('status.success'))
        <input type="hidden" id="status_span" data-status="{{ session('status.success') }}"
            data-msg="{{ session('status.msg') }}">
    @endif

    <div class="auth-split tw-flex tw-flex-col-reverse lg:tw-flex-row tw-min-h-screen">

        {{-- ============================================================
             LEFT PANEL — Brand + Feature Showcase
             (renders BELOW the form on mobile, side-by-side on lg+)
             ============================================================ --}}
        <aside class="auth-split__left tw-relative tw-overflow-hidden lg:tw-w-1/2 tw-text-white tw-px-6 sm:tw-px-10 lg:tw-px-16 tw-py-10 lg:tw-py-0">
            {{-- decorative blobs --}}
            <span class="auth-blob auth-blob--1" aria-hidden="true"></span>
            <span class="auth-blob auth-blob--2" aria-hidden="true"></span>
            <span class="auth-grid" aria-hidden="true"></span>

            <div class="auth-split__left-inner">
                <div class="tw-relative tw-z-10 tw-w-full">
                {{-- Logo (centered, borderless) --}}
                <a href="{{ url('/') }}" class="auth-brand-logo">
                    <img src="{{ asset('img/logo-small.png') }}" alt="{{ config('app.name', 'POSinnova') }}" />
                    <span class="auth-brand-name">{{ config('app.name', 'POSinnova') }}</span>
                </a>

                {{-- Heading --}}
                <h1 class="auth-hero-title tw-text-3xl sm:tw-text-4xl lg:tw-text-5xl tw-font-extrabold tw-leading-tight tw-mb-3">
                    @if($request->segment(1) == 'business' && $request->segment(2) == 'register')
                        @lang('business.register_and_get_started_in_minutes')
                    @else
                        @lang('lang_v1.welcome_back')
                    @endif
                </h1>
                <p class="tw-text-base sm:tw-text-lg tw-text-white/80 tw-mb-8">
                    @if($request->segment(1) == 'business' && $request->segment(2) == 'register')
                        @lang('business.set_up_your_account_today')
                    @else
                        @lang('lang_v1.business_management_platform')
                    @endif
                </p>

                {{-- Feature cards --}}
                <div class="tw-flex tw-flex-col tw-gap-4">
                    @php
                        $features = [
                            ['icon' => 'fa-cash-register', 'title' => __('sale.pos_sale'), 'desc' => __('lang_v1.feature_pos_desc')],
                            ['icon' => 'fa-boxes',         'title' => __('lang_v1.inventory_management'), 'desc' => __('lang_v1.feature_inventory_desc')],
                            ['icon' => 'fa-chart-line',    'title' => __('lang_v1.feature_reports_title'), 'desc' => __('lang_v1.feature_reports_desc')],
                        ];
                    @endphp
                    @foreach($features as $f)
                        <div class="auth-feature-card tw-flex tw-items-center tw-gap-4 tw-p-4 sm:tw-p-5 tw-rounded-2xl tw-bg-white/10 tw-ring-1 tw-ring-white/15 tw-backdrop-blur hover:tw-bg-white/15 tw-transition-all">
                            <div class="auth-feature-icon tw-flex-shrink-0 tw-w-12 tw-h-12 sm:tw-w-14 sm:tw-h-14 tw-rounded-xl tw-flex tw-items-center tw-justify-center tw-text-white tw-text-xl sm:tw-text-2xl tw-shadow-md">
                                <i class="fas {{ $f['icon'] }}"></i>
                            </div>
                            <div class="tw-min-w-0">
                                <div class="tw-text-base sm:tw-text-lg tw-font-semibold">{{ $f['title'] }}</div>
                                <div class="tw-text-xs sm:tw-text-sm tw-text-white/75 tw-leading-snug">{{ $f['desc'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="tw-mt-10 tw-text-xs sm:tw-text-sm tw-text-white/60">
                    &copy; {{ date('Y') }} {{ config('app.name', 'POSinnova') }}. @lang('lang_v1.all_rights_reserved')
                </div>
                </div>{{-- /.tw-relative wrapper --}}
            </div>{{-- /.auth-split__left-inner --}}
        </aside>

        {{-- ============================================================
             RIGHT PANEL — Top utility bar + form
             ============================================================ --}}
        <main class="auth-split__right tw-relative lg:tw-w-1/2 tw-bg-[#f6f8fb] tw-px-4 sm:tw-px-8 lg:tw-px-12 tw-py-8 lg:tw-py-10 tw-flex tw-flex-col">

            {{-- Top utility bar --}}
            <div class="tw-flex tw-items-center tw-justify-end tw-gap-3 sm:tw-gap-4 tw-mb-6 lg:tw-mb-10 tw-flex-wrap">
                @if(config('constants.SHOW_REPAIR_STATUS_LOGIN_SCREEN') && Route::has('repair-status'))
                    <a class="tw-text-sm tw-font-medium tw-text-gray-600 hover:tw-text-blue-600"
                        href="{{ action([\Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index']) }}">
                        @lang('repair::lang.repair_status')
                    </a>
                @endif
                @if(Route::has('member_scanner'))
                    <a class="tw-text-sm tw-font-medium tw-text-gray-600 hover:tw-text-blue-600"
                        href="{{ action([\Modules\Gym\Http\Controllers\MemberController::class, 'member_scanner']) }}">
                        @lang('gym::lang.gym_member_profile')
                    </a>
                @endif

                @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))
                    @if (config('constants.allow_registration'))
                        <a href="{{ route('business.getRegister')}}@if(!empty(request()->lang)){{'?lang='.request()->lang}}@endif"
                           class="tw-inline-flex tw-items-center tw-justify-center tw-h-10 tw-px-5 tw-rounded-full tw-text-sm tw-font-semibold tw-text-blue-700 tw-bg-blue-50 hover:tw-bg-blue-100 tw-ring-1 tw-ring-blue-200 tw-transition">
                            {{ __('business.register') }}
                        </a>
                        @if (Route::has('pricing') && config('app.env') != 'demo' && $request->segment(1) != 'pricing')
                            <a class="tw-text-sm tw-font-medium tw-text-gray-600 hover:tw-text-blue-600"
                                href="{{ action([\Modules\Superadmin\Http\Controllers\PricingController::class, 'index']) }}">@lang('superadmin::lang.pricing')</a>
                        @endif
                    @endif
                @endif
                @if ($request->segment(1) != 'login')
                    <a class="tw-inline-flex tw-items-center tw-justify-center tw-h-10 tw-px-5 tw-rounded-full tw-text-sm tw-font-semibold tw-text-white tw-bg-gradient-to-r tw-from-indigo-500 tw-to-blue-600 hover:tw-from-indigo-600 hover:tw-to-blue-700 tw-shadow-sm tw-transition"
                       href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'login'])}}@if(!empty(request()->lang)){{'?lang='.request()->lang}}@endif">{{ __('business.sign_in') }}</a>
                @endif

                <span class="auth-lang-btn">@include('layouts.partials.language_btn')</span>
            </div>

            <div class="tw-flex-1 tw-flex tw-items-center tw-justify-center">
                <div class="tw-w-full auth-form-wrap @yield('right-max-width', 'auth-form--md')">
                    @yield('content')
                </div>
            </div>
        </main>
    </div>


    @include('layouts.partials.javascripts')

    <!-- Scripts -->
    <script src="{{ asset('js/login.js?v=' . $asset_v) }}"></script>

    @yield('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            $('.select2_register').select2();

            // $('input').iCheck({
            //     checkboxClass: 'icheckbox_square-blue',
            //     radioClass: 'iradio_square-blue',
            //     increaseArea: '20%' // optional
            // });
        });
    </script>
    <style>
        .wizard>.content {
            background-color: white !important;
        }
    </style>
@include('layouts.partials.support_ticker')
</body>

</html>
