@extends('layouts.app')
@section('title', __('lang_v1.admissions_report'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.admissions_report')</h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('adm_date_range', __('report.date_range') . ':') !!}{!! Form::text('adm_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'adm_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('adm_ward', __('lang_v1.ward') . ':') !!}{!! Form::select('adm_ward', $wards, '', ['class' => 'form-control select2', 'id' => 'adm_ward']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('adm_status', __('lang_v1.status') . ':') !!}{!! Form::select('adm_status', ['' => __('lang_v1.all'), 'active' => __('lang_v1.admission_status_active'), 'discharged' => __('lang_v1.admission_status_discharged'), 'transferred' => __('lang_v1.admission_status_transferred')], '', ['class' => 'form-control select2', 'id' => 'adm_status']) !!}</div></div>
    @endcomponent

    <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-4 tw-gap-3 tw-mb-4">
        @include('report.hospital.partials.stat', ['id' => 'adm_total', 'label' => __('lang_v1.total_admissions'), 'icon' => 'fa-procedures', 'fg' => 'tw-text-indigo-600'])
        @include('report.hospital.partials.stat', ['id' => 'adm_current', 'label' => __('lang_v1.current_inpatients'), 'icon' => 'fa-bed', 'fg' => 'tw-text-green-600'])
        @include('report.hospital.partials.stat', ['id' => 'adm_disch', 'label' => __('lang_v1.discharged_today'), 'icon' => 'fa-sign-out-alt', 'fg' => 'tw-text-sky-600'])
        @include('report.hospital.partials.stat', ['id' => 'adm_los', 'label' => __('lang_v1.avg_length_of_stay'), 'icon' => 'fa-calendar-day', 'fg' => 'tw-text-amber-600'])
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.admissions_report')])
        <table class="table table-bordered table-striped" id="adm_report_table" style="width:100%;">
            <thead><tr>
                <th>@lang('purchase.ref_no')</th><th>@lang('lang_v1.file_number')</th><th>@lang('lang_v1.patient')</th>
                <th>@lang('lang_v1.ward')</th><th>@lang('lang_v1.bed')</th><th>@lang('lang_v1.admission_date')</th>
                <th>@lang('lang_v1.discharge_date')</th><th>@lang('lang_v1.days')</th><th>@lang('lang_v1.status')</th>
            </tr></thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'adm_date_range', 'table' => 'adm_report_table'])
    var adm_report_table = $('#adm_report_table').DataTable({
        processing: true, serverSide: true, order: [[5, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'admissions']) }}',
            data: function (d) {
                d.ward_id = $('#adm_ward').val(); d.status = $('#adm_status').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'adm_date_range'])
            } },
        columns: [
            { data: 'ref_no', name: 'admissions.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'ward_name', name: 'w.name' },
            { data: 'bed_number', name: 'b.bed_number' },
            { data: 'admission_date', name: 'admissions.admission_date' },
            { data: 'discharge_date', name: 'admissions.discharge_date' },
            { data: 'days_stayed', name: 'days_stayed', searchable: false },
            { data: 'status', name: 'admissions.status' },
        ],
    });
    adm_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#adm_total').text(json.total_admissions || 0);
        $('#adm_current').text(json.current_inpatients || 0);
        $('#adm_disch').text(json.discharged_today || 0);
        $('#adm_los').text((json.avg_los || 0) + ' {{ __('lang_v1.days') }}');
    });
    $('#adm_ward, #adm_status').change(function () { adm_report_table.ajax.reload(); });
});
</script>
@endsection
