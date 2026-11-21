{{-- I-NNOVA POS: installing / updating happens in a terminal (InstallController) --}}
@extends('layouts.auth2')
@section('title', __('innova_setup.install_title'))
@section('right-max-width', 'auth-form--sm')

@section('content')
    <div class="ia-head">
        <div class="ia-head-ic">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 17l6-6-6-6"/><path d="M12 19h8"/></svg>
        </div>
        <h1>@lang('innova_setup.install_title')</h1>
    </div>
    <div class="ia-card">
        <p style="margin:0 0 6px;">{{ $mode === 'update' ? __('innova_setup.update_text') : __('innova_setup.install_text') }}</p>
        <pre style="margin:0 0 14px;padding:10px 12px;border-radius:8px;background:#0f172a;color:#e2e8f0;font-size:13px;white-space:pre-wrap;">php artisan innova:{{ $mode === 'update' ? 'update' : 'install' }}</pre>
        @if ($mode !== 'update')
            <p style="margin:0 0 16px;color:var(--ia-muted,#667085);font-size:14px;">@lang('innova_setup.install_then')</p>
        @endif
        <a href="{{ url('login') }}" class="ia-btn" style="text-decoration:none;">@lang('innova_setup.go_login')</a>
    </div>
@stop
