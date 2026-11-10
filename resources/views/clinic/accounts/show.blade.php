@extends('layouts.app')
@section('title', __('clinic.patient_account') . ' · ' . $patient->name)

@section('css')
<style>
    .acc-balance { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 14px; }
    .acc-balance .big { font-size: 30px; font-weight: 800; line-height: 1.1; }
    .acc-balance .lbl { font-size: 12px; text-transform: uppercase; color: #64748b; font-weight: 600; }
    .acc-pay .form-group { margin-bottom: 10px; }
    #allocation_table input { max-width: 130px; display: inline-block; }
</style>
@endsection

@section('content')
@php $pd = $patient->patientDetails; @endphp
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $patient->name }}
        <small class="tw-text-gray-600">
            @if (optional($pd)->file_number){{ $pd->file_number }} · @endif
            {{ $patient->mobile }} · @lang('clinic.patient_account')
        </small>
    </h1>
</section>

<section class="content">
    @include('clinic.accounts.partials.register_bar')
    <div style="margin-bottom:12px; display:flex; gap:8px; flex-wrap:wrap;">
        <a href="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-arrow-left"></i> @lang('clinic.patient_accounts')</a>
        @can('patient.view')
            <a href="{{ action([\App\Http\Controllers\PatientController::class, 'show'], [$patient->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-folder-open"></i> @lang('clinic.patient_file')</a>
        @endcan
    </div>

    <div class="row">
        <div class="col-md-4 col-md-push-8">
            <div class="acc-balance">
                <div class="lbl">@lang('clinic.acc_balance')</div>
                <div class="big {{ $balance > 0 ? 'text-danger' : 'text-success' }}"><span class="display_currency" data-currency_symbol="true">{{ $balance }}</span></div>
                <div class="text-muted">{{ trans_choice('clinic.acc_unpaid_count', $due_invoices->count(), ['n' => $due_invoices->count()]) }}</div>
                @if ($plan && $plan['message'])
                    <div style="margin-top:6px;"><i class="fas fa-id-card"></i> {{ $plan['message'] }}</div>
                @endif
                @if ($insurer_open->isNotEmpty())
                    <div style="margin-top:6px;"><i class="fas fa-shield-alt"></i> {{ __('clinic.ins_acc_insurer_owes', ['amount' => app(\App\Utils\Util::class)->num_f($insurer_open->sum('due'), true), 'insurer' => $insurer_open->pluck('insurer.name')->unique()->implode(', ')]) }}</div>
                @endif
                @if ((float) $patient->balance > 0)
                    <div class="text-muted" style="margin-top:4px;">@lang('lang_v1.advance_balance'): <span class="display_currency" data-currency_symbol="true">{{ $patient->balance }}</span></div>
                @endif
            </div>

            @if ($can_pay && $balance > 0)
                <div class="acc-balance acc-pay">
                    <h4 style="margin-top:0;"><i class="fas fa-money-bill-alt"></i> @lang('clinic.acc_receive_payment')</h4>
                    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'pay'], [$patient->id]) }}" id="acc_pay_form">
                        @csrf
                        <div class="form-group">
                            <label for="acc_amount">@lang('sale.amount')</label>
                            <input type="text" name="amount" id="acc_amount" class="form-control input_number input-lg" value="{{ @num_format($balance) }}" required>
                        </div>
                        <div class="form-group">
                            <label for="acc_method">@lang('lang_v1.payment_method')</label>
                            {!! Form::select('method', $payment_types, 'cash', ['class' => 'form-control', 'id' => 'acc_method', 'required']) !!}
                        </div>
                        <div class="form-group">
                            <label for="acc_ref">@lang('clinic.acc_reference')</label>
                            <input type="text" name="transaction_no" id="acc_ref" class="form-control" maxlength="100" placeholder="{{ __('clinic.acc_reference_help') }}">
                        </div>
                        <div class="form-group">
                            <label for="acc_note">@lang('lang_v1.notes')</label>
                            <input type="text" name="note" id="acc_note" class="form-control" maxlength="255">
                        </div>
                        <div id="allocation_inputs"></div>
                        <button type="submit" class="tw-dw-btn tw-dw-btn-success tw-text-white tw-w-full"><i class="fas fa-check"></i> @lang('clinic.acc_receive_and_print')</button>
                        <p class="help-block" style="margin-bottom:0;">@lang('clinic.acc_oldest_first')</p>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-md-8 col-md-pull-4">
            @component('components.widget', ['class' => 'box-danger', 'title' => __('clinic.acc_unpaid_invoices')])
                @if ($due_invoices->isEmpty())
                    <p class="text-muted"><i class="fas fa-check-circle text-success"></i> @lang('clinic.acc_nothing_owed')</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-condensed" id="allocation_table">
                            <thead>
                                <tr>
                                    <th>@lang('messages.date')</th>
                                    <th>@lang('sale.invoice_no')</th>
                                    <th>@lang('clinic.acc_for')</th>
                                    <th class="text-right">@lang('sale.total')</th>
                                    <th class="text-right">@lang('clinic.acc_due')</th>
                                    @if ($can_pay)<th class="text-right">@lang('clinic.acc_pay_now')</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($due_invoices as $inv)
                                    <tr data-id="{{ $inv->id }}" data-due="{{ $inv->due }}">
                                        <td>{{ @format_date($inv->transaction_date) }}</td>
                                        <td><a href="#" class="btn-modal" data-href="{{ action([\App\Http\Controllers\SellController::class, 'show'], [$inv->id]) }}" data-container=".view_modal">{{ $inv->invoice_no }}</a></td>
                                        <td>{{ \App\Utils\ClinicCashier::describe($inv) }}</td>
                                        <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $inv->final_total }}</span></td>
                                        <td class="text-right text-danger"><span class="display_currency" data-currency_symbol="true">{{ $inv->due }}</span></td>
                                        @if ($can_pay)
                                            <td class="text-right"><input type="text" class="form-control input-sm input_number alloc" value="0" aria-label="{{ __('clinic.ins_paid_now') }}"></td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endcomponent

            @if ($insurer_open->isNotEmpty())
                @component('components.widget', ['class' => 'box-info', 'title' => __('clinic.ins_acc_with_insurer')])
                    <p class="help-block" style="margin-top:0;">@lang('clinic.ins_acc_with_insurer_help')</p>
                    <div class="table-responsive">
                        <table class="table table-condensed">
                            <thead><tr>
                                <th>@lang('lang_v1.date')</th><th>@lang('clinic.ins_insurer')</th><th>@lang('clinic.ins_services')</th>
                                <th class="text-right">@lang('clinic.ins_claimed')</th><th class="text-right">@lang('lang_v1.due')</th><th>@lang('clinic.ins_claim')</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($insurer_open as $s)
                                    <tr>
                                        <td>{{ @format_date(optional($s->patientInvoice)->transaction_date) }}<br><small class="text-muted">{{ optional($s->patientInvoice)->invoice_no }}</small></td>
                                        <td>{{ optional($s->insurer)->name }} ({{ \App\Utils\ClinicInsurance::pct($s->pct) }}%)</td>
                                        <td>{{ collect((array) $s->lines)->pluck('name')->implode(', ') }}</td>
                                        <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $s->amount }}</span></td>
                                        <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $s->due }}</span></td>
                                        <td>{{ $s->claim ? $s->claim->ref_no : __('clinic.ins_not_claimed') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endcomponent
            @endif

            @component('components.widget', ['class' => 'box-default', 'title' => __('clinic.acc_payments_received')])
                @if ($payments->isEmpty())
                    <p class="text-muted">@lang('clinic.acc_no_payments')</p>
                @else
                    <div class="table-responsive">
                    <table class="table table-condensed">
                        <thead><tr><th>@lang('messages.date')</th><th>@lang('purchase.ref_no')</th><th>@lang('lang_v1.payment_method')</th><th>@lang('clinic.acc_received_by')</th><th class="text-right">@lang('sale.amount')</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($payments as $p)
                                <tr>
                                    <td>{{ @format_datetime($p->paid_on) }}</td>
                                    <td>{{ $p->payment_ref_no }}</td>
                                    <td>{{ $payment_types[$p->method] ?? $p->method }}</td>
                                    <td>{{ optional($p->created_user)->user_full_name }}</td>
                                    <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $p->is_return ? -1 * $p->amount : $p->amount }}</span></td>
                                    <td class="text-right"><a href="#" class="btn-modal tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline" data-href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'viewPayment'], [$p->id]) }}" data-container=".view_modal"><i class="fas fa-receipt"></i> @lang('clinic.acc_receipt')</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            @endcomponent

            @component('components.widget', ['class' => 'box-default', 'title' => __('clinic.acc_recent_invoices')])
                <div class="table-responsive">
                <table class="table table-condensed">
                    <thead><tr><th>@lang('messages.date')</th><th>@lang('sale.invoice_no')</th><th>@lang('clinic.acc_for')</th><th>@lang('sale.payment_status')</th><th class="text-right">@lang('sale.total')</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($recent_invoices as $inv)
                            <tr>
                                <td>{{ @format_date($inv->transaction_date) }}</td>
                                <td>{{ $inv->invoice_no }}</td>
                                <td>{{ \App\Utils\ClinicCashier::describe($inv) }}</td>
                                <td>{{ __('lang_v1.' . $inv->payment_status) }}</td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $inv->final_total }}</span></td>
                                <td class="text-right"><a href="#" data-href="{{ route('sell.printInvoice', [$inv->id]) }}" class="print-invoice tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline" title="@lang('messages.print')" aria-label="@lang('messages.print') {{ $inv->invoice_no }}"><i class="fas fa-print"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">@lang('lang_v1.no_records_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        var $amount = $('#acc_amount');
        var balance = {{ (float) $balance }};
        var editingSplit = false;

        // Spread the amount over the unpaid invoices, oldest first.
        function spread() {
            var left = __read_number($amount) || 0;
            $('#allocation_table tbody tr').each(function () {
                var due = parseFloat($(this).data('due')) || 0;
                var take = Math.min(left, due);
                __write_number($(this).find('input.alloc'), Math.max(0, take));
                left = Math.round((left - take) * 10000) / 10000;
            });
        }
        $amount.on('input change', function () { editingSplit = false; spread(); });
        $(document).on('input change', '#allocation_table input.alloc', function () {
            editingSplit = true;
            var sum = 0;
            $('#allocation_table input.alloc').each(function () { sum += __read_number($(this)) || 0; });
            __write_number($amount, Math.round(sum * 10000) / 10000);
        });
        spread();

        $('#acc_pay_form').on('submit', function () {
            var amt = __read_number($amount) || 0;
            if (amt <= 0) { toastr.error(@json(__('clinic.acc_amount_required'))); return false; }
            if (amt > balance + 0.004) { toastr.error(@json(__('clinic.acc_more_than_balance'))); return false; }
            var $box = $('#allocation_inputs').empty();
            if (editingSplit) {
                $('#allocation_table tbody tr').each(function () {
                    var v = __read_number($(this).find('input.alloc')) || 0;
                    if (v > 0) { $box.append($('<input type="hidden">').attr('name', 'allocation[' + $(this).data('id') + ']').val(v)); }
                });
            }
            $(this).find('button[type="submit"]').prop('disabled', true);
            return true;
        });

        @if (session('print_payment_id'))
            // the receipt for the payment just taken
            $.get('{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'viewPayment'], [session('print_payment_id')]) }}', function (html) {
                $('.view_modal').html(html).modal('show');
                __currency_convert_recursively($('.view_modal'));
            });
        @endif
    });
</script>
@endsection
