{{-- I-NNOVA POS installation, last step: the client's business and its owner (InnovaSetupController) --}}
@extends('layouts.auth2')
@section('title', __('innova_setup.title'))
@section('right-max-width', 'auth-form--md')

@section('content')
    <style>
        .is-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 14px; }
        @media (max-width: 560px) { .is-grid { grid-template-columns: 1fr; } }
        .is-sec { margin: 6px 0 12px; font-size: 13px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--ia-muted, #667085); }
        .is-sec + .is-note, .is-note { margin: -6px 0 14px; font-size: 13px; color: var(--ia-muted, #667085); }
        .is-card .ia-field select.ia-input { height: auto; min-height: 44px; }
        .is-hr { border: 0; border-top: 1px solid #eaecf0; margin: 8px 0 18px; }
    </style>

    <div class="ia-head">
        <div class="ia-head-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/></svg>
        </div>
        <h1>@lang('innova_setup.title')</h1>
        <p>@lang('innova_setup.sub')</p>
    </div>

    <div class="ia-card is-card">
        <form method="POST" action="{{ route('innova.setup.store', ['token' => $token]) }}" id="innova_setup_form">
            {{ csrf_field() }}

            @if ($errors->any())
                <div class="ia-alert" role="alert">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <div class="is-sec">@lang('innova_setup.sec_business')</div>
            <div class="ia-field">
                <label for="business_name" class="ia-label">@lang('innova_setup.business_name')</label>
                <input id="business_name" name="business_name" type="text" required autofocus maxlength="255" class="ia-input"
                    value="{{ old('business_name') }}" placeholder="@lang('innova_setup.business_name_ph')">
            </div>
            <div class="is-grid">
                <div class="ia-field">
                    <label for="currency_id" class="ia-label">@lang('innova_setup.currency')</label>
                    <select id="currency_id" name="currency_id" class="ia-input" required>
                        @foreach ($currencies as $id => $label)
                            <option value="{{ $id }}" @selected((int) old('currency_id', $default_currency) === (int) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ia-field">
                    <label for="time_zone" class="ia-label">@lang('innova_setup.time_zone')</label>
                    <select id="time_zone" name="time_zone" class="ia-input" required>
                        @foreach ($timezones as $tz => $label)
                            <option value="{{ $tz }}" @selected(old('time_zone', config('innova.defaults.time_zone')) === $tz)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="ia-field">
                    <label for="country" class="ia-label">@lang('innova_setup.country')</label>
                    <input id="country" name="country" type="text" required maxlength="100" class="ia-input" value="{{ old('country', config('innova.defaults.country')) }}">
                </div>
                <div class="ia-field">
                    <label for="city" class="ia-label">@lang('innova_setup.city')</label>
                    <input id="city" name="city" type="text" required maxlength="100" class="ia-input" value="{{ old('city') }}" placeholder="Douala">
                </div>
            </div>

            <hr class="is-hr">
            <div class="is-sec">@lang('innova_setup.sec_owner')</div>
            <p class="is-note">@lang('innova_setup.owner_note')</p>
            <div class="is-grid">
                <div class="ia-field">
                    <label for="first_name" class="ia-label">@lang('innova_setup.first_name')</label>
                    <input id="first_name" name="first_name" type="text" required maxlength="100" class="ia-input" value="{{ old('first_name') }}">
                </div>
                <div class="ia-field">
                    <label for="last_name" class="ia-label">@lang('innova_setup.last_name')</label>
                    <input id="last_name" name="last_name" type="text" maxlength="100" class="ia-input" value="{{ old('last_name') }}">
                </div>
                <div class="ia-field">
                    <label for="username" class="ia-label">@lang('innova_setup.username')</label>
                    <input id="username" name="username" type="text" required minlength="4" maxlength="100" autocomplete="off" class="ia-input" value="{{ old('username') }}">
                </div>
                <div class="ia-field">
                    <label for="email" class="ia-label">@lang('innova_setup.email') <span style="font-weight:400;">(@lang('innova_setup.optional'))</span></label>
                    <input id="email" name="email" type="email" maxlength="191" class="ia-input" value="{{ old('email') }}">
                </div>
                <div class="ia-field">
                    <label for="password" class="ia-label">@lang('innova_setup.password')</label>
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" class="ia-input">
                </div>
                <div class="ia-field">
                    <label for="password_confirmation" class="ia-label">@lang('innova_setup.password_again')</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password" class="ia-input">
                </div>
                <div class="ia-field">
                    <label for="language" class="ia-label">@lang('innova_setup.language')</label>
                    <select id="language" name="language" class="ia-input">
                        <option value="en" @selected(old('language', app()->getLocale()) === 'en')>English</option>
                        <option value="fr" @selected(old('language', app()->getLocale()) === 'fr')>Français</option>
                    </select>
                </div>
                <div class="ia-field">
                    <label for="until" class="ia-label">@lang('innova_setup.until')</label>
                    <input id="until" name="until" type="date" class="ia-input" value="{{ old('until', $default_until) }}">
                </div>
            </div>

            <button type="submit" class="ia-btn" id="innova_setup_submit">
                @lang('innova_setup.create_btn')
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>
    </div>
@stop

@section('javascript')
    <script>
        document.getElementById('innova_setup_form').addEventListener('submit', function () {
            var b = document.getElementById('innova_setup_submit');
            setTimeout(function () { b.disabled = true; b.style.opacity = .7; }, 0);
        });
    </script>
@endsection
