@extends('layouts.app')
@section('title', __('lang_v1.view_imaging_request'))

@php
    $ir = $imaging_request;
    $pd = optional($ir->patient)->patientDetails;
@endphp

@section('content')
<section class="content">

    {{-- PosInnova: is the fee paid? (and who chose to go on before it was) --}}
    @include('clinic.partials.request_payment', ['pay' => $pay, 'req' => $ir, 'ack' => false])

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ optional($ir->patient)->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $ir->ref_no }}</span>
                    <strong>{{ $ir->study_name }}</strong> ·
                    <span class="label {{ $ir->status == 'done' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.imaging_status_' . $ir->status) }}</span>
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                @if($ir->status == 'pending' && auth()->user()->can('imaging_result.enter'))
                    <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'enterReport'], [$ir->id]) }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                        <i class="fas fa-x-ray"></i> @lang('lang_v1.enter_report')
                    </a>
                @endif
                <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('messages.back')
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.request_details')])
                <table class="table table-bordered">
                    <tr><th width="45%">@lang('lang_v1.study')</th><td>{{ $ir->study_name }}</td></tr>
                    <tr><th>@lang('lang_v1.modality')</th><td>{{ optional($ir->catalogue)->modality ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.fee')</th><td><span class="display_currency" data-currency_symbol="true">{{ $ir->fee }}</span></td></tr>
                    <tr><th>@lang('lang_v1.requested_by')</th><td>{{ optional($ir->requestedBy)->user_full_name ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.requested_at')</th><td>{{ @format_datetime($ir->requested_at) }}</td></tr>
                    <tr><th>@lang('lang_v1.radiology_location')</th><td>{{ optional($ir->location)->name ?: '-' }}</td></tr>
                </table>
                @if($ir->clinical_notes)
                    <p><strong>@lang('lang_v1.clinical_notes'):</strong> {{ $ir->clinical_notes }}</p>
                @endif
            @endcomponent
        </div>
        <div class="col-md-7">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.report')])
                @if($ir->status == 'done')
                    <div style="white-space: pre-wrap;">{{ $ir->report }}</div>
                    <hr>
                    <p class="text-muted">
                        @lang('lang_v1.reported_by'): {{ optional($ir->reportedBy)->user_full_name ?? '-' }} ·
                        {{ @format_datetime($ir->reported_at) }}
                    </p>
                @else
                    <p class="text-muted text-center" style="padding:20px 0;">@lang('lang_v1.report_pending')</p>
                @endif
            @endcomponent
        </div>
    </div>

</section>
@endsection
