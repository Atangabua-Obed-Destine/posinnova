@extends('layouts.app')
@section('title', __('clinic.appt_upcoming'))

@php
    $A = \App\Http\Controllers\ClinicAppointmentController::class;
    $from = 'upcoming';
    $show_doctor = ! $doctor_id;
    $mine = $doctor_id && $doctor_id == auth()->id();
    $day_label = function ($d) {
        $d = \Carbon::parse($d);
        if ($d->isToday()) { return __('clinic.plan_today'); }
        if ($d->isTomorrow()) { return __('clinic.appt_tomorrow'); }
        return \Illuminate\Support\Str::ucfirst($d->translatedFormat('l'));
    };
    $label_cls = ['requested' => 'label-warning', 'confirmed' => 'label-primary'];
@endphp

@section('css')
<style>
    .ap-day { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; margin-bottom: 14px; }
    .ap-day h4 { margin: 0; padding: 10px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 15px; font-weight: 700; display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
    .ap-day h4 small { font-weight: normal; color: #64748b; }
    .ap-row { display: flex; gap: 12px; padding: 10px 14px; border-bottom: 1px solid #f1f5f9; align-items: flex-start; }
    .ap-row:last-child { border-bottom: 0; }
    .ap-time { width: 56px; flex-shrink: 0; font-weight: 700; color: #334155; padding-top: 1px; }
    .ap-time small { display: block; font-weight: normal; color: #64748b; font-size: 11px; }
    .ap-body { flex: 1; min-width: 0; }
    .ap-name { font-weight: 600; color: #0f172a; }
    .ap-sub { color: #64748b; font-size: 12px; }
    .ap-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
    .ap-actions form { display: inline; margin: 0; }
    .ap-wa { color: #15803d !important; border-color: #86efac !important; }
    .ap-warn { color: #b45309; font-size: 12px; font-weight: 600; }
    .ap-row--request { background: #fffbeb; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $mine ? __('clinic.appt_my_upcoming') : __('clinic.appt_upcoming') }}
        <small class="tw-text-gray-600">{{ @format_date($today) }} – {{ @format_date($to) }}</small>
    </h1>
</section>

<section class="content">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
        @include('clinic.appointments.partials.nav', ['active' => 'upcoming'])
        <form method="GET" class="form-inline" style="margin-bottom:12px; display:flex; gap:6px; flex-wrap:wrap;">
            @if ($access['see_all'])
                <select name="doctor_id" class="form-control input-sm" onchange="this.form.submit()" aria-label="{{ __('clinic.doctor') }}">
                    <option value="all">@lang('clinic.appt_all_doctors')</option>
                    @foreach ($all_doctors as $id => $name)
                        <option value="{{ $id }}" {{ $doctor_id == $id ? 'selected' : '' }}>{{ $id == auth()->id() ? __('clinic.appt_me', ['name' => $name]) : $name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="days" class="form-control input-sm" onchange="this.form.submit()" aria-label="{{ __('clinic.appt_period') }}">
                @foreach ([7, 14, 30] as $n)
                    <option value="{{ $n }}" {{ $days == $n ? 'selected' : '' }}>{{ __('clinic.appt_next_days', ['n' => $n]) }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @include('clinic.appointments.partials.wa_callout')

    {{-- patients' requests from the portal wait for an answer: first --}}
    @if ($requests->isNotEmpty())
        <div class="ap-day" id="ap_requests" style="border-color:#fcd34d;">
            <h4 style="background:#fffbeb;"><span><i class="fas fa-hourglass-half"></i> {{ trans_choice('clinic.appt_requests_title', $requests->count(), ['n' => $requests->count()]) }}</span>
                <small>@lang('clinic.appt_requests_sub')</small></h4>
            @foreach ($requests as $ap)
                <div class="ap-row ap-row--request">
                    <div class="ap-time">{{ $ap->start }}<small>{{ $day_label($ap->date) }}</small><small>{{ @format_date($ap->date) }}</small></div>
                    <div class="ap-body">
                        <span class="ap-name">{{ optional($ap->patient)->name }}</span>
                        <span class="label label-warning">@lang('clinic.appt_status_requested')</span>
                        <div class="ap-sub">{{ optional(optional($ap->patient)->patientDetails)->file_number }} · {{ optional($ap->patient)->mobile }}
                            @if ($show_doctor) · <i class="fas fa-user-md"></i> {{ trim(optional($ap->doctor)->user_full_name) }}@endif
                            @if ($ap->reason) · {{ $ap->reason }}@endif
                            · {{ __('clinic.appt_asked_on', ['date' => @format_datetime($ap->created_at)]) }}</div>
                        @if (in_array($ap->doctor_id.'|'.$ap->date->toDateString(), $offs))
                            <div class="ap-warn"><i class="fas fa-exclamation-triangle"></i> @lang('clinic.appt_on_day_off')</div>
                        @endif
                        <div style="margin-top:3px;">@include('clinic.appointments.partials.wa_status')</div>
                        @include('clinic.appointments.partials.appt_actions')
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @forelse ($by_day as $day => $list)
        <div class="ap-day">
            <h4><span>{{ $day_label($day) }} · {{ @format_date($day) }}</span>
                <small>{{ trans_choice('clinic.appt_n_booked', $list->count(), ['n' => $list->count()]) }}</small></h4>
            @foreach ($list as $ap)
                <div class="ap-row">
                    <div class="ap-time">{{ $ap->start }}<small>– {{ $ap->end }}</small></div>
                    <div class="ap-body">
                        <span class="ap-name">{{ optional($ap->patient)->name }}</span>
                        @if ($ap->source == 'portal')<span class="label label-default" title="{{ __('clinic.appt_from_portal') }}"><i class="fas fa-mobile-alt"></i></span>@endif
                        <div class="ap-sub">{{ optional(optional($ap->patient)->patientDetails)->file_number }} · {{ optional($ap->patient)->mobile }}
                            @if ($show_doctor) · <i class="fas fa-user-md"></i> {{ trim(optional($ap->doctor)->user_full_name) }}@endif
                            @if ($ap->reason) · {{ $ap->reason }}@endif
                            @if ($ap->reminded_at) · <i class="fab fa-whatsapp"></i> {{ __('clinic.appt_reminded', ['date' => @format_datetime($ap->reminded_at)]) }}@endif
                        </div>
                        @if (in_array($ap->doctor_id.'|'.$day, $offs))
                            <div class="ap-warn"><i class="fas fa-exclamation-triangle"></i> @lang('clinic.appt_on_day_off')</div>
                        @endif
                        <div style="margin-top:3px;">@include('clinic.appointments.partials.wa_status')</div>
                        @include('clinic.appointments.partials.appt_actions')
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        @if ($requests->isEmpty())
            <div class="callout callout-info"><p><i class="fas fa-calendar-check"></i> {{ __('clinic.appt_none_upcoming', ['n' => $days]) }}</p></div>
        @endif
    @endforelse
    <p class="help-block"><i class="fas fa-info-circle"></i> @lang('clinic.appt_upcoming_help')</p>
</section>
@endsection

@section('javascript')
@include('clinic.appointments.partials.move_modal')
@endsection
