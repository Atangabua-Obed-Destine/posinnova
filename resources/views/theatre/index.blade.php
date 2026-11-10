@extends('layouts.app')
@section('title', __('lang_v1.theatre'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.theatre')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.theatre_bookings')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('theatre_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('theatre_status_filter', ['' => __('lang_v1.all'), 'scheduled' => __('lang_v1.theatre_status_scheduled'), 'in_progress' => __('lang_v1.theatre_status_in_progress'), 'completed' => __('lang_v1.theatre_status_completed'), 'cancelled' => __('lang_v1.theatre_status_cancelled')], 'scheduled', ['class' => 'form-control select2']) !!}
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.theatre_bookings')])
            @can('theatre.book')
                @slot('tool')
                    <div class="box-tools">
                        @can('theatre.manage_catalogue')
                            <a class="tw-dw-btn tw-dw-btn-outline pull-right" style="margin-left:6px;"
                                href="{{ action([\App\Http\Controllers\TheatreController::class, 'catalogue']) }}"><i class="fa fa-list"></i> @lang('lang_v1.procedure_catalogue')</a>
                        @endcan
                        <a class="tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full tw-dw-btn pull-right"
                            href="{{ action([\App\Http\Controllers\TheatreController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.book_theatre')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="theatre_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.file_number')</th>
                            <th>@lang('lang_v1.patient')</th>
                            <th>@lang('lang_v1.procedure')</th>
                            <th>@lang('lang_v1.surgeon')</th>
                            <th>@lang('lang_v1.scheduled_at')</th>
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
    var t = $('#theatre_table').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: '{{ action([\App\Http\Controllers\TheatreController::class, 'index']) }}',
            data: function (d) { d.status = $('#theatre_status_filter').val(); },
        },
        columns: [
            { data: 'ref_no', name: 'theatre_bookings.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'procedure_name', name: 'theatre_bookings.procedure_name' },
            { data: 'surgeon_name', name: 's.first_name' },
            { data: 'scheduled_at', name: 'theatre_bookings.scheduled_at' },
            { data: 'status', name: 'theatre_bookings.status' },
            { data: 'action', name: 'action', searchable: false, orderable: false },
        ],
        order: [[5, 'desc']],
        responsive: true,
    });
    $('#theatre_status_filter').change(function () { t.ajax.reload(); });
});
</script>
@endsection
