@extends('patient_portal.layouts.portal')
@section('title', __('clinic.portal_my_plan'))
@section('heading', __('clinic.portal_my_plan'))
@php $portal_group = 'records'; @endphp

@section('content')
@include('patient_portal.partials.records_nav', ['active' => 'plan'])

@if (! $subscription)
    @include('patient_portal.partials.empty', ['msg' => __('clinic.portal_no_plan'), 'icon' => 'fa-id-card'])
@else
    {{-- this month (inline colours: the portal's compiled CSS lacks several utilities) --}}
    <div class="tw-rounded-3xl tw-bg-gradient-to-br tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-p-5 tw-shadow-md tw-mb-4">
        <p class="tw-text-sm" style="opacity:.8;">{{ __('clinic.plan_status_' . $subscription->status) }}</p>
        <p class="tw-text-2xl tw-font-extrabold tw-mt-1">{{ optional($subscription->package)->name }}</p>
        <p class="tw-text-sm tw-mt-2">{{ $coverage['message'] ?: '-' }}</p>
        @if ($subscription->status == 'active')
            <p class="tw-text-xs tw-mt-2" style="opacity:.8;">{{ __('clinic.portal_next_bill', ['date' => @format_date($subscription->next_bill_date)]) }}</p>
        @endif
    </div>
    @if (optional($subscription->package)->description)
        <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm tw-mb-4">
            <p class="tw-text-xs tw-font-semibold tw-text-gray-500 tw-mb-1">@lang('clinic.portal_plan_covers')</p>
            <p class="tw-text-sm tw-text-gray-700" style="white-space:pre-line;">{{ $subscription->package->description }}</p>
        </div>
    @endif

    <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-overflow-hidden tw-shadow-sm">
        @foreach ($periods as $p)
            <div class="tw-flex tw-items-center tw-justify-between tw-gap-2 tw-p-4 tw-border-b tw-border-gray-50">
                <div class="tw-min-w-0">
                    <p class="tw-text-sm tw-font-medium tw-text-gray-800">{{ @format_date($p->period_start) }} – {{ @format_date($p->period_end) }}</p>
                    <p class="tw-text-xs tw-text-gray-400">{{ __('clinic.portal_visits_used', ['used' => $p->used, 'allowed' => $p->visits_allowed ?? '∞']) }}</p>
                </div>
                <div class="tw-text-right tw-shrink-0">
                    <p class="tw-text-sm tw-font-bold tw-text-gray-900"><span class="display_currency" data-currency_symbol="true">{{ $p->amount }}</span></p>
                    <span style="font-size:10px;padding:2px 8px;border-radius:9999px;{{ $p->paid ? 'background:#dcfce7;color:#15803d;' : 'background:#fef3c7;color:#b45309;' }}">{{ $p->paid ? __('lang_v1.paid') : __('lang_v1.due') }}</span>
                </div>
            </div>
        @endforeach
    </div>
    @if ($periods->contains(fn ($p) => ! $p->paid))
        <p class="tw-text-xs tw-text-gray-400 tw-mt-3">@lang('lang_v1.portal_pay_at_cashier')</p>
    @endif
@endif
@endsection
