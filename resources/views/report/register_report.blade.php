@extends('layouts.app')
@section('title', __('report.register_report'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('report.register_report')}}</h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
              {!! Form::open(['url' => action([\App\Http\Controllers\ReportController::class, 'getStockReport']), 'method' => 'get', 'id' => 'register_report_filter_form' ]) !!}
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('register_user_id',  __('report.user') . ':') !!}
                        {!! Form::select('register_user_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('report.all_users')]); !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('register_status',  __('sale.status') . ':') !!}
                        {!! Form::select('register_status', ['open' => __('cash_register.open'), 'close' => __('cash_register.close')], null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('report.all')]); !!}
                    </div>
                </div>
                @if(!empty($shift_enabled))
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('register_shift',  __('lang_v1.shift') . ':') !!}
                        {!! Form::select('register_shift', $shifts, null, ['class' => 'form-control select2', 'id' => 'register_shift', 'style' => 'width:100%', 'placeholder' => __('report.all')]); !!}
                    </div>
                </div>
                @endif
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('register_report_date_range', __('report.date_range') . ':') !!}
                        {!! Form::text('register_report_date_range', null , ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'register_report_date_range', 'readonly']); !!}
                    </div>
                </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
    @include('report.partials.export_buttons', ['export_base' => '/reports/export/register-report', 'export_map_id' => 'registerReport'])

    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <table class="table table-bordered table-striped" id="register_report_table"
                    data-export-title="{{ __('report.register_report') }}"
                    data-export-filter-container="#collapseFilter">
                    <thead>
                        <tr>
                            <th>@lang('report.open_time')</th>
                            <th>@lang('report.close_time')</th>
                            <th>@lang('sale.location')</th>
                            <th>@lang('report.user')</th>
                            @if(!empty($shift_enabled))<th>@lang('lang_v1.shift')</th>@endif
                            <th>@lang('cash_register.total_card_slips')</th>
                            <th>@lang('cash_register.total_cheques')</th>
                            <th>@lang('cash_register.total_cash')</th>
                            <th>@lang('lang_v1.total_bank_transfer')</th>
                            <th>@lang('lang_v1.total_advance_payment')</th>
                            <th>{{$payment_types['custom_pay_1']}}</th>
                            <th>{{$payment_types['custom_pay_2']}}</th>
                            <th>{{$payment_types['custom_pay_3']}}</th>
                            <th>{{$payment_types['custom_pay_4']}}</th>
                            <th>{{$payment_types['custom_pay_5']}}</th>
                            <th>{{$payment_types['custom_pay_6']}}</th>
                            <th>{{$payment_types['custom_pay_7']}}</th>
                            <th>@lang('cash_register.other_payments')</th>
                            <th>@lang('sale.total')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr class="bg-gray font-17 text-center footer-total">
                            <td colspan="{{ !empty($shift_enabled) ? 5 : 4 }}"><strong>@lang('sale.total'):</strong></td>
                            <td class="footer_total_card_payment"></td>
                            <td class="footer_total_cheque_payment"></td>
                            <td class="footer_total_cash_payment"></td>
                            <td class="footer_total_bank_transfer_payment"></td>
                            <td class="footer_total_advance_payment"></td>'
                            <td class="footer_total_custom_pay_1"></td>
                            <td class="footer_total_custom_pay_2"></td>
                            <td class="footer_total_custom_pay_3"></td>
                            <td class="footer_total_custom_pay_4"></td>
                            <td class="footer_total_custom_pay_5"></td>
                            <td class="footer_total_custom_pay_6"></td>
                            <td class="footer_total_custom_pay_7"></td>
                            <td class="footer_total_other_payments"></td>
                            <td class="footer_total"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->
<div class="modal fade view_register" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

@endsection

@section('javascript')
    <script>window.register_report_shift_enabled = {{ !empty($shift_enabled) ? 'true' : 'false' }};</script>
    <script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>
    <script>
    $(document).on('click.registerExport', '.btn-report-export[data-export-map="registerReport"]', function(e) {
        e.stopImmediatePropagation(); e.preventDefault();
        var dr = $('#register_report_date_range').data('daterangepicker');
        var params = {
            format:     $(this).data('export-format'),
            user_id:    $('#register_user_id').val() || '',
            start_date: (dr && $('#register_report_date_range').val()) ? dr.startDate.format('YYYY-MM-DD') : '',
            end_date:   (dr && $('#register_report_date_range').val()) ? dr.endDate.format('YYYY-MM-DD') : '',
        };
        window.location.href = $(this).data('export-base') + '?' + $.param(params);
    });
    </script>
@endsection