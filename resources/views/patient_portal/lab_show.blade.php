@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.lab_result'))
@section('heading', __('lang_v1.lab_result'))
@php $portal_group = 'records'; $flagColors = ['normal'=>'tw-text-green-600','high'=>'tw-text-orange-600','low'=>'tw-text-blue-600','critical'=>'tw-text-red-600']; @endphp

@section('back')
    <a href="{{ route('portal.lab-results') }}" class="portal-press tw-inline-flex tw-items-center tw-gap-1 tw-text-indigo-600 tw-font-medium"><i class="fas fa-chevron-left"></i> <span class="tw-hidden sm:tw-inline">@lang('lang_v1.my_lab_results')</span></a>
@endsection

@section('content')
<div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm tw-mb-4">
    <div class="tw-flex tw-items-center tw-justify-between">
        <div>
            <p class="tw-font-semibold tw-text-gray-800">{{ $lab_request->ref_no }}</p>
            <p class="tw-text-xs tw-text-gray-500">{{ @format_datetime($lab_request->resulted_at ?? $lab_request->requested_at) }}</p>
        </div>
        <span class="tw-text-xs tw-px-2 tw-py-0.5 tw-rounded-full {{ $lab_request->status == 'validated' ? 'tw-bg-green-100 tw-text-green-700' : 'tw-bg-blue-100 tw-text-blue-700' }}">{{ __('lang_v1.lab_status_' . $lab_request->status) }}</span>
    </div>
</div>

<div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-overflow-hidden tw-shadow-sm">
    @foreach($lab_request->lines as $line)
        <div class="tw-p-4 tw-border-b tw-border-gray-50 last:tw-border-0">
            <div class="tw-flex tw-items-center tw-justify-between tw-gap-2">
                <p class="tw-text-sm tw-font-medium tw-text-gray-800">{{ $line->test_name }}</p>
                <p class="tw-text-base tw-font-bold {{ $line->result_flag ? ($flagColors[$line->result_flag] ?? 'tw-text-gray-800') : 'tw-text-gray-800' }} tw-shrink-0">
                    {{ $line->result_value ?: '—' }} <span class="tw-text-xs tw-text-gray-400">{{ $line->unit }}</span>
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-justify-between tw-mt-1 tw-gap-2">
                <p class="tw-text-xs tw-text-gray-400">@lang('lang_v1.normal_range'): {{ $line->normal_range ?: '—' }}</p>
                @if($line->result_flag)
                    <span class="tw-text-[10px] tw-px-1.5 tw-py-0.5 tw-rounded-full tw-bg-gray-100 {{ $flagColors[$line->result_flag] ?? '' }}">{{ __('lang_v1.lab_flag_' . $line->result_flag) }}</span>
                @endif
            </div>
        </div>
    @endforeach
</div>

<p class="tw-text-xs tw-text-gray-400 tw-mt-3">@lang('lang_v1.validated_by'): {{ optional($lab_request->validatedBy)->user_full_name ?? optional($lab_request->requestedBy)->user_full_name ?? '—' }}</p>
@endsection
