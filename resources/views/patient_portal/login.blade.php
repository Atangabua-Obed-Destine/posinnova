<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, viewport-fit=cover" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@lang('lang_v1.patient_portal') · {{ session('business.name', config('app.name')) }}</title>

    {{-- PWA --}}
    <link rel="manifest" href="{{ route('portal.manifest') }}">
    <meta name="theme-color" content="#4f46e5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="{{ asset('img/pwa-icon-192.png') }}">

    <link href="{{ asset('css/tailwind/app.css?v='.$asset_v) }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/vendor.css?v='.$asset_v) }}">
    <link rel="stylesheet" href="{{ asset('css/app.css?v='.$asset_v) }}">
    <style>
        body { -webkit-tap-highlight-color: transparent; }
        .login-press { transition: transform .12s ease, opacity .15s ease; }
        .login-press:active { transform: scale(.98); }
        .login-card { animation: loginIn .4s ease both; }
        @keyframes loginIn { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: none; } }
    </style>
</head>
<body class="tw-min-h-screen tw-bg-[#f6f8fb]" style="min-height:100dvh;">
<div class="tw-flex tw-flex-col-reverse lg:tw-flex-row tw-min-h-screen" style="min-height:100dvh;">

    {{-- LEFT: brand + features --}}
    <aside class="tw-relative tw-overflow-hidden lg:tw-w-1/2 tw-text-white tw-px-6 sm:tw-px-10 lg:tw-px-16 tw-py-10 lg:tw-py-0 tw-flex tw-items-center tw-bg-gradient-to-br tw-from-indigo-600 tw-via-indigo-600 tw-to-blue-500">
        <div class="tw-relative tw-z-10 tw-w-full tw-max-w-lg tw-mx-auto">
            <div class="tw-flex tw-items-center tw-gap-3 tw-mb-8">
                @if(session('business.logo'))
                    <span class="tw-inline-flex tw-w-12 tw-h-12 tw-rounded-2xl tw-bg-white tw-p-1.5 tw-items-center tw-justify-center"><img src="{{ asset('uploads/business_logos/'.session('business.logo')) }}" class="tw-max-w-full tw-max-h-full tw-object-contain"></span>
                @else
                    <span class="tw-inline-flex tw-w-12 tw-h-12 tw-rounded-2xl tw-bg-white/15 tw-items-center tw-justify-center"><i class="fas fa-hospital-user tw-text-xl"></i></span>
                @endif
                <span class="tw-text-lg tw-font-bold">{{ session('business.name', config('app.name')) }}</span>
            </div>
            <h1 class="tw-text-3xl sm:tw-text-4xl lg:tw-text-5xl tw-font-extrabold tw-leading-tight tw-mb-3" style="color:#fff;">@lang('lang_v1.patient_portal')</h1>
            <p class="tw-text-base sm:tw-text-lg tw-mb-8" style="color:rgba(255,255,255,.8);">@lang('lang_v1.portal_sign_in_sub')</p>
            <div class="tw-flex tw-flex-col tw-gap-4">
                @php $feat = [['fa-notes-medical', __('lang_v1.records'), __('lang_v1.my_visits').', '.__('lang_v1.my_lab_results').', '.__('lang_v1.my_imaging')],['fa-file-invoice-dollar', __('lang_v1.my_bills'), __('lang_v1.outstanding_balance')],['fa-shield-heart', __('lang_v1.insurance'), __('lang_v1.insurance_covered')]]; @endphp
                @foreach($feat as $f)
                    <div class="tw-flex tw-items-center tw-gap-4 tw-p-4 sm:tw-p-5 tw-rounded-2xl tw-ring-1" style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);">
                        <div class="tw-flex-shrink-0 tw-w-14 tw-h-14 tw-rounded-xl tw-flex tw-items-center tw-justify-center tw-text-2xl tw-shadow-md" style="background:linear-gradient(135deg,#fb923c,#ea580c);color:#fff;"><i class="fas {{ $f[0] }}"></i></div>
                        <div class="tw-min-w-0"><div class="tw-text-base sm:tw-text-lg tw-font-semibold" style="color:#fff;">{{ $f[1] }}</div><div class="tw-text-xs sm:tw-text-sm tw-leading-snug" style="color:rgba(255,255,255,.75);">{{ $f[2] }}</div></div>
                    </div>
                @endforeach
            </div>
            <div class="tw-mt-10 tw-text-xs sm:tw-text-sm" style="color:rgba(255,255,255,.6);">© {{ date('Y') }} {{ session('business.name', config('app.name')) }}</div>
        </div>
    </aside>

    {{-- RIGHT: form --}}
    <main class="lg:tw-w-1/2 tw-px-4 sm:tw-px-8 lg:tw-px-12 tw-py-8 lg:tw-py-10 tw-flex tw-flex-col">
        <div class="tw-flex tw-items-center tw-justify-end tw-mb-6 lg:tw-mb-10">
            @php $locale = app()->getLocale(); @endphp
            <div class="tw-inline-flex tw-rounded-full tw-bg-gray-100 tw-p-0.5 tw-text-xs">
                @foreach(['en' => 'EN', 'fr' => 'FR'] as $code => $label)
                    <a href="{{ route('portal.lang', $code) }}" class="tw-px-3 tw-py-1 tw-rounded-full tw-font-semibold {{ $locale === $code ? 'tw-bg-white tw-text-indigo-600 tw-shadow-sm' : 'tw-text-gray-500' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <div class="tw-flex-1 tw-flex tw-items-center tw-justify-center">
        <div class="login-card tw-w-full tw-max-w-md">
            <div class="tw-flex tw-flex-col tw-items-center tw-mb-6">
                <div class="tw-w-16 tw-h-16 tw-rounded-2xl tw-flex tw-items-center tw-justify-center tw-shadow-lg tw-mb-4" style="background:linear-gradient(135deg,#4f46e5,#2563eb);color:#fff;"><i class="fas fa-hospital-user tw-text-2xl"></i></div>
                <h1 class="tw-text-2xl sm:tw-text-3xl tw-font-bold tw-text-gray-900">@lang('lang_v1.portal_sign_in')</h1>
                <p class="tw-mt-1 tw-text-sm tw-text-gray-500 tw-text-center">@lang('lang_v1.portal_sign_in_sub')</p>
            </div>

            <div class="tw-bg-white tw-rounded-2xl tw-shadow-xl tw-ring-1 tw-ring-gray-100 tw-p-6 sm:tw-p-8">
                @if(session('login_error'))
                    <div class="tw-mb-4 tw-rounded-xl tw-bg-red-50 tw-ring-1 tw-ring-red-200 tw-px-3 tw-py-2.5 tw-text-sm tw-text-red-700 tw-flex tw-items-center tw-gap-2">
                        <i class="fas fa-circle-exclamation"></i> <span>{{ session('login_error') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('portal.login.submit') }}" class="tw-space-y-5">
                    @csrf
                    <div>
                        <label class="tw-block tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-2">@lang('lang_v1.file_number')</label>
                        <div class="tw-relative">
                            <span class="tw-absolute tw-inset-y-0 tw-left-0 tw-flex tw-items-center tw-pl-3.5 tw-text-gray-400"><i class="fas fa-id-card"></i></span>
                            <input type="text" name="file_number" value="{{ old('file_number') }}" required autofocus inputmode="text" autocomplete="username" placeholder="@lang('lang_v1.file_number')"
                                   class="tw-w-full tw-rounded-xl tw-border tw-border-gray-300 tw-bg-white tw-text-gray-900 tw-pl-10 tw-pr-3 tw-py-3 tw-text-sm focus:tw-ring-2 focus:tw-ring-indigo-500 focus:tw-border-indigo-500 tw-transition">
                        </div>
                    </div>
                    <div>
                        {{-- PosInnova: file number + PIN (set by reception), never the phone number --}}
                        <label for="portal_pin" class="tw-block tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-2">@lang('clinic.your_pin')</label>
                        <div class="tw-relative">
                            <span class="tw-absolute tw-inset-y-0 tw-left-0 tw-flex tw-items-center tw-pl-3.5 tw-text-gray-400"><i class="fas fa-lock"></i></span>
                            <input type="password" id="portal_pin" name="pin" required inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="current-password" placeholder="••••"
                                   class="tw-w-full tw-rounded-xl tw-border tw-border-gray-300 tw-bg-white tw-text-gray-900 tw-pl-10 tw-pr-3 tw-py-3 tw-text-sm tw-tracking-widest focus:tw-ring-2 focus:tw-ring-indigo-500 focus:tw-border-indigo-500 tw-transition">
                        </div>
                    </div>
                    <button type="submit" class="login-press tw-w-full tw-rounded-xl tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-font-semibold tw-py-3 tw-text-sm tw-shadow-lg hover:tw-opacity-95">
                        <i class="fas fa-arrow-right-to-bracket"></i> @lang('lang_v1.portal_sign_in')
                    </button>
                </form>
                <p class="tw-text-xs tw-text-gray-400 tw-text-center tw-mt-5">@lang('lang_v1.portal_help_note')</p>
            </div>
        </div>
        </div>
    </main>
</div>
</body>
</html>

