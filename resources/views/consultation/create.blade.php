@extends('layouts.app')
@section('title', __('lang_v1.new_consultation'))

@php
    $patient = $visit->patient;
    $pd = optional($patient)->patientDetails;
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
    $has_allergies = ! empty(optional($pd)->known_allergies);
    $can_prescribe = auth()->user()->can('consultation.prescribe');
    // PosInnova: the same form continues a consultation saved as a draft ($consultation set)
    $c_edit = $consultation ?? null;
    $fv = function ($field, $default = null) use ($c_edit) {
        if (old($field) !== null) {
            return old($field);
        }

        return $c_edit ? $c_edit->{$field} : $default;
    };
    $num = fn ($v) => $v === null ? null : (str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v);
    // texts for the prescription search (built here: @json takes no multi-line array)
    $rx_js_text = ['_location' => optional($visit->location)->name ?: ''];
    foreach (['rx_avail_in_stock', 'rx_avail_low', 'rx_avail_out', 'rx_avail_expired_only', 'rx_avail_not_tracked', 'rx_avail_not_here',
        'rx_avail_other_loc', 'rx_avail_expiry', 'rx_avail_expires_soon', 'rx_avail_expired_qty', 'rx_buys_outside', 'rx_buys_outside_help',
        'rx_qty_over_stock', 'rx_free_text_option', 'rx_not_in_stock_tag'] as $k) {
        // placeholders stay as :qty / :location... for the script to fill in
        $rx_js_text[$k] = trans('clinic.'.$k);
    }
    $rx_js_text += ['_label_dosage' => __('lang_v1.dosage'), '_label_frequency' => __('lang_v1.frequency'), '_label_duration' => __('lang_v1.duration'), '_label_notes' => __('lang_v1.notes')];
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @if ($c_edit)
            @lang('clinic.cons_continue_title') <small class="tw-text-gray-600">{{ $c_edit->ref_no }} · {{ __('clinic.cons_started_at', ['time' => (new \App\Utils\Util())->format_date($c_edit->created_at, true)]) }}</small>
        @else
            @lang('lang_v1.new_consultation')
        @endif
    </h1>
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

    {{-- PosInnova: the patient's history while consulting --}}
    <details class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4" id="consult_history" {{ $history->isNotEmpty() ? 'open' : '' }}>
        <summary style="cursor:pointer;font-weight:700;font-size:15px;"><i class="fas fa-history"></i> @lang('clinic.hist_title')
            <span class="text-muted" style="font-weight:400;font-size:13px;">({{ $history->count() }})</span></summary>
        @if ($history->isEmpty() && $history_labs->isEmpty())
            <p class="text-muted" style="margin:10px 0 0;">@lang('clinic.hist_none')</p>
        @endif
        @foreach ($history as $h)
            <div style="border-top:1px solid #f2f4f7;padding:10px 0 4px;margin-top:8px;">
                <div style="display:flex;flex-wrap:wrap;gap:4px 12px;align-items:baseline;">
                    <strong>{{ @format_date($h->created_at) }}</strong>
                    <span class="text-muted">{{ trim(optional($h->consultedBy)->user_full_name) }}</span>
                    <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'show'], [$h->id]) }}" target="_blank" style="margin-left:auto;">@lang('clinic.hist_view') <i class="fas fa-external-link-alt"></i></a>
                </div>
                @if ($h->chief_complaint)<div><span class="text-muted">@lang('lang_v1.chief_complaint'):</span> {{ $h->chief_complaint }}</div>@endif
                <div><span class="text-muted">@lang('lang_v1.diagnosis'):</span> <strong>{{ $h->diagnosis ?: '-' }}</strong></div>
                @if ($h->prescriptions->isNotEmpty())
                    <div><span class="text-muted">@lang('lang_v1.prescriptions'):</span>
                        {{ $h->prescriptions->map(fn ($rx) => trim($rx->displayName() . ' ' . collect([$rx->dosage, $rx->frequency, $rx->duration])->filter()->implode(' '))) ->implode('; ') }}</div>
                @endif
            </div>
        @endforeach
        @if ($history_labs->isNotEmpty())
            <div style="border-top:1px solid #f2f4f7;padding-top:10px;margin-top:8px;">
                <strong>@lang('clinic.hist_labs')</strong>
                @foreach ($history_labs as $lr)
                    <div style="margin-top:4px;">
                        <span class="text-muted">{{ @format_date($lr->requested_at) }}:</span>
                        @foreach ($lr->lines as $line)
                            <span style="white-space:nowrap;">{{ $line->test_name }} <strong>{{ trim($line->result_value . ' ' . $line->unit) ?: '-' }}</strong>@if (in_array($line->result_flag, ['high', 'low', 'critical'])) <span class="label {{ $line->result_flag == 'critical' ? 'label-danger' : 'label-warning' }}">@lang('lang_v1.lab_flag_' . $line->result_flag)</span>@endif</span>{{ $loop->last ? '' : ' · ' }}
                        @endforeach
                        <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lr->id]) }}" target="_blank">@lang('clinic.hist_view')</a>
                    </div>
                @endforeach
            </div>
        @endif
    </details>

    @if ($c_edit)
        {!! Form::open(['url' => action([\App\Http\Controllers\ConsultationController::class, 'update'], [$c_edit->id]), 'method' => 'post', 'id' => 'consultation_form']) !!}
    @else
        {!! Form::open(['url' => action([\App\Http\Controllers\ConsultationController::class, 'store']), 'method' => 'post', 'id' => 'consultation_form']) !!}
    @endif
    {!! Form::hidden('visit_id', $visit->id) !!}

    {{-- PosInnova: continuing: what the lab / imaging sent back since the consultation was saved --}}
    @if ($c_edit && ($lab_requests->isNotEmpty() || $imaging_requests->isNotEmpty()))
        <div class="box box-solid" id="consult_results_box" style="border-top:3px solid #4f46e5;">
            <div class="box-header with-border" style="display:flex;align-items:center;gap:8px;">
                <h3 class="box-title"><i class="fas fa-flask"></i> @lang('clinic.cr_title')</h3>
                <button type="button" class="btn btn-xs btn-default" id="consult_results_refresh" style="margin-left:auto;"
                    data-url="{{ action([\App\Http\Controllers\ConsultationController::class, 'results'], [$c_edit->id]) }}">
                    <i class="fas fa-sync-alt"></i> @lang('clinic.cr_refresh')</button>
            </div>
            <div class="box-body" id="consult_results_body">
                @include('consultation.partials.results')
            </div>
        </div>
    @endif

    {{-- Consultation type --}}
    @php
        $spec_now = $fv('specialty');
        $spec_opts = collect($doctor_lists['specialty'] ?? [])->mapWithKeys(fn ($v) => [$v => $v])->all();
        if ($spec_now && ! isset($spec_opts[$spec_now])) {
            $spec_opts = [$spec_now => $spec_now] + $spec_opts;
        }
    @endphp
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.consultation_details')])
        <div class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('consultation_type', __('lang_v1.consultation_type') . ':*') !!}
                    {!! Form::select('consultation_type', ['general' => __('lang_v1.consultation_type_general'), 'specialist' => __('lang_v1.consultation_type_specialist')], $fv('consultation_type', 'general'), ['class' => 'form-control select2', 'id' => 'consultation_type', 'required']) !!}
                </div>
            </div>
            <div class="col-sm-3 specialty_field" style="display:none;">
                <div class="form-group">
                    {!! Form::label('specialty', __('lang_v1.specialty') . ':') !!}
                    <div class="input-group">
                        {!! Form::select('specialty', $spec_opts, $spec_now, ['class' => 'form-control select2', 'id' => 'specialty', 'placeholder' => __('clinic.dl_choose'), 'style' => 'width:100%;']) !!}
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default dl-add-btn" data-list="specialty" data-target="#specialty" title="{{ __('clinic.dl_add_title', ['list' => __('clinic.dl_specialty')]) }}" aria-label="{{ __('clinic.dl_add_title', ['list' => __('clinic.dl_specialty')]) }}"><i class="fas fa-plus"></i></button>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    {!! Form::label('chief_complaint', __('lang_v1.chief_complaint') . ':') !!}
                    {!! Form::text('chief_complaint', $fv('chief_complaint'), ['class' => 'form-control', 'id' => 'chief_complaint', 'placeholder' => __('lang_v1.chief_complaint_placeholder')]) !!}
                    @include('consultation.partials.picker', ['list' => 'complaint', 'target' => '#chief_complaint', 'sep' => ', '])
                </div>
            </div>
        </div>
    @endcomponent

    {{-- Vitals (filled in from the front desk when they were taken there; the saved ones when continuing) --}}
    @php
        $front_vitals = $c_edit
            ? collect(\App\Visit::VITALS)->mapWithKeys(fn ($f) => [$f => $num($c_edit->{$f})])->all()
            : ($visit->hasVitals() ? collect(\App\Visit::VITALS)->mapWithKeys(fn ($f) => [$f => $num($visit->{$f})])->all() : []);
    @endphp
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.vitals')])
        @if ($visit->hasVitals() && ! $c_edit)
            <p class="text-muted" id="front_vitals_note" style="margin-top:0;"><i class="fas fa-heartbeat"></i>
                {{ __('clinic.vit_from_front_desk', ['time' => $visit->vitals_taken_at->format('H:i'), 'name' => trim(optional($visit->vitalsTakenBy)->user_full_name)]) }}
                @if ($visit->vitals_note) · <em>{{ $visit->vitals_note }}</em>@endif</p>
        @endif
        <div class="row">
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_weight', __('lang_v1.weight_kg') . ':') !!}
                    {!! Form::number('vitals_weight', $front_vitals['vitals_weight'] ?? null, ['class' => 'form-control vitals-bmi', 'id' => 'vitals_weight', 'step' => '0.01', 'min' => 0]) !!}
                </div>
            </div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_height', __('lang_v1.height_cm') . ':') !!}
                    {!! Form::number('vitals_height', $front_vitals['vitals_height'] ?? null, ['class' => 'form-control vitals-bmi', 'id' => 'vitals_height', 'step' => '0.01', 'min' => 0]) !!}
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
                        {!! Form::number('vitals_bp_systolic', $front_vitals['vitals_bp_systolic'] ?? null, ['class' => 'form-control', 'placeholder' => __('lang_v1.systolic'), 'min' => 0]) !!}
                        <span>/</span>
                        {!! Form::number('vitals_bp_diastolic', $front_vitals['vitals_bp_diastolic'] ?? null, ['class' => 'form-control', 'placeholder' => __('lang_v1.diastolic'), 'min' => 0]) !!}
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_temperature', __('lang_v1.temperature_c') . ':') !!}
                    {!! Form::number('vitals_temperature', $front_vitals['vitals_temperature'] ?? null, ['class' => 'form-control', 'step' => '0.1', 'min' => 0]) !!}
                </div>
            </div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_pulse', __('lang_v1.pulse_bpm') . ':') !!}
                    {!! Form::number('vitals_pulse', $front_vitals['vitals_pulse'] ?? null, ['class' => 'form-control', 'min' => 0]) !!}
                </div>
            </div>
            <div class="col-sm-2 col-xs-6">
                <div class="form-group">
                    {!! Form::label('vitals_spo2', __('lang_v1.spo2') . ':') !!}
                    {!! Form::number('vitals_spo2', $front_vitals['vitals_spo2'] ?? null, ['class' => 'form-control', 'min' => 0, 'max' => 100]) !!}
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
                    {!! Form::textarea('examination_notes', $fv('examination_notes'), ['class' => 'form-control', 'rows' => 4, 'id' => 'examination_notes']) !!}
                    @include('consultation.partials.picker', ['list' => 'exam_finding', 'target' => '#examination_notes', 'sep' => ', '])
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    {!! Form::label('diagnosis', __('lang_v1.diagnosis') . ':') !!}
                    {!! Form::textarea('diagnosis', $fv('diagnosis'), ['class' => 'form-control', 'rows' => 4, 'id' => 'diagnosis', 'placeholder' => __('lang_v1.diagnosis_placeholder')]) !!}
                    @include('consultation.partials.picker', ['list' => 'diagnosis', 'target' => '#diagnosis', 'sep' => "\n"])
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
                        {!! Form::text('search_prescription_product', null, ['class' => 'form-control', 'id' => 'search_prescription_product', 'placeholder' => __('lang_v1.search_medicine'), 'autocomplete' => 'off']) !!}
                    </div>
                    <p class="help-block" style="margin-bottom:0;"><i class="fas fa-info-circle"></i> @lang('clinic.rx_search_hint', ['location' => optional($visit->location)->name ?: '-'])</p>
                </div>
            </div>
        </div>
        <style>
            .rx-avail { margin-top:3px; display:flex; flex-wrap:wrap; gap:3px 6px; align-items:center; font-size:11px; line-height:1.5; }
            .rx-avail .label { font-size:10.5px; font-weight:600; }
            .rx-ac-item { display:flex; flex-direction:column; gap:1px; padding:2px 0; }
            .rx-ac-free { font-style:italic; color:#92400e; }
            .rx-outside-lbl { font-weight:normal; font-size:12px; margin:4px 0 0; cursor:pointer; display:inline-flex; align-items:center; gap:4px; }
            .rx-over { font-size:11.5px; margin-top:3px; }
            #prescription_rows tr.rx-is-outside > td:first-child strong { text-decoration: none; color:#92400e; }
        </style>
        <div class="table-responsive">
            <table class="table table-bordered table-condensed" id="prescription_table">
                <thead>
                    <tr>
                        <th style="width:30%;">@lang('lang_v1.medicine')</th>
                        <th style="width:14%;">@lang('lang_v1.dosage')</th>
                        <th style="width:16%;">@lang('lang_v1.frequency')</th>
                        <th style="width:14%;">@lang('lang_v1.duration')</th>
                        <th style="width:12%;">@lang('sale.qty')</th>
                        <th style="width:10%;">@lang('lang_v1.notes')</th>
                        <th style="width:4%;"><i class="fa fa-trash"></i></th>
                    </tr>
                </thead>
                <tbody id="prescription_rows"></tbody>
            </table>
        </div>
    @endcomponent
    @endif

    {{-- Lab requests --}}
    @php
        // continuing: tests / studies already asked for in this consultation are marked
        $asked_tests = $lab_requests->flatMap->lines->pluck('lab_test_id')->filter()->flip()->all();
        $asked_studies = $imaging_requests->pluck('imaging_catalogue_id')->filter()->flip()->all();
    @endphp
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
                            @if (isset($asked_tests[$test->id])) <span class="label label-info" title="{{ __('clinic.cr_already_asked_help') }}">@lang('clinic.cr_already_asked')</span> @endif
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
    @if(\App\Utils\ClinicSettings::sectionOn(session('user.business_id'), 'imaging') && auth()->user()->can('imaging_request.create') && $imaging_catalogue->isNotEmpty())
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.request_imaging')])
        <div class="row">
            @foreach($imaging_catalogue as $study)
                <div class="col-sm-4">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="imaging_catalogue_ids[]" value="{{ $study->id }}">
                            {{ $study->name }}
                            @if($study->modality) <small class="text-muted">({{ $study->modality }})</small> @endif
                            @if (isset($asked_studies[$study->id])) <span class="label label-info" title="{{ __('clinic.cr_already_asked_help') }}">@lang('clinic.cr_already_asked')</span> @endif
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
                    {!! Form::select('referral_to', $departments, $fv('referral_to'), ['class' => 'form-control select2']) !!}
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
                        {!! Form::text('consultation_fee', $c_edit ? $num($c_edit->consultation_fee) : $default_fee, ['class' => 'form-control input_number']) !!}
                    @endif
                </div>
            </div>
            {{-- admission only makes sense when the clinic uses wards --}}
            @if (\App\Utils\ClinicSettings::sectionOn(session('user.business_id'), 'wards'))
            <div class="col-sm-4">
                <div class="form-group">
                    <br>
                    <label class="checkbox-inline">
                        {!! Form::checkbox('admission_requested', 1, (bool) $fv('admission_requested', false)) !!}
                        @lang('lang_v1.request_admission')
                    </label>
                </div>
            </div>
            @endif
        </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12 text-center" style="margin-bottom:20px;">
            <button type="submit" name="action" value="draft" class="tw-dw-btn tw-dw-btn-outline tw-font-bold" title="{{ __('clinic.cons_save_later_help') }}">
                <i class="fas fa-save"></i> @lang('clinic.cons_save_later')
            </button>
            @can('consultation.finalize')
                <button type="submit" name="action" value="final" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">
                    <i class="fas fa-check"></i> @lang('lang_v1.finalize_consultation')
                </button>
            @endcan
            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$visit->id]) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            <p class="help-block" style="margin-top:8px;"><i class="fas fa-info-circle"></i> @lang('clinic.cons_save_later_help')</p>
        </div>
    </div>

    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
@include('consultation.partials.doctor_lists_js')
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
    recalcBmi();

    // continuing: refresh the lab / imaging results without leaving the form (and every minute)
    function refreshResults() {
        var $b = $('#consult_results_refresh');
        if (!$b.length) { return; }
        $b.find('i').addClass('fa-spin');
        $.get($b.data('url')).done(function (html) { $('#consult_results_body').html(html); })
            .always(function () { $b.find('i').removeClass('fa-spin'); });
    }
    $('#consult_results_refresh').on('click', refreshResults);
    if ($('#consult_results_refresh').length) { setInterval(refreshResults, 60000); }

    @if($can_prescribe)
    var rxIndex = 0;
    var RX_T = @json($rx_js_text);
    function rxEsc(s) {
        return $('<div>').text(s == null ? '' : String(s)).html();
    }
    function rxT(key, rep) {
        var s = RX_T[key] || key;
        $.each(rep || {}, function (k, v) { s = s.split(':' + k).join(v); });
        return s;
    }
    function rxNum(n) {
        n = parseFloat(n) || 0;
        return (Math.round(n * 100) / 100).toLocaleString();
    }
    // availability of one medicine at the visit's location, as small labels
    function rxAvailHtml(d) {
        var unit = d.unit ? ' ' + rxEsc(d.unit) : '';
        var cls = {in_stock: 'success', low: 'warning', out: 'danger', expired_only: 'danger', not_tracked: 'success', not_here: 'default'}[d.status] || 'default';
        var txt = {
            in_stock: rxT('rx_avail_in_stock', {qty: rxNum(d.here) + unit}),
            low: rxT('rx_avail_low', {qty: rxNum(d.here) + unit}),
            out: rxT('rx_avail_out'),
            expired_only: rxT('rx_avail_expired_only', {qty: rxNum(d.expired_here)}),
            not_tracked: rxT('rx_avail_not_tracked'),
            not_here: rxT('rx_avail_not_here', {location: rxEsc(RX_T._location)}),
        }[d.status];
        var h = '<span class="label label-' + cls + ' rx-avail-status" data-status="' + d.status + '">' + txt + '</span>';
        if (d.next_expiry && (d.status == 'in_stock' || d.status == 'low')) {
            h += d.expires_soon
                ? '<span class="text-warning rx-avail-expiry"><i class="fas fa-hourglass-half"></i> ' + rxT('rx_avail_expires_soon', {date: rxEsc(d.next_expiry_formatted)}) + '</span>'
                : '<span class="text-muted rx-avail-expiry">' + rxT('rx_avail_expiry', {date: rxEsc(d.next_expiry_formatted)}) + '</span>';
        }
        if (d.expired_here > 0 && d.status != 'expired_only') {
            h += '<span class="text-danger rx-avail-expired"><i class="fas fa-exclamation-triangle"></i> ' + rxT('rx_avail_expired_qty', {qty: rxNum(d.expired_here)}) + '</span>';
        }
        $.each(d.elsewhere || [], function (_, e) {
            h += '<span class="text-muted rx-avail-other"><i class="fas fa-warehouse"></i> ' + rxT('rx_avail_other_loc', {qty: rxNum(e.qty), location: rxEsc(e.location)}) + '</span>';
        });
        return '<div class="rx-avail">' + h + '</div>';
    }
    // "only N in stock here" under a line the clinic is to give, when the quantity is above it
    function rxCheckRow($tr) {
        var here = $tr.data('here');
        var outside = $tr.find('.rx_outside').is(':checked') || $tr.data('free') == 1;
        $tr.toggleClass('rx-is-outside', outside);
        var $w = $tr.find('.rx-over');
        var qty = parseFloat($tr.find('.rx_qty').val()) || 0;
        if (! outside && here !== undefined && here !== '' && here !== null && qty > parseFloat(here)) {
            $w.html('<i class="fas fa-exclamation-triangle"></i> ' + rxT('rx_qty_over_stock', {qty: rxNum(here)})).show();
        } else {
            $w.hide().empty();
        }
    }
    /*
     * Quantity from dosage x times a day x days, e.g. "1 tablet" x "3 times daily" x "5 days" = 15.
     * Only for counted forms (tablets, capsules, sachets...) and only until the doctor types a quantity.
     */
    function rxDose(s) {
        s = String(s || '').trim().toLowerCase();
        var m = s.match(/^(½|¼|¾|\d+(?:[.,]\d+)?(?:\s*\/\s*\d+)?)\s*(.*)$/);
        if (!m || !/(tab|comp|cp\b|cap|g[eé]l|sachet|amp|supp|vial|flacon|pessar|ovule|pill|pi[eè]ce|pc|unit)/.test(m[2])) { return null; }
        var n = m[1];
        if (n === '½') { return 0.5; } if (n === '¼') { return 0.25; } if (n === '¾') { return 0.75; }
        if (n.indexOf('/') > 0) { var f = n.split('/'); return parseFloat(f[0]) / parseFloat(f[1]); }
        return parseFloat(n.replace(',', '.'));
    }
    function rxPerDay(s) {
        s = String(s || '').toLowerCase();
        var m;
        if (/(prn|when needed|si besoin|as needed)/.test(s)) { return null; }
        if (/(stat|single dose|dose unique|immediately)/.test(s)) { return 'once'; }
        if ((m = s.match(/(?:every|toutes les)\s*(\d+)\s*(?:h|hours|heures)/))) { return 24 / parseInt(m[1], 10); }
        if (/(once a week|1 fois par semaine|weekly|hebdo)/.test(s)) { return 1 / 7; }
        if (/\bqds\b|\bqid\b/.test(s)) { return 4; }
        if (/\btds\b|\btid\b/.test(s)) { return 3; }
        if (/\bbd\b|\bbid\b|twice/.test(s)) { return 2; }
        if ((m = s.match(/(\d+)\s*(?:x|fois|times)/))) { return parseInt(m[1], 10); }
        if (/\bod\b|once|morning|night|matin|soir|nocte|mane|coucher/.test(s)) { return 1; }
        return null;
    }
    function rxDays(s) {
        s = String(s || '').toLowerCase();
        var m;
        if (/(single dose|dose unique)/.test(s)) { return 'once'; }
        if ((m = s.match(/(\d+)\s*(?:days?|jours?|j\b|d\b)/))) { return parseInt(m[1], 10); }
        if ((m = s.match(/(\d+)\s*(?:weeks?|semaines?|sem\b|wk)/))) { return 7 * parseInt(m[1], 10); }
        if ((m = s.match(/(\d+)\s*(?:months?|mois)/))) { return 30 * parseInt(m[1], 10); }
        return null;
    }
    function rxAutoQty($tr) {
        var $q = $tr.find('.rx_qty'), $hint = $tr.find('.rx-qty-hint');
        if ($q.data('manual')) { $hint.text(''); return; }
        var dose = rxDose($tr.find('input[name$="[dosage]"]').val());
        var per = rxPerDay($tr.find('input[name$="[frequency]"]').val());
        var days = rxDays($tr.find('input[name$="[duration]"]').val());
        var qty = null, how = '';
        if (dose && (per === 'once' || days === 'once')) {
            qty = dose; how = rxNum(dose);
        } else if (dose && per && days) {
            qty = dose * per * days; how = rxNum(dose) + ' × ' + rxNum(per) + ' × ' + days;
        }
        if (qty) {
            qty = Math.ceil(qty - 1e-9);
            $q.val(qty);
            $hint.text('= ' + how);
            rxCheckRow($tr);
        } else {
            $hint.text('');
        }
    }

    function addPrescriptionRow(item, existing) {
        var i = rxIndex++;
        var cell, attrs;
        var idField = item.id ? '<input type="hidden" name="prescriptions[' + i + '][id]" value="' + parseInt(item.id, 10) + '">' : '';
        if (item.locked) {
            // already given, or closed by the dispensary: shown as it is, kept on the prescription
            var $locked = $('<tr class="rx-locked" data-locked="1">' +
                '<td><strong>' + rxEsc(item.name) + '</strong>' + idField +
                    '<div class="rx-avail"><span class="label label-default"><i class="fas fa-lock"></i> ' + rxEsc(item.state_label || '') + '</span></div></td>' +
                '<td>' + rxEsc(item.dosage || '-') + '</td><td>' + rxEsc(item.frequency || '-') + '</td><td>' + rxEsc(item.duration || '-') + '</td>' +
                '<td>' + rxNum(item.quantity) + '</td><td>' + rxEsc(item.notes || '') + '</td><td></td></tr>');
            $locked.appendTo('#prescription_rows');
            return;
        }
        if (item.free_text) {
            // not in the system: the typed name, always "to buy elsewhere"
            attrs = ' data-free="1"';
            cell = '<input type="text" name="prescriptions[' + i + '][drug_name]" class="form-control input-sm rx_drug_name" maxlength="191" required value="' + rxEsc(item.name) + '" aria-label="{{ __('lang_v1.medicine') }}">' + idField +
                '<div class="rx-avail"><span class="label label-warning"><i class="fas fa-store-slash"></i> ' + rxT('rx_not_in_stock_tag') + '</span></div>';
        } else {
            var outside = existing ? !!item.outside : !item.can_give;
            attrs = ' data-variation="' + parseInt(item.variation_id, 10) + '" data-status="' + rxEsc(item.status) + '"' + (item.here !== null && item.here !== undefined ? ' data-here="' + parseFloat(item.here) + '"' : '');
            cell = '<strong>' + rxEsc(item.name) + '</strong>' + (item.status ? rxAvailHtml(item) : '') + idField +
                '<input type="hidden" name="prescriptions[' + i + '][variation_id]" value="' + parseInt(item.variation_id, 10) + '">' +
                '<label class="rx-outside-lbl" title="' + rxEsc(rxT('rx_buys_outside_help')) + '"><input type="checkbox" class="rx_outside" name="prescriptions[' + i + '][outside]" value="1"' + (outside ? ' checked' : '') + '> ' + rxT('rx_buys_outside') + '</label>' +
                '<div class="rx-over text-warning" style="display:none;"></div>';
        }
        var field = function (name, list, ph) {
            return '<td><input type="text" name="prescriptions[' + i + '][' + name + ']" aria-label="' + rxEsc(RX_T['_label_' + name]) + '" class="form-control input-sm dl-ac" data-list="' + list + '" placeholder="' + rxEsc(ph) + '" value="' + rxEsc(existing ? item[name] : '') + '"></td>';
        };
        var row = '<tr' + attrs + '>' +
            '<td>' + cell + '</td>' +
            field('dosage', 'rx_dosage', '{{ __('lang_v1.dosage_eg') }}') +
            field('frequency', 'rx_frequency', '{{ __('lang_v1.frequency_eg') }}') +
            field('duration', 'rx_duration', '{{ __('lang_v1.duration_eg') }}') +
            '<td><input type="number" step="0.01" min="0" name="prescriptions[' + i + '][quantity]" aria-label="{{ __('sale.qty') }}" class="form-control input-sm rx_qty" value="' + (existing ? rxEsc(item.quantity) : '1') + '">' +
                '<small class="text-muted rx-qty-hint"></small></td>' +
            field('notes', 'rx_instruction', '') +
            '<td class="text-center"><a href="#" class="text-danger remove_rx_row" title="{{ __('messages.delete') }}"><i class="fa fa-trash"></i></a></td>' +
        '</tr>';
        var $tr = $(row).appendTo('#prescription_rows');
        if (existing) {
            $tr.find('.rx_qty').data('manual', 1); // what the doctor saved stays as it is
        }
        rxCheckRow($tr);
        if (!existing) {
            $tr.find(item.free_text ? '.rx_drug_name' : 'input[name$="[dosage]"]').trigger('focus');
        }
    }

    $('#search_prescription_product').autocomplete({
        source: function (request, response) {
            var term = $.trim(request.term);
            $.getJSON('{{ action([\App\Http\Controllers\ConsultationController::class, 'drugSearch']) }}', {
                term: term,
                visit_id: {{ (int) $visit->id }},
            }).done(function (data) {
                // last choice: a medicine the clinic does not have, typed as it is
                (data = data || []).push({free_text: true, name: term});
                response(data);
            }).fail(function () {
                response([{free_text: true, name: term}]);
            });
        },
        minLength: 2,
        delay: 250,
        autoFocus: true,
        focus: function () {
            return false; // keep what the doctor typed in the box
        },
        select: function (event, ui) {
            if (ui.item.free_text || ui.item.variation_id) {
                addPrescriptionRow(ui.item);
            }
            $(this).val('');
            return false;
        },
    }).on('keydown', function (e) {
        if (e.which == 13) {
            e.preventDefault(); // Enter picks the highlighted medicine; never submits the consultation
        }
    }).autocomplete('instance')._renderItem = function (ul, item) {
        var html = item.free_text
            ? '<div class="rx-ac-item rx-ac-free"><span><i class="fas fa-pen"></i> ' + rxT('rx_free_text_option', {name: rxEsc(item.name)}) + '</span></div>'
            : '<div class="rx-ac-item"><span>' + rxEsc(item.name) + '</span>' + rxAvailHtml(item) + '</div>';
        return $('<li>').append(html).appendTo(ul);
    };

    $(document).on('input change', '#prescription_rows .rx_qty, #prescription_rows .rx_outside', function () {
        rxCheckRow($(this).closest('tr'));
    });
    // a quantity typed by the doctor is theirs; dosage / frequency / duration fill it in until then
    $(document).on('input', '#prescription_rows .rx_qty', function (e) {
        if (e.originalEvent) { $(this).data('manual', 1); $(this).closest('tr').find('.rx-qty-hint').text(''); }
    });
    $(document).on('input change', '#prescription_rows input[name$="[dosage]"], #prescription_rows input[name$="[frequency]"], #prescription_rows input[name$="[duration]"]', function () {
        rxAutoQty($(this).closest('tr'));
    });

    // continuing a consultation: its prescription as saved
    $.each(@json($rx_rows), function (_, item) { addPrescriptionRow(item, true); });
    $(document).on('click', '.remove_rx_row', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });
    @endif

    __page_leave_confirmation('#consultation_form');
});
</script>
@endsection
