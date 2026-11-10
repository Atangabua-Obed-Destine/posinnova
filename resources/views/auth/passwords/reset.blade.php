@extends('layouts.auth2')
@section('right-max-width', 'auth-form--sm')

@section('title', __('lang_v1.reset_password'))

@section('content')
    <div class="ia-head">
        <div class="ia-head-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="15" r="4"/><path d="M10.8 12.2L20 3M16 7l3 3M14 9l2 2"/></svg>
        </div>
        <h1>@lang('lang_v1.reset_password')</h1>
        <p>@lang('lang_v1.auth_reset_sub')</p>
    </div>

    <div class="ia-card">
        @foreach (['email', 'password', 'password_confirmation'] as $ia_f)
            @if ($errors->has($ia_f))
                <div class="ia-alert" role="alert">{{ $errors->first($ia_f) }}</div>
                @break
            @endif
        @endforeach

        <form method="POST" action="{{ route('password.request') }}">
            {{ csrf_field() }}
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="ia-field">
                <label for="email" class="ia-label">@lang('lang_v1.email_address')</label>
                <input id="email" type="email" name="email" value="{{ $email ?? old('email') }}" required autofocus autocomplete="email" class="ia-input">
            </div>
            <div class="ia-field">
                <label for="password" class="ia-label">@lang('lang_v1.auth_new_password')</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="ia-input">
            </div>
            <div class="ia-field">
                <label for="password_confirmation" class="ia-label">@lang('business.confirm_password')</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="ia-input">
            </div>
            <button type="submit" class="ia-btn">@lang('lang_v1.reset_password')</button>
        </form>

        <p class="ia-alt"><a href="{{ route('login') }}" class="ia-link">← @lang('lang_v1.auth_back_to_sign_in')</a></p>
    </div>
@endsection
