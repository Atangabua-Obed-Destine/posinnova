@extends('layouts.app')
@section('title', __('lang_v1.lab_requests'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.lab_requests')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.laboratory')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('lab_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('lab_status_filter', ['' => __('lang_v1.all'), 'pending' => __('lang_v1.lab_status_pending'), 'sample_collected' => __('lang_v1.lab_status_sample_collected'), 'processing' => __('lang_v1.lab_status_processing'), 'resulted' => __('lang_v1.lab_status_resulted'), 'validated' => __('lang_v1.lab_status_validated')], '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    <br>
                    <label class="checkbox-inline">
                        {!! Form::checkbox('lab_today_only', 1, false, ['id' => 'lab_today_only']) !!}
                        @lang('lang_v1.today_only')
                    </label>
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.lab_requests')])
            @can('lab_request.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\LabRequestController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.new_lab_request')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="lab_requests_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.file_number')</th>
                            <th>@lang('lang_v1.patient')</th>
                            <th>@lang('lang_v1.tests')</th>
                            <th>@lang('lang_v1.requested_by')</th>
                            <th>@lang('lang_v1.requested_at')</th>
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
    var lab_requests_table = $('#lab_requests_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ action([\App\Http\Controllers\LabRequestController::class, 'index']) }}',
            data: function (d) {
                d.status = $('#lab_status_filter').val();
                d.today_only = $('#lab_today_only').is(':checked') ? 1 : 0;
            },
        },
        columns: [
            { data: 'ref_no', name: 'lab_requests.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'tests_count', name: 'tests_count', searchable: false, orderable: false },
            { data: 'requested_by_name', name: 'u.first_name' },
            { data: 'requested_at', name: 'lab_requests.requested_at' },
            { data: 'status', name: 'lab_requests.status' },
            { data: 'action', name: 'action', searchable: false, orderable: false },
        ],
        order: [[5, 'desc']],
        responsive: true,
    });

    $('#lab_status_filter, #lab_today_only').change(function () {
        lab_requests_table.ajax.reload();
    });
});
</script>
@endsection
