@php
    $pay_cls = ['paid' => 'label-success', 'partial' => 'label-warning', 'due' => 'label-danger'];
    $show_doctor = $doctor_id === 'all';
@endphp

@component('components.widget', ['class' => 'box-primary', 'title' => __('clinic.waiting_now') . ' (' . $active->count() . ')'])
    @forelse ($active as $v)
        @php $pd = optional($v->patient)->patientDetails; @endphp
        <div class="dq-row {{ $v->queue_status == 'with_doctor' ? 'is-with' : '' }}" data-visit="{{ $v->id }}">
            <div class="dq-no">{{ $v->queue_no }}</div>
            <div class="dq-main">
                <div class="dq-name">{{ optional($v->patient)->name }}
                    @if (optional($pd)->file_number)<small class="text-muted">· {{ $pd->file_number }}</small>@endif
                    @if ($v->appointment)<span class="label label-info" style="margin-left:4px;"><i class="fas fa-calendar-check"></i> {{ __('clinic.appt_booked_at', ['time' => $v->appointment->start]) }}</span>@endif
                </div>
                <div class="dq-meta">
                    {{ optional($v->package)->name ?: '-' }}
                    @if ($show_doctor) · <i class="fas fa-user-md"></i> {{ optional($v->doctor)->user_full_name ?: '-' }}@endif
                    · @lang('clinic.checked_in_at') {{ optional($v->checked_in_at)->format('H:i') }}
                    @if ($v->checked_in_at)
                        @php $waited = $v->checked_in_at->diffInMinutes(now()); @endphp
                        ({{ $waited < 60 ? __('clinic.waited_min', ['n' => $waited]) : __('clinic.rep_h_min', ['h' => intdiv($waited, 60), 'm' => sprintf('%02d', $waited % 60)]) }})
                    @endif
                    @if ($v->notes) · <em>{{ $v->notes }}</em>@endif
                </div>
                {{-- vitals from the front desk, so the doctor sees them before calling the patient in --}}
                <div class="dq-meta dq-vitals" style="margin-top:2px;">
                    @if ($v->hasVitals())
                        <i class="fas fa-heartbeat" style="color:#dc2626;"></i> <span style="color:#0f172a;">{{ $v->vitalsSummary() }}</span>
                        @if ($v->vitals_note) · <em>{{ $v->vitals_note }}</em>@endif
                    @else
                        <i class="fas fa-heartbeat" style="color:#cbd5e1;"></i> @lang('clinic.vit_not_taken')
                    @endif
                    @if ($v->covered_by_visit_id) · <span class="label label-info"><i class="fas fa-redo"></i> @lang('clinic.cover_label')</span>@endif
                </div>
                <div style="margin-top:4px;">
                    @if ($v->queue_status == 'with_doctor')
                        <span class="label label-warning">@lang('clinic.queue_with_doctor')</span>
                    @endif
                    @if ($v->pay_state != 'none')
                        <span class="label {{ $pay_cls[$v->pay_state] ?? 'label-default' }}">{{ __('lang_v1.' . $v->pay_state) }}</span>
                    @endif
                    @if ($v->pay_blocks)
                        <span class="text-warning"><i class="fas fa-lock"></i> @lang('clinic.waiting_for_payment')</span>
                    @elseif ($v->pay_override_at)
                        <span class="text-muted"><i class="fas fa-door-open"></i> @lang('clinic.let_through_short')</span>
                    @endif
                </div>
            </div>
            <div class="dq-actions">
                @if (! $v->pay_blocks && auth()->user()->can('consultation.create'))
                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'start'], [$v->id]) }}">
                        @csrf
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                            <i class="fas fa-stethoscope"></i> {{ $v->queue_status == 'with_doctor' ? __('clinic.continue') : __('clinic.call_in') }}
                        </button>
                    </form>
                @endif
                @if ($v->pay_blocks && auth()->user()->can('visit.override_pay_first'))
                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'letThrough'], [$v->id]) }}" class="js-confirm" data-confirm="{{ __('clinic.let_through_confirm') }}">
                        @csrf
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-warning"><i class="fas fa-door-open"></i> @lang('clinic.let_through')</button>
                    </form>
                @endif
                @if ($v->queue_status == 'waiting')
                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'noShow'], [$v->id]) }}" class="js-confirm" data-confirm="{{ __('clinic.no_show_confirm') }}">
                        @csrf
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">@lang('clinic.no_show')</button>
                    </form>
                @endif
                <a href="{{ action([\App\Http\Controllers\PatientController::class, 'show'], [$v->contact_id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" title="@lang('clinic.patient_file')"><i class="fas fa-folder-open"></i></a>
            </div>
        </div>
    @empty
        <div class="dq-empty"><i class="fas fa-mug-hot fa-2x"></i><br>@lang('clinic.queue_empty')</div>
    @endforelse
@endcomponent

@if ($done->isNotEmpty())
    @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.done_today') . ' (' . $done->count() . ')'])
        <table class="table table-condensed" style="margin:0;">
            <tbody>
                @foreach ($done as $v)
                    <tr>
                        <td style="width:50px;"><strong>{{ $v->queue_no }}</strong></td>
                        <td>{{ optional($v->patient)->name }}</td>
                        <td>{{ optional($v->package)->name ?: '-' }}</td>
                        @if ($show_doctor)<td>{{ optional($v->doctor)->user_full_name ?: '-' }}</td>@endif
                        <td><span class="label {{ ['seen' => 'label-success', 'no_show' => 'label-default', 'cancelled' => 'label-danger'][$v->queue_status] ?? 'label-default' }}">{{ __('clinic.queue_' . ($v->queue_status ?: 'seen')) }}</span></td>
                        <td>{{ $v->seen_at ? $v->seen_at->format('H:i') : '' }}</td>
                        <td class="text-right">
                            @if ($v->queue_status == 'no_show' && $v->status == 'open')
                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'requeue'], [$v->id]) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline">@lang('clinic.requeue')</button>
                                </form>
                            @endif
                            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$v->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline"><i class="fas fa-eye"></i></a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endcomponent
@endif
