@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.maternity'))
@section('heading', __('lang_v1.maternity'))
@php $portal_group = 'records'; @endphp

@section('back')
    <a href="{{ route('portal.dashboard') }}" class="portal-press tw-inline-flex tw-items-center tw-gap-1 tw-text-indigo-600 tw-font-medium"><i class="fas fa-chevron-left"></i> <span class="tw-hidden sm:tw-inline">@lang('lang_v1.portal_home')</span></a>
@endsection

@section('content')
<div class="tw-space-y-4">
    @foreach($records as $r)
        <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm">
            <div class="tw-flex tw-items-center tw-gap-3 tw-mb-3">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-bg-pink-100 tw-text-pink-600 tw-shrink-0"><i class="fas fa-baby"></i></span>
                <div class="tw-flex-1 tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-gray-800">{{ $r->ref_no }}</p>
                </div>
                <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full tw-shrink-0 {{ $r->status == 'active' ? 'tw-bg-green-100 tw-text-green-700' : 'tw-bg-blue-100 tw-text-blue-700' }}">{{ __('lang_v1.anc_status_' . $r->status) }}</span>
            </div>

            <div class="tw-grid tw-grid-cols-3 tw-gap-2 tw-text-center tw-mb-3">
                <div class="tw-rounded-xl tw-bg-gray-50 tw-p-2"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.lmp')</p><p class="tw-text-xs tw-font-semibold">{{ $r->lmp ? @format_date($r->lmp) : '—' }}</p></div>
                <div class="tw-rounded-xl tw-bg-gray-50 tw-p-2"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.edd')</p><p class="tw-text-xs tw-font-semibold">{{ $r->edd ? @format_date($r->edd) : '—' }}</p></div>
                <div class="tw-rounded-xl tw-bg-gray-50 tw-p-2"><p class="tw-text-[10px] tw-text-gray-400">@lang('lang_v1.ga_weeks')</p><p class="tw-text-xs tw-font-semibold">{{ $r->gestational_age_weeks ?? '—' }}</p></div>
            </div>

            @if($r->antenatalVisits->count() > 0)
                <p class="tw-text-xs tw-font-semibold tw-text-gray-500 tw-mb-1.5">@lang('lang_v1.anc_visits')</p>
                <div class="tw-space-y-1 tw-mb-3">
                    @foreach($r->antenatalVisits->sortByDesc('visit_date') as $v)
                        <div class="tw-flex tw-items-center tw-justify-between tw-text-sm">
                            <span class="tw-text-gray-700">#{{ $v->visit_number }} · {{ @format_date($v->visit_date) }}</span>
                            <span class="tw-text-xs tw-text-gray-400">{{ $v->gestational_age_weeks ? $v->gestational_age_weeks.' '.__('lang_v1.weeks') : '' }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($r->delivery)
                <div class="tw-rounded-xl tw-bg-pink-50 tw-ring-1 tw-ring-pink-100 tw-p-3">
                    <p class="tw-text-sm tw-font-semibold tw-text-pink-700">@lang('lang_v1.delivery') · {{ __('lang_v1.delivery_type_' . $r->delivery->delivery_type) }}</p>
                    <p class="tw-text-xs tw-text-pink-600 tw-mt-0.5">{{ @format_datetime($r->delivery->delivery_date) }}@if($r->delivery->baby_weight) · {{ $r->delivery->baby_weight + 0 }}kg @endif</p>
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
