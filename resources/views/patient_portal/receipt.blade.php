<!DOCTYPE html>
<html data-theme="light" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, viewport-fit=cover" name="viewport">
    <title>{{ $charge->invoice_no }} · {{ session('business.name', config('app.name')) }}</title>
    <link href="{{ asset('css/tailwind/app.css?v='.$asset_v) }}" rel="stylesheet">
    <link href="{{ asset('css/tailwind/portal.css?v='.$asset_v) }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/vendor.css?v='.$asset_v) }}">
    <input type="hidden" id="__symbol" value="{{ session('currency')['symbol'] ?? '' }}">
    <input type="hidden" id="__thousand" value="{{ session('currency')['thousand_separator'] ?? ',' }}">
    <input type="hidden" id="__decimal" value="{{ session('currency')['decimal_separator'] ?? '.' }}">
    <input type="hidden" id="__symbol_placement" value="{{ session('business.currency_symbol_placement', 'before') }}">
    <input type="hidden" id="__precision" value="{{ session('business.currency_precision', 2) }}">
    <style>@media print { .no-print { display:none } } body { background:#f3f4f6 }</style>
</head>
<body class="tw-p-4">
<div class="tw-max-w-md tw-mx-auto tw-bg-white tw-rounded-2xl tw-ring-1 tw-ring-gray-200 tw-p-5 tw-shadow">
    <div class="tw-text-center tw-mb-4">
        @if(session('business.logo'))<img src="{{ asset('uploads/business_logos/'.session('business.logo')) }}" class="tw-h-12 tw-mx-auto tw-mb-1">@endif
        <h1 class="tw-text-lg tw-font-bold tw-text-gray-900">{{ session('business.name', config('app.name')) }}</h1>
        <p class="tw-text-xs tw-text-gray-400">{{ optional($charge->location)->name }}</p>
    </div>
    <div class="tw-flex tw-justify-between tw-text-sm tw-mb-3">
        <span class="tw-text-gray-500">{{ $charge->invoice_no }}</span>
        <span class="tw-text-gray-500">{{ @format_date($charge->transaction_date) }}</span>
    </div>
    <p class="tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-3">{{ $patient->name }} · {{ optional($patient->patientDetails)->file_number }}</p>
    <table class="tw-w-full tw-text-sm tw-mb-3">
        @foreach($charge->sell_lines as $l)
        <tr class="tw-border-b tw-border-gray-50"><td class="tw-py-1.5">{{ optional($l->product)->name }} <span class="tw-text-gray-400">×{{ $l->quantity+0 }}</span></td><td class="tw-py-1.5 tw-text-right"><span class="display_currency" data-currency_symbol="true">{{ $l->unit_price_inc_tax * $l->quantity }}</span></td></tr>
        @endforeach
    </table>
    <div class="tw-flex tw-justify-between tw-font-bold tw-text-gray-900 tw-border-t tw-pt-2"><span>@lang('lang_v1.total_billed')</span><span class="display_currency" data-currency_symbol="true">{{ $charge->final_total }}</span></div>
    <div class="tw-flex tw-justify-between tw-text-sm tw-text-green-600 tw-mt-1"><span>@lang('lang_v1.paid')</span><span class="display_currency" data-currency_symbol="true">{{ $charge->payment_lines->sum('amount') }}</span></div>
    <button onclick="window.print()" class="no-print tw-w-full tw-mt-4 tw-rounded-xl tw-bg-indigo-600 tw-text-white tw-py-2.5 tw-text-sm tw-font-semibold"><i class="fas fa-print"></i> @lang('messages.print')</button>
    <a href="{{ route('portal.bills') }}" class="no-print tw-block tw-text-center tw-mt-2 tw-text-sm tw-text-indigo-600" style="padding:10px 0;"><i class="fas fa-arrow-left"></i> @lang('messages.back')</a>
</div>
<script src="{{ asset('js/vendor.js?v='.$asset_v) }}"></script>
<script src="{{ asset('js/functions.js?v='.$asset_v) }}"></script>
<script>
    var __currency_symbol=$('#__symbol').val()||'',__currency_thousand_separator=$('#__thousand').val()||',',__currency_decimal_separator=$('#__decimal').val()||'.',__currency_symbol_placement=$('#__symbol_placement').val()||'before',__currency_precision=parseInt($('#__precision').val()||2,10);
    $(function(){ if(typeof __currency_convert_recursively==='function'){__currency_convert_recursively($('body'));} });
</script>
</body>
</html>
