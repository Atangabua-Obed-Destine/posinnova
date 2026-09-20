@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.my_admissions'))
@section('heading', __('lang_v1.my_admissions'))
@php $portal_group = 'records'; @endphp

@section('content')
@include('patient_portal.partials.records_nav', ['active' => 'admissions'])

<div class="tw-space-y-3" data-list>
    @forelse($admissions as $a)
        <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm">
            <div class="tw-flex tw-items-center tw-gap-3 tw-mb-2">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-text-white tw-shrink-0 tw-shadow" style="background:linear-gradient(135deg,#22c55e,#16a34a);"><i class="fas fa-procedures"></i></span>
                <div class="tw-flex-1 tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-gray-800">{{ optional($a->ward)->name ?: '—' }}@if($a->bed) · @lang('lang_v1.bed') {{ $a->bed->bed_number }}@endif</p>
                    <p class="tw-text-xs tw-text-gray-400">{{ $a->ref_no }}</p>
                </div>
                <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full tw-shrink-0 {{ $a->status == 'active' ? 'tw-bg-green-100 tw-text-green-700' : 'tw-bg-gray-100 tw-text-gray-600' }}">{{ __('lang_v1.admission_status_' . $a->status) }}</span>
            </div>
            <div class="tw-grid tw-grid-cols-3 tw-gap-2 tw-text-center">
                <div class="tw-rounded-xl tw-bg-gray-50 tw-p-2"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.admission_date')</p><p class="tw-text-xs tw-font-semibold tw-text-gray-700">{{ @format_date($a->admission_date) }}</p></div>
                <div class="tw-rounded-xl tw-bg-gray-50 tw-p-2"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.discharge_date')</p><p class="tw-text-xs tw-font-semibold tw-text-gray-700">{{ $a->discharge_date ? @format_date($a->discharge_date) : '—' }}</p></div>
                <div class="tw-rounded-xl tw-bg-gray-50 tw-p-2"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.days')</p><p class="tw-text-xs tw-font-semibold tw-text-gray-700">{{ $a->days_admitted }}</p></div>
            </div>
            @if($a->discharge_notes)<p class="tw-text-xs tw-text-gray-500 tw-mt-2"><strong>@lang('lang_v1.discharge_notes'):</strong> {{ $a->discharge_notes }}</p>@endif
        </div>
    @empty
        @include('patient_portal.partials.empty', ['msg' => __('lang_v1.no_admissions'), 'icon' => 'fa-procedures'])
    @endforelse
</div>
@include('patient_portal.partials.infinite', ['paginator' => $admissions])
@endsection
