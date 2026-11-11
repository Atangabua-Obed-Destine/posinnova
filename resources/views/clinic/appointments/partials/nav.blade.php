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
</div>
