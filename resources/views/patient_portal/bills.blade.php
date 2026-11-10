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
    {{-- inline colours: the portal's compiled CSS has no amber utilities --}}
    <div style="border-radius:16px;background:#fffbeb;border:1px solid #fde68a;padding:12px 14px;margin-bottom:16px;font-size:14px;color:#92400e;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-info-circle"></i> <span>@lang('lang_v1.portal_pay_at_cashier')</span>
    </div>
@endif

@if($insurer_owes > 0)
    <div style="border-radius:16px;background:#eff6ff;border:1px solid #bfdbfe;padding:12px 14px;margin-bottom:16px;font-size:14px;color:#1e40af;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-shield-alt"></i> <span>{{ __('clinic.ins_portal_insurer_owes') }} <strong><span class="display_currency" data-currency_symbol="true">{{ $insurer_owes }}</span></strong></span>
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
                    @if(isset($insurer_parts[$c->id]))
                        <p style="font-size:12px;color:#1d4ed8;margin:2px 0 0;">{{ __('clinic.ins_portal_insurer_pays', ['insurer' => optional($insurer_parts[$c->id]->insurer)->name]) }} <span class="display_currency" data-currency_symbol="true">{{ $insurer_parts[$c->id]->amount - $insurer_parts[$c->id]->rebilled_amount }}</span></p>
                    @endif
                </div>
                <div class="tw-text-right tw-shrink-0">
                    <p class="tw-text-sm tw-font-bold tw-text-gray-900"><span class="display_currency" data-currency_symbol="true">{{ $c->final_total }}</span></p>
                    <span style="font-size:10px;padding:2px 8px;border-radius:9999px;{{ $c->payment_status == 'paid' ? 'background:#dcfce7;color:#15803d;' : ($c->payment_status == 'partial' ? 'background:#dbeafe;color:#1d4ed8;' : 'background:#fef3c7;color:#b45309;') }}">
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
