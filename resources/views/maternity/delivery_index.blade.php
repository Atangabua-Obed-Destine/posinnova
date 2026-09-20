@extends('layouts.app')
@section('title', __('lang_v1.deliveries'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.deliveries')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.maternity')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.deliveries')])
            @can('delivery.record')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full tw-dw-btn pull-right"
                            href="{{ action([\App\Http\Controllers\DeliveryController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.record_delivery')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="deliveries_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.mother')</th>
                            <th>@lang('lang_v1.baby')</th>
                            <th>@lang('lang_v1.delivery_date')</th>
                            <th>@lang('lang_v1.delivery_type')</th>
                            <th>@lang('lang_v1.baby_sex')</th>
                            <th>@lang('lang_v1.baby_weight')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                </table>
            </div>
        @endcomponent
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    $('#deliveries_table').DataTable({
        processing: true, serverSide: true,
        ajax: '{{ action([\App\Http\Controllers\DeliveryController::class, 'index']) }}',
        columns: [
            { data: 'ref_no', name: 'delivery_records.ref_no' },
            { data: 'mother_name', name: 'm.name' },
            { data: 'baby_name', name: 'baby.name' },
            { data: 'delivery_date', name: 'delivery_records.delivery_date' },
            { data: 'delivery_type', name: 'delivery_records.delivery_type' },
            { data: 'baby_sex', name: 'delivery_records.baby_sex' },
            { data: 'baby_weight', name: 'delivery_records.baby_weight' },
            { data: 'action', name: 'action', searchable: false, orderable: false },
        ],
        order: [[3, 'desc']],
        responsive: true,
    });
});
</script>
@endsection
