@extends('layouts.app')
@section('title', __('lang_v1.admissions'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.admissions')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.inpatient_admissions')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('admission_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('admission_status_filter', ['' => __('lang_v1.all'), 'active' => __('lang_v1.admission_status_active'), 'discharged' => __('lang_v1.admission_status_discharged'), 'transferred' => __('lang_v1.admission_status_transferred')], 'active', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('admission_ward_filter', __('lang_v1.ward') . ':') !!}
                    {!! Form::select('admission_ward_filter', $wards, '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.admissions')])
            @can('admission.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\AdmissionController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.new_admission')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="admissions_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.file_number')</th>
                            <th>@lang('lang_v1.patient')</th>
                            <th>@lang('lang_v1.ward')</th>
                            <th>@lang('lang_v1.bed')</th>
                            <th>@lang('lang_v1.admission_date')</th>
                            <th>@lang('lang_v1.discharge_date')</th>
                            <th>@lang('lang_v1.status')</th>
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
    var admissions_table = $('#admissions_table').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: '{{ action([\App\Http\Controllers\AdmissionController::class, 'index']) }}',
            data: function (d) {
                d.status = $('#admission_status_filter').val();
                d.ward_id = $('#admission_ward_filter').val();
            },
        },
        columns: [
            { data: 'ref_no', name: 'admissions.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'ward_name', name: 'w.name' },
            { data: 'bed_number', name: 'b.bed_number' },
            { data: 'admission_date', name: 'admissions.admission_date' },
            { data: 'discharge_date', name: 'admissions.discharge_date' },
            { data: 'status', name: 'admissions.status' },
            { data: 'action', name: 'action', searchable: false, orderable: false },
        ],
        order: [[5, 'desc']],
        responsive: true,
    });

    $('#admission_status_filter, #admission_ward_filter').change(function () { admissions_table.ajax.reload(); });
});
</script>
@endsection
