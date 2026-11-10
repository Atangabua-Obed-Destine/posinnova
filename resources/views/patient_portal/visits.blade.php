@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.my_visits'))
@section('heading', __('lang_v1.my_visits'))
@php $portal_group = 'records'; @endphp

@section('content')
@include('patient_portal.partials.records_nav', ['active' => 'visits'])

<div class="tw-space-y-3" data-list>
    @forelse($visits as $v)
        <a href="{{ route('portal.visit-show', $v->id) }}" class="portal-card tw-block tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm">
            <div class="tw-flex tw-items-center tw-gap-3">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-text-white tw-shrink-0 tw-shadow" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);"><i class="fas fa-stethoscope"></i></span>
                <div class="tw-flex-1 tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-gray-800">{{ __('lang_v1.visit_type_' . $v->type) }}</p>
                    <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">{{ @format_date($v->visit_date) }} · {{ $v->consultations_count }} {{ __('lang_v1.consultations') }}</p>
                </div>
                <div class="tw-flex tw-items-center tw-gap-2 tw-shrink-0">
                    <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full {{ $v->status == 'open' ? 'tw-bg-green-100 tw-text-green-700' : 'tw-bg-gray-100 tw-text-gray-600' }}">{{ __('lang_v1.visit_status_' . $v->status) }}</span>
                    <i class="fas fa-chevron-right tw-text-gray-300"></i>
                </div>
            </div>
        </a>
    @empty
        @include('patient_portal.partials.empty', ['msg' => __('lang_v1.no_visits'), 'icon' => 'fa-stethoscope'])
    @endforelse
</div>
@include('patient_portal.partials.infinite', ['paginator' => $visits])
@endsection
