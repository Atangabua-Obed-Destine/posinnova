@extends('layouts.app')
@section('title', __('lang_v1.new_department_issue'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.new_department_issue')</h1>
    </section>

    <section class="content no-print">
        {!! Form::open([
            'url' => action([\App\Http\Controllers\DepartmentIssueController::class, 'store']),
            'method' => 'post',
            'id' => 'department_issue_form',
        ]) !!}

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('transaction_date', __('messages.date') . ':*') !!}
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-calendar"></i></span>
                            {!! Form::text('transaction_date', @format_datetime('now'), ['class' => 'form-control', 'readonly', 'required']) !!}
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('ref_no', __('purchase.ref_no') . ':') !!}
                        {!! Form::text('ref_no', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.leave_empty_to_autogenerate')]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('location_id', __('lang_v1.issuing_pharmacy') . ':*') !!}
                        {!! Form::select('location_id', $business_locations, null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'location_id',
                        ]) !!}
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('department_id', __('lang_v1.issue_to_department') . ':*') !!}
                        {!! Form::select('department_id', $departments, null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'department_id',
                        ]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-8 col-sm-offset-2">
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fa fa-search"></i></span>
                            {!! Form::text('search_product', null, [
                                'class' => 'form-control',
                                'id' => 'search_product_for_srock_adjustment',
                                'placeholder' => __('stock_adjustment.search_product'),
                                'disabled',
                            ]) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-10 col-sm-offset-1">
                    <input type="hidden" id="product_row_index" value="0">
                    <input type="hidden" id="total_amount" name="final_total" value="0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed" id="stock_adjustment_product_table">
                            <thead>
                                <tr>
                                    <th class="col-sm-4 text-center">@lang('sale.product')</th>
                                    <th class="col-sm-2 text-center">@lang('sale.qty')</th>
                                    <th class="col-sm-2 text-center show_price_with_permission">@lang('lang_v1.purchase_price')</th>
                                    <th class="col-sm-2 text-center show_price_with_permission">@lang('sale.subtotal')</th>
                                    <th class="col-sm-2 text-center"><i class="fa fa-trash"></i></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot>
                                <tr class="text-center show_price_with_permission">
                                    <td colspan="3"></td>
                                    <td>
                                        <div class="pull-right"><b>@lang('sale.total'): </b>
                                            <span id="total_adjustment">0.00</span>
                                        </div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-8">
                    <div class="form-group">
                        {!! Form::label('additional_notes', __('purchase.additional_notes')) !!}
                        {!! Form::textarea('additional_notes', null, ['class' => 'form-control', 'rows' => 3]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 text-right show_price_with_permission">
                    <b>@lang('stock_adjustment.total_amount'):</b>
                    <span id="final_total_text">0.00</span>
                </div>
                <br><br>
                <div class="col-sm-12 text-center">
                    <button type="submit" id="save_department_issue" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white">@lang('messages.save')</button>
                </div>
            </div>
        @endcomponent

        {!! Form::close() !!}
    </section>
@stop

@section('javascript')
    <script src="{{ asset('js/department_issue.js?v=' . $asset_v) }}"></script>
    <script>
        __page_leave_confirmation('#department_issue_form');
    </script>
@endsection

@cannot('view_purchase_price')
    <style>
        .show_price_with_permission { display: none !important; }
    </style>
@endcannot
