@extends('layouts.app')
@section('title', __('clinic.appt_doctor_hours'))

@php $A = \App\Http\Controllers\ClinicAppointmentController::class; @endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.appt_doctor_hours')
        <small class="tw-text-gray-600">@lang('clinic.appt_hours_sub')</small>
    </h1>
</section>

<section class="content">
    @include('clinic.appointments.partials.nav', ['active' => 'hours'])

    @if (empty($doctors))
        <div class="callout callout-info"><p>@lang('clinic.appt_no_doctors')</p></div>
    @endif

    <div class="row">
        @foreach ($doctors as $id => $name)
            @php $blocks = $hours[$id] ?? collect(); $offs = $days_off[$id] ?? collect(); @endphp
            <div class="col-md-6">
                @component('components.widget', ['class' => 'box-primary', 'title' => $name])
                    @if ($blocks->isEmpty())
                        <p class="text-muted">@lang('clinic.appt_doctor_no_hours')</p>
                    @else
                        <table class="table table-condensed">
                            <thead><tr><th>@lang('clinic.appt_day')</th><th>@lang('clinic.appt_hours')</th><th>@lang('clinic.appt_slot_length')</th><th></th></tr></thead>
                            <tbody>
                                @foreach ($blocks as $b)
                                    <tr>
                                        <td>@lang('clinic.weekday_' . $b->weekday)</td>
                                        <td>{{ substr($b->start_time, 0, 5) }} – {{ substr($b->end_time, 0, 5) }}</td>
                                        <td>{{ __('clinic.appt_minutes', ['n' => $b->slot_minutes]) }}</td>
                                        <td class="text-right">
                                            <form method="POST" action="{{ action([$A, 'deleteHours'], [$b->id]) }}" style="display:inline;">@csrf
                                                <button class="btn btn-xs btn-link text-danger" title="{{ __('messages.delete') }}"><i class="fas fa-trash-alt"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    <form method="POST" action="{{ action([$A, 'storeHours']) }}" style="background:#f8fafc; border-radius:8px; padding:10px; margin-bottom:12px;">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $id }}">
                        <div style="margin-bottom:6px;"><strong>@lang('clinic.appt_add_hours')</strong></div>
                        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;">
                            @foreach (range(1, 7) as $d)
                                <label style="font-weight:normal; margin:0;"><input type="checkbox" name="weekdays[]" value="{{ $d }}" {{ $d <= 5 ? 'checked' : '' }}> {{ __('clinic.weekday_short_' . $d) }}</label>
                            @endforeach
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
                            <div><label for="hs_start_{{ $id }}" style="font-weight:normal; font-size:12px;">@lang('clinic.appt_from')</label><input type="time" name="start_time" id="hs_start_{{ $id }}" value="08:00" class="form-control input-sm" required></div>
                            <div><label for="hs_end_{{ $id }}" style="font-weight:normal; font-size:12px;">@lang('clinic.appt_to')</label><input type="time" name="end_time" id="hs_end_{{ $id }}" value="12:00" class="form-control input-sm" required></div>
                            <div><label for="hs_slot_{{ $id }}" style="font-weight:normal; font-size:12px;">@lang('clinic.appt_slot_length')</label>
                                <select name="slot_minutes" id="hs_slot_{{ $id }}" class="form-control input-sm">
                                    @foreach ([10, 15, 20, 30, 45, 60] as $m)
                                        <option value="{{ $m }}" {{ $m == 20 ? 'selected' : '' }}>{{ __('clinic.appt_minutes', ['n' => $m]) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus"></i> @lang('messages.add')</button>
                        </div>
                    </form>

                    <div><strong><i class="fas fa-umbrella-beach"></i> @lang('clinic.appt_days_off')</strong></div>
                    @if ($offs->isEmpty())
                        <p class="text-muted" style="margin:4px 0 8px;">@lang('clinic.appt_no_days_off')</p>
                    @else
                        <ul style="padding-left:18px; margin:4px 0 8px;">
                            @foreach ($offs as $o)
                                <li>{{ @format_date($o->date) }}{{ $o->reason ? ' · '.$o->reason : '' }}
                                    <form method="POST" action="{{ action([$A, 'deleteDayOff'], [$o->id]) }}" style="display:inline;">@csrf
                                        <button class="btn btn-xs btn-link text-danger" title="{{ __('messages.delete') }}"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <form method="POST" action="{{ action([$A, 'storeDayOff']) }}" style="display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end;">
                        @csrf
                        <input type="hidden" name="user_id" value="{{ $id }}">
                        <div><label for="off_from_{{ $id }}" style="font-weight:normal; font-size:12px;">@lang('clinic.appt_from')</label><input type="date" name="date" id="off_from_{{ $id }}" class="form-control input-sm" min="{{ \Carbon::today()->toDateString() }}" required></div>
                        <div><label for="off_to_{{ $id }}" style="font-weight:normal; font-size:12px;">@lang('clinic.appt_to_optional')</label><input type="date" name="date_to" id="off_to_{{ $id }}" class="form-control input-sm" min="{{ \Carbon::today()->toDateString() }}"></div>
                        <div style="flex:1; min-width:120px;"><label for="off_reason_{{ $id }}" style="font-weight:normal; font-size:12px;">@lang('clinic.appt_reason')</label><input type="text" name="reason" id="off_reason_{{ $id }}" class="form-control input-sm" maxlength="191"></div>
                        <button type="submit" class="btn btn-sm btn-default"><i class="fas fa-plus"></i> @lang('clinic.appt_add_day_off')</button>
                    </form>
                @endcomponent
            </div>
            @if ($loop->iteration % 2 == 0)<div class="clearfix"></div>@endif
        @endforeach
    </div>
    <p class="help-block"><i class="fas fa-info-circle"></i> @lang('clinic.appt_hours_help')</p>
</section>
@endsection
