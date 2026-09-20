@extends('layouts.app')
@section('title', __('lang_v1.enter_report'))

@php
    $ir = $imaging_request;
    $pd = optional($ir->patient)->patientDetails;
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.enter_report')
        <small class="tw-text-gray-600">{{ $ir->ref_no }} · {{ $ir->study_name }}</small>
    </h1>
</section>

<section class="content">
    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
        <span class="tw-text-lg tw-font-semibold">{{ optional($ir->patient)->name }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.file_number'):</strong> {{ optional($pd)->file_number ?: '-' }}</span>
        @if($ir->clinical_notes)
            <p class="tw-mt-2 tw-text-sm tw-text-gray-600"><strong>@lang('lang_v1.clinical_notes'):</strong> {{ $ir->clinical_notes }}</p>
        @endif
    </div>

    {!! Form::open(['url' => action([\App\Http\Controllers\ImagingController::class, 'saveReport'], [$ir->id]), 'method' => 'post', 'id' => 'imaging_report_form']) !!}

    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.radiologist_report')])
        <div class="form-group">
            {!! Form::label('report', __('lang_v1.report') . ':*') !!}
            {!! Form::textarea('report', $ir->report, ['class' => 'form-control', 'rows' => 10, 'required', 'placeholder' => __('lang_v1.report_findings_placeholder')]) !!}
        </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12 text-center" style="margin-bottom:20px;">
            <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">
                <i class="fas fa-check"></i> @lang('lang_v1.save_report')
            </button>
            <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'show'], [$ir->id]) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </div>

    {!! Form::close() !!}
</section>
@endsection
