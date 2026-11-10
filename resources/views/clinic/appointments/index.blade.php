@extends('layouts.app')
@section('title', __('clinic.appt_menu'))

@php
    $A = \App\Http\Controllers\ClinicAppointmentController::class;
    $is_today = $date->isToday();
    $is_past_day = $date->lt(\Carbon::today());
    $q = fn ($d) => action([$A, 'index'], array_filter(['date' => $d->toDateString(), 'doctor_id' => $doctor_id]));
    $label_cls = ['requested' => 'label-warning', 'confirmed' => 'label-primary', 'checked_in' => 'label-success', 'no_show' => 'label-default', 'cancelled' => 'label-default', 'declined' => 'label-default'];
@endphp

@section('css')
<style>
    .ap-cols { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-start; }
    .ap-col { flex: 1 1 340px; max-width: 560px; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
    .ap-col h4 { margin: 0; padding: 12px 14px; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 15px; font-weight: 700; display: flex; justify-content: space-between; gap: 8px; }
    .ap-slot { display: flex; gap: 10px; padding: 8px 14px; border-bottom: 1px solid #f1f5f9; align-items: flex-start; }
    .ap-slot:last-child { border-bottom: 0; }
    .ap-time { width: 48px; font-weight: 700; color: #334155; flex-shrink: 0; padding-top: 2px; }
    .ap-body { flex: 1; min-width: 0; }
    .ap-free { color: #94a3b8; }
    .ap-slot.is-past .ap-time { color: #94a3b8; }
    .ap-name { font-weight: 600; color: #0f172a; }
    .ap-sub { color: #64748b; font-size: 12px; }
    .ap-actions { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
    .ap-actions form { display: inline; margin: 0; }
    .ap-wa { color: #15803d !important; border-color: #86efac !important; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.appt_menu')
        <small class="tw-text-gray-600">{{ $date->translatedFormat('l') }} {{ @format_date($date) }}</small>
    </h1>
</section>

<section class="content">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
        @include('clinic.appointments.partials.nav', ['active' => 'diary'])
        <div class="btn-group" style="margin-bottom:12px;">
            <a href="{{ $q($date->copy()->subDay()) }}" class="btn btn-sm btn-default" title="{{ __('clinic.appt_prev_day') }}"><i class="fas fa-chevron-left"></i></a>
            <a href="{{ $q(\Carbon::today()) }}" class="btn btn-sm {{ $is_today ? 'btn-primary' : 'btn-default' }}">@lang('clinic.plan_today')</a>
            <a href="{{ $q($date->copy()->addDay()) }}" class="btn btn-sm btn-default" title="{{ __('clinic.appt_next_day') }}"><i class="fas fa-chevron-right"></i></a>
        </div>
        <form method="GET" class="form-inline" style="margin-bottom:12px; display:flex; gap:6px;">
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control input-sm" onchange="this.form.submit()" aria-label="{{ __('lang_v1.date') }}">
            <select name="doctor_id" class="form-control input-sm" onchange="this.form.submit()" aria-label="{{ __('clinic.doctor') }}">
                <option value="">@lang('clinic.appt_all_doctors')</option>
                @foreach ($all_doctors as $id => $name)
                    <option value="{{ $id }}" {{ $doctor_id == $id ? 'selected' : '' }}>{{ $name }}</option>
                @endforeach
            </select>
        </form>
        @if ($reminders_due > 0 && $can_manage)
            <a href="{{ $q($tomorrow) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline ap-wa" style="margin-left:auto; margin-bottom:12px;">
                <i class="fab fa-whatsapp"></i> {{ trans_choice('clinic.appt_reminders_due', $reminders_due, ['n' => $reminders_due]) }}
            </a>
        @endif
    </div>

    @if (session('appt_whatsapp'))
        <div class="callout callout-success" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span><i class="fas fa-check"></i> @lang('clinic.appt_tell_patient')</span>
            <a href="{{ session('appt_whatsapp') }}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="fab fa-whatsapp"></i> @lang('clinic.appt_send_whatsapp')</a>
        </div>
    @endif

    @if (empty($with_hours))
        <div class="callout callout-info">
            <p><i class="fas fa-info-circle"></i> @lang('clinic.appt_no_hours')
                @can('clinic_schedule.manage') <a href="{{ action([$A, 'hours']) }}">@lang('clinic.appt_set_hours')</a>@endcan</p>
        </div>
    @endif

    @if ($requests->isNotEmpty())
        @component('components.widget', ['class' => 'box-warning', 'title' => trans_choice('clinic.appt_requests_title', $requests->count(), ['n' => $requests->count()])])
            <div class="table-responsive">
                <table class="table table-condensed" style="margin-bottom:0;">
                    <tbody>
                        @foreach ($requests as $r)
                            <tr>
                                <td style="white-space:nowrap;"><strong>{{ @format_date($r->date) }} {{ $r->start }}</strong><br><small class="text-muted">{{ trim(optional($r->doctor)->user_full_name) }}</small></td>
                                <td><span class="ap-name">{{ optional($r->patient)->name }}</span>
                                    <br><small class="text-muted">{{ optional(optional($r->patient)->patientDetails)->file_number }} · {{ optional($r->patient)->mobile }}</small></td>
                                <td>{{ $r->reason ?: '-' }}<br><small class="text-muted">{{ __('clinic.appt_asked_on', ['date' => @format_datetime($r->created_at)]) }}</small></td>
                                <td class="text-right" style="white-space:nowrap;">
                                    @if ($can_manage)
                                        <form method="POST" action="{{ action([$A, 'confirm'], [$r->id]) }}" style="display:inline;">@csrf
                                            <button class="btn btn-xs btn-success"><i class="fas fa-check"></i> @lang('clinic.appt_confirm')</button>
                                        </form>
                                        <form method="POST" action="{{ action([$A, 'decline'], [$r->id]) }}" style="display:inline;" class="js-reason" data-prompt="{{ __('clinic.appt_decline_prompt') }}">@csrf
                                            <input type="hidden" name="reason">
                                            <button class="btn btn-xs btn-default"><i class="fas fa-ban"></i> @lang('clinic.appt_decline')</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endcomponent
    @endif

    <div class="ap-cols">
        @forelse ($columns as $col)
            <div class="ap-col">
                <h4><span><i class="fas fa-user-md"></i> {{ $col['name'] }}</span>
                    @php $taken = collect($col['slots'])->where('state', 'taken')->count(); @endphp
                    <small class="text-muted" style="font-weight:normal;">{{ __('clinic.appt_booked_count', ['n' => $taken, 'total' => count($col['slots'])]) }}</small>
                </h4>
                @if ($col['off'])
                    <div class="ap-slot"><div class="ap-body text-muted"><i class="fas fa-umbrella-beach"></i> @lang('clinic.appt_day_off'){{ $col['off_reason'] ? ': '.$col['off_reason'] : '' }}</div></div>
                @elseif (empty($col['slots']))
                    <div class="ap-slot"><div class="ap-body text-muted">@lang('clinic.appt_no_hours_day')</div></div>
                @endif
                @foreach ($col['slots'] as $s)
                    @php $ap = $s['appointment']; @endphp
                    <div class="ap-slot {{ $s['state'] == 'past' ? 'is-past' : '' }}">
                        <div class="ap-time">{{ $s['start'] }}</div>
                        <div class="ap-body">
                            @if ($ap)
                                <span class="ap-name">{{ optional($ap->patient)->name }}</span>
                                <span class="label {{ $label_cls[$ap->status] ?? 'label-default' }}">@lang('clinic.appt_status_' . $ap->status)</span>
                                @if ($ap->source == 'portal')<span class="label label-default" title="{{ __('clinic.appt_from_portal') }}"><i class="fas fa-mobile-alt"></i></span>@endif
                                <div class="ap-sub">{{ optional(optional($ap->patient)->patientDetails)->file_number }} · {{ optional($ap->patient)->mobile }}
                                    @if ($ap->reason) · {{ $ap->reason }}@endif
                                    @if ($ap->reminded_at) · <i class="fab fa-whatsapp"></i> {{ __('clinic.appt_reminded', ['date' => @format_datetime($ap->reminded_at)]) }}@endif
                                </div>
                                @if ($can_manage)
                                    <div class="ap-actions">
                                        @if ($ap->status == 'confirmed' && $is_today)
                                            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'create'], ['appointment_id' => $ap->id]) }}" class="btn btn-xs btn-primary"><i class="fas fa-sign-in-alt"></i> @lang('clinic.appt_check_in')</a>
                                        @endif
                                        @if ($ap->status == 'checked_in' && $ap->visit_id)
                                            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$ap->visit_id]) }}" class="btn btn-xs btn-default"><i class="fas fa-eye"></i> @lang('clinic.appt_open_visit')</a>
                                        @endif
                                        @if ($ap->status == 'requested')
                                            <form method="POST" action="{{ action([$A, 'confirm'], [$ap->id]) }}">@csrf<button class="btn btn-xs btn-success"><i class="fas fa-check"></i> @lang('clinic.appt_confirm')</button></form>
                                        @endif
                                        @if (in_array($ap->status, ['requested', 'confirmed']) && ! $is_past_day)
                                            @php $wa = \App\Utils\ClinicAppointments::whatsappFor($ap); @endphp
                                            @if ($wa && $ap->status == 'confirmed')
                                                <a href="{{ $wa }}" target="_blank" rel="noopener" class="btn btn-xs btn-default ap-wa js-wa" data-url="{{ action([$A, 'reminded'], [$ap->id]) }}"><i class="fab fa-whatsapp"></i> @lang('clinic.appt_whatsapp')</a>
                                            @endif
                                            <form method="POST" action="{{ action([$A, 'cancel'], [$ap->id]) }}" class="js-reason" data-prompt="{{ __('clinic.appt_cancel_prompt') }}">@csrf
                                                <input type="hidden" name="reason">
                                                <button class="btn btn-xs btn-default"><i class="fas fa-ban"></i> @lang('messages.cancel')</button>
                                            </form>
                                        @endif
                                        @if ($ap->status == 'confirmed' && $ap->startsAt()->isPast())
                                            <form method="POST" action="{{ action([$A, 'noShow'], [$ap->id]) }}">@csrf<button class="btn btn-xs btn-default"><i class="fas fa-user-slash"></i> @lang('clinic.appt_no_show')</button></form>
                                        @endif
                                    </div>
                                @endif
                            @elseif ($s['state'] == 'free')
                                <span class="ap-free">@lang('clinic.appt_free')</span>
                                @if ($can_manage)
                                    <button type="button" class="btn btn-xs btn-default js-book" style="margin-left:6px;"
                                        data-doctor="{{ $col['id'] }}" data-doctor-name="{{ $col['name'] }}" data-start="{{ $s['start'] }}"><i class="fas fa-plus"></i> @lang('clinic.appt_book')</button>
                                @endif
                            @else
                                <span class="ap-free">–</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <p class="text-muted">@lang('clinic.appt_nobody_consults')</p>
        @endforelse
    </div>

    @if ($cancelled->isNotEmpty())
        <p class="text-muted" style="margin-top:14px;"><i class="fas fa-ban"></i> @lang('clinic.appt_cancelled_today'):
            @foreach ($cancelled as $c)
                {{ $c->start }} {{ optional($c->patient)->name }} ({{ __('clinic.appt_status_' . $c->status) }}{{ $c->cancel_reason ? ': '.$c->cancel_reason : '' }}){{ $loop->last ? '' : ' · ' }}
            @endforeach
        </p>
    @endif
    <p class="help-block"><i class="fas fa-info-circle"></i> @lang('clinic.appt_diary_help')</p>
</section>

@if ($can_manage)
<div class="modal fade" id="book_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <form method="POST" action="{{ action([$A, 'store']) }}" class="modal-content">
            @csrf
            <input type="hidden" name="doctor_id" id="book_doctor">
            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
            <input type="hidden" name="start" id="book_start">
            <input type="hidden" name="doctor_filter" value="{{ $doctor_id }}">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title" id="book_title">@lang('clinic.appt_book')</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="book_patient">@lang('lang_v1.patient'):*</label>
                    <select name="contact_id" id="book_patient" class="form-control" required style="width:100%;"></select>
                </div>
                <div class="form-group">
                    <label for="book_reason">@lang('clinic.appt_reason'):</label>
                    <input type="text" name="reason" id="book_reason" class="form-control" maxlength="500">
                </div>
                <div class="form-group">
                    <label for="book_notes">@lang('lang_v1.notes'):</label>
                    <input type="text" name="notes" id="book_notes" class="form-control" maxlength="500">
                </div>
                <p class="help-block">@lang('clinic.appt_book_help')</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="tw-dw-btn tw-dw-btn-outline" data-dismiss="modal">@lang('messages.cancel')</button>
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white"><i class="fas fa-calendar-check"></i> @lang('clinic.appt_book')</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@section('javascript')
<script>
    $(function () {
        var dateLabel = @json(app(\App\Utils\Util::class)->format_date($date->toDateString()));
        $('#book_patient').select2({
            dropdownParent: $('#book_modal'),
            ajax: { url: '{{ route('patients.search') }}', dataType: 'json', delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results }; } },
            minimumInputLength: 1, placeholder: @json(__('lang_v1.search_patient')), width: '100%',
        });
        $(document).on('click', '.js-book', function () {
            $('#book_doctor').val($(this).data('doctor'));
            $('#book_start').val($(this).data('start'));
            $('#book_title').text($(this).data('doctor-name') + ' · ' + dateLabel + ' ' + $(this).data('start'));
            $('#book_modal').modal('show');
        });
        // cancel / decline ask for a short reason
        $(document).on('submit', 'form.js-reason', function () {
            var r = prompt($(this).data('prompt'), '');
            if (r === null) { return false; }
            $(this).find('input[name=reason]').val(r);
        });
        // opening WhatsApp records that the reminder was sent
        $(document).on('click', '.js-wa', function () {
            $.post($(this).data('url'), { _token: '{{ csrf_token() }}' });
        });
    });
</script>
@endsection
