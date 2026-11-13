@extends('layouts.app')
@section('title', __('clinic.pay_print_title'))

{{-- PosInnova: printing hands the result to the patient; it is not paid yet, so the user chooses first --}}
@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.pay_print_title')
        <small class="tw-text-gray-600">{{ $lab_request->ref_no }} · {{ optional($lab_request->patient)->name }}</small>
    </h1>
</section>

<section class="content">
    @include('clinic.partials.request_payment', ['pay' => $pay, 'req' => $lab_request, 'ack' => false])

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5" style="max-width:760px;">
        <p style="font-size:15px; margin-bottom:12px;">@lang('clinic.pay_print_intro')</p>
        <form method="post" action="{{ action([\App\Http\Controllers\LabRequestController::class, 'printUnpaid'], [$lab_request->id]) }}" id="print_unpaid_form">
            @csrf
            <label style="display:flex; gap:8px; align-items:flex-start; font-weight:600; cursor:pointer;">
                <input type="checkbox" name="unpaid_ack" value="1" id="print_unpaid_ack" required style="margin-top:3px;">
                <span>@lang('clinic.pay_print_ack', ['due' => (new \App\Utils\Util())->num_f($pay['due'], true)])</span>
            </label>
            <input type="text" name="unpaid_reason" maxlength="191" class="form-control" style="margin:8px 0 14px;"
                placeholder="{{ __('clinic.pay_ack_reason') }}: {{ __('clinic.pay_ack_reason_ph') }}">
            <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">
                <i class="fas fa-print"></i> @lang('clinic.pay_print_btn')
            </button>
            <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lab_request->id]) }}" class="tw-dw-btn tw-dw-btn-outline">
                <i class="fas fa-arrow-left"></i> @lang('messages.back')
            </a>
        </form>
    </div>
</section>
@endsection
