@extends('layouts.app')
@section('title', __('clinic.plan') . ' · ' . optional($sub->patient)->name)

@section('content')
@php $pd = optional($sub->patient)->patientDetails; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ optional($sub->patient)->name }}
        <small class="tw-text-gray-600">@if (optional($pd)->file_number){{ $pd->file_number }} · @endif{{ optional($sub->package)->name }}</small>
    </h1>
</section>

<section class="content">
    <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ action([\App\Http\Controllers\ClinicPlanController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-arrow-left"></i> @lang('clinic.monthly_plans')</a>
        @can('patient.view_bill')
            <a href="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$sub->contact_id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-wallet"></i> @lang('clinic.patient_account')</a>
        @endcan
    </div>

    <div class="row">
        <div class="col-md-4">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('clinic.plan')])
                <p><strong>{{ optional($sub->package)->name }}</strong>
                    <span class="label {{ $sub->status == 'active' ? 'label-success' : 'label-default' }}">{{ __('clinic.plan_status_' . $sub->status) }}</span></p>
                <p class="text-muted" style="white-space:pre-line;">{{ optional($sub->package)->description }}</p>
                <table class="table table-condensed" style="margin-bottom:0;">
                    <tr><th>@lang('clinic.plan_start_date')</th><td>{{ @format_date($sub->start_date) }}</td></tr>
                    @if ($sub->status == 'active')
                        <tr><th>@lang('clinic.plan_next_bill')</th><td>{{ @format_date($sub->next_bill_date) }}</td></tr>
                    @else
                        <tr><th>@lang('clinic.plan_cancelled_on')</th><td>{{ $sub->cancelled_at ? @format_datetime($sub->cancelled_at) : '-' }} · {{ optional($sub->cancelledBy)->user_full_name }}
                            @if ($sub->cancel_reason)<br><small>{{ $sub->cancel_reason }}</small>@endif</td></tr>
                    @endif
                    <tr><th>@lang('clinic.plan_today')</th><td>{{ $coverage['message'] ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.added_by')</th><td>{{ optional($sub->createdBy)->user_full_name }}</td></tr>
                </table>
                @if ($sub->notes)<p style="margin-top:8px;"><em>{{ $sub->notes }}</em></p>@endif
            @endcomponent

            @if ($sub->status == 'active')
                @component('components.widget', ['class' => 'box-danger', 'title' => __('clinic.plan_cancel')])
                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicPlanController::class, 'cancel'], [$sub->id]) }}" class="js-confirm" data-confirm="{{ __('clinic.plan_cancel_confirm') }}">
                        @csrf
                        <div class="form-group">
                            <input type="text" name="reason" class="form-control" maxlength="191" placeholder="{{ __('clinic.plan_cancel_reason') }}">
                        </div>
                        @if ($can_void)
                            <div class="checkbox"><label><input type="checkbox" name="void_current" value="1"> @lang('clinic.plan_void_current')</label></div>
                        @endif
                        <p class="help-block">@lang('clinic.plan_cancel_help')</p>
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-error tw-text-white"><i class="fas fa-ban"></i> @lang('clinic.plan_cancel')</button>
                    </form>
                @endcomponent
            @endif
        </div>

        <div class="col-md-8">
            @component('components.widget', ['class' => 'box-default', 'title' => __('clinic.plan_months')])
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead><tr><th>@lang('clinic.plan_period')</th><th>@lang('sale.invoice_no')</th><th class="text-right">@lang('sale.amount')</th><th>@lang('sale.payment_status')</th><th>@lang('clinic.plan_visits_used')</th></tr></thead>
                        <tbody>
                            @forelse ($periods as $p)
                                <tr>
                                    <td>{{ @format_date($p->period_start) }} – {{ @format_date($p->period_end) }}</td>
                                    <td>@if ($p->invoice)<a href="#" class="btn-modal" data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$p->transaction_id]) }}" data-container=".view_modal">{{ $p->invoice->invoice_no }}</a>@else - @endif</td>
                                    <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $p->amount }}</span></td>
                                    <td><span class="label {{ $p->paid ? 'label-success' : 'label-danger' }}">{{ $p->paid ? __('lang_v1.paid') : __('lang_v1.' . (optional($p->invoice)->payment_status ?: 'due')) }}</span></td>
                                    <td>{{ $p->used }} / {{ $p->visits_allowed ?? '∞' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-muted text-center">{{ __('clinic.plan_subscribed_later', ['date' => @format_date($sub->start_date)]) }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(function () { $(document).on('submit', 'form.js-confirm', function () { return confirm($(this).data('confirm')); }); });
</script>
@endsection
