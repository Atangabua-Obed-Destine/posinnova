@extends('layouts.app')
@section('title', __('lang_v1.new_admission'))

@php
    // Build a ward -> available beds map for the cascading bed selector.
    $ward_beds = [];
    foreach ($wards as $w) {
        $ward_beds[$w->id] = $w->beds->map(fn($b) => ['id' => $b->id, 'number' => $b->bed_number])->values();
    }
@endphp

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.new_admission')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\AdmissionController::class, 'store']), 'method' => 'post', 'id' => 'admission_form']) !!}
        @if(!empty($visit_id)) {!! Form::hidden('visit_id', $visit_id) !!} @endif

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.admission_details')])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('contact_id', __('lang_v1.patient') . ':*') !!}
                        <select name="contact_id" id="admission_patient_id" class="form-control" required style="width:100%;">
                            @if($preselected_patient)
                                <option value="{{ $preselected_patient->id }}" selected>
                                    {{ $preselected_patient->name }}
                                    @if(optional($preselected_patient->patientDetails)->file_number)({{ $preselected_patient->patientDetails->file_number }})@endif
                                </option>
                            @endif
                        </select>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('admission_date', __('lang_v1.admission_date') . ':*') !!}
                        {!! Form::text('admission_date', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control', 'required', 'id' => 'admission_date']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('admitting_doctor_id', __('lang_v1.admitting_doctor') . ':') !!}
                        {!! Form::select('admitting_doctor_id', $doctors, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>

                <div class="clearfix"></div>

                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('ward_id', __('lang_v1.ward') . ':*') !!}
                        <select name="ward_id" id="admission_ward_id" class="form-control select2" required>
                            <option value="">{{ __('messages.please_select') }}</option>
                            @foreach($wards as $w)
                                <option value="{{ $w->id }}">{{ $w->name }} ({{ $w->beds->count() }} {{ __('lang_v1.available') }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('bed_id', __('lang_v1.bed') . ':') !!}
                        <select name="bed_id" id="admission_bed_id" class="form-control select2">
                            <option value="">{{ __('messages.please_select') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('deposit_amount', __('lang_v1.deposit') . ':') !!}
                        {!! Form::text('deposit_amount', 0, ['class' => 'form-control input_number']) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('reason_for_admission', __('lang_v1.reason_for_admission') . ':') !!}
                        {!! Form::textarea('reason_for_admission', null, ['class' => 'form-control', 'rows' => 2]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.primary_caretaker')])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('caretaker_name', __('lang_v1.caretaker_name') . ':') !!}
                        {!! Form::text('caretaker_name', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('caretaker_phone', __('lang_v1.caretaker_phone') . ':') !!}
                        {!! Form::text('caretaker_phone', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('caretaker_relationship', __('lang_v1.relationship') . ':') !!}
                        {!! Form::text('caretaker_relationship', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">@lang('lang_v1.admit_patient')</button>
                <a href="{{ action([\App\Http\Controllers\AdmissionController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    var wardBeds = @json($ward_beds);

    @if(!$preselected_patient)
    $('#admission_patient_id').select2({
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

    $('#admission_ward_id').on('change', function () {
        var wid = $(this).val();
        var $bed = $('#admission_bed_id');
        $bed.empty().append('<option value="">{{ __('messages.please_select') }}</option>');
        if (wid && wardBeds[wid]) {
            $.each(wardBeds[wid], function (i, b) {
                $bed.append(new Option(b.number, b.id));
            });
        }
        $bed.trigger('change');
    });
});
</script>
@endsection
