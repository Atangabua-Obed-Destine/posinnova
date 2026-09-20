@extends('layouts.app')
@section('title', __('lang_v1.view_consultation'))

@php
    $c = $consultation;
    $patient = $c->patient;
    $pd = optional($patient)->patientDetails;
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ $patient->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $c->ref_no }}</span>
                    {{ __('lang_v1.consultation_type_' . $c->consultation_type) }}{{ $c->specialty ? ' · ' . $c->specialty : '' }} ·
                    <span class="label {{ $c->status == 'final' ? 'label-success' : 'label-warning' }}">{{ __('lang_v1.consultation_status_' . $c->status) }}</span> ·
                    {{ @format_datetime($c->created_at) }}
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                @if($c->status == 'draft')
                    @can('consultation.finalize')
                        <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'finalize'], [$c->id]) }}"
                            class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                            <i class="fas fa-check"></i> @lang('lang_v1.finalize_consultation')
                        </a>
                    @endcan
                @endif
                @php $pending_rx = $c->prescriptions->where('is_dispensed', 0)->count(); @endphp
                @if($pending_rx > 0 && auth()->user()->can('sell.create'))
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}?prescription_id={{ $c->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                        <i class="fas fa-prescription-bottle-alt"></i> @lang('lang_v1.dispense_in_pos')
                    </a>
                @endif
                @can('lab_request.create')
                    <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'create']) }}?visit_id={{ $c->visit_id }}&consultation_id={{ $c->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-vial"></i> @lang('lang_v1.request_lab_tests')
                    </a>
                @endcan
                @can('imaging_request.create')
                    <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'create']) }}?visit_id={{ $c->visit_id }}&consultation_id={{ $c->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-x-ray"></i> @lang('lang_v1.request_imaging')
                    </a>
                @endcan
                @if($c->prescriptions->count() > 0)
                    <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'prescription'], [$c->id]) }}" target="_blank"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-print"></i> @lang('lang_v1.print_prescription')
                    </a>
                @endif
                <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$c->visit_id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('lang_v1.back_to_visit')
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.vitals')])
                <table class="table table-bordered">
                    <tr><th width="50%">@lang('lang_v1.weight_kg')</th><td>{{ $c->vitals_weight ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.height_cm')</th><td>{{ $c->vitals_height ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.bmi')</th><td>{{ $c->bmi ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.blood_pressure')</th><td>{{ ($c->vitals_bp_systolic && $c->vitals_bp_diastolic) ? $c->vitals_bp_systolic . '/' . $c->vitals_bp_diastolic . ' mmHg' : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.temperature_c')</th><td>{{ $c->vitals_temperature ? $c->vitals_temperature . ' °C' : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.pulse_bpm')</th><td>{{ $c->vitals_pulse ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.spo2')</th><td>{{ $c->vitals_spo2 ? $c->vitals_spo2 . '%' : '-' }}</td></tr>
                </table>
            @endcomponent
        </div>
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.examination_and_diagnosis')])
                <p><strong>@lang('lang_v1.chief_complaint'):</strong><br>{{ $c->chief_complaint ?: '-' }}</p>
                <p><strong>@lang('lang_v1.examination_notes'):</strong><br>{{ $c->examination_notes ?: '-' }}</p>
                <p><strong>@lang('lang_v1.diagnosis'):</strong><br>{{ $c->diagnosis ?: '-' }}</p>
                @if($c->referralDepartment)
                    <p><strong>@lang('lang_v1.referred_to'):</strong> {{ $c->referralDepartment->name }}</p>
                @endif
                @if($c->admission_requested)
                    <p><span class="label label-warning"><i class="fas fa-procedures"></i> @lang('lang_v1.admission_requested')</span></p>
                @endif
                <p><strong>@lang('lang_v1.consulted_by'):</strong> {{ optional($c->consultedBy)->user_full_name ?? '-' }}</p>
                <p><strong>@lang('lang_v1.consultation_fee'):</strong> <span class="display_currency" data-currency_symbol="true">{{ $c->consultation_fee }}</span></p>
            @endcomponent
        </div>
    </div>

    @if($c->prescriptions->count() > 0)
        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.prescription')])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('lang_v1.medicine')</th>
                            <th>@lang('lang_v1.dosage')</th>
                            <th>@lang('lang_v1.frequency')</th>
                            <th>@lang('lang_v1.duration')</th>
                            <th>@lang('sale.qty')</th>
                            <th>@lang('lang_v1.notes')</th>
                            <th>@lang('lang_v1.dispensed')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($c->prescriptions as $rx)
                            <tr>
                                <td>{{ optional($rx->product)->name }} {{ optional($rx->variation)->name && optional($rx->variation)->name != 'DUMMY' ? '(' . $rx->variation->name . ')' : '' }}</td>
                                <td>{{ $rx->dosage ?: '-' }}</td>
                                <td>{{ $rx->frequency ?: '-' }}</td>
                                <td>{{ $rx->duration ?: '-' }}</td>
                                <td>{{ $rx->quantity + 0 }}</td>
                                <td>{{ $rx->notes ?: '-' }}</td>
                                <td>
                                    @if($rx->is_dispensed)
                                        <span class="label label-success">@lang('lang_v1.dispensed')</span>
                                    @else
                                        <span class="label label-default">@lang('lang_v1.pending')</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endcomponent
    @endif

</section>
@endsection
