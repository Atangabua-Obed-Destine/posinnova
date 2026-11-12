@php
    $A = \App\Http\Controllers\ClinicAppointmentController::class;
    $nav_access = \App\Utils\ClinicAppointments::access(session('user.business_id'), auth()->user());
    // keep the doctor picked on the diary / list when switching between them
    $nav_q = array_filter(['doctor_id' => $doctor_param ?? null]);
@endphp
<div class="btn-group" style="margin-bottom:12px;">
    @if ($nav_access['see'])
        <a href="{{ action([$A, 'index'], $nav_q) }}" class="btn btn-sm {{ $active == 'diary' ? 'btn-primary' : 'btn-default' }}"><i class="fas fa-calendar-alt"></i> @lang('clinic.appt_diary')</a>
        <a href="{{ action([$A, 'upcoming'], $nav_q) }}" class="btn btn-sm {{ $active == 'upcoming' ? 'btn-primary' : 'btn-default' }}"><i class="fas fa-list-ul"></i> @lang('clinic.appt_upcoming')</a>
    @endif
    @if ($nav_access['schedule_all'] || $nav_access['schedule_own'] !== null)
        <a href="{{ action([$A, 'hours']) }}" class="btn btn-sm {{ $active == 'hours' ? 'btn-primary' : 'btn-default' }}"><i class="fas fa-clock"></i> {{ $nav_access['schedule_all'] ? __('clinic.appt_doctor_hours') : __('clinic.appt_my_hours') }}</a>
    @endif
    {{-- patients' WhatsApp answers, once the clinic sends automatic messages --}}
    @if ((auth()->user()->can('appointment.manage') || auth()->user()->can('clinic_settings.manage'))
        && \App\ClinicWhatsappAccount::where('business_id', session('user.business_id'))->whereNotNull('phone_number_id')->exists())
        @php $nav_replies = \App\ClinicWhatsappMessage::forBusiness(session('user.business_id'))->unhandled()->count(); @endphp
        <a href="{{ action([\App\Http\Controllers\ClinicWhatsAppController::class, 'inbox']) }}" id="wa_replies_btn"
            class="btn btn-sm {{ $active == 'replies' ? 'btn-primary' : 'btn-default' }}" style="{{ $nav_replies && $active != 'replies' ? 'color:#15803d; font-weight:700;' : '' }}">
            <i class="fab fa-whatsapp"></i> @lang('clinic.wa_replies')@if ($nav_replies) <span class="badge" style="{{ $active == 'replies' ? 'background:#fff; color:#15803d;' : 'background:#16a34a;' }}">{{ $nav_replies }}</span>@endif</a>
    @endif
</div>
