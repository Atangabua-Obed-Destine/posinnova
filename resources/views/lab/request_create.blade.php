@extends('layouts.app')
@section('title', __('lang_v1.new_lab_request'))

@php
    $preselected_patient = $visit ? $visit->patient : null;
@endphp

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.new_lab_request')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\LabRequestController::class, 'store']), 'method' => 'post', 'id' => 'lab_request_form']) !!}
        @if($visit)
            {!! Form::hidden('visit_id', $visit->id) !!}
        @endif
        @if($consultation)
            {!! Form::hidden('consultation_id', $consultation->id) !!}
        @endif

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('contact_id', __('lang_v1.patient') . ':*') !!}
                        <select name="contact_id" id="lab_patient_id" class="form-control" required style="width:100%;">
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
                        {!! Form::label('location_id', __('lang_v1.lab_location') . ':') !!}
                        {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('clinical_notes', __('lang_v1.clinical_notes') . ':') !!}
                        {!! Form::textarea('clinical_notes', null, ['class' => 'form-control', 'rows' => 2, 'placeholder' => __('lang_v1.clinical_notes_placeholder')]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.select_tests')])
            @if($lab_tests->isEmpty())
                <p class="text-muted">@lang('lang_v1.no_lab_tests_create_first')
                    @can('lab_test.manage_catalogue')
                        <a href="{{ action([\App\Http\Controllers\LabTestController::class, 'index']) }}" target="_blank">@lang('lang_v1.lab_test_catalogue')</a>
                    @endcan
                </p>
            @else
                <div class="row">
                    @foreach($lab_tests as $test)
                        <div class="col-sm-4">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" name="lab_test_ids[]" value="{{ $test->id }}">
                                    <strong>{{ $test->name }}</strong>
                                    @if($test->code) <small class="text-muted">({{ $test->code }})</small> @endif
                                    @if($test->fee > 0)
                                        <br><small class="text-muted display_currency" data-currency_symbol="true">{{ $test->fee }}</small>
                                    @endif
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold" {{ $lab_tests->isEmpty() ? 'disabled' : '' }}>
                    @lang('lang_v1.create_lab_request')
                </button>
                <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @if(!$preselected_patient)
    $('#lab_patient_id').select2({
        ajax: {
            url: '{{ route('patients.search') }}',
            dataType: 'json', delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
        },
        minimumInputLength: 1,
        placeholder: '{{ __('lang_v1.search_patient') }}',
        width: '100%',
    });
    @endif

    $('#lab_request_form').on('submit', function (e) {
        if ($('input[name="lab_test_ids[]"]:checked').length === 0) {
            e.preventDefault();
            toastr.error('{{ __('lang_v1.select_at_least_one_test') }}');
        }
    });
});
</script>
@endsection
