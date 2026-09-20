@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.my_bills'))
@section('heading', __('lang_v1.my_bills'))
@php $portal_group = 'bills'; @endphp

@section('content')
{{-- Balance hero --}}
<div class="tw-rounded-3xl tw-bg-gradient-to-br tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-p-5 tw-shadow-md tw-mb-4">
    <p class="tw-text-sm tw-opacity-80">@lang('lang_v1.outstanding_due')</p>
    <p class="tw-text-3xl tw-font-extrabold tw-mt-1"><span class="display_currency" data-currency_symbol="true">{{ $due }}</span></p>
    <div class="tw-grid tw-grid-cols-2 tw-gap-2 tw-mt-4">
        <div class="tw-rounded-xl tw-bg-white/15 tw-px-3 tw-py-2">
            <p class="tw-text-[11px] tw-opacity-70">@lang('lang_v1.total_billed')</p>
            <p class="tw-text-sm tw-font-bold"><span class="display_currency" data-currency_symbol="true">{{ $total_billed }}</span></p>
        </div>
        <div class="tw-rounded-xl tw-bg-white/15 tw-px-3 tw-py-2">
            <p class="tw-text-[11px] tw-opacity-70">@lang('lang_v1.paid')</p>
            <p class="tw-text-sm tw-font-bold"><span class="display_currency" data-currency_symbol="true">{{ $total_paid }}</span></p>
        </div>
    </div>
</div>

@if($due > 0)
    <div class="tw-rounded-2xl tw-bg-amber-50 tw-ring-1 tw-ring-amber-200 tw-p-3.5 tw-mb-4 tw-text-sm tw-text-amber-800 tw-flex tw-items-center tw-gap-2">
        <i class="fas fa-circle-info"></i> <span>@lang('lang_v1.portal_pay_at_cashier')</span>
    </div>
@endif

{{-- Charge list --}}
<div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-overflow-hidden tw-shadow-sm" data-list>
    @forelse($charges as $c)
        <a href="{{ route('portal.bill-receipt', $c->id) }}" class="portal-card tw-block tw-p-4 tw-border-b tw-border-gray-50 last:tw-border-0">
            <div class="tw-flex tw-items-center tw-justify-between tw-gap-2">
                <div class="tw-min-w-0">
                    <p class="tw-text-sm tw-font-medium tw-text-gray-800 tw-truncate">{{ $c->additional_notes ?: $c->invoice_no }}</p>
                    <p class="tw-text-xs tw-text-gray-400">{{ @format_date($c->transaction_date) }} · {{ $c->invoice_no }}</p>
                </div>
                <div class="tw-text-right tw-shrink-0">
                    <p class="tw-text-sm tw-font-bold tw-text-gray-900"><span class="display_currency" data-currency_symbol="true">{{ $c->final_total }}</span></p>
                    <span class="tw-text-[10px] tw-px-1.5 tw-py-0.5 tw-rounded-full {{ $c->payment_status == 'paid' ? 'tw-bg-green-100 tw-text-green-700' : ($c->payment_status == 'partial' ? 'tw-bg-blue-100 tw-text-blue-700' : 'tw-bg-amber-100 tw-text-amber-700') }}">
                        {{ $c->payment_status == 'paid' ? __('lang_v1.paid') : ($c->payment_status == 'partial' ? __('lang_v1.partial') : __('lang_v1.due')) }}
                    </span>
                </div>
            </div>
        </a>
    @empty
        @include('patient_portal.partials.empty', ['msg' => __('lang_v1.no_bills'), 'icon' => 'fa-file-invoice'])
    @endforelse
</div>
@include('patient_portal.partials.infinite', ['paginator' => $charges])
@endsection
