@extends('layouts.app')
@section('title', __('clinic.wa_title'))

@php
    $W = \App\Http\Controllers\ClinicWhatsAppController::class;
    $done = array_sum($checks);
    $total = count($checks);
    $live = $checks['enabled'];
    // checklist: key => [title, guide step anchor]
    $steps = [
        'details' => [__('clinic.wa_ck_details'), 'g3'],
        'connection' => [__('clinic.wa_ck_connection'), 'g3'],
        'test' => [__('clinic.wa_ck_test'), 'g4'],
        'registered' => [__('clinic.wa_ck_registered'), 'g7'],
        'secret' => [__('clinic.wa_ck_secret'), 'g10'],
        'webhook' => [__('clinic.wa_ck_webhook'), 'g11'],
        'templates' => [__('clinic.wa_ck_templates'), 'g14'],
        'enabled' => [__('clinic.wa_ck_enabled'), 'g15'],
    ];
    $st_cls = ['APPROVED' => 'label-success', 'PENDING' => 'label-warning', 'IN_APPEAL' => 'label-warning', 'REJECTED' => 'label-danger', 'PAUSED' => 'label-danger', 'DISABLED' => 'label-danger'];
    $msg_cls = ['queued' => 'label-default', 'sent' => 'label-default', 'delivered' => 'label-info', 'read' => 'label-primary', 'failed' => 'label-danger', 'received' => 'label-success', 'handled' => 'label-default'];
@endphp

@section('css')
<style>
    .wa-banner { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; padding: 16px 18px; border-radius: 14px; margin-bottom: 16px; }
    .wa-banner.is-live { background: #f0fdf4; border: 1px solid #86efac; }
    .wa-banner.is-setup { background: #fffbeb; border: 1px solid #fcd34d; }
    .wa-banner .wa-big { width: 48px; height: 48px; border-radius: 50%; background: #16a34a; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 26px; flex-shrink: 0; }
    .wa-banner h3 { margin: 0; font-size: 18px; font-weight: 700; }
    .wa-progress { height: 8px; background: #e5e7eb; border-radius: 99px; overflow: hidden; margin-top: 8px; max-width: 360px; }
    .wa-progress span { display: block; height: 100%; background: #16a34a; }
    .wa-step { display: flex; gap: 12px; padding: 14px 4px; border-bottom: 1px solid #f1f5f9; }
    .wa-step:last-child { border-bottom: 0; }
    .wa-step .wa-dot { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-weight: 700; font-size: 13px; }
    .wa-step.is-done .wa-dot { background: #16a34a; color: #fff; }
    .wa-step.is-todo .wa-dot { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .wa-step .wa-step-body { flex: 1; min-width: 0; }
    .wa-step .wa-step-title { font-weight: 700; color: #0f172a; }
    .wa-step .wa-step-note { color: #64748b; font-size: 13px; margin: 2px 0 6px; }
    .wa-inline { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .wa-inline .form-control { width: auto; max-width: 100%; }
    .wa-copy { margin: 6px 0 10px; }
    .wa-copy-label { font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 2px; }
    .wa-copy-row { display: flex; gap: 6px; align-items: flex-start; }
    .wa-copy-text { flex: 1; min-width: 0; display: block; padding: 6px 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; color: #0f172a; font-size: 13px; word-break: break-all; white-space: pre-wrap; }
    .wa-guide h3 { font-size: 17px; font-weight: 700; margin: 26px 0 8px; padding-top: 12px; border-top: 2px solid #e2e8f0; color: #0f172a; }
    .wa-guide h3:first-of-type { border-top: 0; margin-top: 4px; }
    .wa-guide h4 { font-size: 15px; font-weight: 700; margin: 18px 0 6px; color: #1e3a8a; scroll-margin-top: 80px; }
    .wa-guide ol, .wa-guide ul { padding-left: 22px; }
    .wa-guide li { margin-bottom: 5px; line-height: 1.55; }
    .wa-guide .wa-tip { background: #eff6ff; border-left: 4px solid #3b82f6; padding: 8px 12px; border-radius: 6px; margin: 8px 0; font-size: 13px; }
    .wa-guide .wa-warn { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 8px 12px; border-radius: 6px; margin: 8px 0; font-size: 13px; }
    .wa-guide .wa-menu { font-weight: 700; color: #0f172a; background: #f1f5f9; padding: 1px 6px; border-radius: 4px; white-space: nowrap; }
    .wa-tpl { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin: 10px 0 16px; background: #fff; }
    .wa-tpl-head { font-weight: 700; margin-bottom: 6px; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black"><i class="fab fa-whatsapp" style="color:#16a34a;"></i> @lang('clinic.wa_title')
        <small class="tw-text-gray-600">@lang('clinic.wa_sub')</small>
    </h1>
</section>

<section class="content">
    {{-- where things stand --}}
    <div class="wa-banner {{ $live ? 'is-live' : 'is-setup' }}" id="wa_banner">
        <div class="wa-big"><i class="fab fa-whatsapp"></i></div>
        <div style="flex:1; min-width:220px;">
            <h3>{{ $live ? __('clinic.wa_state_live') : ($done == 0 ? __('clinic.wa_state_none') : __('clinic.wa_state_setup', ['n' => $done, 'total' => $total])) }}</h3>
            <div class="text-muted">{{ $live ? __('clinic.wa_state_live_sub', ['n' => $consenting, 'total' => $patients]) : __('clinic.wa_state_setup_sub') }}</div>
            <div class="wa-progress"><span style="width:{{ round(100 * $done / $total) }}%;"></span></div>
        </div>
        @if (! $live)
            <a href="{{ action([$W, 'index'], ['tab' => 'guide']) }}" class="btn btn-success"><i class="fas fa-book-open"></i> @lang('clinic.wa_open_guide')</a>
        @endif
    </div>

    <ul class="nav nav-tabs" style="margin-bottom:0;">
        <li class="{{ $tab == 'setup' ? 'active' : '' }}"><a href="{{ action([$W, 'index'], ['tab' => 'setup']) }}"><i class="fas fa-tasks"></i> @lang('clinic.wa_tab_setup')</a></li>
        <li class="{{ $tab == 'guide' ? 'active' : '' }}"><a href="{{ action([$W, 'index'], ['tab' => 'guide']) }}"><i class="fas fa-book-open"></i> @lang('clinic.wa_tab_guide')</a></li>
        <li class="{{ $tab == 'log' ? 'active' : '' }}"><a href="{{ action([$W, 'index'], ['tab' => 'log']) }}"><i class="fas fa-history"></i> @lang('clinic.wa_tab_log')</a></li>
    </ul>

    <div style="background:#fff; border:1px solid #ddd; border-top:0; border-radius:0 0 10px 10px; padding:16px 18px; margin-bottom:20px;">
    @if ($tab == 'setup')
        <div class="row">
            <div class="col-md-6">
                <h4 style="margin-top:0; font-weight:700;">@lang('clinic.wa_checklist')</h4>
                <p class="text-muted">@lang('clinic.wa_checklist_sub')</p>
                @foreach ($steps as $key => [$title, $anchor])
                    <div class="wa-step {{ $checks[$key] ? 'is-done' : 'is-todo' }}" id="ck_{{ $key }}">
                        <div class="wa-dot">@if ($checks[$key])<i class="fas fa-check"></i>@else{{ $loop->iteration }}@endif</div>
                        <div class="wa-step-body">
                            <div class="wa-step-title">{{ $title }}</div>
                            @switch($key)
                                @case('details')
                                    <div class="wa-step-note">{{ $checks['details'] ? __('clinic.wa_ck_details_ok') : __('clinic.wa_ck_details_todo') }}</div>
                                    @break
                                @case('connection')
                                    <div class="wa-step-note">
                                        @if ($acc->connection_ok_at){{ __('clinic.wa_ck_connection_ok', ['number' => $acc->display_phone ?: '-', 'name' => $acc->verified_name ?: '-']) }}
                                        @elseif ($acc->connection_error)<span class="text-danger">{{ $acc->connection_error }}</span>
                                        @else @lang('clinic.wa_ck_connection_todo')@endif
                                    </div>
                                    <form method="POST" action="{{ action([$W, 'testConnection']) }}">@csrf
                                        <button class="btn btn-sm btn-default" {{ $acc->hasCredentials() ? '' : 'disabled' }}><i class="fas fa-plug"></i> @lang('clinic.wa_test_connection')</button></form>
                                    @break
                                @case('test')
                                    <div class="wa-step-note">
                                        @if ($last_test)
                                            {{ __('clinic.wa_last_test', ['to' => '+'.$last_test->wa_number, 'date' => @format_datetime($last_test->created_at)]) }}
                                            <span class="label {{ $msg_cls[$last_test->status] ?? 'label-default' }}">@lang('clinic.wa_status_' . $last_test->status)</span>
                                            @if ($last_test->status == 'failed')<br><span class="text-danger">{{ $last_test->error_message }}</span>@endif
                                            @if ($last_test->status == 'sent')<br>@lang('clinic.wa_test_sent_wait')@endif
                                        @else @lang('clinic.wa_ck_test_todo')@endif
                                    </div>
                                    <form method="POST" action="{{ action([$W, 'sendTest']) }}" class="wa-inline">@csrf
                                        <input type="text" name="to" class="form-control input-sm" placeholder="{{ __('clinic.wa_test_to') }}" value="{{ old('to') }}" style="width:160px;" required aria-label="{{ __('clinic.wa_test_to') }}">
                                        <select name="which" class="form-control input-sm" aria-label="{{ __('clinic.wa_test_which') }}">
                                            <option value="hello_world">hello_world (Meta)</option>
                                            <option value="confirmation">@lang('clinic.wa_kind_confirmation')</option>
                                            <option value="reminder">@lang('clinic.wa_kind_reminder')</option>
                                            <option value="moved">@lang('clinic.wa_kind_moved')</option>
                                        </select>
                                        <button class="btn btn-sm btn-success" {{ $acc->hasCredentials() ? '' : 'disabled' }}><i class="fab fa-whatsapp"></i> @lang('clinic.wa_send_test')</button>
                                    </form>
                                    @break
                                @case('registered')
                                    <div class="wa-step-note">{{ $acc->registered_at ? __('clinic.wa_ck_registered_ok', ['date' => @format_date($acc->registered_at)]) : __('clinic.wa_ck_registered_todo') }}</div>
                                    @if (! $acc->registered_at)
                                        <form method="POST" action="{{ action([$W, 'register']) }}" class="wa-inline">@csrf
                                            <input type="text" name="pin" class="form-control input-sm" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="{{ __('clinic.wa_pin_ph') }}" style="width:150px;" required aria-label="PIN">
                                            <button class="btn btn-sm btn-default" {{ $acc->hasCredentials() ? '' : 'disabled' }}><i class="fas fa-key"></i> @lang('clinic.wa_register')</button>
                                        </form>
                                    @endif
                                    @break
                                @case('secret')
                                    <div class="wa-step-note">{{ $checks['secret'] ? __('clinic.wa_ck_secret_ok') : __('clinic.wa_ck_secret_todo') }}</div>
                                    @break
                                @case('webhook')
                                    <div class="wa-step-note">
                                        {{ $acc->webhook_verified_at ? __('clinic.wa_ck_webhook_verified', ['date' => @format_datetime($acc->webhook_verified_at)]) : __('clinic.wa_ck_webhook_todo') }}
                                        @if ($acc->last_webhook_at)<br>{{ __('clinic.wa_ck_webhook_last', ['date' => @format_datetime($acc->last_webhook_at)]) }}@endif
                                    </div>
                                    @include('clinic.whatsapp.partials.copy', ['label' => __('clinic.wa_callback_url'), 'text' => $webhook_url])
                                    @include('clinic.whatsapp.partials.copy', ['label' => __('clinic.wa_verify_token'), 'text' => $acc->verify_token])
                                    <form method="POST" action="{{ action([$W, 'subscribe']) }}" style="display:inline;">@csrf
                                        <button class="btn btn-sm btn-default" {{ $acc->hasCredentials() && $acc->waba_id ? '' : 'disabled' }}><i class="fas fa-link"></i> @lang('clinic.wa_connect_webhook')</button></form>
                                    @if ($acc->webhook_subscribed_at)<small class="text-success"><i class="fas fa-check"></i> {{ __('clinic.wa_subscribed_on', ['date' => @format_date($acc->webhook_subscribed_at)]) }}</small>@endif
                                    @break
                                @case('templates')
                                    <table class="table table-condensed" style="margin:4px 0 6px; font-size:13px;">
                                        <thead><tr><th>@lang('clinic.wa_template')</th><th>Français</th><th>English</th></tr></thead>
                                        <tbody>
                                            @foreach (\App\Utils\ClinicWhatsApp::KINDS as $kind)
                                                @php $tn = $acc->templateFor($kind); @endphp
                                                <tr class="{{ $acc->sends($kind) ? '' : 'text-muted' }}">
                                                    <td><code>{{ $tn }}</code>@if (! $acc->sends($kind)) <small>(@lang('clinic.wa_off'))</small>@endif</td>
                                                    @foreach (['fr', 'en'] as $lg)
                                                        @php $ts = $acc->templateStatus($tn, $lg); @endphp
                                                        <td>@if ($ts)<span class="label {{ $st_cls[$ts] ?? 'label-default' }}">{{ \Illuminate\Support\Facades\Lang::has('clinic.wa_tpl_state_' . strtolower($ts)) ? __('clinic.wa_tpl_state_' . strtolower($ts)) : $ts }}</span>@else<span class="text-muted">@lang('clinic.wa_tpl_missing')</span>@endif</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <div class="wa-step-note">{{ $acc->templates_checked_at ? __('clinic.wa_templates_checked', ['date' => @format_datetime($acc->templates_checked_at)]) : __('clinic.wa_ck_templates_todo') }}
                                        {{ __('clinic.wa_templates_lang_note', ['lang' => $acc->language == 'fr' ? 'Français' : 'English']) }}</div>
                                    <form method="POST" action="{{ action([$W, 'checkTemplates']) }}">@csrf
                                        <button class="btn btn-sm btn-default" {{ $acc->hasCredentials() && $acc->waba_id ? '' : 'disabled' }}><i class="fas fa-sync"></i> @lang('clinic.wa_check_templates')</button></form>
                                    @break
                                @case('enabled')
                                    <div class="wa-step-note">{{ $live ? __('clinic.wa_ck_enabled_ok') : __('clinic.wa_ck_enabled_todo') }}
                                        {{ __('clinic.wa_consent_count', ['n' => $consenting, 'total' => $patients]) }}</div>
                                    @break
                            @endswitch
                            <a href="{{ action([$W, 'index'], ['tab' => 'guide']) }}#{{ $anchor }}" class="small"><i class="fas fa-book-open"></i> @lang('clinic.wa_how_to')</a>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="col-md-6">
                <form method="POST" action="{{ action([$W, 'save']) }}" id="wa_details">
                    @csrf
                    <h4 style="margin-top:0; font-weight:700;">@lang('clinic.wa_settings')</h4>
                    <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin-bottom:14px;">
                        <div class="checkbox" style="margin:0;">
                            <label style="font-weight:700; font-size:15px;"><input type="checkbox" name="enabled" value="1" {{ $acc->enabled ? 'checked' : '' }}> @lang('clinic.wa_enabled')</label>
                        </div>
                        <p class="help-block" style="margin-bottom:0;">@lang('clinic.wa_enabled_help')</p>
                    </div>

                    <div class="form-group">
                        <label>@lang('clinic.wa_what_to_send')</label>
                        <div class="checkbox" style="margin-top:0;"><label><input type="checkbox" name="send_confirmation" value="1" {{ $acc->send_confirmation ? 'checked' : '' }}> @lang('clinic.wa_send_confirmation')</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="send_reminder" value="1" {{ $acc->send_reminder ? 'checked' : '' }}> @lang('clinic.wa_send_reminder')</label>
                            <span class="wa-inline" style="display:inline-flex; margin-left:6px;"><input type="time" name="reminder_time" value="{{ old('reminder_time', $acc->reminder_time) }}" class="form-control input-sm" aria-label="{{ __('clinic.wa_reminder_time') }}" required></span></div>
                        <div class="checkbox" style="margin-left:20px;"><label><input type="checkbox" name="reminder_buttons" value="1" {{ $acc->reminder_buttons ? 'checked' : '' }}> @lang('clinic.wa_reminder_buttons')</label></div>
                        <div class="checkbox"><label><input type="checkbox" name="send_moved" value="1" {{ $acc->send_moved ? 'checked' : '' }}> @lang('clinic.wa_send_moved')</label></div>
                    </div>
                    <div class="form-group">
                        <label for="wa_language">@lang('clinic.wa_language')</label>
                        <select name="language" id="wa_language" class="form-control" style="max-width:260px;">
                            <option value="fr" {{ $acc->language == 'fr' ? 'selected' : '' }}>Français</option>
                            <option value="en" {{ $acc->language == 'en' ? 'selected' : '' }}>English</option>
                        </select>
                        <p class="help-block">@lang('clinic.wa_language_help')</p>
                    </div>

                    <h4 style="font-weight:700; margin-top:22px;">@lang('clinic.wa_meta_details')</h4>
                    <p class="help-block">@lang('clinic.wa_meta_details_help')</p>
                    <div class="form-group">
                        <label for="phone_number_id">@lang('clinic.wa_phone_number_id')</label>
                        <input type="text" name="phone_number_id" id="phone_number_id" class="form-control" inputmode="numeric" value="{{ old('phone_number_id', $acc->phone_number_id) }}" placeholder="e.g. 106540352242922">
                    </div>
                    <div class="form-group">
                        <label for="waba_id">@lang('clinic.wa_waba_id')</label>
                        <input type="text" name="waba_id" id="waba_id" class="form-control" inputmode="numeric" value="{{ old('waba_id', $acc->waba_id) }}" placeholder="e.g. 102290129340398">
                    </div>
                    <div class="form-group">
                        <label for="access_token">@lang('clinic.wa_access_token')</label>
                        <input type="password" name="access_token" id="access_token" class="form-control" autocomplete="new-password"
                            placeholder="{{ $acc->access_token ? __('clinic.wa_secret_saved') : 'EAAG...' }}">
                        <p class="help-block">@lang('clinic.wa_access_token_help')</p>
                    </div>
                    <div class="form-group">
                        <label for="app_secret">@lang('clinic.wa_app_secret')</label>
                        <input type="password" name="app_secret" id="app_secret" class="form-control" autocomplete="new-password"
                            placeholder="{{ $acc->app_secret ? __('clinic.wa_secret_saved') : '' }}">
                        <p class="help-block">@lang('clinic.wa_app_secret_help')</p>
                    </div>
                    <details style="margin-bottom:14px;">
                        <summary style="cursor:pointer; font-weight:600;">@lang('clinic.wa_advanced')</summary>
                        <div class="form-group" style="margin-top:10px;">
                            <label for="api_version">@lang('clinic.wa_api_version')</label>
                            <input type="text" name="api_version" id="api_version" class="form-control" style="max-width:140px;" value="{{ old('api_version', $acc->api_version) }}">
                        </div>
                        @foreach (\App\Utils\ClinicWhatsApp::KINDS as $kind)
                            <div class="form-group">
                                <label for="tpl_{{ $kind }}">{{ __('clinic.wa_template_name_for', ['kind' => __('clinic.wa_kind_' . $kind)]) }}</label>
                                <input type="text" name="tpl_{{ $kind }}" id="tpl_{{ $kind }}" class="form-control" value="{{ old('tpl_' . $kind, $acc->templateFor($kind)) }}">
                            </div>
                        @endforeach
                        <p class="help-block">@lang('clinic.wa_template_names_help')</p>
                    </details>
                    <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold"><i class="fas fa-save"></i> @lang('messages.save')</button>
                </form>
            </div>
        </div>
    @elseif ($tab == 'guide')
        <div class="wa-guide">
            @include(app()->getLocale() == 'fr' ? 'clinic.whatsapp.partials.guide_fr' : 'clinic.whatsapp.partials.guide_en')
        </div>
    @else
        <p class="text-muted" style="margin-top:0;">@lang('clinic.wa_log_help')</p>
        <div class="table-responsive">
            <table class="table table-condensed table-striped" style="font-size:13px;">
                <thead><tr><th>@lang('lang_v1.date')</th><th></th><th>@lang('lang_v1.patient')</th><th>@lang('clinic.wa_log_what')</th><th>@lang('lang_v1.status')</th></tr></thead>
                <tbody>
                    @forelse ($log as $m)
                        <tr>
                            <td style="white-space:nowrap;">{{ @format_datetime($m->created_at) }}</td>
                            <td>{!! $m->direction == 'out' ? '<i class="fas fa-arrow-up text-muted" title="'.e(__('clinic.wa_sent_dir')).'"></i>' : '<i class="fas fa-arrow-down text-success" title="'.e(__('clinic.wa_received_dir')).'"></i>' !!}</td>
                            <td>{{ optional($m->patient)->name ?: '+'.$m->wa_number }}</td>
                            <td><strong>{{ __('clinic.wa_kind_' . $m->kind) }}</strong>
                                @if ($m->appointment) <small class="text-muted">· {{ @format_date($m->appointment->date) }} {{ $m->appointment->start }}</small>@endif
                                <br><small class="text-muted">{{ \Illuminate\Support\Str::limit($m->body, 120) }}</small></td>
                            <td><span class="label {{ $msg_cls[$m->status] ?? 'label-default' }}">@lang('clinic.wa_status_' . $m->status)</span>
                                @if ($m->status == 'failed')<br><small class="text-danger">{{ $m->error_message }}</small>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">@lang('clinic.wa_log_empty')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $(document).on('click', '.js-copy', function () {
            var btn = $(this), text = btn.data('copy');
            var done = function () { var h = btn.html(); btn.html('<i class="fas fa-check"></i> ' + @json(__('clinic.wa_copied'))); setTimeout(function () { btn.html(h); }, 1500); };
            // the older way: works where the browser refuses clipboard access
            var fallback = function () {
                var t = $('<textarea>').val(text).css({position: 'fixed', top: -1000}).appendTo('body');
                t[0].select();
                try { document.execCommand('copy'); } catch (e) {}
                t.remove(); done();
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(String(text)).then(done, fallback);
            } else {
                fallback();
            }
        });
    });
</script>
@endsection
