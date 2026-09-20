@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.my_lab_results'))
@section('heading', __('lang_v1.my_lab_results'))
@php $portal_group = 'records'; @endphp

@section('content')
@include('patient_portal.partials.records_nav', ['active' => 'labs'])

<div class="tw-space-y-3" data-list>
    @forelse($lab_requests as $lr)
        @php $resulted = in_array($lr->status, ['resulted', 'validated']); @endphp
        @if($resulted)
        <a href="{{ route('portal.lab-show', $lr->id) }}" class="portal-card tw-block tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm">
        @else
        <div class="tw-block tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm tw-opacity-80">
        @endif
            <div class="tw-flex tw-items-center tw-gap-3">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-text-white tw-shrink-0 tw-shadow" style="background:linear-gradient(135deg,#f59e0b,#d97706);"><i class="fas fa-vial"></i></span>
                <div class="tw-flex-1 tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-gray-800 tw-truncate">{{ $lr->lines->pluck('test_name')->take(2)->implode(', ') }}{{ $lr->lines->count() > 2 ? ' +'.($lr->lines->count()-2) : '' }}</p>
                    <p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">{{ @format_date($lr->requested_at) }} · {{ $lr->ref_no }}</p>
                </div>
                <div class="tw-flex tw-items-center tw-gap-2 tw-shrink-0">
                    @if($lr->status == 'validated')
                        <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full tw-bg-green-100 tw-text-green-700">@lang('lang_v1.lab_status_validated')</span>
                    @elseif($lr->status == 'resulted')
                        <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full tw-bg-blue-100 tw-text-blue-700">@lang('lang_v1.lab_status_resulted')</span>
                    @else
                        <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full tw-bg-amber-100 tw-text-amber-700">@lang('lang_v1.pending')</span>
                    @endif
                    @if($resulted)<i class="fas fa-chevron-right tw-text-gray-300"></i>@endif
                </div>
            </div>
        @if($resulted)</a>@else</div>@endif
    @empty
        @include('patient_portal.partials.empty', ['msg' => __('lang_v1.no_lab_results'), 'icon' => 'fa-vial'])
    @endforelse
</div>
@include('patient_portal.partials.infinite', ['paginator' => $lab_requests])
@endsection
