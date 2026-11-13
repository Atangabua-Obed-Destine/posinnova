@extends('layouts.app')
@section('title', __('lang_v1.imaging_requests'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.imaging_requests')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.radiology')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('imaging_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('imaging_status_filter', ['' => __('lang_v1.all'), 'pending' => __('lang_v1.imaging_status_pending'), 'done' => __('lang_v1.imaging_status_done')], '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.imaging_requests')])
            @can('imaging_request.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\ImagingController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.new_imaging_request')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="imaging_requests_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.file_number')</th>
                            <th>@lang('lang_v1.patient')</th>
                            <th>@lang('lang_v1.study')</th>
                            <th>@lang('lang_v1.requested_by')</th>
                            <th>@lang('lang_v1.requested_at')</th>
                            <th>@lang('lang_v1.status')</th>
                            <th>@lang('clinic.pay_col')</th>
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
    var imaging_requests_table = $('#imaging_requests_table').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: '{{ action([\App\Http\Controllers\ImagingController::class, 'index']) }}',
            data: function (d) { d.status = $('#imaging_status_filter').val(); },
        },
        columns: [
            { data: 'ref_no', name: 'imaging_requests.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'study_name', name: 'imaging_requests.study_name' },
            { data: 'requested_by_name', name: 'u.first_name' },
            { data: 'requested_at', name: 'imaging_requests.requested_at' },
            { data: 'status', name: 'imaging_requests.status' },
            { data: 'payment', name: 'payment', searchable: false, orderable: false },
            { data: 'action', name: 'action', searchable: false, orderable: false },
        ],
        order: [[5, 'desc']],
        responsive: true,
    });

    $('#imaging_status_filter').change(function () { imaging_requests_table.ajax.reload(); });
});
</script>
@endsection
