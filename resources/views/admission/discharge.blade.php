@extends('layouts.app')
@section('title', __('lang_v1.discharge'))

@php
    $a = $admission;
    $pd = optional($a->patient)->patientDetails;
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.discharge')
        <small class="tw-text-gray-600">{{ $a->ref_no }}</small>
    </h1>
</section>

<section class="content">
    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
        <span class="tw-text-lg tw-font-semibold">{{ optional($a->patient)->name }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.file_number'):</strong> {{ optional($pd)->file_number ?: '-' }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.ward'):</strong> {{ optional($a->ward)->name }} @if($a->bed)· {{ $a->bed->bed_number }}@endif</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.days_admitted'):</strong> {{ $a->days_admitted }}</span>
    </div>

    {{-- Outstanding due + optional collect-before-discharge (discharge does NOT require full payment) --}}
    <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3 tw-mb-4 tw-p-4 tw-rounded-xl tw-ring-1 {{ $patient_total_due > 0 ? 'tw-bg-amber-50 tw-ring-amber-200' : 'tw-bg-green-50 tw-ring-green-200' }}">
        <div>
            <span class="tw-text-sm tw-text-gray-600">@lang('lang_v1.outstanding_due')</span>
            <div class="tw-text-2xl tw-font-bold {{ $patient_total_due > 0 ? 'tw-text-red-600' : 'tw-text-green-600' }}">
                <span class="display_currency" data-currency_symbol="true">{{ $patient_total_due }}</span>
            </div>
            @if($patient_total_due > 0)
                <small class="tw-text-gray-500">@lang('lang_v1.discharge_due_note')</small>
            @endif
        </div>
        @if(auth()->user()->can('sell.payments') && $patient_total_due > 0)
            <a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$a->contact_id]) }}?type=sell"
                class="pay_sale_due tw-dw-btn tw-bg-green-600 tw-text-white tw-border-none">
                <i class="fas fa-money-bill-alt"></i> @lang('lang_v1.collect_payment')
            </a>
        @endif
    </div>

    {!! Form::open(['url' => action([\App\Http\Controllers\AdmissionController::class, 'discharge'], [$a->id]), 'method' => 'post', 'id' => 'discharge_form']) !!}

    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.discharge_details')])
        <div class="row">
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('discharge_date', __('lang_v1.discharge_date') . ':*') !!}
                    {!! Form::text('discharge_date', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control', 'required']) !!}
                </div>
            </div>
            <div class="col-sm-12">
                <div class="form-group">
                    {!! Form::label('discharge_notes', __('lang_v1.discharge_notes') . ':') !!}
                    {!! Form::textarea('discharge_notes', null, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('lang_v1.discharge_summary_placeholder')]) !!}
                </div>
            </div>
        </div>
        <p class="help-block">@lang('lang_v1.discharge_frees_bed_note')</p>
    @endcomponent

    <div class="row">
        <div class="col-md-12 text-center" style="margin-bottom:20px;">
            <button type="submit" class="tw-dw-btn tw-bg-green-600 tw-text-white tw-border-none tw-font-bold">
                <i class="fas fa-sign-out-alt"></i> @lang('lang_v1.confirm_discharge')
            </button>
            <a href="{{ action([\App\Http\Controllers\AdmissionController::class, 'show'], [$a->id]) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </div>

    {!! Form::close() !!}

    {{-- Pay-due modal (reuse customer payment flow) --}}
    <div class="modal fade pay_contact_due_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
</section>
@endsection

@section('javascript')
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
    <script>
        $('#discharge_form').on('submit', function (e) {
            var due = {{ (float) $patient_total_due }};
            if (due > 0) {
                e.preventDefault();
                swal({
                    title: '{{ __('lang_v1.discharge_with_balance_title') }}',
                    text: '{{ __('lang_v1.discharge_with_balance_note') }}',
                    icon: 'warning', buttons: true, dangerMode: true
                }).then((ok) => { if (ok) { e.target.submit(); } });
            }
        });
    </script>
@endsection
