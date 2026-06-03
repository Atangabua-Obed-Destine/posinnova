<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">
                @lang('lang_v1.department_issue') &mdash; {{ $issue->ref_no }}
            </h4>
        </div>
        <div class="modal-body">
            <div class="row">
                <div class="col-sm-6">
                    <b>@lang('messages.date'):</b> {{ @format_datetime($issue->transaction_date) }}<br>
                    <b>@lang('lang_v1.issuing_pharmacy'):</b> {{ optional($issue->location)->name }}<br>
                    <b>@lang('lang_v1.department'):</b> {{ optional($department)->name }}<br>
                    @if(! empty(optional($department)->hod_name))
                        <b>@lang('lang_v1.hod_name'):</b> {{ $department->hod_name }}<br>
                    @endif
                </div>
                <div class="col-sm-6">
                    <b>@lang('lang_v1.issued_by'):</b> {{ optional($creator)->first_name }} {{ optional($creator)->last_name }}<br>
                    <b>@lang('sale.status'):</b> {{ ucfirst($issue->status) }}<br>
                    @if(! empty($issue->additional_notes))
                        <b>@lang('purchase.additional_notes'):</b> {{ $issue->additional_notes }}<br>
                    @endif
                </div>
            </div>
            <hr>
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('sale.product')</th>
                        <th class="text-right">@lang('sale.qty')</th>
                        @can('view_purchase_price')
                            <th class="text-right">@lang('lang_v1.purchase_price')</th>
                            <th class="text-right">@lang('sale.subtotal')</th>
                        @endcan
                    </tr>
                </thead>
                <tbody>
                    @foreach($issue->sell_lines as $line)
                        @php
                            $qty_display = $line->quantity;
                            $unit_label = '';
                            if (! empty($line->sub_unit) && ! empty($line->sub_unit->short_name)) {
                                $unit_label = $line->sub_unit->short_name;
                            }
                            $subtotal = $line->quantity * $line->unit_price;
                        @endphp
                        <tr>
                            <td>{{ optional($line->product)->name }} ({{ optional($line->variations)->sub_sku }})</td>
                            <td class="text-right">{{ @num_format($qty_display) }} {{ $unit_label }}</td>
                            @can('view_purchase_price')
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $line->unit_price }}</span></td>
                                <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $subtotal }}</span></td>
                            @endcan
                        </tr>
                    @endforeach
                </tbody>
                @can('view_purchase_price')
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">@lang('sale.total'):</th>
                            <th class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $issue->final_total }}</span></th>
                        </tr>
                    </tfoot>
                @endcan
            </table>
        </div>
        <div class="modal-footer">
            <a href="{{ action([\App\Http\Controllers\DepartmentIssueController::class, 'printVoucher'], [$issue->id]) }}" target="_blank" class="tw-dw-btn tw-dw-btn-info tw-text-white">
                <i class="fa fa-print"></i> @lang('messages.print')
            </a>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>
    </div>
</div>
<script>
    __currency_convert_recursively($('.view_modal'));
</script>
