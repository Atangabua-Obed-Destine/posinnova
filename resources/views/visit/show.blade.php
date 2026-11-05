@extends('layouts.app')
@section('title', __('lang_v1.view_visit'))

@php
    $pd = optional($visit->patient)->patientDetails;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">
                    {{ optional($visit->patient)->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $visit->ref_no }}</span>
                    {{ __('lang_v1.visit_type_' . $visit->type) }} ·
                    {{ @format_date($visit->visit_date) }} ·
                    <span class="label {{ $visit->status == 'open' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.visit_status_' . $visit->status) }}</span>
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                @if($visit->status == 'open')
                    @can('consultation.create')
                        @if ($visit->queue_status == 'waiting' && auth()->user()->can('visit.receive_patients'))
                            {{-- queued patients are called in through the queue (pay-first gate, timings) --}}
                            <form method="POST" action="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'start'], [$visit->id]) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" {{ $pay_blocks ? 'disabled' : '' }}>
                                    <i class="fas fa-stethoscope"></i> @lang('clinic.call_in')
                                </button>
                            </form>
                        @elseif (! $pay_blocks)
                            <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'create']) }}?visit_id={{ $visit->id }}"
                                class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                                <i class="fas fa-stethoscope"></i> @lang('lang_v1.new_consultation')
                            </a>
                        @endif
                    @endcan
                    @can('visit.close')
                        <form method="POST" action="{{ action([\App\Http\Controllers\VisitController::class, 'close'], [$visit->id]) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                                <i class="fas fa-check-circle"></i> @lang('lang_v1.close_visit')
                            </button>
                        </form>
                    @endcan
                @endif
                <a href="{{ action([\App\Http\Controllers\VisitController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('messages.back')
                </a>
            </div>
        </div>
    </div>

    {{-- PosInnova clinic: check-in (queue number, doctor, package, invoice) --}}
    @if ($visit->queue_no || $visit->fee_transaction_id)
        @php
            $fee = $visit->feeTransaction;
            $fee_paid = $fee ? (float) $fee->payment_lines()->sum('amount') : 0;
            $pay_cls = ['paid' => 'label-success', 'partial' => 'label-warning', 'due' => 'label-danger'][$pay_state] ?? 'label-default';
        @endphp
        <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4" id="checkin_panel">
            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-6">
                @if ($visit->queue_no)
                    <div class="tw-text-center" style="min-width:110px;">
                        <div class="tw-text-xs tw-uppercase tw-text-gray-500 tw-font-semibold">@lang('clinic.queue_no')</div>
                        <div class="tw-font-bold" style="font-size:44px; line-height:1; color:#1d4ed8;" id="queue_no_big">{{ $visit->queue_no }}</div>
                        @if ($visit->queue_status)
                            <span class="label {{ ['waiting' => 'label-info', 'with_doctor' => 'label-warning', 'seen' => 'label-success', 'no_show' => 'label-default', 'cancelled' => 'label-danger'][$visit->queue_status] ?? 'label-default' }}">{{ __('clinic.queue_' . $visit->queue_status) }}</span>
                        @endif
                    </div>
                @endif
                <div style="min-width:180px;">
                    <div class="tw-text-xs tw-uppercase tw-text-gray-500 tw-font-semibold">@lang('clinic.doctor')</div>
                    <div class="tw-font-semibold tw-text-gray-900">{{ optional($visit->doctor)->user_full_name ?: '-' }}</div>
                    <div class="tw-text-xs tw-uppercase tw-text-gray-500 tw-font-semibold tw-mt-2">@lang('clinic.package')</div>
                    <div class="tw-font-semibold tw-text-gray-900">{{ optional($visit->package)->name ?: '-' }}</div>
                </div>
                @if ($fee)
                    <div style="min-width:200px;">
                        <div class="tw-text-xs tw-uppercase tw-text-gray-500 tw-font-semibold">@lang('clinic.checkin_invoice')</div>
                        <div class="tw-font-semibold tw-text-gray-900">{{ $fee->invoice_no }}
                            <span class="label {{ $pay_cls }}">{{ __('lang_v1.' . ($pay_state == 'none' ? 'paid' : $pay_state)) }}</span></div>
                        <div class="tw-text-sm tw-text-gray-700">
                            @lang('sale.total'): <strong><span class="display_currency" data-currency_symbol="true">{{ $fee->final_total }}</span></strong>
                            · @lang('clinic.paid_so_far'): <span class="display_currency" data-currency_symbol="true">{{ $fee_paid }}</span>
                        </div>
                    </div>
                @elseif ($visit->subscription_period_id)
                    @php $plan_period = $visit->subscriptionPeriod; @endphp
                    <div style="min-width:200px;">
                        <div class="tw-text-xs tw-uppercase tw-text-gray-500 tw-font-semibold">@lang('clinic.checkin_invoice')</div>
                        <div class="tw-font-semibold tw-text-green-700"><i class="fas fa-id-card"></i> {{ __('clinic.covered_by_plan', ['plan' => optional($visit->package)->name]) }}</div>
                        @if ($plan_period)
                            <div class="tw-text-sm tw-text-gray-700">{{ @format_date($plan_period->period_start) }} – {{ @format_date($plan_period->period_end) }}</div>
                        @endif
                    </div>
                @endif
                <div class="tw-flex tw-flex-wrap tw-gap-2 tw-ml-auto">
                    @if ($fee && $pay_state != 'paid')
                        @can('sell.payments')
                            <a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$fee->id]) }}?post_to_register=1" class="add_payment_modal tw-dw-btn tw-dw-btn-sm tw-dw-btn-success tw-text-white">
                                <i class="fas fa-money-bill-alt"></i> @lang('clinic.take_payment')
                            </a>
                        @endcan
                    @endif
                    @if ($fee)
                        <a href="#" data-href="{{ route('sell.printInvoice', [$fee->id]) }}" class="print-invoice tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                            <i class="fas fa-print"></i> @lang('clinic.print_receipt')
                        </a>
                    @endif
                    @can('patient.view_bill')
                        <a href="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$visit->contact_id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                            <i class="fas fa-wallet"></i> @lang('clinic.patient_account')
                        </a>
                    @endcan
                    @if ($visit->status == 'open' && in_array($visit->queue_status, ['waiting', 'no_show']))
                        @if ($pay_blocks && $visit->queue_status == 'waiting')
                            @can('visit.override_pay_first')
                                <form method="POST" action="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'letThrough'], [$visit->id]) }}" style="display:inline;" class="js-confirm" data-confirm="{{ __('clinic.let_through_confirm') }}">
                                    @csrf
                                    <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-warning"><i class="fas fa-door-open"></i> @lang('clinic.let_through')</button>
                                </form>
                            @endcan
                        @endif
                        @can('visit.create')
                            <form method="POST" action="{{ action([\App\Http\Controllers\VisitController::class, 'cancel'], [$visit->id]) }}" style="display:inline;" class="js-confirm" data-confirm="{{ __('clinic.cancel_checkin_confirm') }}">
                                @csrf
                                <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-error"><i class="fas fa-ban"></i> @lang('clinic.cancel_checkin')</button>
                            </form>
                        @endcan
                    @endif
                    @can('visit.create')
                        <a href="{{ action([\App\Http\Controllers\VisitController::class, 'create']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-primary">
                            <i class="fas fa-plus"></i> @lang('clinic.check_in_next')
                        </a>
                    @endcan
                </div>
            </div>
            @if ($pay_blocks)
                <div class="callout callout-warning" style="margin:12px 0 0;">
                    <i class="fas fa-lock"></i> @lang('clinic.pay_first_blocked')
                </div>
            @elseif ($visit->pay_override_at)
                <p class="text-muted" style="margin:10px 0 0;"><i class="fas fa-door-open"></i>
                    {{ __('clinic.let_through_by', ['user' => optional(\App\User::find($visit->pay_override_by))->user_full_name ?: '-', 'date' => app(\App\Utils\Util::class)->format_date($visit->pay_override_at, true)]) }}</p>
            @endif
        </div>
    @endif

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.consultations')])
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('purchase.ref_no')</th>
                        <th>@lang('lang_v1.consultation_type')</th>
                        <th>@lang('lang_v1.diagnosis')</th>
                        <th>@lang('lang_v1.prescriptions')</th>
                        <th>@lang('lang_v1.consulted_by')</th>
                        <th>@lang('lang_v1.status')</th>
                        <th>@lang('lang_v1.date')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consultations as $c)
                        <tr>
                            <td>{{ $c->ref_no }}</td>
                            <td>{{ __('lang_v1.consultation_type_' . $c->consultation_type) }}{{ $c->specialty ? ' · ' . $c->specialty : '' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($c->diagnosis, 60) ?: '-' }}</td>
                            <td>{{ $c->prescriptions->count() }}</td>
                            <td>{{ optional($c->consultedBy)->user_full_name ?? '-' }}</td>
                            <td><span class="label {{ $c->status == 'final' ? 'label-success' : 'label-warning' }}">{{ __('lang_v1.consultation_status_' . $c->status) }}</span></td>
                            <td>{{ @format_datetime($c->created_at) }}</td>
                            <td>
                                <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'show'], [$c->id]) }}" class="btn btn-xs btn-info">
                                    <i class="fas fa-eye"></i> @lang('messages.view')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent

</section>

<div class="modal fade payment_modal" tabindex="-1" role="dialog"></div>
<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog"></div>
<section class="invoice print_section" id="receipt_section"></section>
@endsection

@section('javascript')
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
    <script>$(document).on('submit', 'form.js-confirm', function () { return confirm($(this).data('confirm')); });</script>
@endsection
