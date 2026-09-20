@extends('layouts.app')
@section('title', __('lang_v1.morgue_register'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.morgue_register')</h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('mg_date_range', __('report.date_range') . ':') !!}{!! Form::text('mg_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'mg_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('mg_status', __('lang_v1.status') . ':') !!}{!! Form::select('mg_status', ['' => __('lang_v1.all'), 'in_storage' => __('lang_v1.morgue_status_in_storage'), 'released' => __('lang_v1.morgue_status_released')], '', ['class' => 'form-control select2', 'id' => 'mg_status']) !!}</div></div>
    @endcomponent

    <div class="tw-grid tw-grid-cols-3 tw-gap-3 tw-mb-4">
        @include('report.hospital.partials.stat', ['id' => 'mg_total', 'label' => __('lang_v1.total_bodies'), 'icon' => 'fa-snowflake', 'fg' => 'tw-text-slate-600'])
        @include('report.hospital.partials.stat', ['id' => 'mg_storage', 'label' => __('lang_v1.morgue_status_in_storage'), 'icon' => 'fa-warehouse', 'fg' => 'tw-text-amber-600'])
        @include('report.hospital.partials.stat', ['id' => 'mg_fees', 'label' => __('lang_v1.total_fees_collected'), 'icon' => 'fa-coins', 'fg' => 'tw-text-green-600', 'money' => true])
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.morgue_register')])
        <table class="table table-bordered table-striped" id="mg_report_table" style="width:100%;">
            <thead><tr>
                <th>@lang('purchase.ref_no')</th><th>@lang('lang_v1.deceased')</th><th>@lang('lang_v1.received_at')</th>
                <th>@lang('lang_v1.released_at')</th><th>@lang('lang_v1.days_stored')</th><th>@lang('lang_v1.family_contact')</th>
                <th class="text-right">@lang('lang_v1.total_charged')</th><th>@lang('lang_v1.status')</th>
            </tr></thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'mg_date_range', 'table' => 'mg_report_table'])
    var mg_report_table = $('#mg_report_table').DataTable({
        processing: true, serverSide: true, order: [[2, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'morgue']) }}',
            data: function (d) {
                d.status = $('#mg_status').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'mg_date_range'])
            } },
        columns: [
            { data: 'ref_no', name: 'morgue_records.ref_no' },
            { data: 'deceased_name', name: 'morgue_records.deceased_name' },
            { data: 'received_at', name: 'morgue_records.received_at' },
            { data: 'released_at', name: 'morgue_records.released_at' },
            { data: 'days_stored', name: 'days_stored', searchable: false },
            { data: 'family_contact_name', name: 'morgue_records.family_contact_name' },
            { data: 'total_fee_charged', name: 'morgue_records.total_fee_charged', className: 'text-right' },
            { data: 'status', name: 'morgue_records.status' },
        ],
        fnDrawCallback: function () { __currency_convert_recursively($('#mg_report_table')); },
    });
    mg_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#mg_total').text(json.total_bodies || 0);
        $('#mg_storage').text(json.in_storage || 0);
        $('#mg_fees').text(__currency_trans_from_en(json.total_fees || 0, true));
    });
    $('#mg_status').change(function () { mg_report_table.ajax.reload(); });
});
</script>
@endsection
