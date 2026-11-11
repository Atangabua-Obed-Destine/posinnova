{{--
    The buttons of one appointment, in the diary and in the upcoming list.
    Needs $ap and $access; $from ('diary' | 'upcoming'), $doctor_param and $days
    are posted back so the action returns to the same screen.
--}}
@php
    $A = \App\Http\Controllers\ClinicAppointmentController::class;
    $ap_may = \App\Utils\ClinicAppointments::mayManage($access, $ap->doctor_id);
    $ap_today = $ap->date->isToday();
    $ap_past_day = $ap->date->lt(\Carbon::today());
    $ap_keep = '<input type="hidden" name="from" value="'.e($from ?? 'diary').'">'
        .'<input type="hidden" name="doctor_filter" value="'.e($doctor_param ?? '').'">'
        .'<input type="hidden" name="days" value="'.e($days ?? '').'">';
@endphp
@if ($ap_may)
    <div class="ap-actions">
        @if ($ap->status == 'confirmed' && $ap_today && auth()->user()->can('visit.create'))
            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'create'], ['appointment_id' => $ap->id]) }}" class="btn btn-xs btn-primary"><i class="fas fa-sign-in-alt"></i> @lang('clinic.appt_check_in')</a>
        @endif
        @if ($ap->status == 'checked_in' && $ap->visit_id)
            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$ap->visit_id]) }}" class="btn btn-xs btn-default"><i class="fas fa-eye"></i> @lang('clinic.appt_open_visit')</a>
        @endif
        @if ($ap->status == 'requested')
            <form method="POST" action="{{ action([$A, 'confirm'], [$ap->id]) }}">@csrf{!! $ap_keep !!}<button class="btn btn-xs btn-success"><i class="fas fa-check"></i> @lang('clinic.appt_confirm')</button></form>
            <form method="POST" action="{{ action([$A, 'decline'], [$ap->id]) }}" class="js-reason" data-prompt="{{ __('clinic.appt_decline_prompt') }}">@csrf{!! $ap_keep !!}
                <input type="hidden" name="reason">
                <button class="btn btn-xs btn-default"><i class="fas fa-ban"></i> @lang('clinic.appt_decline')</button>
            </form>
        @endif
        @if (in_array($ap->status, ['requested', 'confirmed']) && ! $ap_past_day)
            @php $ap_wa = $ap->status == 'confirmed' ? \App\Utils\ClinicAppointments::whatsappFor($ap) : null; @endphp
            @if ($ap_wa)
                <a href="{{ $ap_wa }}" target="_blank" rel="noopener" class="btn btn-xs btn-default ap-wa js-wa" data-url="{{ action([$A, 'reminded'], [$ap->id]) }}"><i class="fab fa-whatsapp"></i> @lang('clinic.appt_whatsapp')</a>
            @endif
            <button type="button" class="btn btn-xs btn-default js-move" data-id="{{ $ap->id }}" data-doctor="{{ $ap->doctor_id }}" data-date="{{ $ap->date->toDateString() }}"
                data-patient="{{ optional($ap->patient)->name }}" data-current="{{ @format_date($ap->date) }} {{ $ap->start }} · {{ trim(optional($ap->doctor)->user_full_name) }}"><i class="fas fa-exchange-alt"></i> @lang('clinic.appt_move')</button>
            <form method="POST" action="{{ action([$A, 'cancel'], [$ap->id]) }}" class="js-reason" data-prompt="{{ __('clinic.appt_cancel_prompt') }}">@csrf{!! $ap_keep !!}
                <input type="hidden" name="reason">
                <button class="btn btn-xs btn-default"><i class="fas fa-ban"></i> @lang('messages.cancel')</button>
            </form>
        @endif
        @if ($ap->status == 'confirmed' && $ap->startsAt()->isPast())
            <form method="POST" action="{{ action([$A, 'noShow'], [$ap->id]) }}">@csrf{!! $ap_keep !!}<button class="btn btn-xs btn-default"><i class="fas fa-user-slash"></i> @lang('clinic.appt_no_show')</button></form>
        @endif
    </div>
@endif
