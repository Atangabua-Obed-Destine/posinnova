@php $A = \App\Http\Controllers\ClinicAppointmentController::class; @endphp
<div class="btn-group" style="margin-bottom:12px;">
    @if (auth()->user()->can('appointment.view') || auth()->user()->can('appointment.manage'))
        <a href="{{ action([$A, 'index']) }}" class="btn btn-sm {{ $active == 'diary' ? 'btn-primary' : 'btn-default' }}"><i class="fas fa-calendar-alt"></i> @lang('clinic.appt_diary')</a>
    @endif
    @can('clinic_schedule.manage')
        <a href="{{ action([$A, 'hours']) }}" class="btn btn-sm {{ $active == 'hours' ? 'btn-primary' : 'btn-default' }}"><i class="fas fa-clock"></i> @lang('clinic.appt_doctor_hours')</a>
    @endcan
</div>
