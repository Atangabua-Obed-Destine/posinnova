@extends('layouts.app')
@section('title', __('lang_v1.invoice_commission_report'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('lang_v1.invoice_commission_report') }}</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('ic_commission_agent', __('lang_v1.commission_recipient') . ':') !!}
                        {!! Form::select('ic_commission_agent', $commission_agents, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('ic_location_id', __('purchase.business_location') . ':') !!}
                        {!! Form::select('ic_location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('lang_v1.all')]); !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('ic_date_range', __('report.date_range') . ':') !!}
                        {!! Form::text('ic_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'ic_date_range', 'readonly']); !!}
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['title' => __('lang_v1.total_commission')])
                <h3 class="text-muted">
                    {{ __('lang_v1.total_commission') }}:
                    <span id="ic_grand_total"><i class="fas fa-sync fa-spin fa-fw"></i></span>
                </h3>
                <table class="table table-condensed" style="max-width:520px;">
                    <thead>
                        <tr>
                            <th>@lang('lang_v1.commission_recipient')</th>
                            <th class="text-right">@lang('lang_v1.total_commission')</th>
                        </tr>
                    </thead>
                    <tbody id="ic_by_recipient_body"></tbody>
                </table>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['title' => __('lang_v1.invoice_commission_report')])
                <table class="table table-bordered table-striped" id="invoice_commission_table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>@lang('messages.date')</th>
                            <th>@lang('sale.invoice_no')</th>
                            <th>@lang('contact.customer')</th>
                            <th>@lang('lang_v1.commission_recipient')</th>
                            <th class="text-right">@lang('lang_v1.commission_amount')</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr class="bg-gray text-center">
                            <td colspan="4" class="text-right"><strong>@lang('sale.total'):</strong></td>
                            <td class="text-right"><span id="ic_footer_total"></span></td>
                        </tr>
                    </tfoot>
                </table>
            @endcomponent
        </div>
    </div>
</section>

<div class="modal fade view_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    if (typeof dateRangeSettings !== 'undefined') {
        $('#ic_date_range').daterangepicker(dateRangeSettings, function (start, end) {
            $('#ic_date_range').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
            invoice_commission_table.ajax.reload();
        });
        $('#ic_date_range').on('cancel.daterangepicker', function () {
            $('#ic_date_range').val('');
            invoice_commission_table.ajax.reload();
        });
    }

    var invoice_commission_table = $('#invoice_commission_table').DataTable({
        processing: true,
        serverSide: false,
        order: [[0, 'desc']],
        ajax: {
            url: '/reports/invoice-commission',
            data: function (d) {
                d.commission_agent = $('#ic_commission_agent').val();
                d.location_id = $('#ic_location_id').val();
                if ($('#ic_date_range').val()) {
                    var dr = $('#ic_date_range').data('daterangepicker');
                    d.start_date = dr.startDate.format('YYYY-MM-DD');
                    d.end_date = dr.endDate.format('YYYY-MM-DD');
                }
            }
        },
        columns: [
            { data: 'transaction_date', name: 'transactions.transaction_date' },
            { data: 'invoice_no', name: 'transactions.invoice_no' },
            { data: 'customer_name', name: 'c.name' },
            { data: 'agent_name', name: 'u.first_name' },
            {
                data: 'commission_amount', name: 'transactions.commission_amount', className: 'text-right',
                render: function (d) { return __currency_trans_from_en(d, true); }
            }
        ]
    });

    // Server extras: grand total + per-recipient breakdown (over the active filters).
    invoice_commission_table.on('xhr.dt', function (e, settings, json) {
        if (!json) return;
        var gt = json.grand_total || 0;
        $('#ic_grand_total').text(__currency_trans_from_en(gt, true));
        $('#ic_footer_total').text(__currency_trans_from_en(gt, true));

        var rows = json.by_recipient || [];
        var $body = $('#ic_by_recipient_body').empty();
        if (!rows.length) {
            $body.append('<tr><td colspan="2" class="text-center text-muted">--</td></tr>');
        } else {
            rows.forEach(function (r) {
                $body.append('<tr><td>' + $('<div>').text(r.agent_name).html() +
                    '</td><td class="text-right">' + __currency_trans_from_en(r.total, true) + '</td></tr>');
            });
        }
    });

    $('#ic_commission_agent, #ic_location_id').change(function () {
        invoice_commission_table.ajax.reload();
    });
});
</script>
@endsection
