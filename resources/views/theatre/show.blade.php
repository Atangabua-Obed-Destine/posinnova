@extends('layouts.app')
@section('title', __('lang_v1.theatre_booking'))

@php
    $t = $booking;
    $pd = optional($t->patient)->patientDetails;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ optional($t->patient)->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $t->ref_no }}</span>
                    <strong>{{ $t->procedure_name }}</strong> ·
                    <span class="label {{ ['scheduled'=>'label-info','in_progress'=>'label-warning','completed'=>'label-success','cancelled'=>'label-danger'][$t->status] ?? 'label-default' }}">{{ __('lang_v1.theatre_status_' . $t->status) }}</span> ·
                    {{ @format_datetime($t->scheduled_at) }}
                </p>
            </div>
            <a href="{{ action([\App\Http\Controllers\TheatreController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                <i class="fa fa-arrow-left"></i> @lang('messages.back')
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.booking_details')])
                <table class="table table-bordered">
                    <tr><th width="45%">@lang('lang_v1.procedure')</th><td>{{ $t->procedure_name }}</td></tr>
                    <tr><th>@lang('lang_v1.surgeon')</th><td>{{ optional($t->surgeon)->user_full_name ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.anaesthetist')</th><td>{{ optional($t->anaesthetist)->user_full_name ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.scheduled_at')</th><td>{{ @format_datetime($t->scheduled_at) }}</td></tr>
                    <tr><th>@lang('lang_v1.started_at')</th><td>{{ $t->started_at ? @format_datetime($t->started_at) : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.ended_at')</th><td>{{ $t->ended_at ? @format_datetime($t->ended_at) : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.procedure_fee')</th><td><span class="display_currency" data-currency_symbol="true">{{ $t->fee }}</span></td></tr>
                    <tr><th>@lang('lang_v1.anaesthesia_fee')</th><td><span class="display_currency" data-currency_symbol="true">{{ $t->anaesthesia_fee }}</span></td></tr>
                    <tr><th>@lang('lang_v1.total_fee')</th><td><strong><span class="display_currency" data-currency_symbol="true">{{ $t->total_fee }}</span></strong> @if($t->fee_charged)<span class="label label-success">@lang('lang_v1.billed')</span>@endif</td></tr>
                </table>
            @endcomponent
        </div>
        <div class="col-md-7">
            @if(in_array($t->status, ['scheduled', 'in_progress']) && auth()->user()->can('theatre.record_procedure'))
                @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.record_procedure')])
                    {!! Form::open(['url' => action([\App\Http\Controllers\TheatreController::class, 'record'], [$t->id]), 'method' => 'post']) !!}
                    <div class="form-group">
                        {!! Form::label('preop_notes', __('lang_v1.preop_notes') . ':') !!}
                        {!! Form::textarea('preop_notes', $t->preop_notes, ['class' => 'form-control', 'rows' => 2]) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('intraop_notes', __('lang_v1.intraop_notes') . ':') !!}
                        {!! Form::textarea('intraop_notes', $t->intraop_notes, ['class' => 'form-control', 'rows' => 3]) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('postop_notes', __('lang_v1.postop_notes') . ':') !!}
                        {!! Form::textarea('postop_notes', $t->postop_notes, ['class' => 'form-control', 'rows' => 2]) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('status', __('lang_v1.update_status') . ':') !!}
                        {!! Form::select('status', ['scheduled' => __('lang_v1.theatre_status_scheduled'), 'in_progress' => __('lang_v1.theatre_status_in_progress'), 'completed' => __('lang_v1.theatre_status_completed'), 'cancelled' => __('lang_v1.theatre_status_cancelled')], $t->status, ['class' => 'form-control']) !!}
                        <p class="help-block">@lang('lang_v1.theatre_complete_bills_note')</p>
                    </div>
                    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
                    {!! Form::close() !!}
                @endcomponent
            @else
                @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.operative_notes')])
                    <p><strong>@lang('lang_v1.preop_notes'):</strong><br>{{ $t->preop_notes ?: '-' }}</p>
                    <p><strong>@lang('lang_v1.intraop_notes'):</strong><br>{{ $t->intraop_notes ?: '-' }}</p>
                    <p><strong>@lang('lang_v1.postop_notes'):</strong><br>{{ $t->postop_notes ?: '-' }}</p>
                @endcomponent
            @endif
        </div>
    </div>

</section>
@endsection
