@extends('layouts.app')
@section('title', __('clinic.dispensary'))

@section('content')
@php $pd = optional($consultation->patient)->patientDetails; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ optional($consultation->patient)->name }}
        <small class="tw-text-gray-600">
            @if (optional($pd)->file_number){{ $pd->file_number }} · @endif
            <i class="fas fa-user-md"></i> {{ optional($consultation->consultedBy)->user_full_name ?: '-' }}
            · {{ @format_datetime($consultation->updated_at) }}
        </small>
    </h1>
</section>

<section class="content">
    <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ action([\App\Http\Controllers\ClinicDispensaryController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
            <i class="fas fa-arrow-left"></i> @lang('clinic.dispensary')
        </a>
        @if ($waiting > 0 && auth()->user()->can('sell.create'))
            <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}?prescription_id={{ $consultation->id }}"
                class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                <i class="fas fa-cash-register"></i> @lang('clinic.rx_dispense_waiting', ['n' => $waiting])
            </a>
        @endif
    </div>

    @if ($consultation->status != 'final')
        <div class="callout callout-warning">@lang('clinic.rx_consultation_draft')</div>
    @endif

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.prescriptions')])
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('lang_v1.medicine')</th>
                        <th>@lang('clinic.rx_how_to_take')</th>
                        <th>@lang('clinic.rx_prescribed')</th>
                        <th>@lang('clinic.rx_given')</th>
                        <th style="min-width:260px;">@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($consultation->prescriptions as $rx)
                        <tr>
                            <td><strong>{{ $rx->displayName() }}</strong>
                                @if ($rx->notes)<br><small class="text-muted">{{ $rx->notes }}</small>@endif
                            </td>
                            <td>{{ collect([$rx->dosage, $rx->frequency, $rx->duration])->filter()->implode(' · ') ?: '-' }}</td>
                            <td>{{ $rx->quantity + 0 }}</td>
                            <td>@include('clinic.partials.rx_state', ['rx' => $rx])
                                @if ($rx->closed_at)
                                    <br><small class="text-muted">{{ optional($rx->closedBy)->user_full_name }} · {{ @format_datetime($rx->closed_at) }}</small>
                                @endif
                            </td>
                            <td>
                                @if ($rx->outstanding_qty > 0)
                                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicDispensaryController::class, 'close'], [$rx->id]) }}" class="form-inline" style="display:flex; gap:4px; flex-wrap:wrap;">
                                        @csrf
                                        {!! Form::select('reason', $reasons, null, ['class' => 'form-control input-sm', 'required', 'placeholder' => __('clinic.rx_close_reason')]) !!}
                                        <input type="text" name="note" class="form-control input-sm" maxlength="120" placeholder="{{ __('clinic.rx_close_note') }}">
                                        <button type="submit" class="btn btn-sm btn-default"><i class="fas fa-ban"></i> @lang('clinic.rx_close')</button>
                                    </form>
                                @elseif ($rx->closed_at)
                                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicDispensaryController::class, 'reopen'], [$rx->id]) }}">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-default"><i class="fas fa-undo"></i> @lang('clinic.rx_reopen')</button>
                                    </form>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="help-block">@lang('clinic.rx_given_help')</p>
    @endcomponent

    @component('components.widget', ['class' => 'box-default', 'title' => __('clinic.rx_sales')])
        @if ($sales->isEmpty())
            <p class="text-muted">@lang('clinic.rx_no_sales')</p>
        @else
            <table class="table table-condensed">
                <thead><tr><th>@lang('sale.invoice_no')</th><th>@lang('messages.date')</th><th>@lang('sale.status')</th><th>@lang('sale.payment_status')</th><th class="text-right">@lang('sale.total_amount')</th></tr></thead>
                <tbody>
                    @foreach ($sales as $s)
                        <tr>
                            <td><a href="#" class="btn-modal" data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$s->id]) }}" data-container=".view_modal">{{ $s->invoice_no }}</a></td>
                            <td>{{ @format_datetime($s->transaction_date) }}</td>
                            <td>{{ $s->status == 'final' ? __('sale.final') : __('sale.' . $s->status) }}</td>
                            <td>{{ __('lang_v1.' . $s->payment_status) }}</td>
                            <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $s->final_total }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endcomponent
</section>
@endsection
