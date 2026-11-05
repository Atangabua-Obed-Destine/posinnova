@extends('layouts.app')
@section('title', $claim->ref_no)

@php
    $fmt = function ($n) { return app(\App\Utils\Util::class)->num_f((float) $n); };
    $can_settle = $claim->status == 'submitted' && $summary['due'] > 0.0001;
    $C = \App\Http\Controllers\ClinicClaimController::class;
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $claim->ref_no }}
        <small class="tw-text-gray-600">{{ optional($claim->insurer)->name }} · {{ @format_date($claim->date_from) }} – {{ @format_date($claim->date_to) }}</small>
    </h1>
</section>

<section class="content">
    <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center; margin-bottom:12px;">
        <a href="{{ action([$C, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-arrow-left"></i> @lang('clinic.ins_claims')</a>
        @include('clinic.insurance.partials.claim_state', ['state' => $summary['state']])
        <span style="margin-left:auto;"></span>
        <a href="{{ action([$C, 'printClaim'], [$claim->id]) }}" target="_blank" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-print"></i> @lang('clinic.ins_print_claim')</a>
        @if ($claim->status == 'draft')
            @if ($unclaimed > 0)
                <form method="POST" action="{{ action([$C, 'refresh'], [$claim->id]) }}" style="display:inline;">@csrf
                    <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-sync"></i> {{ trans_choice('clinic.ins_add_new_items', $unclaimed, ['n' => $unclaimed]) }}</button>
                </form>
            @endif
            <form method="POST" action="{{ action([$C, 'destroy'], [$claim->id]) }}" style="display:inline;" class="js-confirm" data-confirm="{{ __('clinic.ins_delete_claim_confirm') }}">@csrf
                <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-error"><i class="fas fa-trash"></i> @lang('clinic.ins_delete_claim')</button>
            </form>
            <form method="POST" action="{{ action([$C, 'submit'], [$claim->id]) }}" style="display:inline;" class="js-confirm" data-confirm="{{ __('clinic.ins_submit_confirm') }}">@csrf
                <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white" {{ $summary['count'] ? '' : 'disabled' }}><i class="fas fa-paper-plane"></i> @lang('clinic.ins_mark_submitted')</button>
            </form>
        @elseif ($claim->status == 'submitted' && $summary['paid'] <= 0 && $summary['rebilled'] <= 0)
            <form method="POST" action="{{ action([$C, 'reopen'], [$claim->id]) }}" style="display:inline;">@csrf
                <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-undo"></i> @lang('clinic.ins_reopen')</button>
            </form>
        @endif
    </div>

    <div class="row">
        @foreach ([['ins_items', $summary['count'], false], ['ins_full_price', $summary['full'], true], ['ins_claimed', $summary['claimed'], true], ['lang_v1.paid', $summary['paid'], true], ['ins_to_patient', $summary['rebilled'], true], ['lang_v1.due', $summary['due'], true]] as [$label, $value, $money])
            <div class="col-xs-6 col-sm-4 col-md-2">
                <div class="tw-rounded-xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-3 tw-mb-3">
                    <div class="text-muted" style="font-size:12px;">{{ str_contains($label, '.') ? __($label) : __('clinic.'.$label) }}</div>
                    <div style="font-size:18px; font-weight:700;">@if ($money)<span class="display_currency" data-currency_symbol="true">{{ $value }}</span>@else{{ $value }}@endif</div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="text-muted" style="margin-top:-4px;">
        {{ __('clinic.ins_prepared_by', ['name' => optional($claim->createdBy)->user_full_name, 'date' => @format_datetime($claim->created_at)]) }}
        @if ($claim->submitted_at) · {{ __('clinic.ins_submitted_by', ['name' => optional($claim->submittedBy)->user_full_name, 'date' => @format_datetime($claim->submitted_at)]) }}@endif
        @if ($claim->notes) · {{ $claim->notes }}@endif
    </p>

    @if ($can_settle)
        <form method="POST" action="{{ action([$C, 'settle'], [$claim->id]) }}" id="settle_form">
        @csrf
    @endif

    @component('components.widget', ['class' => 'box-primary'])
        @if ($can_settle)
            <p class="help-block" style="margin-top:0;"><i class="fas fa-hand-holding-usd"></i> @lang('clinic.ins_settle_help')</p>
        @endif
        <div class="table-responsive">
            <table class="table table-striped" id="claim_items">
                <thead>
                    <tr>
                        <th>@lang('lang_v1.date')</th>
                        <th>@lang('lang_v1.patient')</th>
                        <th>@lang('clinic.ins_services')</th>
                        <th class="text-right">@lang('clinic.ins_full_price')</th>
                        <th class="text-right">@lang('clinic.ins_claimed')</th>
                        <th class="text-right">@lang('lang_v1.paid')</th>
                        <th class="text-right">@lang('lang_v1.due')</th>
                        @if ($can_settle)
                            <th style="min-width:130px;">@lang('clinic.ins_paid_now')</th>
                            <th>@lang('clinic.ins_bill_rest')</th>
                        @elseif ($claim->status == 'draft')
                            <th></th>
                        @else
                            <th>@lang('sale.status')</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $it)
                        @php $pd = optional(optional($it->patient)->patientDetails); @endphp
                        <tr>
                            <td>{{ @format_date($it->charged_on) }}<br><small class="text-muted">{{ $it->patient_invoice_no }}</small></td>
                            <td><strong>{{ optional($it->patient)->name }}</strong>
                                <br><small class="text-muted">{{ $pd->file_number }}@if ($pd->insurance_number) · @lang('lang_v1.insurance_number'): {{ $pd->insurance_number }}@endif</small>
                            </td>
                            <td style="max-width:280px;">
                                @foreach ((array) $it->lines as $l)
                                    <div>{{ $l['name'] ?? '' }}@if (($l['qty'] ?? 1) != 1) × {{ (float) $l['qty'] }}@endif</div>
                                @endforeach
                                <small class="text-muted">{{ __('clinic.ins_pct_covered', ['pct' => \App\Utils\ClinicInsurance::pct($it->pct)]) }}</small>
                            </td>
                            <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $it->full_amount }}</span></td>
                            <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $it->amount }}</span></td>
                            <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $it->paid }}</span></td>
                            <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $it->due }}</span>
                                @if ($it->rebilled_amount > 0)
                                    <br><small class="text-warning">{{ __('clinic.ins_rebilled_note', ['amount' => $fmt($it->rebilled_amount), 'invoice' => optional($it->rebillInvoice)->invoice_no ?: '-']) }}</small>
                                    @if ($it->rebill_reason)<br><small class="text-muted">{{ $it->rebill_reason }}</small>@endif
                                @endif
                            </td>
                            @if ($can_settle)
                                <td>
                                    @if ($it->due > 0.0001)
                                        <input type="text" name="items[{{ $it->id }}][paid]" class="form-control input-sm input_number js-paid" value="{{ $fmt($it->due) }}" data-due="{{ $it->due }}">
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if ($it->due > 0.0001)
                                        <input type="checkbox" name="items[{{ $it->id }}][rebill]" value="1" class="js-rebill" title="{{ __('clinic.ins_bill_rest') }}">
                                    @endif
                                </td>
                            @elseif ($claim->status == 'draft')
                                <td class="text-right">
                                    <form method="POST" action="{{ action([$C, 'removeItem'], [$claim->id, $it->id]) }}" style="display:inline;">@csrf
                                        <button type="submit" class="btn btn-xs btn-link text-danger" title="{{ __('clinic.ins_remove_item') }}"><i class="fas fa-times"></i> @lang('clinic.ins_remove_item')</button>
                                    </form>
                                </td>
                            @else
                                <td>@lang('clinic.ins_item_' . $it->state)</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">@lang('clinic.ins_claim_empty')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent

    @if ($can_settle)
        @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.ins_record_payment')])
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label>@lang('clinic.ins_total_received'):</label>
                        <div style="font-size:20px; font-weight:700;" id="settle_total">0</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="method">@lang('lang_v1.payment_method'):</label>
                        {!! Form::select('method', $payment_types, 'bank_transfer', ['class' => 'form-control', 'id' => 'method']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="paid_on">@lang('lang_v1.paid_on'):</label>
                        <input type="date" name="paid_on" id="paid_on" class="form-control" value="{{ \Carbon::today()->toDateString() }}" max="{{ \Carbon::today()->toDateString() }}">
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="reference">@lang('clinic.ins_reference'):</label>
                        <input type="text" name="reference" id="reference" class="form-control" maxlength="200" placeholder="{{ __('clinic.ins_reference_ph') }}">
                    </div>
                </div>
            </div>
            <div class="form-group" id="reason_box" style="display:none;">
                <label for="reason">@lang('clinic.ins_reject_reason'):</label>
                <input type="text" name="reason" id="reason" class="form-control" maxlength="150" placeholder="{{ __('clinic.ins_reject_reason_ph') }}">
                <p class="help-block">@lang('clinic.ins_bill_rest_help')</p>
            </div>
            <div class="text-center">
                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white"><i class="fas fa-check"></i> @lang('clinic.ins_save_payment')</button>
            </div>
        @endcomponent
        </form>
    @endif
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $(document).on('submit', 'form.js-confirm', function () { return confirm($(this).data('confirm')); });

        function settleTotal() {
            var total = 0;
            $('#claim_items .js-paid').each(function () { total += __read_number($(this)) || 0; });
            $('#settle_total').text(__currency_trans_from_en(total, true));
            $('#reason_box').toggle($('#claim_items .js-rebill:checked').length > 0);
        }
        $(document).on('input change', '#claim_items .js-paid, #claim_items .js-rebill', settleTotal);
        if ($('#settle_form').length) { settleTotal(); }

        $('#settle_form').on('submit', function () {
            var over = false;
            $('#claim_items .js-paid').each(function () {
                if ((__read_number($(this)) || 0) > parseFloat($(this).data('due')) + 0.004) { over = true; }
            });
            if (over) { toastr.error(@json(__('clinic.ins_paid_more_than_due'))); return false; }
            return confirm(@json(__('clinic.ins_settle_confirm')));
        });
    });
</script>
@endsection
