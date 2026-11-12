@extends('layouts.app')
@section('title', __('clinic.wa_replies'))

@php
    $W = \App\Http\Controllers\ClinicWhatsAppController::class;
    $A = \App\Http\Controllers\ClinicAppointmentController::class;
    $active = 'replies';
@endphp

@section('css')
<style>
    .wr-row { display: flex; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #f1f5f9; align-items: flex-start; }
    .wr-row:last-child { border-bottom: 0; }
    .wr-row.is-open { background: #f0fdf4; }
    .wr-ic { width: 36px; height: 36px; border-radius: 50%; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 18px; }
    .wr-body { flex: 1; min-width: 0; }
    .wr-name { font-weight: 700; color: #0f172a; }
    .wr-meta { color: #64748b; font-size: 12px; }
    .wr-text { margin: 6px 0; padding: 8px 12px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px 10px 10px 2px; white-space: pre-wrap; word-wrap: break-word; }
    .wr-ctx { color: #64748b; font-size: 12px; border-left: 3px solid #cbd5e1; padding-left: 8px; margin-bottom: 4px; }
    .wr-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: flex-start; }
    .wr-actions form { margin: 0; }
    .wr-reply { margin-top: 6px; display: none; }
    .wr-reply textarea { width: 100%; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black"><i class="fab fa-whatsapp" style="color:#16a34a;"></i> @lang('clinic.wa_replies')
        <small class="tw-text-gray-600">@lang('clinic.wa_replies_sub')</small>
    </h1>
</section>

<section class="content">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
        @include('clinic.appointments.partials.nav', ['active' => 'replies'])
        <div class="btn-group" style="margin-bottom:12px;">
            <a href="{{ action([$W, 'inbox']) }}" class="btn btn-sm {{ $show == 'open' ? 'btn-primary' : 'btn-default' }}">{{ __('clinic.wa_show_open', ['n' => $open_count]) }}</a>
            <a href="{{ action([$W, 'inbox'], ['show' => 'all']) }}" class="btn btn-sm {{ $show == 'all' ? 'btn-primary' : 'btn-default' }}">@lang('clinic.wa_show_all')</a>
        </div>
    </div>

    @if (! $acc || ! $acc->ready())
        <div class="callout callout-info"><p><i class="fas fa-info-circle"></i> @lang('clinic.wa_inbox_off')
            @can('clinic_settings.manage') <a href="{{ action([$W, 'index']) }}">@lang('clinic.wa_open_setup')</a>@endcan</p></div>
    @endif

    @component('components.widget', ['class' => 'box-solid'])
        @forelse ($replies as $m)
            @php
                $ap = $m->appointment;
                $ctx = $m->context_wamid ? ($sent[$m->context_wamid] ?? null) : null;
                $can_reply = \App\Utils\ClinicWhatsApp::canReply($m->business_id, $m->wa_number);
            @endphp
            <div class="wr-row {{ $m->status == 'received' ? 'is-open' : '' }}" id="reply_{{ $m->id }}">
                <div class="wr-ic"><i class="fab fa-whatsapp"></i></div>
                <div class="wr-body">
                    <div>
                        @if ($m->patient)
                            <a href="{{ action([\App\Http\Controllers\PatientController::class, 'show'], [$m->contact_id]) }}" class="wr-name">{{ $m->patient->name }}</a>
                            <span class="wr-meta">· {{ optional($m->patient->patientDetails)->file_number }}</span>
                        @else
                            <span class="wr-name">@lang('clinic.wa_unknown_number')</span>
                        @endif
                        <span class="wr-meta">· +{{ $m->wa_number }} · {{ @format_datetime($m->sent_at ?: $m->created_at) }}</span>
                        @if ($m->status == 'handled')<span class="label label-default">@lang('clinic.wa_handled')</span>@endif
                    </div>
                    @if ($ctx)
                        <div class="wr-ctx" style="margin-top:6px;">{{ __('clinic.wa_in_reply_to') }} {{ \Illuminate\Support\Str::limit($ctx->body, 140) }}</div>
                    @endif
                    <div class="wr-text">@if ($m->kind == 'button')<i class="fas fa-hand-pointer"></i> @endif{{ $m->body }}</div>
                    @if ($ap)
                        <div class="wr-meta">
                            <i class="fas fa-calendar-alt"></i> {{ __('clinic.wa_about_appt', ['date' => @format_date($ap->date), 'time' => $ap->start, 'doctor' => trim(optional($ap->doctor)->user_full_name)]) }}
                            · @lang('clinic.appt_status_' . $ap->status)
                            @if ($ap->patient_reply == 'not_coming') · <span class="label label-danger">@lang('clinic.wa_reply_not_coming')</span>@endif
                            @if ($ap->patient_reply == 'coming') · <span class="label label-success">@lang('clinic.wa_reply_coming')</span>@endif
                        </div>
                    @endif
                    <form method="POST" action="{{ action([$W, 'reply'], [$m->id]) }}" class="wr-reply" id="reply_form_{{ $m->id }}">
                        @csrf
                        <textarea name="text" rows="2" maxlength="1000" class="form-control" placeholder="{{ __('clinic.wa_reply_ph') }}" required></textarea>
                        <button type="submit" class="btn btn-sm btn-success" style="margin-top:6px;"><i class="fab fa-whatsapp"></i> @lang('clinic.wa_send_reply')</button>
                    </form>
                </div>
                <div class="wr-actions">
                    @if ($ap && in_array($ap->status, ['requested', 'confirmed']))
                        <a href="{{ action([$A, 'index'], ['date' => $ap->date->toDateString(), 'doctor_id' => $ap->doctor_id]) }}" class="btn btn-xs btn-default"><i class="fas fa-calendar-alt"></i> @lang('clinic.wa_open_in_diary')</a>
                    @endif
                    @if ($can_reply && $acc && $acc->hasCredentials())
                        <button type="button" class="btn btn-xs btn-default js-reply" data-id="{{ $m->id }}"><i class="fas fa-reply"></i> @lang('clinic.wa_reply')</button>
                    @elseif (optional($m->patient)->mobile)
                        <a href="{{ \App\Utils\ClinicAppointments::whatsappLink($m->business_id, $m->patient->mobile, '') }}" target="_blank" rel="noopener" class="btn btn-xs btn-default" title="{{ __('clinic.wa_reply_too_late') }}"><i class="fab fa-whatsapp"></i> @lang('clinic.wa_reply_by_hand')</a>
                    @endif
                    @if ($m->status == 'received')
                        <form method="POST" action="{{ action([$W, 'handled'], [$m->id]) }}">@csrf
                            <button type="submit" class="btn btn-xs btn-success"><i class="fas fa-check"></i> @lang('clinic.wa_mark_handled')</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="text-center text-muted" style="padding:30px;"><i class="fab fa-whatsapp fa-2x"></i><br>{{ $show == 'open' ? __('clinic.wa_no_open_replies') : __('clinic.wa_no_replies') }}</div>
        @endforelse
    @endcomponent
    <p class="help-block"><i class="fas fa-info-circle"></i> @lang('clinic.wa_inbox_help')</p>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $(document).on('click', '.js-reply', function () {
            var f = $('#reply_form_' + $(this).data('id'));
            f.toggle();
            f.find('textarea').trigger('focus');
        });
    });
</script>
@endsection
