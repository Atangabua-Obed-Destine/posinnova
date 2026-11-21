{{-- I-NNOVA POS installation: a setup link that is wrong, used or out of date --}}
@extends('layouts.auth2')
@section('title', __('innova_setup.title'))
@section('right-max-width', 'auth-form--sm')

@section('content')
    <div class="ia-head">
        <div class="ia-head-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
        </div>
        <h1>@lang('innova_setup.invalid_title')</h1>
        <p>@lang('innova_setup.invalid_text')</p>
    </div>
    <div class="ia-card">
        <p style="margin:0 0 6px;">@lang('innova_setup.invalid_how')</p>
        <pre style="margin:0 0 16px;padding:10px 12px;border-radius:8px;background:#0f172a;color:#e2e8f0;font-size:13px;white-space:pre-wrap;">php artisan innova:install</pre>
        <a href="{{ url('login') }}" class="ia-btn" style="text-decoration:none;">@lang('innova_setup.go_login')</a>
    </div>
@stop
