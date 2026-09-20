@extends('layouts.app')
@section('title', __('lang_v1.view_lab_request'))

@php
    $lr = $lab_request;
    $pd = optional($lr->patient)->patientDetails;
    $flag_labels = ['normal' => 'label-success', 'high' => 'label-warning', 'low' => 'label-info', 'critical' => 'label-danger'];
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ optional($lr->patient)->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $lr->ref_no }}</span>
                    @lang('lang_v1.requested_by'): {{ optional($lr->requestedBy)->user_full_name ?? '-' }} ·
                    {{ @format_datetime($lr->requested_at) }}
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                @if(in_array($lr->status, ['pending','sample_collected','processing','resulted']) && auth()->user()->can('lab_result.enter'))
                    <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'enterResult'], [$lr->id]) }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                        <i class="fas fa-vial"></i> @lang('lang_v1.enter_results')
                    </a>
                @endif
                @if($lr->status == 'resulted' && auth()->user()->can('lab_result.validate'))
                    <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'validateResult'], [$lr->id]) }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                        <i class="fas fa-check-double"></i> @lang('lang_v1.validate_result')
                    </a>
                @endif
                @if(in_array($lr->status, ['resulted','validated']))
                    <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'print'], [$lr->id]) }}" target="_blank"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-print"></i> @lang('lang_v1.print_result')
                    </a>
                @endif
                <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('messages.back')
                </a>
            </div>
        </div>
        <div class="tw-mt-2">
            <span class="label {{ ['pending'=>'label-default','sample_collected'=>'label-info','processing'=>'label-warning','resulted'=>'label-primary','validated'=>'label-success'][$lr->status] ?? 'label-default' }}">
                {{ __('lang_v1.lab_status_' . $lr->status) }}
            </span>
            @if($lr->validatedBy)
                <small class="text-muted">· @lang('lang_v1.validated_by'): {{ $lr->validatedBy->user_full_name }}</small>
            @endif
        </div>
        @if($lr->clinical_notes)
            <p class="tw-mt-2 tw-text-sm tw-text-gray-600"><strong>@lang('lang_v1.clinical_notes'):</strong> {{ $lr->clinical_notes }}</p>
        @endif
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.test_results')])
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('lang_v1.test_name')</th>
                        <th>@lang('lang_v1.result')</th>
                        <th>@lang('lang_v1.normal_range')</th>
                        <th>@lang('lang_v1.unit')</th>
                        <th>@lang('lang_v1.flag')</th>
                        <th>@lang('lang_v1.notes')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lr->lines as $line)
                        <tr>
                            <td>{{ $line->test_name }}</td>
                            <td><strong>{{ $line->result_value ?: '-' }}</strong></td>
                            <td>{{ $line->normal_range ?: '-' }}</td>
                            <td>{{ $line->unit ?: '-' }}</td>
                            <td>
                                @if($line->result_flag)
                                    <span class="label {{ $flag_labels[$line->result_flag] ?? 'label-default' }}">{{ __('lang_v1.lab_flag_' . $line->result_flag) }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $line->result_notes ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="5" class="text-right">@lang('lang_v1.total_fee')</th>
                        <th><span class="display_currency" data-currency_symbol="true">{{ $lr->total_fee }}</span></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endcomponent

</section>
@endsection
