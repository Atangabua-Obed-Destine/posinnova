@extends('layouts.app')
@section('title', __('lang_v1.enter_results'))

@php
    $lr = $lab_request;
    $pd = optional($lr->patient)->patientDetails;
    $flags = ['' => '-', 'normal' => __('lang_v1.lab_flag_normal'), 'high' => __('lang_v1.lab_flag_high'), 'low' => __('lang_v1.lab_flag_low'), 'critical' => __('lang_v1.lab_flag_critical')];
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.enter_results')
        <small class="tw-text-gray-600">{{ $lr->ref_no }}</small>
    </h1>
</section>

<section class="content">
    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
        <span class="tw-text-lg tw-font-semibold">{{ optional($lr->patient)->name }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.file_number'):</strong> {{ optional($pd)->file_number ?: '-' }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.sex'):</strong> {{ optional($pd)->sex ?: '-' }}</span>
    </div>

    {!! Form::open(['url' => action([\App\Http\Controllers\LabRequestController::class, 'saveResult'], [$lr->id]), 'method' => 'post', 'id' => 'lab_result_form']) !!}

    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.test_results')])
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th style="width:22%;">@lang('lang_v1.test_name')</th>
                        <th style="width:14%;">@lang('lang_v1.normal_range')</th>
                        <th style="width:8%;">@lang('lang_v1.unit')</th>
                        <th style="width:22%;">@lang('lang_v1.result')</th>
                        <th style="width:14%;">@lang('lang_v1.flag')</th>
                        <th style="width:20%;">@lang('lang_v1.notes')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lr->lines as $line)
                        <tr>
                            <td><strong>{{ $line->test_name }}</strong></td>
                            <td>{{ $line->normal_range ?: '-' }}</td>
                            <td>{{ $line->unit ?: '-' }}</td>
                            <td>{!! Form::text("results[{$line->id}][value]", $line->result_value, ['class' => 'form-control input-sm', 'aria-label' => __('lang_v1.result')]) !!}</td>
                            <td>{!! Form::select("results[{$line->id}][flag]", $flags, $line->result_flag, ['class' => 'form-control input-sm', 'aria-label' => __('lang_v1.flag')]) !!}</td>
                            <td>{!! Form::text("results[{$line->id}][notes]", $line->result_notes, ['class' => 'form-control input-sm', 'aria-label' => __('lang_v1.notes')]) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12 text-center" style="margin-bottom:20px;">
            <button type="submit" name="action" value="save" class="tw-dw-btn tw-dw-btn-outline tw-font-bold">
                <i class="fas fa-save"></i> @lang('lang_v1.save_results')
            </button>
            @can('lab_result.validate')
                <button type="submit" name="action" value="validate" class="tw-dw-btn tw-bg-green-600 tw-text-white tw-border-none tw-font-bold">
                    <i class="fas fa-check-double"></i> @lang('lang_v1.save_and_validate')
                </button>
            @endcan
            <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lr->id]) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </div>

    {!! Form::close() !!}
</section>
@endsection
