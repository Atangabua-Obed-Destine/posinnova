@extends('layouts.app')
@section('title', __( 'sale.list_pos'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('sale.pos_sale')
        {{-- PosInnova Stock Doctor: detect & heal display/purchase stock drift --}}
        <button type="button" id="stock_doctor_btn" title="@lang('lang_v1.stock_doctor')"
            class="tw-dw-btn tw-dw-btn-sm tw-bg-[#EF4B53] tw-text-white tw-border-none tw-rounded-full tw-relative pull-right"
            style="display:none;">
            <i class="fa fa-stethoscope"></i> @lang('lang_v1.stock_doctor')
            <span id="stock_doctor_badge"
                class="tw-absolute tw-top-0 tw-right-0 tw--mt-1 tw--mr-1 tw-min-w-[18px] tw-h-[18px] tw-px-1 tw-rounded-full tw-bg-[#b91c1c] tw-text-white tw-text-[10px] tw-font-bold tw-leading-[18px] tw-text-center"></span>
        </button>
    </h1>
</section>

<!-- Main content -->
<section class="content no-print">
    @include('sale_pos.partials.stock_integrity_modal')

    @component('components.filters', ['title' => __('report.filters')])
        @include('sell.partials.sell_list_filters')
    @endcomponent

    @component('components.widget', ['class' => 'box-primary', 'title' => __( 'sale.list_pos')])
        @can('sell.create')
            @slot('tool')
                <div class="box-tools">
                    <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{action([\App\Http\Controllers\SellPosController::class, 'create'])}}">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                class="icon icon-tabler icons-tabler-outline icon-tabler-plus">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                <path d="M12 5l0 14" />
                                <path d="M5 12l14 0" />
                            </svg> @lang('messages.add')
                        </a>
                </div>
            @endslot
        @endcan
        @can('sell.view')
            <input type="hidden" name="is_direct_sale" id="is_direct_sale" value="0">
            @include('sale_pos.partials.sales_table')
        @endcan
    @endcomponent
</section>
<!-- /.content -->
<div class="modal fade payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade edit_payment_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<div class="modal fade register_details_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog" 
    aria-labelledby="gridSystemModalLabel">
</div>

<!-- This will be printed -->
<section class="invoice print_section" id="receipt_section">
</section> 


@stop

@section('javascript')
@include('sale_pos.partials.sale_table_javascript')
<script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection