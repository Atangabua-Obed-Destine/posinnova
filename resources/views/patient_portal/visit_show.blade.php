@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.view_visit'))
@section('heading', __('lang_v1.view_visit'))
@php $portal_group = 'records'; @endphp

@section('back')
    <a href="{{ route('portal.visits') }}" class="portal-press tw-inline-flex tw-items-center tw-gap-1 tw-text-indigo-600 tw-font-medium"><i class="fas fa-chevron-left"></i> <span class="tw-hidden sm:tw-inline">@lang('lang_v1.my_visits')</span></a>
@endsection

@section('content')
<div class="tw-rounded-2xl tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-p-4 tw-shadow-sm tw-mb-4">
    <div class="tw-flex tw-items-center tw-justify-between">
        <div>
            <p class="tw-font-semibold">{{ __('lang_v1.visit_type_' . $visit->type) }}</p>
            <p class="tw-text-xs tw-opacity-80">{{ @format_date($visit->visit_date) }} · {{ $visit->ref_no }}</p>
        </div>
        <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full tw-bg-white/20">{{ __('lang_v1.visit_status_' . $visit->status) }}</span>
    </div>
</div>

@forelse($visit->consultations->sortByDesc('created_at') as $c)
    <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm tw-mb-3">
        <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
            <p class="tw-text-sm tw-font-semibold tw-text-gray-800">{{ __('lang_v1.consultation_type_' . $c->consultation_type) }}{{ $c->specialty ? ' · '.$c->specialty : '' }}</p>
            <span class="tw-text-xs tw-text-gray-400">{{ @format_datetime($c->created_at) }}</span>
        </div>

        @if($c->chief_complaint)<p class="tw-text-sm tw-text-gray-600 tw-mb-1"><strong>@lang('lang_v1.chief_complaint'):</strong> {{ $c->chief_complaint }}</p>@endif
        @if($c->diagnosis)<p class="tw-text-sm tw-text-gray-600 tw-mb-1"><strong>@lang('lang_v1.diagnosis'):</strong> {{ $c->diagnosis }}</p>@endif
        <p class="tw-text-xs tw-text-gray-400 tw-mb-3">{{ __('lang_v1.consulted_by') }}: {{ optional($c->consultedBy)->user_full_name ?? '—' }}</p>

        @php $hasVitals = $c->vitals_temperature || $c->vitals_pulse || $c->vitals_bp_systolic || $c->vitals_spo2 || $c->vitals_weight; @endphp
        @if($hasVitals)
            <div class="tw-grid tw-grid-cols-3 sm:tw-grid-cols-5 tw-gap-2 tw-mb-3">
                @if($c->vitals_temperature)<div class="tw-rounded-xl tw-bg-orange-50 tw-p-2 tw-text-center"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.temperature_c')</p><p class="tw-text-sm tw-font-bold tw-text-orange-600">{{ $c->vitals_temperature + 0 }}°</p></div>@endif
                @if($c->vitals_pulse)<div class="tw-rounded-xl tw-bg-red-50 tw-p-2 tw-text-center"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.pulse_bpm')</p><p class="tw-text-sm tw-font-bold tw-text-red-600">{{ $c->vitals_pulse }}</p></div>@endif
                @if($c->vitals_bp_systolic && $c->vitals_bp_diastolic)<div class="tw-rounded-xl tw-bg-purple-50 tw-p-2 tw-text-center"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.blood_pressure')</p><p class="tw-text-sm tw-font-bold tw-text-purple-600">{{ $c->vitals_bp_systolic }}/{{ $c->vitals_bp_diastolic }}</p></div>@endif
                @if($c->vitals_spo2)<div class="tw-rounded-xl tw-bg-blue-50 tw-p-2 tw-text-center"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.spo2')</p><p class="tw-text-sm tw-font-bold tw-text-blue-600">{{ $c->vitals_spo2 }}%</p></div>@endif
                @if($c->vitals_weight)<div class="tw-rounded-xl tw-bg-gray-50 tw-p-2 tw-text-center"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.weight_kg')</p><p class="tw-text-sm tw-font-bold tw-text-gray-700">{{ $c->vitals_weight + 0 }}</p></div>@endif
            </div>
        @endif

        @if($c->prescriptions->count() > 0)
            <p class="tw-text-xs tw-font-semibold tw-text-gray-500 tw-mb-1.5">@lang('lang_v1.prescription')</p>
            <div class="tw-space-y-1.5">
                @foreach($c->prescriptions as $rx)
                    <div class="tw-flex tw-items-center tw-justify-between tw-text-sm tw-gap-2">
                        <span class="tw-text-gray-700 tw-truncate">{{ optional($rx->product)->name }} <span class="tw-text-gray-400">· {{ $rx->dosage }} {{ $rx->frequency }}</span></span>
                        <span class="tw-text-[10px] tw-px-1.5 tw-py-0.5 tw-rounded-full tw-shrink-0 {{ $rx->is_dispensed ? 'tw-bg-green-100 tw-text-green-700' : 'tw-bg-gray-100 tw-text-gray-500' }}">{{ $rx->is_dispensed ? __('lang_v1.dispensed') : __('lang_v1.pending') }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@empty
    @include('patient_portal.partials.empty', ['msg' => __('lang_v1.no_consultations'), 'icon' => 'fa-notes-medical'])
@endforelse
@endsection
