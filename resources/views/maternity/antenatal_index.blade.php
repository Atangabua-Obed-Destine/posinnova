@extends('layouts.app')
@section('title', __('lang_v1.antenatal_records'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.antenatal_records')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.maternity')</small>
        </h1>
    </section>

    <section class="content">
        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('anc_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('anc_status_filter', ['' => __('lang_v1.all'), 'active' => __('lang_v1.anc_status_active'), 'delivered' => __('lang_v1.anc_status_delivered'), 'terminated' => __('lang_v1.anc_status_terminated')], 'active', ['class' => 'form-control select2']) !!}
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.antenatal_records')])
            @can('antenatal.create')
                @slot('tool')
                    <div class="box-tools">
                        <a class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full pull-right"
                            href="{{ action([\App\Http\Controllers\AntenatalController::class, 'create']) }}">
                            <i class="fa fa-plus"></i> @lang('lang_v1.new_antenatal_record')
                        </a>
                    </div>
                @endslot
            @endcan

            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="antenatal_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.file_number')</th>
                            <th>@lang('lang_v1.mother')</th>
                            <th>@lang('lang_v1.lmp')</th>
                            <th>@lang('lang_v1.edd')</th>
                            <th>@lang('lang_v1.anc_visits')</th>
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
    var t = $('#antenatal_table').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: '{{ action([\App\Http\Controllers\AntenatalController::class, 'index']) }}',
            data: function (d) { d.status = $('#anc_status_filter').val(); },
        },
        columns: [
            { data: 'ref_no', name: 'antenatal_records.ref_no' },
            { data: 'file_number', name: 'pd.file_number' },
            { data: 'mother_name', name: 'c.name' },
            { data: 'lmp', name: 'antenatal_records.lmp' },
            { data: 'edd', name: 'antenatal_records.edd' },
            { data: 'visits_count', name: 'visits_count', searchable: false, orderable: false },
            { data: 'status', name: 'antenatal_records.status' },
            { data: 'action', name: 'action', searchable: false, orderable: false },
        ],
        responsive: true,
    });
    $('#anc_status_filter').change(function () { t.ajax.reload(); });
});
</script>
@endsection
