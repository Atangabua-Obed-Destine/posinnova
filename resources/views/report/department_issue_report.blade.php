@extends('layouts.app')
@section('title', __('lang_v1.department_issue_report'))

@section('content')

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('lang_v1.department_issue_report')
    </h1>
</section>

<section class="content no-print">

    {{-- Filters --}}
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                {!! Form::open(['url' => '#', 'method' => 'get', 'id' => 'department_issue_report_filter_form']) !!}
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fa fa-map-marker"></i></span>
                                {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.all')]) !!}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('department_id', __('lang_v1.department') . ':') !!}
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fas fa-hospital"></i></span>
                                {!! Form::select('department_id', $departments, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('messages.all')]) !!}
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('status', __('sale.status') . ':') !!}
                            {!! Form::select('status', ['' => __('messages.all'), 'final' => __('lang_v1.issued'), 'pending' => __('lang_v1.pending')], null, ['class' => 'form-control select2', 'style' => 'width:100%']) !!}
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            {!! Form::label('dir_date_filter', __('report.date_range') . ':') !!}
                            {!! Form::text('date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'dir_date_filter', 'readonly']) !!}
                            {!! Form::hidden('start_date', null, ['id' => 'dir_start_date']) !!}
                            {!! Form::hidden('end_date', null, ['id' => 'dir_end_date']) !!}
                        </div>
                    </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>

    {{-- Summary cards --}}
    <div class="row">
        <div class="col-md-3 col-sm-6">
            @component('components.widget', ['class' => 'bg-light-blue'])
                <h5 class="tw-m-0"><i class="fa fa-file-invoice"></i> @lang('lang_v1.department_issues')</h5>
                <h2 class="tw-m-0"><span id="dir_total_issues">0</span></h2>
            @endcomponent
        </div>
        <div class="col-md-3 col-sm-6">
            @component('components.widget', ['class' => 'bg-green'])
                <h5 class="tw-m-0"><i class="fa fa-money-bill"></i> @lang('sale.total')</h5>
                <h2 class="tw-m-0"><span class="display_currency" id="dir_total_amount" data-currency_symbol="true">0</span></h2>
            @endcomponent
        </div>
        <div class="col-md-3 col-sm-6">
            @component('components.widget', ['class' => 'bg-aqua'])
                <h5 class="tw-m-0"><i class="fa fa-check"></i> @lang('lang_v1.issued')</h5>
                <h2 class="tw-m-0"><span class="display_currency" id="dir_total_issued" data-currency_symbol="true">0</span></h2>
            @endcomponent
        </div>
        <div class="col-md-3 col-sm-6">
            @component('components.widget', ['class' => 'bg-yellow'])
                <h5 class="tw-m-0"><i class="fas fa-hospital"></i> @lang('lang_v1.departments')</h5>
                <h2 class="tw-m-0"><span id="dir_department_count">0</span></h2>
            @endcomponent
        </div>
    </div>

    {{-- Tabs --}}
    <div class="row">
        <div class="col-md-12">
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active"><a href="#dir_vouchers_tab" data-toggle="tab"><i class="fa fa-list"></i> @lang('lang_v1.department_issues')</a></li>
                    <li><a href="#dir_by_dept_tab" data-toggle="tab"><i class="fas fa-hospital"></i> @lang('lang_v1.by_department')</a></li>
                    <li><a href="#dir_by_product_tab" data-toggle="tab"><i class="fa fa-cubes"></i> @lang('lang_v1.by_product')</a></li>
                    <li><a href="#dir_by_period_tab" data-toggle="tab"><i class="fa fa-calendar"></i> @lang('lang_v1.by_period')</a></li>
                </ul>
                <div class="tab-content">

                    <div class="tab-pane active" id="dir_vouchers_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped"
                                id="dir_vouchers_table"
                                data-export-title="{{ __('lang_v1.department_issue_report') }} - @lang('lang_v1.department_issues')"
                                data-export-filter-container="#collapseFilter">
                                <thead>
                                    <tr>
                                        <th>@lang('messages.date')</th>
                                        <th>@lang('purchase.ref_no')</th>
                                        <th>@lang('business.location')</th>
                                        <th>@lang('lang_v1.department')</th>
                                        <th>@lang('lang_v1.issued_by')</th>
                                        <th>@lang('sale.status')</th>
                                        <th>@lang('sale.total')</th>
                                        <th>@lang('lang_v1.additional_notes')</th>
                                        <th>@lang('messages.action')</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="dir_by_dept_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped"
                                id="dir_by_dept_table"
                                data-export-title="{{ __('lang_v1.department_issue_report') }} - @lang('lang_v1.by_department')"
                                data-export-filter-container="#collapseFilter">
                                <thead>
                                    <tr>
                                        <th>@lang('lang_v1.department')</th>
                                        <th>@lang('lang_v1.hod_name')</th>
                                        <th>@lang('lang_v1.department_issues')</th>
                                        <th>@lang('lang_v1.total_quantity')</th>
                                        <th>@lang('sale.total')</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="dir_by_product_tab">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped"
                                id="dir_by_product_table"
                                data-export-title="{{ __('lang_v1.department_issue_report') }} - @lang('lang_v1.by_product')"
                                data-export-filter-container="#collapseFilter">
                                <thead>
                                    <tr>
                                        <th>@lang('product.product')</th>
                                        <th>@lang('product.sku')</th>
                                        <th>@lang('lang_v1.department_issues')</th>
                                        <th>@lang('lang_v1.total_quantity')</th>
                                        <th>@lang('sale.total')</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane" id="dir_by_period_tab">
                        <div class="row" style="padding:10px;">
                            <div class="col-md-3">
                                {!! Form::label('dir_group_by', __('lang_v1.group_by') . ':') !!}
                                {!! Form::select('group_by', ['day' => __('lang_v1.day'), 'month' => __('lang_v1.month'), 'year' => __('lang_v1.year')], 'month', ['class' => 'form-control', 'id' => 'dir_group_by', 'style' => 'width:100%']) !!}
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped"
                                id="dir_by_period_table"
                                data-export-title="{{ __('lang_v1.department_issue_report') }} - @lang('lang_v1.by_period')"
                                data-export-filter-container="#collapseFilter">
                                <thead>
                                    <tr>
                                        <th>@lang('lang_v1.period')</th>
                                        <th>@lang('lang_v1.department_issues')</th>
                                        <th>@lang('sale.total')</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

</section>

@endsection

@section('javascript')
<script src="{{ asset('js/department_issue_report.js?v=' . $asset_v) }}"></script>
@endsection
