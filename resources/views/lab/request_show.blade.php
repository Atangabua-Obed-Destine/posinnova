@extends('layouts.app')
@section('title', __('lang_v1.view_lab_request'))

@php
    $lr = $lab_request;
    $pd = optional($lr->patient)->patientDetails;
    $flag_labels = ['normal' => 'label-success', 'high' => 'label-warning', 'low' => 'label-info', 'critical' => 'label-danger'];
@endphp

@section('content')
<section class="content">

    {{-- PosInnova: is the fee paid? (and who chose to go on before it was) --}}
    @include('clinic.partials.request_payment', ['pay' => $pay, 'req' => $lr, 'ack' => false])

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
                    <form method="POST" action="{{ action([\App\Http\Controllers\LabRequestController::class, 'validateResult'], [$lr->id]) }}" style="display:inline;">
                        @csrf
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                            <i class="fas fa-check-double"></i> @lang('lang_v1.validate_result')
                        </button>
                    </form>
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
                            <td>
                                <strong>{{ $line->result_value ?: '-' }}</strong>
                                @if (($amendments ?? collect())->where('lab_request_line_id', $line->id)->count())
                                    <span class="label label-warning">@lang('clinic.amended')</span>
                                @endif
                            </td>
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

    {{-- PosInnova: a validated result is locked; corrections are amendments
         that keep the original value, the reason, who and when. --}}
    @if ($lr->status === 'validated')
        <div class="callout callout-info" style="margin-bottom:15px;">
            <i class="fas fa-lock"></i> @lang('clinic.result_locked')
        </div>
        @if (auth()->user()->can('lab_result.amend'))
            @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.amend_result')])
                {!! Form::open(['url' => action([\App\Http\Controllers\LabRequestController::class, 'amendResult'], [$lr->id]), 'method' => 'post', 'id' => 'lab_amend_form']) !!}
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('line_id', __('clinic.amend_test') . ':*') !!}
                            {!! Form::select('line_id', $lr->lines->pluck('test_name', 'id'), null, ['class' => 'form-control', 'required']) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('new_value', __('clinic.amend_new_value') . ':*') !!}
                            {!! Form::text('new_value', null, ['class' => 'form-control', 'required', 'maxlength' => 191]) !!}
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            {!! Form::label('new_flag', __('clinic.amend_new_flag') . ':') !!}
                            {!! Form::select('new_flag', ['' => '-', 'normal' => __('lang_v1.lab_flag_normal'), 'high' => __('lang_v1.lab_flag_high'), 'low' => __('lang_v1.lab_flag_low'), 'critical' => __('lang_v1.lab_flag_critical')], null, ['class' => 'form-control']) !!}
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            {!! Form::label('reason', __('clinic.amend_reason') . ':*') !!}
                            {!! Form::text('reason', null, ['class' => 'form-control', 'required']) !!}
                        </div>
                    </div>
                </div>
                <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">@lang('clinic.amend_save')</button>
                {!! Form::close() !!}
            @endcomponent
        @endif
    @endif

    @if (($amendments ?? collect())->count())
        @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.amendments')])
            <ul class="list-unstyled" style="margin:0;">
                @foreach ($amendments as $am)
                    <li style="padding:6px 0; border-bottom:1px solid #eee;">
                        <strong>{{ __('clinic.amended_from_to', ['test' => $am->test_name, 'from' => $am->old_value ?? '-', 'to' => $am->new_value]) }}</strong>
                        <span class="text-muted">· {{ __('clinic.amended_by_on', ['user' => $am->amended_by_name ?: '-', 'date' => app(\App\Utils\Util::class)->format_date($am->created_at, true)]) }}</span>
                        <br>{{ $am->reason }}
                    </li>
                @endforeach
            </ul>
        @endcomponent
    @endif

</section>
@endsection
