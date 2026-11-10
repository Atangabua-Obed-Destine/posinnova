@extends('layouts.auth2')
@section('right-max-width', 'auth-form--sm')

@section('title', __('lang_v1.reset_password'))

@section('content')
    <div class="ia-head">
        <div class="ia-head-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="5.5" width="17" height="13" rx="2.5"/><path d="M4 7l8 6 8-6"/></svg>
        </div>
        <h1>@lang('lang_v1.auth_forgot_title')</h1>
        <p>@lang('lang_v1.auth_forgot_sub')</p>
    </div>

    <div class="ia-card">
        @if (session('status') && is_string(session('status')))
            <div class="ia-alert ia-alert--ok" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->has('email'))
            <div class="ia-alert" role="alert">{{ $errors->first('email') }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            {{ csrf_field() }}
            <div class="ia-field">
                <label for="email" class="ia-label">@lang('lang_v1.email_address')</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                    placeholder="name@example.com" class="ia-input">
            </div>
            <button type="submit" class="ia-btn">@lang('lang_v1.send_password_reset_link')</button>
        </form>

        <p class="ia-alt"><a href="{{ route('login') }}" class="ia-link">← @lang('lang_v1.auth_back_to_sign_in')</a></p>
    </div>
@endsection

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('.change_lang').click(function() {
                window.location = "{{ route('password.request') }}?lang=" + $(this).attr('value');
            });
        })
    </script>
@endsection
