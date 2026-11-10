@extends('layouts.app')
@section('title', __('lang_v1.receive_body'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.receive_body')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\MorgueController::class, 'store']), 'method' => 'post', 'id' => 'morgue_form']) !!}
        @if($preselected_patient) {!! Form::hidden('contact_id', $preselected_patient->id) !!} @endif

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.deceased_details')])
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('source', __('lang_v1.source') . ':*') !!}
                        {!! Form::select('source', ['hospital' => __('lang_v1.source_hospital'), 'external' => __('lang_v1.source_external')], $preselected_patient ? 'hospital' : 'external', ['class' => 'form-control select2', 'required']) !!}
                    </div>
                </div>
                <div class="col-sm-5">
                    <div class="form-group">
                        {!! Form::label('deceased_name', __('lang_v1.deceased_name') . ':*') !!}
                        {!! Form::text('deceased_name', $preselected_patient->name ?? null, ['class' => 'form-control', 'required', 'maxlength' => 191]) !!}
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        {!! Form::label('deceased_age', __('lang_v1.age') . ':') !!}
                        {!! Form::number('deceased_age', null, ['class' => 'form-control', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        {!! Form::label('deceased_sex', __('lang_v1.sex') . ':') !!}
                        {!! Form::select('deceased_sex', ['' => __('messages.please_select'), 'M' => __('lang_v1.male'), 'F' => __('lang_v1.female'), 'Unknown' => __('lang_v1.unknown')], null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('date_of_death', __('lang_v1.date_of_death') . ':*') !!}
                        {!! Form::text('date_of_death', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('received_at', __('lang_v1.received_at') . ':') !!}
                        {!! Form::text('received_at', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('cause_of_death', __('lang_v1.cause_of_death') . ':') !!}
                        {!! Form::text('cause_of_death', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <label class="checkbox-inline">{!! Form::checkbox('certify_self', 1) !!} @lang('lang_v1.certify_death_self')</label>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.storage_and_family')])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('morgue_slot_id', __('lang_v1.assign_slot') . ':') !!}
                        {!! Form::select('morgue_slot_id', $slots->pluck('slot_number', 'id'), null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                        @if($slots->isEmpty())<p class="help-block text-warning">@lang('lang_v1.no_slots_available')</p>@endif
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('daily_storage_fee', __('lang_v1.daily_storage_fee') . ':') !!}
                        {!! Form::text('daily_storage_fee', $default_daily_fee, ['class' => 'form-control input_number']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('preparation_fee', __('lang_v1.preparation_fee') . ':') !!}
                        {!! Form::text('preparation_fee', 0, ['class' => 'form-control input_number']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('family_contact_name', __('lang_v1.family_contact_name') . ':') !!}
                        {!! Form::text('family_contact_name', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('family_contact_phone', __('lang_v1.family_contact_phone') . ':') !!}
                        {!! Form::text('family_contact_phone', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('family_relationship', __('lang_v1.relationship') . ':') !!}
                        {!! Form::text('family_relationship', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <label class="checkbox-inline">{!! Form::checkbox('legal_hold', 1, false, ['id' => 'legal_hold']) !!} <strong>@lang('lang_v1.legal_hold')</strong></label>
                    <p class="help-block">@lang('lang_v1.legal_hold_note')</p>
                </div>
                <div class="col-sm-12 legal_hold_reason_field" style="display:none;">
                    <div class="form-group">
                        {!! Form::label('legal_hold_reason', __('lang_v1.legal_hold_reason') . ':') !!}
                        {!! Form::text('legal_hold_reason', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">@lang('lang_v1.receive_body')</button>
                <a href="{{ action([\App\Http\Controllers\MorgueController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    $('#legal_hold').on('change', function () {
        $('.legal_hold_reason_field').toggle($(this).is(':checked'));
    });
});
</script>
@endsection
