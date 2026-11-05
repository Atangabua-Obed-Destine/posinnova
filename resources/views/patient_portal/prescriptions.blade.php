@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.my_prescriptions'))
@section('heading', __('lang_v1.my_prescriptions'))
@php $portal_group = 'records'; @endphp

@section('content')
@include('patient_portal.partials.records_nav', ['active' => 'prescriptions'])

<div class="tw-space-y-3" data-list>
    @forelse($prescriptions as $rx)
        <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm">
            <div class="tw-flex tw-items-start tw-gap-3">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-text-white tw-shrink-0 tw-shadow" style="background:linear-gradient(135deg,#6366f1,#4f46e5);"><i class="fas fa-pills"></i></span>
                <div class="tw-flex-1 tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-gray-800">{{ $rx->displayName() }}</p>
                    <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">
                        @if($rx->dosage){{ $rx->dosage }}@endif
                        @if($rx->frequency) · {{ $rx->frequency }}@endif
                        @if($rx->duration) · {{ $rx->duration }}@endif
                    </p>
                    <p class="tw-text-[11px] tw-text-gray-400 tw-mt-1">{{ @format_date(optional($rx->consultation)->created_at) }}</p>
                </div>
                @include('patient_portal.partials.rx_state', ['rx' => $rx])
            </div>
        </div>
    @empty
        @include('patient_portal.partials.empty', ['msg' => __('lang_v1.no_prescriptions'), 'icon' => 'fa-prescription'])
    @endforelse
</div>
@include('patient_portal.partials.infinite', ['paginator' => $prescriptions])
@endsection
