@extends('layouts.app')
@section('title', __('lang_v1.products_price_list'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        {{ __('lang_v1.products_price_list') }}
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                {!! Form::open([
                    'url' => action([\App\Http\Controllers\ReportController::class, 'getProductsPriceList']),
                    'method' => 'get',
                    'id' => 'products_price_list_filter_form'
                ]) !!}
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('category_id', __('category.category') . ':') !!}
                        {!! Form::select('category_id', $categories, null, [
                            'placeholder' => __('messages.all'),
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'ppl_category_id'
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('sub_category_id', __('product.sub_category') . ':') !!}
                        {!! Form::select('sub_category_id', [], null, [
                            'placeholder' => __('messages.all'),
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'ppl_sub_category_id'
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('brand_id', __('product.brand') . ':') !!}
                        {!! Form::select('brand_id', $brands, null, [
                            'placeholder' => __('messages.all'),
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'ppl_brand_id'
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('unit_id', __('product.unit') . ':') !!}
                        {!! Form::select('unit_id', $units, null, [
                            'placeholder' => __('messages.all'),
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'ppl_unit_id'
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('price_group_id', __('lang_v1.selling_price_group') . ':') !!}
                        {!! Form::select('price_group_id', $price_groups, null, [
                            'placeholder' => __('lang_v1.default_selling_price'),
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'ppl_price_group_id'
                        ]) !!}
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('active_state', __('lang_v1.is_active') . ':') !!}
                        {!! Form::select('active_state', [
                            'active' => __('lang_v1.active'),
                            'inactive' => __('lang_v1.inactive')
                        ], 'active', [
                            'class' => 'form-control select2',
                            'style' => 'width:100%',
                            'id' => 'ppl_active_state'
                        ]) !!}
                    </div>
                </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.widget', ['class' => 'box-primary'])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="products_price_list_table"
                        data-export-title="{{ __('lang_v1.products_price_list') }}"
                        data-export-filter-container="#collapseFilter">
                        <thead>
                            <tr>
                                <th>@lang('product.sku')</th>
                                <th>@lang('business.product')</th>
                                <th>@lang('product.category')</th>
                                <th>@lang('product.brand')</th>
                                <th>@lang('product.unit')</th>
                                @can('access_default_selling_price')
                                <th class="text-right">@lang('lang_v1.default_purchase_price')</th>
                                @endcan
                                <th class="text-right">@lang('lang_v1.default_selling_price')</th>
                                <th>@lang('lang_v1.other_units')</th>
                                <th id="ppl_group_price_header" class="text-right" style="display:none;">@lang('lang_v1.group_price')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->

@endsection

@section('javascript')
<script>
$(document).ready(function () {
    var pplTable = $('table#products_price_list_table').DataTable({
        processing: true,
        serverSide: true,
        fixedHeader: false,
        order: [[1, 'asc']],
        ajax: {
            url: '{{ action([\App\Http\Controllers\ReportController::class, 'getProductsPriceList']) }}',
            data: function (d) {
                d.category_id     = $('#ppl_category_id').val();
                d.sub_category_id = $('#ppl_sub_category_id').val();
                d.brand_id        = $('#ppl_brand_id').val();
                d.unit_id         = $('#ppl_unit_id').val();
                d.price_group_id  = $('#ppl_price_group_id').val();
                d.active_state    = $('#ppl_active_state').val();
            }
        },
        columns: [
            { data: 'sub_sku',                name: 'v.sub_sku' },
            { data: 'product',                name: 'products.name' },
            { data: 'category',               name: 'c.name' },
            { data: 'brand',                  name: 'b.name' },
            { data: 'unit',                   name: 'u.short_name', orderable: false },
            @can('access_default_selling_price')
            { data: 'default_purchase_price', name: 'v.default_purchase_price', className: 'text-right' },
            @endcan
            { data: 'sell_price_inc_tax',     name: 'v.sell_price_inc_tax',     className: 'text-right' },
            { data: 'other_units',            name: 'other_units', orderable: false, searchable: false },
            { data: 'group_price',            name: 'group_price', orderable: false, searchable: false, visible: false, className: 'text-right' }
        ],
        fnDrawCallback: function () {
            __currency_convert_recursively($('#products_price_list_table'));
        }
    });

    // Sub-category cascade
    $('#ppl_category_id').on('change', function () {
        var cat = $(this).val();
        $.ajax({
            method: 'POST',
            url: '{{ url('/products/get_sub_categories') }}',
            dataType: 'html',
            data: { cat_id: cat, _token: '{{ csrf_token() }}' },
            success: function (result) {
                $('#ppl_sub_category_id').html(result).trigger('change.select2');
            }
        });
    });

    $('#ppl_category_id, #ppl_sub_category_id, #ppl_brand_id, #ppl_unit_id, #ppl_active_state')
        .on('change', function () { pplTable.ajax.reload(); });

    $('#ppl_price_group_id').on('change', function () {
        var hasGroup = !!$(this).val();
        var col = pplTable.column(pplTable.columns().count() - 1);
        col.visible(hasGroup);
        $('#ppl_group_price_header').toggle(hasGroup);
        pplTable.ajax.reload();
    });
});
</script>
@endsection
