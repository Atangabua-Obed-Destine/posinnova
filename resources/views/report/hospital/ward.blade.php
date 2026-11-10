@extends('layouts.app')
@section('title', __('lang_v1.ward_consumption_report'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.ward_consumption_report')</h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('wc_date_range', __('report.date_range') . ':') !!}{!! Form::text('wc_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'wc_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('wc_ward', __('lang_v1.ward') . ':') !!}{!! Form::select('wc_ward', $wards, '', ['class' => 'form-control select2', 'id' => 'wc_ward']) !!}</div></div>
    @endcomponent

    <div class="tw-grid tw-grid-cols-2 tw-gap-3 tw-mb-4">
        @include('report.hospital.partials.stat', ['id' => 'wc_count', 'label' => __('lang_v1.total_dispenses'), 'icon' => 'fa-pills', 'fg' => 'tw-text-indigo-600'])
        @include('report.hospital.partials.stat', ['id' => 'wc_total', 'label' => __('lang_v1.total_value'), 'icon' => 'fa-coins', 'fg' => 'tw-text-green-600', 'money' => true])
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.ward_consumption_report')])
        <table class="table table-bordered table-striped" id="wc_report_table" style="width:100%;">
            <thead><tr><th>@lang('lang_v1.ward')</th><th class="text-right">@lang('lang_v1.dispenses')</th><th class="text-right">@lang('lang_v1.total_value')</th></tr></thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'wc_date_range', 'table' => 'wc_report_table'])
    var wc_report_table = $('#wc_report_table').DataTable({
        processing: true, serverSide: false, order: [[2, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'ward']) }}',
            data: function (d) {
                d.ward_id = $('#wc_ward').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'wc_date_range'])
            } },
        columns: [
            { data: 'ward_name', name: 'ward_name' },
            { data: 'dispense_count', name: 'dispense_count', className: 'text-right' },
            { data: 'total_amount', name: 'total_amount', className: 'text-right' },
        ],
        fnDrawCallback: function () { __currency_convert_recursively($('#wc_report_table')); },
    });
    wc_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#wc_count').text(json.grand_count || 0);
        $('#wc_total').text(__currency_trans_from_en(json.grand_total || 0, true));
    });
    $('#wc_ward').change(function () { wc_report_table.ajax.reload(); });
});
</script>
@endsection
