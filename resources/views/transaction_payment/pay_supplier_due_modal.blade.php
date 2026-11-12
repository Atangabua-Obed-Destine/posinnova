<div class="modal-dialog modal-lg" role="document">
  <div class="modal-content">

    {!! Form::open(['url' => action([\App\Http\Controllers\TransactionPaymentController::class, 'postPayContactDue']), 'method' => 'post', 'id' => 'pay_contact_due_form', 'files' => true ]) !!}

    {!! Form::hidden("contact_id", $contact_details->contact_id); !!}
    {{-- PosInnova: what the "check before recording" dialog lists --}}
    <span style="display:none" data-confirm-label="{{ in_array($due_payment_type, ['purchase', 'purchase_return']) ? __('purchase.supplier') : __('contact.customer') }}">{{ $contact_details->name }}</span>
    {!! Form::hidden("due_payment_type", $due_payment_type); !!}
    {!! Form::hidden("allocation_mode", 'auto', ['id' => 'allocation_mode_input']); !!}
    <div class="modal-header">
      <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
      <h4 class="modal-title">@lang( 'purchase.add_payment' )</h4>
    </div>

    <div class="modal-body">
      <div class="row">
        @if($due_payment_type == 'purchase')
        <div class="col-md-6">
          <div class="well">
            <strong>@lang('purchase.supplier'): </strong>{{ $contact_details->name }}<br>
            <strong>@lang('business.business'): </strong>{{ $contact_details->supplier_business_name }}<br><br>
          </div>
        </div>
        <div class="col-md-6">
          <div class="well">
            <strong>@lang('report.total_purchase'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_purchase }}</span><br>
            <strong>@lang('contact.total_paid'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_paid }}</span><br>
            <strong>@lang('contact.total_purchase_due'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_purchase - $contact_details->total_paid }}</span><br>
             @if(!empty($contact_details->opening_balance) || $contact_details->opening_balance != '0.00')
                  <strong>@lang('lang_v1.opening_balance'): </strong>
                  <span class="display_currency" data-currency_symbol="true">
                  {{ $contact_details->opening_balance }}</span><br>
                  <strong>@lang('lang_v1.opening_balance_due'): </strong>
                  <span class="display_currency" data-currency_symbol="true">
                  {{ $ob_due }}</span>
              @endif
          </div>
        </div>
        @elseif($due_payment_type == 'purchase_return')
        <div class="col-md-6">
          <div class="well">
            <strong>@lang('purchase.supplier'): </strong>{{ $contact_details->name }}<br>
            <strong>@lang('business.business'): </strong>{{ $contact_details->supplier_business_name }}<br><br>
          </div>
        </div>
        <div class="col-md-6">
          <div class="well">
            <strong>@lang('lang_v1.total_purchase_return'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_purchase_return }}</span><br>
            <strong>@lang('lang_v1.total_purchase_return_paid'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_return_paid }}</span><br>
            <strong>@lang('lang_v1.total_purchase_return_due'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_purchase_return - $contact_details->total_return_paid }}</span>
          </div>
        </div>
        @elseif(in_array($due_payment_type, ['sell']))
          <div class="col-md-6">
            <div class="well">
              <strong>@lang('sale.customer_name'): </strong>{{ $contact_details->name }}<br>
              <br><br>
            </div>
          </div>
          <div class="col-md-6">
            <div class="well">
              <strong>@lang('report.total_sell'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_invoice }}</span><br>
              <strong>@lang('contact.total_paid'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_paid }}</span><br>
              <strong>@lang('contact.total_sale_due'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_invoice - $contact_details->total_paid }}</span><br>
              @if(!empty($contact_details->opening_balance) || $contact_details->opening_balance != '0.00')
                  <strong>@lang('lang_v1.opening_balance'): </strong>
                  <span class="display_currency" data-currency_symbol="true">
                  {{ $contact_details->opening_balance }}</span><br>
                  <strong>@lang('lang_v1.opening_balance_due'): </strong>
                  <span class="display_currency" data-currency_symbol="true">
                  {{ $ob_due }}</span>
              @endif
            </div>
          </div>
         @elseif(in_array($due_payment_type, ['sell_return']))
         <div class="col-md-6">
          <div class="well">
            <strong>@lang('sale.customer_name'): </strong>{{ $contact_details->name }}<br>
              <br><br>
          </div>
        </div>
        <div class="col-md-6">
          <div class="well">
            <strong>@lang('lang_v1.total_sell_return'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_sell_return }}</span><br>
            <strong>@lang('lang_v1.total_sell_return_paid'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_return_paid }}</span><br>
            <strong>@lang('lang_v1.total_sell_return_due'): </strong><span class="display_currency" data-currency_symbol="true">{{ $contact_details->total_sell_return - $contact_details->total_return_paid }}</span>
          </div>
        </div>
        @endif
      </div>
        @if(config('constants.show_payment_type_on_contact_pay') && ($due_payment_type == 'purchase' || $due_payment_type == 'sell'))
            @php
                $reverse_payment_types = [];

                if($due_payment_type == 'purchase') {
                    $reverse_payment_types = [
                        0 => __('lang_v1.pay_to_supplier'),
                        1 => __('lang_v1.receive_from_supplier')
                    ];
                } else if($due_payment_type == 'sell') {
                    $reverse_payment_types = [
                        0 => __('lang_v1.receive_from_customer'),
                        1 => __('lang_v1.pay_to_customer')
                    ];
                }
            @endphp
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label("is_reverse" , __('lang_v1.payment_type') . ':') !!}
                        {!! Form::select("is_reverse", $reverse_payment_types, 0, ['class' => 'form-control select2', 'style' => 'width:100%;']); !!}
                    </div>
                </div>
            </div>
        @endif

        {{-- Allocation mode selector + per-invoice list --}}
        @if(in_array($due_payment_type, ['sell', 'purchase']) && isset($due_invoices) && $due_invoices->count() > 0)
        <div class="row" style="margin-bottom:10px;">
            <div class="col-md-12">
                <div class="form-group">
                    <label>@lang('lang_v1.allocation_mode'):</label>
                    <div class="radio" style="margin-top:0;">
                        <label style="margin-right:20px;">
                            <input type="radio" name="allocation_mode_radio" value="auto" checked>
                            @lang('lang_v1.allocation_auto_distribute')
                        </label>
                        <label>
                            <input type="radio" name="allocation_mode_radio" value="invoices">
                            @lang('lang_v1.allocation_specific_invoices')
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="row invoice_allocation_block hide" style="margin-bottom:10px;">
            <div class="col-md-12">
                <div class="table-responsive" style="max-height:280px; overflow:auto;">
                    <table class="table table-bordered table-condensed table-striped invoice_allocation_table" style="margin-bottom:5px;">
                        <thead>
                            <tr>
                                <th width="40"><input type="checkbox" class="invoice_select_all" title="@lang('lang_v1.select_all')"></th>
                                <th>@lang('sale.invoice_no')</th>
                                <th class="text-nowrap">@lang('messages.date')</th>
                                <th class="text-right">@lang('sale.total_amount')</th>
                                <th class="text-right">@lang('contact.total_paid')</th>
                                <th class="text-right">@lang('sale.due')</th>
                                <th class="text-right" style="min-width:120px;">@lang('lang_v1.pay_now')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($due_invoices as $inv)
                            <tr>
                                <td><input type="checkbox" class="invoice_select" data-invoice-id="{{ $inv->id }}" data-due="{{ $inv->due }}"></td>
                                <td>
                                    @if($inv->type == 'opening_balance')
                                        <span class="label label-info">@lang('lang_v1.opening_balance')</span>
                                    @else
                                        {{ $inv->invoice_no }}
                                    @endif
                                </td>
                                <td>{{ \Carbon::parse($inv->transaction_date)->format(session('business.date_format') ?? 'Y-m-d') }}</td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $inv->final_total }}</span></td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $inv->paid }}</span></td>
                                <td class="text-right invoice_due_cell" data-due="{{ $inv->due }}"><span class="display_currency" data-currency_symbol="true">{{ $inv->due }}</span></td>
                                <td>
                                    <input type="text" name="invoice_payments[{{ $inv->id }}]" class="form-control input-sm input_number invoice_pay_amount" value="" disabled placeholder="0.00" data-max-due="{{ $inv->due }}">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="text-right">@lang('sale.total'):</th>
                                <th class="text-right"><span class="invoice_allocation_total">0.00</span></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <p class="help-block">@lang('lang_v1.allocation_invoices_help')</p>
            </div>
        </div>
        @endif

      <div class="row payment_row">
        <div class="col-md-4">
          <div class="form-group">
            {!! Form::label("method" , __('purchase.payment_method') . ':*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fas fa-money-bill-alt"></i>
              </span>
              {!! Form::select("method", $payment_types, $payment_line->method, ['class' => 'form-control select2 payment_types_dropdown', 'required', 'style' => 'width:100%;', 'data-confirm-label' => __('purchase.payment_method')]); !!}
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            {!! Form::label("paid_on" , __('lang_v1.paid_on') . ':*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-calendar"></i>
              </span>
              {!! Form::text('paid_on', @format_datetime($payment_line->paid_on), ['class' => 'form-control', 'readonly', 'required', 'data-confirm-label' => __('lang_v1.paid_on')]); !!}
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-group">
            {!! Form::label("amount" , __('sale.amount') . ':*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fas fa-money-bill-alt"></i>
              </span>
              @if(in_array($due_payment_type, ['sell_return', 'purchase_return']))
              {!! Form::text("amount", @num_format($payment_line->amount), ['class' => 'form-control input_number payment_amount', 'required', 'data-confirm-amount' => 1, 'placeholder' => __('sale.amount'), 'data-rule-max-value' => $payment_line->amount, 'data-msg-max-value' => __('lang_v1.max_amount_to_be_paid_is', ['amount' => $amount_formated])]); !!}
              @else
                {!! Form::text("amount", @num_format($payment_line->amount), ['class' => 'form-control input_number payment_amount', 'required', 'data-confirm-amount' => 1, 'placeholder' => __('sale.amount')]); !!}
              @endif
            </div>
          </div>
        </div>
        @php
            $pos_settings = !empty(session()->get('business.pos_settings')) ? json_decode(session()->get('business.pos_settings'), true) : [];

            $enable_cash_denomination_for_payment_methods = !empty($pos_settings['enable_cash_denomination_for_payment_methods']) ? $pos_settings['enable_cash_denomination_for_payment_methods'] : [];
        @endphp

        @if(!empty($pos_settings['enable_cash_denomination_on']) && $pos_settings['enable_cash_denomination_on'] == 'all_screens')
            <input type="hidden" class="enable_cash_denomination_for_payment_methods" value="{{json_encode($pos_settings['enable_cash_denomination_for_payment_methods'])}}">
            <div class="clearfix"></div>
            <div class="col-md-12 cash_denomination_div @if(!in_array($payment_line->method, $enable_cash_denomination_for_payment_methods)) hide @endif">
                <hr>
                <strong>@lang( 'lang_v1.cash_denominations' )</strong>
                  @if(!empty($pos_settings['cash_denominations']))
                    <table class="table table-slim">
                      <thead>
                        <tr>
                          <th width="20%" class="text-right">@lang('lang_v1.denomination')</th>
                          <th width="20%">&nbsp;</th>
                          <th width="20%" class="text-center">@lang('lang_v1.count')</th>
                          <th width="20%">&nbsp;</th>
                          <th width="20%" class="text-left">@lang('sale.subtotal')</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach(explode(',', $pos_settings['cash_denominations']) as $dnm)
                        <tr>
                          <td class="text-right">{{$dnm}}</td>
                          <td class="text-center" >X</td>
                          <td>{!! Form::number("denominations[$dnm]", null, ['class' => 'form-control cash_denomination input-sm', 'min' => 0, 'data-denomination' => $dnm, 'style' => 'width: 100px; margin:auto;' ]); !!}</td>
                          <td class="text-center">=</td>
                          <td class="text-left">
                            <span class="denomination_subtotal">0</span>
                          </td>
                        </tr>
                        @endforeach
                      </tbody>
                      <tfoot>
                        <tr>
                          <th colspan="4" class="text-center">@lang('sale.total')</th>
                          <td>
                            <span class="denomination_total">0</span>
                            <input type="hidden" class="denomination_total_amount" value="0">
                            <input type="hidden" class="is_strict" value="{{$pos_settings['cash_denomination_strict_check'] ?? ''}}">
                          </td>
                        </tr>
                      </tfoot>
                    </table>
                    <p class="cash_denomination_error error hide">@lang('lang_v1.cash_denomination_error')</p>
                  @else
                    <p class="help-block">@lang('lang_v1.denomination_add_help_text')</p>
                  @endif
            </div>
        @endif

        <div class="clearfix"></div>
        <div class="col-md-4">
          <div class="form-group">
            {!! Form::label('document', __('purchase.attach_document') . ':') !!}
            {!! Form::file('document', ['accept' => implode(',', array_keys(config('constants.document_upload_mimes_types')))]); !!}
            <p class="help-block">
            @includeIf('components.document_help_text')</p>
          </div>
        </div>
        @if(!empty($accounts))
          <div class="col-md-6">
            <div class="form-group">
              {!! Form::label("account_id" , __('lang_v1.payment_account') . ':') !!}
              <div class="input-group">
                <span class="input-group-addon">
                  <i class="fas fa-money-bill-alt"></i>
                </span>
                {!! Form::select("account_id", $accounts, !empty($payment_line->account_id) ? $payment_line->account_id : '' , ['class' => 'form-control select2', 'id' => "account_id", 'style' => 'width:100%;', 'data-confirm-label' => __('lang_v1.payment_account')]); !!}
              </div>
            </div>
          </div>
        @endif
        <div class="clearfix"></div>

          @include('transaction_payment.payment_type_details')
        <div class="col-md-12">
          <div class="form-group">
            {!! Form::label("note", __('lang_v1.payment_note') . ':') !!}
            {!! Form::textarea("note", $payment_line->note, ['class' => 'form-control', 'rows' => 3]); !!}
          </div>
        </div>
      </div>
    </div>

    <div class="modal-footer">
      <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang( 'messages.save' )</button>
      <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang( 'messages.close' )</button>
    </div>

    {!! Form::close() !!}

  </div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->

<script>
(function () {
    var $modal = $('.pay_contact_due_modal');
    var $form = $modal.find('form#pay_contact_due_form');
    var $allocBlock = $form.find('.invoice_allocation_block');
    var $modeInput = $form.find('#allocation_mode_input');
    var $amount = $form.find('input.payment_amount');
    var defaultAmount = $amount.val();

    function updateTotal() {
        var total = 0;
        $allocBlock.find('input.invoice_pay_amount:enabled').each(function () {
            var v = parseFloat(__read_number($(this))) || 0;
            total += v;
        });
        $allocBlock.find('.invoice_allocation_total').text(__currency_trans_from_en(total, false, false));
    }

    $form.on('change', 'input[name="allocation_mode_radio"]', function () {
        var mode = $(this).val();
        $modeInput.val(mode);
        if (mode === 'invoices') {
            $allocBlock.removeClass('hide');
            // Amount field stays editable; treat it as informational in this mode
            updateTotal();
        } else {
            $allocBlock.addClass('hide');
            $amount.val(defaultAmount);
        }
    });

    $form.on('change', 'input.invoice_select', function () {
        var $row = $(this).closest('tr');
        var $input = $row.find('input.invoice_pay_amount');
        if (this.checked) {
            $input.prop('disabled', false);
            if (!$input.val() || parseFloat(__read_number($input)) === 0) {
                var due = parseFloat($input.data('max-due')) || 0;
                $input.val(__currency_trans_from_en(due, false, false));
            }
        } else {
            $input.prop('disabled', true).val('');
        }
        updateTotal();
    });

    $form.on('change', 'input.invoice_select_all', function () {
        var checked = this.checked;
        $allocBlock.find('input.invoice_select').prop('checked', checked).trigger('change');
    });

    $form.on('input change', 'input.invoice_pay_amount', function () {
        var max = parseFloat($(this).data('max-due')) || 0;
        var v = parseFloat(__read_number($(this))) || 0;
        if (v > max) {
            $(this).val(__currency_trans_from_en(max, false, false));
        }
        updateTotal();
    });

    // Submit guard
    $form.on('submit', function (e) {
        if ($modeInput.val() === 'invoices') {
            var hasRow = false;
            $allocBlock.find('input.invoice_pay_amount:enabled').each(function () {
                if ((parseFloat(__read_number($(this))) || 0) > 0) {
                    hasRow = true;
                }
            });
            if (!hasRow) {
                e.preventDefault();
                toastr.warning('{{ __('lang_v1.no_invoice_amount_entered') }}');
                return false;
            }
        }
    });
})();
</script>