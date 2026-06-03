@extends('layouts.auth2')
@section('title', __('lang_v1.login'))
@section('right-max-width', 'auth-form--sm')
@inject('request', 'Illuminate\Http\Request')
@section('content')
    @php
        $username = old('username');
        $password = null;
        if (config('app.env') == 'demo') {
            $username = 'admin';
            $password = '123456';

            $demo_types = [
                'all_in_one' => 'admin',
                'super_market' => 'admin',
                'pharmacy' => 'admin-pharmacy',
                'electronics' => 'admin-electronics',
                'services' => 'admin-services',
                'restaurant' => 'admin-restaurant',
                'superadmin' => 'superadmin',
                'woocommerce' => 'woocommerce_user',
                'essentials' => 'admin-essentials',
                'manufacturing' => 'manufacturer-demo',
            ];

            if (!empty($_GET['demo_type']) && array_key_exists($_GET['demo_type'], $demo_types)) {
                $username = $demo_types[$_GET['demo_type']];
            }
        }
    @endphp
    <div class="tw-w-full">
        @if (config('app.env') == 'demo')
            <div class="tw-mb-6 tw-p-4 tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-shadow-sm">
                <h4 class="tw-text-sm tw-font-semibold tw-text-gray-700 tw-text-center tw-mb-2">Demo Shops <small class="tw-block tw-text-xs tw-font-normal tw-text-gray-500"><i>Demos are for example purpose only — click a button to login.</i></small></h4>
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-justify-center">
                    <a href="?demo_type=all_in_one" class="btn btn-app bg-olive demo-login" data-toggle="tooltip" title="Showcases all features." data-admin="{{ $demo_types['all_in_one'] }}"><i class="fas fa-star"></i> All In One</a>
                    <a href="?demo_type=pharmacy" class="btn btn-app bg-maroon demo-login" data-toggle="tooltip" title="Pharmacy" data-admin="{{ $demo_types['pharmacy'] }}"><i class="fas fa-medkit"></i> Pharmacy</a>
                    <a href="?demo_type=services" class="btn btn-app bg-orange demo-login" data-admin="{{ $demo_types['services'] }}"><i class="fas fa-wrench"></i> Services</a>
                    <a href="?demo_type=electronics" class="btn btn-app bg-purple demo-login" data-admin="{{ $demo_types['electronics'] }}"><i class="fas fa-laptop"></i> Electronics</a>
                    <a href="?demo_type=super_market" class="btn btn-app bg-navy demo-login" data-admin="{{ $demo_types['super_market'] }}"><i class="fas fa-shopping-cart"></i> Super Market</a>
                    <a href="?demo_type=restaurant" class="btn btn-app bg-red demo-login" data-admin="{{ $demo_types['restaurant'] }}"><i class="fas fa-utensils"></i> Restaurant</a>
                </div>
            </div>
        @endif

        <div class="tw-flex tw-flex-col tw-items-center tw-mb-6">
            <div class="tw-w-16 tw-h-16 tw-rounded-2xl tw-bg-gradient-to-br tw-from-blue-500 tw-to-indigo-600 tw-flex tw-items-center tw-justify-center tw-shadow-lg tw-mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="tw-w-8 tw-h-8 tw-text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M22 11l-3 3-2-2"/>
                </svg>
            </div>
            <h1 class="tw-text-2xl sm:tw-text-3xl tw-font-bold tw-text-gray-900">@lang('lang_v1.login_into_your_dashboard')</h1>
            <p class="tw-mt-1 tw-text-sm tw-text-gray-500 tw-text-center">@lang('lang_v1.access_your_dashboard_subtitle')</p>
        </div>

        <div class="tw-bg-white tw-rounded-2xl tw-shadow-xl tw-ring-1 tw-ring-gray-100 tw-p-6 sm:tw-p-8">
            <form method="POST" action="{{ route('login') }}" id="login-form" class="tw-space-y-5">
                {{ csrf_field() }}

                <div class="form-group {{ $errors->has('username') ? ' has-error' : '' }} tw-mb-0">
                    <label for="username" class="tw-flex tw-items-center tw-gap-2 tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="tw-w-4 tw-h-4 tw-text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm-4 6c-3.314 0-8 1.343-8 4v1h16v-1c0-2.657-4.686-4-8-4z"/></svg>
                        @lang('lang_v1.username')
                    </label>
                    <input id="username" name="username" type="text" required autofocus
                        value="{{ $username }}"
                        placeholder="@lang('lang_v1.username')"
                        class="auth-input" />
                    @if ($errors->has('username'))
                        <span class="help-block tw-mt-1 tw-block"><strong class="tw-text-red-600 tw-text-xs">{{ $errors->first('username') }}</strong></span>
                    @endif
                </div>

                <div class="form-group {{ $errors->has('password') ? ' has-error' : '' }} tw-mb-0">
                    <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
                        <label for="password" class="tw-flex tw-items-center tw-gap-2 tw-text-sm tw-font-semibold tw-text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="tw-w-4 tw-h-4 tw-text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 11c1.657 0 3-1.343 3-3V6a3 3 0 10-6 0v2c0 1.657 1.343 3 3 3zm-7 9a2 2 0 002 2h10a2 2 0 002-2v-7a2 2 0 00-2-2H7a2 2 0 00-2 2v7z"/></svg>
                            @lang('lang_v1.password')
                        </label>
                        @if (config('app.env') != 'demo')
                            <a href="{{ route('password.request') }}"
                                class="tw-text-xs tw-font-semibold tw-text-blue-600 hover:tw-text-blue-700 hover:tw-underline"
                                tabindex="-1">@lang('lang_v1.forgot_your_password')</a>
                        @endif
                    </div>
                    <div class="auth-pwd-wrap">
                        <input id="password" name="password" type="password" required
                            value="{{ $password }}"
                            placeholder="@lang('lang_v1.password')"
                            class="auth-input auth-input--pwd" />
                        <button type="button" id="show_hide_icon" class="show_hide_icon auth-pwd-toggle" aria-label="Toggle password">
                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye" width="20" height="20" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                            </svg>
                        </button>
                    </div>
                    @if ($errors->has('password'))
                        <span class="help-block tw-mt-1 tw-block"><strong class="tw-text-red-600 tw-text-xs">{{ $errors->first('password') }}</strong></span>
                    @endif
                </div>

                <label class="tw-flex tw-items-center tw-gap-2 tw-cursor-pointer tw-select-none">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}
                        class="tw-w-4 tw-h-4 tw-rounded tw-border-gray-300 tw-text-blue-600 focus:tw-ring-blue-500" />
                    <span class="tw-text-sm tw-font-medium tw-text-gray-700">@lang('lang_v1.remember_me')</span>
                </label>

                @if(config('constants.enable_recaptcha'))
                    <div>
                        <div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div>
                        @if ($errors->has('g-recaptcha-response'))
                            <span class="tw-text-red-600 tw-text-xs">{{ $errors->first('g-recaptcha-response') }}</span>
                        @endif
                    </div>
                @endif

                <button type="submit" class="auth-btn-primary">
                    @lang('lang_v1.login')
                </button>
            </form>

            @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))
                @if (config('constants.allow_registration'))
                    <p class="tw-text-center tw-text-sm tw-text-gray-500 tw-mt-6 tw-mb-0">
                        {{ __('business.not_yet_registered') }}
                        <a href="{{ route('business.getRegister') }}@if (!empty(request()->lang)){{ '?lang=' . request()->lang }}@endif"
                            class="tw-font-semibold tw-text-blue-600 hover:tw-text-blue-700 hover:tw-underline">{{ __('business.register_now') }}</a>
                    </p>
                @endif
            @endif
        </div>
    </div>

@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('#show_hide_icon').off('click');
            $('.change_lang').click(function() {
                window.location = "{{ route('login') }}?lang=" + $(this).attr('value');
            });
            $('a.demo-login').click(function(e) {
                e.preventDefault();
                $('#username').val($(this).data('admin'));
                $('#password').val("{{ $password }}");
                $('form#login-form').submit();
            });

            $('#show_hide_icon').on('click', function(e) {
            e.preventDefault();
            const passwordInput = $('#password');

            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                $('#show_hide_icon').html('<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye-off tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.585 10.587a2 2 0 0 0 2.829 2.828"/><path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87"/><path d="M3 3l18 18"/></svg>');
            }
            else if (passwordInput.attr('type') === 'text') {
                passwordInput.attr('type', 'password');
                $('#show_hide_icon').html('<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/></svg>');
            }
        });
        })
    </script>
@endsection
