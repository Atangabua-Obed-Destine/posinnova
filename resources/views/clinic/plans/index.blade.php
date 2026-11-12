@extends('layouts.app')
@section('title', __('clinic.monthly_plans'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.monthly_plans')
        <small class="tw-text-gray-600">
            @if ($settings['plan_billing_anchor'] == 'fixed')
                {{ __('clinic.plans_bill_fixed', ['day' => $settings['plan_fixed_day']]) }}
            @else
                @lang('clinic.plans_bill_start')
            @endif
        </small>
    </h1>
</section>

<section class="content">
    <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        @if ($has_plans)
            <a href="{{ action([\App\Http\Controllers\ClinicPlanController::class, 'create']) }}" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                <i class="fas fa-plus"></i> @lang('clinic.plan_add_patient')
            </a>
        @else
            <span class="text-muted"><i class="fas fa-info-circle"></i> @lang('clinic.plans_need_package')
                @can('clinic_package.manage')<a href="{{ action([\App\Http\Controllers\ClinicPackageController::class, 'create']) }}">@lang('clinic.add_package')</a>@endcan
            </span>
        @endif
        <div class="btn-group">
            <a href="?show=active" class="btn btn-sm {{ $show == 'active' ? 'btn-primary' : 'btn-default' }}">@lang('clinic.plan_status_active')</a>
            <a href="?show=cancelled" class="btn btn-sm {{ $show == 'cancelled' ? 'btn-primary' : 'btn-default' }}">@lang('clinic.plan_status_cancelled')</a>
        </div>
        @if ($due_now > 0)
            <form method="POST" action="{{ action([\App\Http\Controllers\ClinicPlanController::class, 'billNow']) }}" style="margin-left:auto;" data-record-confirm="plan_bill">
                @csrf
                <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-warning"><i class="fas fa-file-invoice"></i> {{ trans_choice('clinic.plan_bill_due_now', $due_now, ['n' => $due_now]) }}</button>
            </form>
        @endif
    </div>

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>@lang('lang_v1.patient')</th>
                        <th>@lang('clinic.plan')</th>
                        <th>@lang('clinic.plan_this_month')</th>
                        <th>@lang('clinic.plan_visits_used')</th>
                        <th>{{ $show == 'active' ? __('clinic.plan_next_bill') : __('clinic.plan_cancelled_on') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subs as $s)
                        @php $cp = $s->current_period; @endphp
                        <tr>
                            <td><strong>{{ optional($s->patient)->name }}</strong>
                                @if (optional(optional($s->patient)->patientDetails)->file_number)<br><small class="text-muted">{{ $s->patient->patientDetails->file_number }}</small>@endif
                            </td>
                            <td>{{ optional($s->package)->name }}<br><small class="text-muted">{{ __('clinic.plan_since', ['date' => @format_date($s->start_date)]) }}</small></td>
                            <td>
                                @if ($cp)
                                    {{ @format_date($cp->period_start) }} – {{ @format_date($cp->period_end) }}<br>
                                    <span class="label {{ $s->current_paid ? 'label-success' : 'label-danger' }}">{{ $s->current_paid ? __('lang_v1.paid') : __('lang_v1.due') }}</span>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if ($cp)
                                    {{ $s->current_used }} / {{ $cp->visits_allowed ?? '∞' }}
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $show == 'active' ? @format_date($s->next_bill_date) : ($s->cancelled_at ? @format_date($s->cancelled_at) : '-') }}</td>
                            <td class="text-right">
                                <a href="{{ action([\App\Http\Controllers\ClinicPlanController::class, 'show'], [$s->id]) }}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline"><i class="fas fa-eye"></i> @lang('messages.view')</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">@lang('clinic.plans_none')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent
    <p class="help-block"><i class="fas fa-clock"></i> @lang('clinic.plans_auto_note')</p>
</section>
@endsection
