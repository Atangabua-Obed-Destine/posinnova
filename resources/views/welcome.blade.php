@extends('layouts.auth2')
@section('title', config('app.name', 'ultimatePOS'))
@section('right-max-width', 'auth-form--sm')
@inject('request', 'Illuminate\Http\Request')
@section('content')
    <div class="ia-head">
        <div class="ia-head-ic" style="background:#fff;border:1px solid var(--ia-rule);">
            <img src="{{ asset('img/logo-small.png') }}" alt="" style="width:46px;height:46px;object-fit:contain;">
        </div>
        <h1>{{ config('app.name', 'UltimatePOS') }}</h1>
        @if (env('APP_TITLE'))
            <p>{{ env('APP_TITLE') }}</p>
        @endif
    </div>
    <div class="ia-card">
        <a href="{{ route('login') }}" class="ia-btn">
            {{ __('business.sign_in') }}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
        @if (config('constants.allow_registration'))
            <p class="ia-alt">{{ __('business.not_yet_registered') }}
                <a href="{{ route('business.getRegister') }}" class="ia-link">{{ __('business.register_now') }}</a></p>
        @endif
    </div>
@endsection
