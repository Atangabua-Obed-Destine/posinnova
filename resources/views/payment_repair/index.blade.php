@extends('layouts.app')
@section('title', __('lang_v1.payment_repair'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.payment_repair')
        <small class="tw-text-gray-600">@lang('lang_v1.payment_repair_sub')</small>
    </h1>
</section>

<section class="content">
    @component('components.widget', ['class' => 'box-primary'])
        <p style="margin-top:0;">@lang('lang_v1.payment_repair_help')</p>

        @if ($rows->isEmpty())
            <div class="callout callout-success" style="margin-bottom:0;">
                <i class="fas fa-check-circle"></i> @lang('lang_v1.payment_repair_all_fine')
            </div>
        @else
            <div class="callout callout-warning">
                <i class="fas fa-exclamation-triangle"></i> {{ trans_choice('lang_v1.payment_repair_found', $rows->count(), ['n' => $rows->count()]) }}
            </div>
            <div class="table-responsive">
                <table class="table table-striped table-condensed" id="payment_repair_table">
                    <thead>
                        <tr>
                            <th>@lang('sale.invoice_no')</th>
                            <th>@lang('lang_v1.date')</th>
                            <th>@lang('contact.customer')</th>
                            <th class="text-right">@lang('sale.total')</th>
                            <th class="text-right">@lang('lang_v1.payment_repair_recorded')</th>
                            <th class="text-right">@lang('lang_v1.change_return')</th>
                            <th class="text-right">@lang('lang_v1.payment_repair_after')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $r)
                            <tr>
                                <td><a href="#" class="btn-modal" data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$r->id]) }}" data-container=".view_modal">{{ $r->invoice_no }}</a></td>
                                <td>{{ @format_datetime($r->transaction_date) }}</td>
                                <td>{{ $r->customer }}</td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $r->final_total }}</span></td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $r->paid - $r->change_given }}</span>
                                    <br><span class="label label-warning">@lang('lang_v1.' . $r->payment_status)</span></td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $r->change_given }}</span></td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $r->final_total }}</span>
                                    <br><span class="label label-success">@lang('lang_v1.paid')</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <form method="POST" action="{{ action([\App\Http\Controllers\PaymentRepairController::class, 'repair']) }}" class="text-center"
                onsubmit="var b=this.querySelector('button'); if (!confirm({{ json_encode(trans_choice('lang_v1.payment_repair_confirm', $rows->count(), ['n' => $rows->count()])) }})) return false; b.disabled=true; return true;">
                @csrf
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white" id="payment_repair_button">
                    <i class="fas fa-wrench"></i> {{ trans_choice('lang_v1.payment_repair_button', $rows->count(), ['n' => $rows->count()]) }}
                </button>
                <p class="help-block">@lang('lang_v1.payment_repair_note')</p>
            </form>
        @endif
    @endcomponent
</section>
@endsection
