@extends('layouts.app')
@section('title', __('lang_v1.new_consultation'))

@php
    $patient = $visit->patient;
    $pd = optional($patient)->patientDetails;
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
    $has_allergies = ! empty(optional($pd)->known_allergies);
    $can_prescribe = auth()->user()->can('consultation.prescribe');
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.new_consultation')</h1>
</section>

<section class="content">

    {{-- Patient context bar --}}
    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-x-6 tw-gap-y-1">
            <span class="tw-text-lg tw-font-semibold tw-text-gray-900">{{ $patient->name }}</span>
            <span class="tw-text-sm tw-text-gray-600"><strong>@lang('lang_v1.file_number'):</strong> {{ optional($pd)->file_number ?: '-' }}</span>
            <span class="tw-text-sm tw-text-gray-600"><strong>@lang('lang_v1.sex'):</strong> {{ optional($pd)->sex ?: '-' }}</span>
            <span class="tw-text-sm tw-text-gray-600"><strong>@lang('lang_v1.age'):</strong> {{ !is_null($age) ? $age : '-' }}</span>
            <span class="tw-text-sm tw-text-gray-600"><strong>@lang('lang_v1.blood_group'):</strong> {{ optional($pd)->blood_group ?: '-' }}</span>
            <span class="tw-text-sm tw-text-gray-600"><span class="label label-info">{{ $visit->ref_no }}</span></span>
        </div>
        @if($has_allergies)
            <div class="tw-mt-2 tw-rounded-lg tw-bg-red-50 tw-ring-1 tw-ring-red-200 tw-px-3 tw-py-2 tw-text-sm tw-text-red-700">
                <i class="fas fa-exclamation-triangle"></i> <strong>@lang('lang_v1.known_allergies'):</strong> {{ $pd->known_allergies }}
            </div>
        @endif
    </div>

    {!! Form::open(['url' => action([\App\Http\Controllers\ConsultationController::class, 'store']), 'method' => 'post', 'id' => 'consultation_form']) !!}
    {!! Form::hidden('visit_id', $visit->id) !!}

    {{-- Consultation type --}}
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.consultation_details')])
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('consultation_type', __('lang_v1.consultation_type') . ':*') !!}
                    {!! Form::select('consultation_type', ['general' => __('lang_v1.consultation_type_general'), 'specialist' => __('lang_v1.consultation_type_specialist')], 'general', ['class' => 'form-control select2', 'id' => 'consultation_type', 'required']) !!}
                </div>
            </div>
            <div class="col-sm-3 specialty_field" style="display:none;">
                <div class="form-group">
                    {!! Form::label('specialty', __('lang_v1.specialty') . ':') !!}
                    {!! Form::text('specialty', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.specialty_placeholder')]) !!}
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    {!! Form::label('chief_complaint', __('lang_v1.chief_complaint') . ':') !!}
                    {!! Form::text('chief_complaint', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.chief_complaint_placeholder')]) !!}
                </div>
            </div>
        </div>
    @endcomponent

    {{-- Vitals --}}
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.vitals')])
        <div class="row">
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_weight', __('lang_v1.weight_kg') . ':') !!}
                    {!! Form::number('vitals_weight', null, ['class' => 'form-control vitals-bmi', 'id' => 'vitals_weight', 'step' => '0.01', 'min' => 0]) !!}
                </div>
            </div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_height', __('lang_v1.height_cm') . ':') !!}
                    {!! Form::number('vitals_height', null, ['class' => 'form-control vitals-bmi', 'id' => 'vitals_height', 'step' => '0.01', 'min' => 0]) !!}
                </div>
            </div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('bmi_display', __('lang_v1.bmi') . ':') !!}
                    {!! Form::text('bmi_display', null, ['class' => 'form-control', 'id' => 'bmi_display', 'readonly']) !!}
                </div>
            </div>
            <div class="col-sm-3 col-xs-12">
                <div class="form-group">
                    {!! Form::label('vitals_bp', __('lang_v1.blood_pressure') . ' (mmHg):') !!}
                    <div class="tw-flex tw-items-center tw-gap-1">
                        {!! Form::number('vitals_bp_systolic', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.systolic'), 'min' => 0]) !!}
                        <span>/</span>
                        {!! Form::number('vitals_bp_diastolic', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.diastolic'), 'min' => 0]) !!}
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_temperature', __('lang_v1.temperature_c') . ':') !!}
                    {!! Form::number('vitals_temperature', null, ['class' => 'form-control', 'step' => '0.1', 'min' => 0]) !!}
                </div>
            </div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_pulse', __('lang_v1.pulse_bpm') . ':') !!}
                    {!! Form::number('vitals_pulse', null, ['class' => 'form-control', 'min' => 0]) !!}
                </div>
            </div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_spo2', __('lang_v1.spo2') . ':') !!}
                    {!! Form::number('vitals_spo2', null, ['class' => 'form-control', 'min' => 0, 'max' => 100]) !!}
                </div>
            </div>
        </div>
    @endcomponent

    {{-- Examination + diagnosis --}}
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.examination_and_diagnosis')])
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    {!! Form::label('examination_notes', __('lang_v1.examination_notes') . ':') !!}
                    {!! Form::textarea('examination_notes', null, ['class' => 'form-control', 'rows' => 4]) !!}
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    {!! Form::label('diagnosis', __('lang_v1.diagnosis') . ':') !!}
                    {!! Form::textarea('diagnosis', null, ['class' => 'form-control', 'rows' => 4, 'placeholder' => __('lang_v1.diagnosis_placeholder')]) !!}
                </div>
            </div>
        </div>
    @endcomponent

    {{-- Prescription --}}
    @if($can_prescribe)
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.prescription')])
        <div class="row">
            <div class="col-sm-8 col-sm-offset-2">
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon"><i class="fa fa-search"></i></span>
                        {!! Form::text('search_prescription_product', null, ['class' => 'form-control', 'id' => 'search_prescription_product', 'placeholder' => __('lang_v1.search_medicine')]) !!}
                    </div>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-condensed" id="prescription_table">
                <thead>
                    <tr>
                        <th style="width:28%;">@lang('lang_v1.medicine')</th>
                        <th style="width:14%;">@lang('lang_v1.dosage')</th>
                        <th style="width:16%;">@lang('lang_v1.frequency')</th>
                        <th style="width:14%;">@lang('lang_v1.duration')</th>
                        <th style="width:12%;">@lang('sale.qty')</th>
                        <th style="width:12%;">@lang('lang_v1.notes')</th>
                        <th style="width:4%;"><i class="fa fa-trash"></i></th>
                    </tr>
                </thead>
                <tbody id="prescription_rows"></tbody>
            </table>
        </div>
    @endcomponent
    @endif

    {{-- Lab requests --}}
    @if(auth()->user()->can('lab_request.create') && $lab_tests->isNotEmpty())
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.request_lab_tests')])
        <div class="row">
            @foreach($lab_tests as $test)
                <div class="col-sm-4">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="lab_test_ids[]" value="{{ $test->id }}">
                            {{ $test->name }}
                            @if($test->code) <small class="text-muted">({{ $test->code }})</small> @endif
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="form-group">
                    {!! Form::label('lab_clinical_notes', __('lang_v1.clinical_notes') . ':') !!}
                    {!! Form::text('lab_clinical_notes', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.clinical_notes_placeholder')]) !!}
                </div>
            </div>
        </div>
    @endcomponent
    @endif

    {{-- Imaging requests --}}
    @if(auth()->user()->can('imaging_request.create') && $imaging_catalogue->isNotEmpty())
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.request_imaging')])
        <div class="row">
            @foreach($imaging_catalogue as $study)
                <div class="col-sm-4">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="imaging_catalogue_ids[]" value="{{ $study->id }}">
                            {{ $study->name }}
                            @if($study->modality) <small class="text-muted">({{ $study->modality }})</small> @endif
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    @endcomponent
    @endif

    {{-- Actions --}}
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.referral_and_actions')])
        <div class="row">
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('referral_to', __('lang_v1.refer_to_department') . ':') !!}
                    {!! Form::select('referral_to', $departments, null, ['class' => 'form-control select2']) !!}
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    {!! Form::label('consultation_fee', __('lang_v1.consultation_fee') . ':') !!}
                    @if (! empty($fee_billed))
                        {{-- PosInnova clinic: billed at check-in, never twice --}}
                        <p class="form-control-static"><i class="fas fa-check-circle text-success"></i>
                            @if (! empty($visit->subscription_period_id))
                                {{ __('clinic.covered_by_plan', ['plan' => optional($visit->package)->name]) }}</p>
                            @else
                                {{ __('clinic.billed_at_checkin', ['package' => optional($visit->package)->name ?: __('lang_v1.consultation_fee')]) }}</p>
                            @endif
                    @else
                        {!! Form::text('consultation_fee', $default_fee, ['class' => 'form-control input_number']) !!}
                    @endif
                </div>
            </div>
            <div class="col-sm-4">
                <div class="form-group">
                    <br>
                    <label class="checkbox-inline">
                        {!! Form::checkbox('admission_requested', 1, false) !!}
                        @lang('lang_v1.request_admission')
                    </label>
                </div>
            </div>
        </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12 text-center" style="margin-bottom:20px;">
            <button type="submit" name="action" value="draft" class="tw-dw-btn tw-dw-btn-outline tw-font-bold">
                <i class="fas fa-save"></i> @lang('lang_v1.save_as_draft')
            </button>
            @can('consultation.finalize')
                <button type="submit" name="action" value="final" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">
                    <i class="fas fa-check"></i> @lang('lang_v1.finalize_consultation')
                </button>
            @endcan
            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$visit->id]) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </div>

    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    // Toggle specialty field
    $('#consultation_type').on('change', function () {
        if ($(this).val() === 'specialist') {
            $('.specialty_field').show();
        } else {
            $('.specialty_field').hide();
        }
    }).trigger('change');

    // BMI auto-calc
    function recalcBmi() {
        var w = parseFloat($('#vitals_weight').val());
        var h = parseFloat($('#vitals_height').val());
        if (w > 0 && h > 0) {
            var hm = h / 100;
            $('#bmi_display').val((w / (hm * hm)).toFixed(1));
        } else {
            $('#bmi_display').val('');
        }
    }
    $('.vitals-bmi').on('input', recalcBmi);

    @if($can_prescribe)
    var rxIndex = 0;
    function addPrescriptionRow(item) {
        var i = rxIndex++;
        var row = '<tr>' +
            '<td>' + item.name +
                '<input type="hidden" name="prescriptions[' + i + '][product_id]" value="' + item.product_id + '">' +
                '<input type="hidden" name="prescriptions[' + i + '][variation_id]" value="' + item.variation_id + '"></td>' +
            '<td><input type="text" name="prescriptions[' + i + '][dosage]" class="form-control input-sm" placeholder="{{ __('lang_v1.dosage_eg') }}"></td>' +
            '<td><input type="text" name="prescriptions[' + i + '][frequency]" class="form-control input-sm" placeholder="{{ __('lang_v1.frequency_eg') }}"></td>' +
            '<td><input type="text" name="prescriptions[' + i + '][duration]" class="form-control input-sm" placeholder="{{ __('lang_v1.duration_eg') }}"></td>' +
            '<td><input type="number" step="0.01" min="0" name="prescriptions[' + i + '][quantity]" class="form-control input-sm" value="1"></td>' +
            '<td><input type="text" name="prescriptions[' + i + '][notes]" class="form-control input-sm"></td>' +
            '<td class="text-center"><a href="#" class="text-danger remove_rx_row"><i class="fa fa-trash"></i></a></td>' +
        '</tr>';
        $('#prescription_rows').append(row);
    }

    $('#search_prescription_product').autocomplete({
        source: function (request, response) {
            $.getJSON('{{ action([\App\Http\Controllers\ProductController::class, 'getProducts']) }}', {
                term: request.term,
                not_for_selling: 0,
            }, response);
        },
        minLength: 2,
        response: function (event, ui) {
            if (ui.content.length == 1) {
                ui.item = ui.content[0];
                $(this).data('ui-autocomplete')._trigger('select', 'autocompleteselect', ui);
                $(this).autocomplete('close');
            }
        },
        select: function (event, ui) {
            if (ui.item.variation_id) {
                addPrescriptionRow(ui.item);
            }
            $(this).val('');
            return false;
        },
    }).autocomplete('instance')._renderItem = function (ul, item) {
        return $('<li>').append('<div>' + item.name + '</div>').appendTo(ul);
    };

    $(document).on('click', '.remove_rx_row', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });
    @endif

    __page_leave_confirmation('#consultation_form');
});
</script>
@endsection
