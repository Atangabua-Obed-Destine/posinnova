@extends('layouts.app')
@section('title', __('lang_v1.lab_report'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.lab_report')</h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('lab_date_range', __('report.date_range') . ':') !!}{!! Form::text('lab_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'lab_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('lab_status', __('lang_v1.status') . ':') !!}{!! Form::select('lab_status', ['' => __('lang_v1.all'), 'pending' => __('lang_v1.lab_status_pending'), 'processing' => __('lang_v1.lab_status_processing'), 'resulted' => __('lang_v1.lab_status_resulted'), 'validated' => __('lang_v1.lab_status_validated')], '', ['class' => 'form-control select2', 'id' => 'lab_status']) !!}</div></div>
    @endcomponent

    <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-3 tw-gap-3 tw-mb-4">
        @include('report.hospital.partials.stat', ['id' => 'lab_total', 'label' => __('lang_v1.total_requests'), 'icon' => 'fa-vial', 'fg' => 'tw-text-sky-600'])
        @include('report.hospital.partials.stat', ['id' => 'lab_pending', 'label' => __('lang_v1.lab_status_pending'), 'icon' => 'fa-hourglass-half', 'fg' => 'tw-text-amber-600'])
        @include('report.hospital.partials.stat', ['id' => 'lab_tat', 'label' => __('lang_v1.avg_turnaround'), 'icon' => 'fa-stopwatch', 'fg' => 'tw-text-green-600'])
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.lab_report')])
        <table class="table table-bordered table-striped" id="lab_report_table" style="width:100%;">
            <thead><tr>
                <th>@lang('purchase.ref_no')</th><th>@lang('lang_v1.file_number')</th><th>@lang('lang_v1.patient')</th>
                <th>@lang('lang_v1.tests')</th><th>@lang('lang_v1.requested_at')</th><th>@lang('lang_v1.resulted_at')</th>
                <th>@lang('lang_v1.turnaround')</th><th>@lang('lang_v1.status')</th>
            </tr></thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'lab_date_range', 'table' => 'lab_report_table'])
    var lab_report_table = $('#lab_report_table').DataTable({
        processing: true, serverSide: true, order: [[4, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'lab']) }}',
            data: function (d) {
                d.status = $('#lab_status').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'lab_date_range'])
            } },
        columns: [
            { data: 'ref_no', name: 'lab_requests.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'tests_count', name: 'tests_count', searchable: false },
            { data: 'requested_at', name: 'lab_requests.requested_at' },
            { data: 'resulted_at', name: 'lab_requests.resulted_at' },
            { data: 'turnaround_hours', name: 'turnaround_hours', searchable: false },
            { data: 'status', name: 'lab_requests.status' },
        ],
    });
    lab_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#lab_total').text(json.total_requests || 0);
        $('#lab_pending').text(json.pending_requests || 0);
        $('#lab_tat').text((json.avg_turnaround || 0) + 'h');
    });
    $('#lab_status').change(function () { lab_report_table.ajax.reload(); });
});
</script>
@endsection
