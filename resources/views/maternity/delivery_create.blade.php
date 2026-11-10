@extends('layouts.app')
@section('title', __('lang_v1.record_delivery'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.record_delivery')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\DeliveryController::class, 'store']), 'method' => 'post', 'id' => 'delivery_form']) !!}
        @if($antenatal_record) {!! Form::hidden('antenatal_record_id', $antenatal_record->id) !!} @endif

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.delivery_details')])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('mother_contact_id', __('lang_v1.mother') . ':*') !!}
                        <select name="mother_contact_id" id="delivery_mother_id" class="form-control" required style="width:100%;">
                            @if($mother)
                                <option value="{{ $mother->id }}" selected>
                                    {{ $mother->name }}
                                    @if(optional($mother->patientDetails)->file_number)({{ $mother->patientDetails->file_number }})@endif
                                </option>
                            @endif
                        </select>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('delivery_date', __('lang_v1.delivery_date') . ':*') !!}
                        {!! Form::text('delivery_date', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('delivery_type', __('lang_v1.delivery_type') . ':*') !!}
                        {!! Form::select('delivery_type', ['normal' => __('lang_v1.delivery_type_normal'), 'cs' => __('lang_v1.delivery_type_cs'), 'forceps' => __('lang_v1.delivery_type_forceps'), 'vacuum' => __('lang_v1.delivery_type_vacuum'), 'breech' => __('lang_v1.delivery_type_breech')], 'normal', ['class' => 'form-control select2', 'required']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('fee', __('lang_v1.delivery_fee') . ':') !!}
                        {!! Form::text('fee', $default_fee, ['class' => 'form-control input_number']) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('complications', __('lang_v1.complications') . ':') !!}
                        {!! Form::text('complications', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.baby_details')])
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('baby_sex', __('lang_v1.baby_sex') . ':') !!}
                        {!! Form::select('baby_sex', ['Unknown' => __('lang_v1.unknown'), 'M' => __('lang_v1.male'), 'F' => __('lang_v1.female')], 'Unknown', ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('baby_weight', __('lang_v1.baby_weight') . ' (kg):') !!}
                        {!! Form::number('baby_weight', null, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        {!! Form::label('apgar_1min', __('lang_v1.apgar_1min') . ':') !!}
                        {!! Form::number('apgar_1min', null, ['class' => 'form-control', 'min' => 0, 'max' => 10]) !!}
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        {!! Form::label('apgar_5min', __('lang_v1.apgar_5min') . ':') !!}
                        {!! Form::number('apgar_5min', null, ['class' => 'form-control', 'min' => 0, 'max' => 10]) !!}
                    </div>
                </div>

                <div class="clearfix"></div>

                <div class="col-sm-12">
                    <div class="checkbox">
                        <label><input type="checkbox" name="register_baby" value="1" checked id="register_baby"> <strong>@lang('lang_v1.register_baby_as_patient')</strong></label>
                        <p class="help-block">@lang('lang_v1.register_baby_note')</p>
                    </div>
                </div>
                <div class="col-sm-6 baby_name_field">
                    <div class="form-group">
                        {!! Form::label('baby_name', __('lang_v1.baby_name') . ':') !!}
                        {!! Form::text('baby_name', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.baby_name_placeholder')]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-green-600 tw-text-white tw-border-none tw-font-bold">@lang('lang_v1.record_delivery')</button>
                <a href="{{ action([\App\Http\Controllers\DeliveryController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @if(!$mother)
    $('#delivery_mother_id').select2({
        ajax: {
            url: '{{ route('patients.search') }}', dataType: 'json', delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
        },
        minimumInputLength: 1, placeholder: '{{ __('lang_v1.search_patient') }}', width: '100%',
    });
    @endif

    $('#register_baby').on('change', function () {
        $('.baby_name_field').toggle($(this).is(':checked'));
    });
});
</script>
@endsection
