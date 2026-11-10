@extends('layouts.app')
@section('title', __('lang_v1.opd_report'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.opd_report')</h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('opd_date_range', __('report.date_range') . ':') !!}{!! Form::text('opd_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'opd_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('opd_type', __('lang_v1.consultation_type') . ':') !!}{!! Form::select('opd_type', ['' => __('lang_v1.all'), 'general' => __('lang_v1.consultation_type_general'), 'specialist' => __('lang_v1.consultation_type_specialist')], '', ['class' => 'form-control select2', 'id' => 'opd_type']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('opd_doctor', __('lang_v1.consulted_by') . ':') !!}{!! Form::select('opd_doctor', $doctors, '', ['class' => 'form-control select2', 'id' => 'opd_doctor']) !!}</div></div>
    @endcomponent

    <div class="tw-grid tw-grid-cols-2 tw-gap-3 tw-mb-4">
        @include('report.hospital.partials.stat', ['id' => 'opd_total', 'label' => __('lang_v1.total_consultations'), 'icon' => 'fa-stethoscope', 'fg' => 'tw-text-sky-600'])
        @include('report.hospital.partials.stat', ['id' => 'opd_patients', 'label' => __('lang_v1.unique_patients'), 'icon' => 'fa-users', 'fg' => 'tw-text-indigo-600'])
        {{-- PosInnova: fees are billed at check-in (packages), not on the consultation: see Clinic reports > Income --}}
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.opd_report')])
        <table class="table table-bordered table-striped" id="opd_report_table" style="width:100%;">
            <thead><tr>
                <th>@lang('lang_v1.date')</th><th>@lang('purchase.ref_no')</th><th>@lang('lang_v1.file_number')</th>
                <th>@lang('lang_v1.patient')</th><th>@lang('lang_v1.consultation_type')</th><th>@lang('lang_v1.doctor')</th>
                <th>@lang('lang_v1.diagnosis')</th>
            </tr></thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'opd_date_range', 'table' => 'opd_report_table'])
    var opd_report_table = $('#opd_report_table').DataTable({
        processing: true, serverSide: true, order: [[0, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'opd']) }}',
            data: function (d) {
                d.consultation_type = $('#opd_type').val(); d.doctor_id = $('#opd_doctor').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'opd_date_range'])
            } },
        columns: [
            { data: 'created_at', name: 'consultations.created_at' },
            { data: 'ref_no', name: 'consultations.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'consultation_type', name: 'consultations.consultation_type' },
            { data: 'doctor_name', name: 'u.first_name' },
            { data: 'diagnosis', name: 'consultations.diagnosis' },
        ],
        fnDrawCallback: function () { __currency_convert_recursively($('#opd_report_table')); },
    });
    opd_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#opd_total').text(json.total_consultations || 0);
        $('#opd_patients').text(json.unique_patients || 0);
    });
    $('#opd_type, #opd_doctor').change(function () { opd_report_table.ajax.reload(); });
});
</script>
@endsection
