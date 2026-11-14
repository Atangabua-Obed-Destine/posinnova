@extends('layouts.app')
@section('title', __('lang_v1.new_consultation'))

@php
    $patient = $visit->patient;
    $pd = optional($patient)->patientDetails;
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
    $has_allergies = ! empty(optional($pd)->known_allergies);
    $can_prescribe = auth()->user()->can('consultation.prescribe');
    // texts for the prescription search (built here: @json takes no multi-line array)
    $rx_js_text = ['_location' => optional($visit->location)->name ?: ''];
    foreach (['rx_avail_in_stock', 'rx_avail_low', 'rx_avail_out', 'rx_avail_expired_only', 'rx_avail_not_tracked', 'rx_avail_not_here',
        'rx_avail_other_loc', 'rx_avail_expiry', 'rx_avail_expires_soon', 'rx_avail_expired_qty', 'rx_buys_outside', 'rx_buys_outside_help',
        'rx_qty_over_stock', 'rx_free_text_option', 'rx_not_in_stock_tag'] as $k) {
        // placeholders stay as :qty / :location... for the script to fill in
        $rx_js_text[$k] = trans('clinic.'.$k);
    }
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

    {{-- Vitals (filled in from the front desk when they were taken there) --}}
    @php
        $num = fn ($v) => $v === null ? null : (str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v);
        $front_vitals = $visit->hasVitals() ? collect(\App\Visit::VITALS)->mapWithKeys(fn ($f) => [$f => $num($visit->{$f})])->all() : [];
    @endphp
    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.vitals')])
        @if ($visit->hasVitals())
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
            {{-- admission only makes sense when the clinic uses wards --}}
            @if (\App\Utils\ClinicSettings::sectionOn(session('user.business_id'), 'wards'))
            <div class="col-sm-4">
                <div class="form-group">
                    <br>
                    <label class="checkbox-inline">
                        {!! Form::checkbox('admission_requested', 1, false) !!}
                        @lang('lang_v1.request_admission')
                    </label>
                </div>
            </div>
            @endif
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
    function addPrescriptionRow(item) {
        var i = rxIndex++;
        var cell, attrs;
        if (item.free_text) {
            // not in the system: the typed name, always "to buy elsewhere"
            attrs = ' data-free="1"';
            cell = '<input type="text" name="prescriptions[' + i + '][drug_name]" class="form-control input-sm rx_drug_name" maxlength="191" required value="' + rxEsc(item.name) + '" aria-label="{{ __('lang_v1.medicine') }}">' +
                '<div class="rx-avail"><span class="label label-warning"><i class="fas fa-store-slash"></i> ' + rxT('rx_not_in_stock_tag') + '</span></div>';
        } else {
            attrs = ' data-variation="' + parseInt(item.variation_id, 10) + '" data-status="' + rxEsc(item.status) + '"' + (item.here !== null && item.here !== undefined ? ' data-here="' + parseFloat(item.here) + '"' : '');
            cell = '<strong>' + rxEsc(item.name) + '</strong>' + rxAvailHtml(item) +
                '<input type="hidden" name="prescriptions[' + i + '][variation_id]" value="' + parseInt(item.variation_id, 10) + '">' +
                '<label class="rx-outside-lbl" title="' + rxEsc(rxT('rx_buys_outside_help')) + '"><input type="checkbox" class="rx_outside" name="prescriptions[' + i + '][outside]" value="1"' + (item.can_give ? '' : ' checked') + '> ' + rxT('rx_buys_outside') + '</label>' +
                '<div class="rx-over text-warning" style="display:none;"></div>';
        }
        var row = '<tr' + attrs + '>' +
            '<td>' + cell + '</td>' +
            '<td><input type="text" name="prescriptions[' + i + '][dosage]" aria-label="{{ __('lang_v1.dosage') }}" class="form-control input-sm" placeholder="{{ __('lang_v1.dosage_eg') }}"></td>' +
            '<td><input type="text" name="prescriptions[' + i + '][frequency]" aria-label="{{ __('lang_v1.frequency') }}" class="form-control input-sm" placeholder="{{ __('lang_v1.frequency_eg') }}"></td>' +
            '<td><input type="text" name="prescriptions[' + i + '][duration]" aria-label="{{ __('lang_v1.duration') }}" class="form-control input-sm" placeholder="{{ __('lang_v1.duration_eg') }}"></td>' +
            '<td><input type="number" step="0.01" min="0" name="prescriptions[' + i + '][quantity]" aria-label="{{ __('sale.qty') }}" class="form-control input-sm rx_qty" value="1"></td>' +
            '<td><input type="text" name="prescriptions[' + i + '][notes]" aria-label="{{ __('lang_v1.notes') }}" class="form-control input-sm"></td>' +
            '<td class="text-center"><a href="#" class="text-danger remove_rx_row"><i class="fa fa-trash"></i></a></td>' +
        '</tr>';
        var $tr = $(row).appendTo('#prescription_rows');
        rxCheckRow($tr);
        $tr.find(item.free_text ? '.rx_drug_name' : 'input[name$="[dosage]"]').trigger('focus');
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
    $(document).on('click', '.remove_rx_row', function (e) {
        e.preventDefault();
        $(this).closest('tr').remove();
    });
    @endif

    __page_leave_confirmation('#consultation_form');
});
</script>
@endsection
