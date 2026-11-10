@extends('layouts.app')
@section('title', __('lang_v1.new_imaging_request'))

@php
    $preselected_patient = $visit ? $visit->patient : null;
@endphp

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.new_imaging_request')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\ImagingController::class, 'store']), 'method' => 'post', 'id' => 'imaging_request_form']) !!}
        @if($visit) {!! Form::hidden('visit_id', $visit->id) !!} @endif
        @if($consultation) {!! Form::hidden('consultation_id', $consultation->id) !!} @endif

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('contact_id', __('lang_v1.patient') . ':*') !!}
                        <select name="contact_id" id="imaging_patient_id" class="form-control" required style="width:100%;">
                            @if($preselected_patient)
                                <option value="{{ $preselected_patient->id }}" selected>
                                    {{ $preselected_patient->name }}
                                    @if(optional($preselected_patient->patientDetails)->file_number)({{ $preselected_patient->patientDetails->file_number }})@endif
                                </option>
                            @endif
                        </select>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('imaging_catalogue_id', __('lang_v1.study') . ':*') !!}
                        {!! Form::select('imaging_catalogue_id', $catalogue->pluck('name', 'id'), null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']) !!}
                        @if($catalogue->isEmpty())
                            <p class="help-block text-warning">@lang('lang_v1.no_imaging_create_first')
                                @can('imaging.manage_catalogue')
                                    <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'catalogue']) }}" target="_blank">@lang('lang_v1.imaging_catalogue')</a>
                                @endcan
                            </p>
                        @endif
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('location_id', __('lang_v1.radiology_location') . ':') !!}
                        {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('clinical_notes', __('lang_v1.clinical_notes') . ':') !!}
                        {!! Form::textarea('clinical_notes', null, ['class' => 'form-control', 'rows' => 2]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold" {{ $catalogue->isEmpty() ? 'disabled' : '' }}>
                    @lang('lang_v1.create_imaging_request')
                </button>
                <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @if(!$preselected_patient)
    $('#imaging_patient_id').select2({
        ajax: {
            url: '{{ route('patients.search') }}', dataType: 'json', delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
        },
        minimumInputLength: 1,
        placeholder: '{{ __('lang_v1.search_patient') }}',
        width: '100%',
    });
    @endif
});
</script>
@endsection
