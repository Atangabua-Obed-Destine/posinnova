@extends('layouts.app')
@section('title', __('lang_v1.view_admission'))

@php
    $a = $admission;
    $pd = optional($a->patient)->patientDetails;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ optional($a->patient)->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $a->ref_no }}</span>
                    {{ optional($a->ward)->name }} @if($a->bed) · {{ __('lang_v1.bed') }} {{ $a->bed->bed_number }} @endif ·
                    <span class="label {{ $a->status == 'active' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.admission_status_' . $a->status) }}</span>
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                @if($a->status == 'active')
                    @if(auth()->user()->can('ward_dispense.create') && auth()->user()->can('sell.create'))
                        <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}?admission_id={{ $a->id }}"
                            class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                            <i class="fas fa-pills"></i> @lang('lang_v1.dispense_to_patient')
                        </a>
                    @endif
                    @can('ward_transfer.create')
                        <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" data-toggle="modal" data-target="#transfer_modal">
                            <i class="fas fa-exchange-alt"></i> @lang('lang_v1.transfer_ward')
                        </button>
                    @endcan
                    @can('admission.discharge')
                        <a href="{{ action([\App\Http\Controllers\AdmissionController::class, 'dischargeForm'], [$a->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                            <i class="fas fa-sign-out-alt"></i> @lang('lang_v1.discharge')
                        </a>
                    @endcan
                @endif
                <a href="{{ action([\App\Http\Controllers\AdmissionController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('messages.back')
                </a>
            </div>
        </div>
    </div>

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs">
            <li class="active"><a href="#adm_overview" data-toggle="tab"><i class="fas fa-info-circle"></i> @lang('lang_v1.overview')</a></li>
            <li><a href="#adm_caretakers" data-toggle="tab"><i class="fas fa-user-friends"></i> @lang('lang_v1.caretakers')</a></li>
            <li><a href="#adm_vitals" data-toggle="tab"><i class="fas fa-heartbeat"></i> @lang('lang_v1.vitals_chart')</a></li>
            <li><a href="#adm_mar" data-toggle="tab"><i class="fas fa-pills"></i> @lang('lang_v1.mar')</a></li>
            <li><a href="#adm_notes" data-toggle="tab"><i class="fas fa-notes-medical"></i> @lang('lang_v1.nursing_notes')</a></li>
            <li><a href="#adm_transfers" data-toggle="tab"><i class="fas fa-exchange-alt"></i> @lang('lang_v1.ward_transfers')</a></li>
            <li><a href="#adm_bill" data-toggle="tab"><i class="fas fa-file-invoice"></i> @lang('lang_v1.running_bill')</a></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane active" id="adm_overview">
                <table class="table table-bordered">
                    <tr><th width="30%">@lang('lang_v1.ward')</th><td>{{ optional($a->ward)->name }}</td></tr>
                    <tr><th>@lang('lang_v1.bed')</th><td>{{ optional($a->bed)->bed_number ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.admission_date')</th><td>{{ @format_datetime($a->admission_date) }}</td></tr>
                    <tr><th>@lang('lang_v1.days_admitted')</th><td>{{ $a->days_admitted }}</td></tr>
                    <tr><th>@lang('lang_v1.admitting_doctor')</th><td>{{ optional($a->admittingDoctor)->user_full_name ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.admitted_by')</th><td>{{ optional($a->admittedBy)->user_full_name ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.deposit')</th><td><span class="display_currency" data-currency_symbol="true">{{ $a->deposit_amount }}</span></td></tr>
                    <tr><th>@lang('lang_v1.reason_for_admission')</th><td>{{ $a->reason_for_admission ?: '-' }}</td></tr>
                    @if($a->status == 'discharged')
                        <tr><th>@lang('lang_v1.discharge_date')</th><td>{{ @format_datetime($a->discharge_date) }}</td></tr>
                        <tr><th>@lang('lang_v1.discharge_notes')</th><td>{{ $a->discharge_notes ?: '-' }}</td></tr>
                    @endif
                </table>
            </div>

            <div class="tab-pane" id="adm_caretakers">
                @can('admission.update')
                    @if($a->status == 'active')
                        {!! Form::open(['url' => action([\App\Http\Controllers\AdmissionController::class, 'addCaretaker'], [$a->id]), 'method' => 'post', 'class' => 'form-inline tw-mb-3']) !!}
                            {!! Form::text('name', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.caretaker_name'), 'required']) !!}
                            {!! Form::text('phone', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.caretaker_phone')]) !!}
                            {!! Form::text('relationship', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.relationship')]) !!}
                            <label class="checkbox-inline">{!! Form::checkbox('is_primary', 1) !!} @lang('lang_v1.primary')</label>
                            <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white">@lang('messages.add')</button>
                        {!! Form::close() !!}
                    @endif
                @endcan
                <table class="table table-bordered table-striped">
                    <thead><tr><th>@lang('lang_v1.caretaker_name')</th><th>@lang('lang_v1.caretaker_phone')</th><th>@lang('lang_v1.relationship')</th><th>@lang('lang_v1.primary')</th></tr></thead>
                    <tbody>
                        @forelse($a->caretakers as $ct)
                            <tr>
                                <td>{{ $ct->name }}</td>
                                <td>{{ $ct->phone ?: '-' }}</td>
                                <td>{{ $ct->relationship ?: '-' }}</td>
                                <td>@if($ct->is_primary)<span class="label label-success">@lang('lang_v1.primary')</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="tab-pane" id="adm_transfers">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>@lang('lang_v1.date')</th><th>@lang('lang_v1.from')</th><th>@lang('lang_v1.to')</th><th>@lang('lang_v1.reason')</th></tr></thead>
                    <tbody>
                        @forelse($a->transfers->sortByDesc('transfer_date') as $t)
                            <tr>
                                <td>{{ @format_datetime($t->transfer_date) }}</td>
                                <td>{{ optional($t->fromWard)->name ?: '-' }}</td>
                                <td>{{ optional($t->toWard)->name ?: '-' }}</td>
                                <td>{{ $t->reason ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- ============ NURSING: VITALS ============ --}}
            <div class="tab-pane" id="adm_vitals">
                @php $lv = $nursing['latest_vital']; @endphp

                {{-- KPI strip --}}
                <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-4 tw-gap-3 tw-mb-4">
                    <div class="tw-rounded-xl tw-ring-1 {{ $nursing['kpis']['abnormal_latest'] ? 'tw-ring-red-200 tw-bg-red-50' : 'tw-ring-green-200 tw-bg-green-50' }} tw-p-3">
                        <p class="tw-text-xs tw-text-gray-500 tw-mb-1">@lang('lang_v1.latest_status')</p>
                        <p class="tw-text-base tw-font-bold {{ $nursing['kpis']['abnormal_latest'] ? 'tw-text-red-600' : 'tw-text-green-600' }}">
                            <i class="fas fa-{{ $nursing['kpis']['abnormal_latest'] ? 'exclamation-triangle' : 'check-circle' }}"></i>
                            {{ $nursing['kpis']['abnormal_latest'] ? __('lang_v1.needs_attention') : __('lang_v1.stable') }}
                        </p>
                    </div>
                    <div class="tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-bg-white tw-p-3">
                        <p class="tw-text-xs tw-text-gray-500 tw-mb-1">@lang('lang_v1.vitals_today')</p>
                        <p class="tw-text-xl tw-font-bold tw-text-gray-900">{{ $nursing['kpis']['vitals_today'] }}</p>
                    </div>
                    <div class="tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-bg-white tw-p-3">
                        <p class="tw-text-xs tw-text-gray-500 tw-mb-1">@lang('lang_v1.meds_given_today')</p>
                        <p class="tw-text-xl tw-font-bold tw-text-gray-900">{{ $nursing['kpis']['meds_given_today'] }}</p>
                    </div>
                    <div class="tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-bg-white tw-p-3">
                        <p class="tw-text-xs tw-text-gray-500 tw-mb-1">@lang('lang_v1.last_obs')</p>
                        <p class="tw-text-sm tw-font-semibold tw-text-gray-900">{{ $lv ? $lv->observed_at->diffForHumans() : '—' }}</p>
                    </div>
                </div>

                {{-- Trend sparklines --}}
                <div class="tw-grid tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-3 tw-mb-4">
                    @include('nursing.partials.sparkline', ['series' => $nursing['vital_series']['temperature'], 'label' => __('lang_v1.temperature_c'), 'unit' => '°C', 'color' => '#e67e22', 'lo' => 36.1, 'hi' => 37.9])
                    @include('nursing.partials.sparkline', ['series' => $nursing['vital_series']['pulse'], 'label' => __('lang_v1.pulse_bpm'), 'unit' => 'bpm', 'color' => '#e74c3c', 'lo' => 60, 'hi' => 100])
                    @include('nursing.partials.sparkline', ['series' => $nursing['vital_series']['spo2'], 'label' => __('lang_v1.spo2'), 'unit' => '%', 'color' => '#2980b9', 'lo' => 95, 'hi' => 100])
                    @include('nursing.partials.sparkline', ['series' => $nursing['vital_series']['bp_systolic'], 'label' => __('lang_v1.bp_systolic'), 'unit' => 'mmHg', 'color' => '#8e44ad', 'lo' => 90, 'hi' => 130])
                </div>

                @if($a->status == 'active' && auth()->user()->can('nursing.chart_vitals'))
                    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.record_vitals')])
                        {!! Form::open(['url' => action([\App\Http\Controllers\NursingController::class, 'storeVitals'], [$a->id]), 'method' => 'post']) !!}
                        <div class="row">
                            <div class="col-sm-2 col-xs-6"><div class="form-group">{!! Form::label('temperature', __('lang_v1.temperature_c')) !!}{!! Form::number('temperature', null, ['class' => 'form-control', 'step' => '0.1', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-2 col-xs-6"><div class="form-group">{!! Form::label('pulse', __('lang_v1.pulse_bpm')) !!}{!! Form::number('pulse', null, ['class' => 'form-control', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-2 col-xs-6"><div class="form-group">{!! Form::label('respiratory_rate', __('lang_v1.resp_rate')) !!}{!! Form::number('respiratory_rate', null, ['class' => 'form-control', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-3 col-xs-12"><div class="form-group">{!! Form::label('bp', __('lang_v1.blood_pressure')) !!}<div class="tw-flex tw-items-center tw-gap-1">{!! Form::number('bp_systolic', null, ['class' => 'form-control', 'placeholder' => 'Sys']) !!}<span>/</span>{!! Form::number('bp_diastolic', null, ['class' => 'form-control', 'placeholder' => 'Dia']) !!}</div></div></div>
                            <div class="col-sm-3 col-xs-12"><div class="form-group">{!! Form::label('spo2', __('lang_v1.spo2')) !!}{!! Form::number('spo2', null, ['class' => 'form-control', 'min' => 0, 'max' => 100]) !!}</div></div>
                            <div class="col-sm-2 col-xs-6"><div class="form-group">{!! Form::label('pain_score', __('lang_v1.pain_score')) !!}{!! Form::number('pain_score', null, ['class' => 'form-control', 'min' => 0, 'max' => 10]) !!}</div></div>
                            <div class="col-sm-2 col-xs-6"><div class="form-group">{!! Form::label('blood_sugar', __('lang_v1.blood_sugar')) !!}{!! Form::number('blood_sugar', null, ['class' => 'form-control', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-2 col-xs-6"><div class="form-group">{!! Form::label('weight', __('lang_v1.weight_kg')) !!}{!! Form::number('weight', null, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-6"><div class="form-group">{!! Form::label('notes', __('lang_v1.notes')) !!}{!! Form::text('notes', null, ['class' => 'form-control']) !!}</div></div>
                        </div>
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white">@lang('lang_v1.record_vitals')</button>
                        {!! Form::close() !!}
                    @endcomponent
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-condensed table-striped">
                        <thead><tr>
                            <th>@lang('lang_v1.time')</th><th>@lang('lang_v1.temperature_c')</th><th>@lang('lang_v1.pulse_bpm')</th>
                            <th>@lang('lang_v1.resp_rate')</th><th>@lang('lang_v1.blood_pressure')</th><th>@lang('lang_v1.spo2')</th>
                            <th>@lang('lang_v1.pain_score')</th><th>@lang('lang_v1.by')</th>
                        </tr></thead>
                        <tbody>
                            @forelse($nursing['vitals']->sortByDesc('observed_at') as $v)
                                <tr @if($v->is_abnormal) style="background-color:#fff3cd;" @endif>
                                    <td>{{ @format_datetime($v->observed_at) }}</td>
                                    <td>{{ $v->temperature !== null ? $v->temperature + 0 : '-' }}</td>
                                    <td>{{ $v->pulse ?? '-' }}</td>
                                    <td>{{ $v->respiratory_rate ?? '-' }}</td>
                                    <td>{{ ($v->bp_systolic && $v->bp_diastolic) ? $v->bp_systolic.'/'.$v->bp_diastolic : '-' }}</td>
                                    <td>{{ $v->spo2 ? $v->spo2.'%' : '-' }}</td>
                                    <td>{{ $v->pain_score !== null ? $v->pain_score.'/10' : '-' }}</td>
                                    <td>{{ optional($v->recordedBy)->user_full_name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center text-muted">@lang('lang_v1.no_vitals_yet')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============ NURSING: MAR ============ --}}
            <div class="tab-pane" id="adm_mar">
                {{-- Ordered drugs context (from the doctor's prescriptions) --}}
                @if($nursing['prescriptions']->count() > 0)
                    <div class="tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-bg-gray-50 tw-p-3 tw-mb-4">
                        <p class="tw-text-xs tw-font-semibold tw-text-gray-500 tw-mb-2">@lang('lang_v1.ordered_medications')</p>
                        <div class="tw-flex tw-flex-wrap tw-gap-2">
                            @foreach($nursing['prescriptions'] as $rx)
                                <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline mar_quick"
                                    data-drug="{{ e($rx->displayName()) }}" data-dose="{{ e($rx->dosage) }}" data-rx="{{ $rx->id }}" data-product="{{ $rx->product_id }}">
                                    <i class="fas fa-prescription-bottle"></i> {{ $rx->displayName() }}
                                    @if($rx->frequency)<small class="text-muted">· {{ $rx->frequency }}</small>@endif
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($a->status == 'active' && auth()->user()->can('nursing.record_medication'))
                    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.administer_medication')])
                        {!! Form::open(['url' => action([\App\Http\Controllers\NursingController::class, 'recordMedication'], [$a->id]), 'method' => 'post', 'id' => 'mar_form']) !!}
                        {!! Form::hidden('consultation_prescription_id', null, ['id' => 'mar_rx_id']) !!}
                        {!! Form::hidden('product_id', null, ['id' => 'mar_product_id']) !!}
                        <div class="row">
                            <div class="col-sm-4"><div class="form-group">{!! Form::label('drug_name', __('lang_v1.drug_name') . ':*') !!}{!! Form::text('drug_name', null, ['class' => 'form-control', 'id' => 'mar_drug_name', 'required']) !!}</div></div>
                            <div class="col-sm-2"><div class="form-group">{!! Form::label('dose', __('lang_v1.dose')) !!}{!! Form::text('dose', null, ['class' => 'form-control', 'id' => 'mar_dose']) !!}</div></div>
                            <div class="col-sm-2"><div class="form-group">{!! Form::label('route', __('lang_v1.route')) !!}{!! Form::select('route', ['' => '-', 'PO' => 'PO', 'IV' => 'IV', 'IM' => 'IM', 'SC' => 'SC', 'PR' => 'PR', 'Topical' => 'Topical', 'Inhaled' => 'Inhaled'], null, ['class' => 'form-control']) !!}</div></div>
                            <div class="col-sm-2"><div class="form-group">{!! Form::label('status', __('lang_v1.status') . ':*') !!}{!! Form::select('status', ['given' => __('lang_v1.mar_given'), 'refused' => __('lang_v1.mar_refused'), 'held' => __('lang_v1.mar_held'), 'missed' => __('lang_v1.mar_missed')], 'given', ['class' => 'form-control', 'required']) !!}</div></div>
                            <div class="col-sm-2"><div class="form-group">{!! Form::label('administered_at', __('lang_v1.time')) !!}{!! Form::text('administered_at', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control']) !!}</div></div>
                            <div class="col-sm-12"><div class="form-group">{!! Form::label('notes', __('lang_v1.notes')) !!}{!! Form::text('notes', null, ['class' => 'form-control']) !!}</div></div>
                        </div>
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">@lang('lang_v1.record_administration')</button>
                        {!! Form::close() !!}
                    @endcomponent
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>@lang('lang_v1.time')</th><th>@lang('lang_v1.drug_name')</th><th>@lang('lang_v1.dose')</th><th>@lang('lang_v1.route')</th><th>@lang('lang_v1.status')</th><th>@lang('lang_v1.by')</th><th>@lang('lang_v1.notes')</th></tr></thead>
                        <tbody>
                            @php $mar_labels = ['given'=>'label-success','refused'=>'label-danger','held'=>'label-warning','missed'=>'label-default']; @endphp
                            @forelse($nursing['mar'] as $m)
                                <tr>
                                    <td>{{ @format_datetime($m->administered_at) }}</td>
                                    <td>{{ $m->drug_name }}</td>
                                    <td>{{ $m->dose ?: '-' }}</td>
                                    <td>{{ $m->route ?: '-' }}</td>
                                    <td><span class="label {{ $mar_labels[$m->status] ?? 'label-default' }}">{{ __('lang_v1.mar_' . $m->status) }}</span></td>
                                    <td>{{ optional($m->administeredBy)->user_full_name ?? '-' }}</td>
                                    <td>{{ $m->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">@lang('lang_v1.no_mar_yet')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- ============ NURSING: NOTES ============ --}}
            <div class="tab-pane" id="adm_notes">
                @if($a->status == 'active' && auth()->user()->can('nursing.add_notes'))
                    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.add_nursing_note')])
                        {!! Form::open(['url' => action([\App\Http\Controllers\NursingController::class, 'storeNote'], [$a->id]), 'method' => 'post']) !!}
                        <div class="row">
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('shift', __('lang_v1.shift')) !!}{!! Form::select('shift', ['' => '-', 'morning' => __('lang_v1.morning'), 'afternoon' => __('lang_v1.afternoon'), 'night' => __('lang_v1.night')], null, ['class' => 'form-control']) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('note_type', __('lang_v1.note_type')) !!}{!! Form::select('note_type', ['general' => __('lang_v1.note_type_general'), 'observation' => __('lang_v1.note_type_observation'), 'intervention' => __('lang_v1.note_type_intervention'), 'incident' => __('lang_v1.note_type_incident'), 'handover' => __('lang_v1.note_type_handover')], 'general', ['class' => 'form-control']) !!}</div></div>
                            <div class="col-sm-12"><div class="form-group">{!! Form::label('note', __('lang_v1.note') . ':*') !!}{!! Form::textarea('note', null, ['class' => 'form-control', 'rows' => 3, 'required']) !!}</div></div>
                        </div>
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white">@lang('lang_v1.add_note')</button>
                        {!! Form::close() !!}
                    @endcomponent
                @endif

                {{-- Timeline --}}
                <div class="tw-relative tw-pl-6">
                    @php $note_icons = ['observation'=>'fa-eye','intervention'=>'fa-hand-holding-medical','incident'=>'fa-exclamation-triangle','handover'=>'fa-people-arrows','general'=>'fa-comment-medical']; @endphp
                    @forelse($nursing['notes'] as $n)
                        <div class="tw-relative tw-pb-4 tw-border-l-2 tw-border-gray-200 tw-pl-4 tw--ml-px">
                            <span class="tw-absolute tw--left-[9px] tw-top-1 tw-w-4 tw-h-4 tw-rounded-full tw-bg-indigo-500 tw-ring-2 tw-ring-white tw-flex tw-items-center tw-justify-center">
                                <i class="fas {{ $note_icons[$n->note_type] ?? 'fa-comment-medical' }} tw-text-white tw-text-[8px]"></i>
                            </span>
                            <div class="tw-flex tw-items-center tw-gap-2 tw-flex-wrap">
                                <span class="tw-text-sm tw-font-semibold tw-text-gray-900">{{ __('lang_v1.note_type_' . $n->note_type) }}</span>
                                @if($n->shift)<span class="label label-default">{{ __('lang_v1.' . $n->shift) }}</span>@endif
                                <span class="tw-text-xs tw-text-gray-400">{{ @format_datetime($n->noted_at) }} · {{ optional($n->recordedBy)->user_full_name }}</span>
                            </div>
                            <p class="tw-text-sm tw-text-gray-700 tw-mt-1 tw-mb-0" style="white-space: pre-wrap;">{{ $n->note }}</p>
                        </div>
                    @empty
                        <p class="text-muted text-center" style="padding:20px 0;">@lang('lang_v1.no_notes_yet')</p>
                    @endforelse
                </div>
            </div>

            <div class="tab-pane" id="adm_bill">
                @php
                    $total_amount = $charges->sum('final_total');
                    $deposit = $a->deposit_amount;
                    $balance = $total_amount - $deposit;
                @endphp

                {{-- Patient's overall outstanding due (across all charges, not just this admission) + Pay --}}
                <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3 tw-mb-3 tw-p-3 tw-rounded-lg tw-bg-gray-50 tw-ring-1 tw-ring-gray-200">
                    <div>
                        <span class="tw-text-sm tw-text-gray-500">@lang('lang_v1.patient_total_due')</span>
                        <div class="tw-text-xl tw-font-bold {{ $patient_total_due > 0 ? 'tw-text-red-600' : 'tw-text-green-600' }}">
                            <span class="display_currency" data-currency_symbol="true">{{ $patient_total_due }}</span>
                        </div>
                    </div>
                    @if(auth()->user()->can('sell.payments') && $patient_total_due > 0)
                        <a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$a->contact_id]) }}?type=sell"
                            class="pay_sale_due tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                            <i class="fas fa-money-bill-alt"></i> @lang('lang_v1.collect_payment')
                        </a>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('sale.invoice_no')</th>
                                <th>@lang('lang_v1.description')</th>
                                <th class="text-right">@lang('lang_v1.amount')</th>
                                <th>@lang('lang_v1.status')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($charges as $charge)
                                <tr>
                                    <td>{{ @format_date($charge->transaction_date) }}</td>
                                    <td>{{ $charge->invoice_no }}</td>
                                    <td>{{ $charge->additional_notes ?: '-' }}</td>
                                    <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $charge->final_total }}</span></td>
                                    <td>
                                        @if($charge->payment_status == 'paid')
                                            <span class="label label-success">@lang('lang_v1.paid')</span>
                                        @elseif($charge->payment_status == 'partial')
                                            <span class="label label-info">@lang('lang_v1.partial')</span>
                                        @else
                                            <span class="label label-warning">@lang('lang_v1.due')</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">@lang('lang_v1.no_charges_yet')</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-right">@lang('lang_v1.total_charges')</th>
                                <th class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $total_amount }}</span></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-right">@lang('lang_v1.deposit_paid')</th>
                                <th class="text-right">- <span class="display_currency" data-currency_symbol="true">{{ $deposit }}</span></th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="3" class="text-right">@lang('lang_v1.balance_due')</th>
                                <th class="text-right"><strong><span class="display_currency" data-currency_symbol="true">{{ $balance }}</span></strong></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="help-block">@lang('lang_v1.running_bill_note')</p>
            </div>
        </div>
    </div>

    {{-- Ward transfer modal --}}
    @if($a->status == 'active')
        @can('ward_transfer.create')
            <div class="modal fade" id="transfer_modal" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        {!! Form::open(['url' => action([\App\Http\Controllers\AdmissionController::class, 'transferWard'], [$a->id]), 'method' => 'post']) !!}
                        <div class="modal-header">
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                            <h4 class="modal-title">@lang('lang_v1.transfer_ward')</h4>
                        </div>
                        <div class="modal-body">
                            <div class="form-group">
                                {!! Form::label('to_ward_id', __('lang_v1.to_ward') . ':*') !!}
                                {!! Form::select('to_ward_id', \App\Ward::forDropdown(session('user.business_id'), true), null, ['class' => 'form-control', 'required']) !!}
                            </div>
                            <div class="form-group">
                                {!! Form::label('reason', __('lang_v1.reason') . ':') !!}
                                {!! Form::textarea('reason', null, ['class' => 'form-control', 'rows' => 2]) !!}
                            </div>
                            <p class="help-block">@lang('lang_v1.transfer_bed_note')</p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('lang_v1.transfer')</button>
                            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
                        </div>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        @endcan
    @endif

    {{-- Pay-due modal (reuse customer payment flow) --}}
    <div class="modal fade pay_contact_due_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

</section>
@endsection

@section('javascript')
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
    <script>
        $(document).ready(function () {
            // Re-open the relevant tab after a nursing form submit.
            @if(session('open_tab'))
                var target = '#{{ session('open_tab') }}';
                $('.nav-tabs a[href="' + target + '"]').tab('show');
            @endif

            // MAR quick-fill: clicking an ordered drug fills the administration form.
            $(document).on('click', '.mar_quick', function () {
                $('#mar_drug_name').val($(this).data('drug'));
                $('#mar_dose').val($(this).data('dose'));
                $('#mar_rx_id').val($(this).data('rx'));
                $('#mar_product_id').val($(this).data('product'));
                $('html, body').animate({ scrollTop: $('#mar_form').offset().top - 80 }, 300);
            });
        });
    </script>
@endsection
