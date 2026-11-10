@extends('layouts.app')
@section('title', __('lang_v1.antenatal_report'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.antenatal_report')</h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('an_date_range', __('report.date_range') . ':') !!}{!! Form::text('an_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'an_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('an_status', __('lang_v1.status') . ':') !!}{!! Form::select('an_status', ['' => __('lang_v1.all'), 'active' => __('lang_v1.anc_status_active'), 'delivered' => __('lang_v1.anc_status_delivered')], '', ['class' => 'form-control select2', 'id' => 'an_status']) !!}</div></div>
    @endcomponent

    <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-4 tw-gap-3 tw-mb-4">
        @include('report.hospital.partials.stat', ['id' => 'an_total', 'label' => __('lang_v1.total_pregnancies'), 'icon' => 'fa-female', 'fg' => 'tw-text-pink-600'])
        @include('report.hospital.partials.stat', ['id' => 'an_active', 'label' => __('lang_v1.active_pregnancies'), 'icon' => 'fa-heartbeat', 'fg' => 'tw-text-green-600'])
        @include('report.hospital.partials.stat', ['id' => 'an_deliv', 'label' => __('lang_v1.deliveries'), 'icon' => 'fa-baby', 'fg' => 'tw-text-indigo-600'])
        @include('report.hospital.partials.stat', ['id' => 'an_cs', 'label' => __('lang_v1.cs_rate'), 'icon' => 'fa-percentage', 'fg' => 'tw-text-amber-600'])
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.antenatal_report')])
        <table class="table table-bordered table-striped" id="an_report_table" style="width:100%;">
            <thead><tr>
                <th>@lang('purchase.ref_no')</th><th>@lang('lang_v1.file_number')</th><th>@lang('lang_v1.mother')</th>
                <th>@lang('lang_v1.lmp')</th><th>@lang('lang_v1.edd')</th><th>@lang('lang_v1.anc_visits')</th><th>@lang('lang_v1.status')</th>
            </tr></thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'an_date_range', 'table' => 'an_report_table'])
    var an_report_table = $('#an_report_table').DataTable({
        processing: true, serverSide: true,
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'antenatal']) }}',
            data: function (d) {
                d.status = $('#an_status').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'an_date_range'])
            } },
        columns: [
            { data: 'ref_no', name: 'ar.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'mother_name', name: 'c.name' },
            { data: 'lmp', name: 'ar.lmp' },
            { data: 'edd', name: 'ar.edd' },
            { data: 'anc_count', name: 'anc_count', searchable: false },
            { data: 'status', name: 'ar.status' },
        ],
    });
    an_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#an_total').text(json.total_pregnancies || 0);
        $('#an_active').text(json.active_pregnancies || 0);
        $('#an_deliv').text(json.deliveries || 0);
        $('#an_cs').text((json.cs_rate || 0) + '%');
    });
    $('#an_status').change(function () { an_report_table.ajax.reload(); });
});
</script>
@endsection
