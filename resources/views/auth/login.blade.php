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
        $ia_eye = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0-4 0"/><path d="M21 12c-2.4 4-5.4 6-9 6c-3.6 0-6.6-2-9-6c2.4-4 5.4-6 9-6c3.6 0 6.6 2 9 6"/></svg>';
        $ia_eye_off = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.585 10.587a2 2 0 0 0 2.829 2.828"/><path d="M16.681 16.673a8.717 8.717 0 0 1-4.681 1.327c-3.6 0-6.6-2-9-6c1.272-2.12 2.712-3.678 4.32-4.674m2.86-1.146a9.055 9.055 0 0 1 1.82-.18c3.6 0 6.6 2 9 6c-.666 1.11-1.379 2.067-2.138 2.87"/><path d="M3 3l18 18"/></svg>';
        $ia_errors = array_filter([$errors->first('username'), $errors->first('password'), $errors->first('g-recaptcha-response')]);
    @endphp

    @if (config('app.env') == 'demo')
        <div class="ia-card" style="margin:0 0 22px;padding:16px;">
            <p style="margin:0 0 10px;text-align:center;font-weight:600;font-size:14px;">Demo shops <small style="display:block;font-weight:400;color:var(--ia-muted);">Demos are for example purpose only — click a button to login.</small></p>
            <div style="display:flex;flex-wrap:wrap;gap:6px;justify-content:center;">
                <a href="?demo_type=all_in_one" class="btn btn-app bg-olive demo-login" data-admin="{{ $demo_types['all_in_one'] }}"><i class="fas fa-star"></i> All In One</a>
                <a href="?demo_type=pharmacy" class="btn btn-app bg-maroon demo-login" data-admin="{{ $demo_types['pharmacy'] }}"><i class="fas fa-medkit"></i> Pharmacy</a>
                <a href="?demo_type=services" class="btn btn-app bg-orange demo-login" data-admin="{{ $demo_types['services'] }}"><i class="fas fa-wrench"></i> Services</a>
                <a href="?demo_type=electronics" class="btn btn-app bg-purple demo-login" data-admin="{{ $demo_types['electronics'] }}"><i class="fas fa-laptop"></i> Electronics</a>
                <a href="?demo_type=super_market" class="btn btn-app bg-navy demo-login" data-admin="{{ $demo_types['super_market'] }}"><i class="fas fa-shopping-cart"></i> Super Market</a>
                <a href="?demo_type=restaurant" class="btn btn-app bg-red demo-login" data-admin="{{ $demo_types['restaurant'] }}"><i class="fas fa-utensils"></i> Restaurant</a>
            </div>
        </div>
    @endif

    <div class="ia-head">
        <div class="ia-head-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4.5" y="10.5" width="15" height="10" rx="2.5"/><path d="M8 10.5V7.5a4 4 0 0 1 8 0v3"/><path d="M12 14.5v2"/></svg>
        </div>
        <h1>@lang('lang_v1.auth_sign_in_title')</h1>
        <p>@lang('lang_v1.auth_sign_in_sub')</p>
    </div>

    <div class="ia-card">
        <form method="POST" action="{{ route('login') }}" id="login-form">
            {{ csrf_field() }}

            @if (count($ia_errors))
                <div class="ia-alert" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    <span>{{ reset($ia_errors) }}</span>
                </div>
            @endif

            <div class="ia-field">
                <label for="username" class="ia-label">@lang('lang_v1.username')</label>
                <input id="username" name="username" type="text" required autofocus autocomplete="username"
                    value="{{ $username }}" placeholder="@lang('lang_v1.auth_username_ph')"
                    class="ia-input" @if ($errors->has('username')) aria-invalid="true" @endif />
            </div>

            <div class="ia-field">
                <label for="password" class="ia-label">@lang('lang_v1.password')</label>
                <div class="ia-pwd">
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        value="{{ $password }}" placeholder="••••••••"
                        class="ia-input" @if ($errors->has('password')) aria-invalid="true" @endif />
                    <button type="button" id="show_hide_icon" class="ia-eye" aria-label="@lang('lang_v1.auth_show_password')" aria-pressed="false">{!! $ia_eye !!}</button>
                </div>
            </div>

            <div class="ia-row">
                <label class="ia-check">
                    <input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }} />
                    <span>@lang('lang_v1.auth_stay_signed_in')</span>
                </label>
                @if (config('app.env') != 'demo')
                    <a href="{{ route('password.request') }}" class="ia-link">@lang('lang_v1.forgot_your_password')</a>
                @endif
            </div>

            @if (config('constants.enable_recaptcha'))
                <div style="margin-bottom:18px;">
                    <div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div>
                </div>
            @endif

            <button type="submit" class="ia-btn">
                @lang('lang_v1.auth_sign_in_btn')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        @if (! ($request->segment(1) == 'business' && $request->segment(2) == 'register') && config('constants.allow_registration'))
            <p class="ia-alt">
                {{ __('business.not_yet_registered') }}
                <a href="{{ route('business.getRegister') }}@if (!empty(request()->lang)){{ '?lang=' . request()->lang }}@endif" class="ia-link">{{ __('business.register_now') }}</a>
            </p>
        @endif
    </div>
@stop

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('.change_lang').click(function() {
                window.location = "{{ route('login') }}?lang=" + $(this).attr('value');
            });
            $('a.demo-login').click(function(e) {
                e.preventDefault();
                $('#username').val($(this).data('admin'));
                $('#password').val("{{ $password }}");
                $('form#login-form').submit();
            });

            // login.js binds its own toggle to #show_hide_icon; this page owns it
            var eye = {!! json_encode($ia_eye) !!}, eyeOff = {!! json_encode($ia_eye_off) !!};
            $('#show_hide_icon').off('click').on('click', function(e) {
                e.preventDefault();
                var input = $('#password'), show = input.attr('type') === 'password';
                input.attr('type', show ? 'text' : 'password');
                $(this).html(show ? eyeOff : eye)
                    .attr('aria-pressed', show ? 'true' : 'false')
                    .attr('aria-label', show ? @json(__('lang_v1.auth_hide_password')) : @json(__('lang_v1.auth_show_password')));
            });
        });
    </script>
@endsection
