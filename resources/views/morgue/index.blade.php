@extends('layouts.app')
@section('title', __('lang_v1.morgue'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.morgue')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.morgue_register')</small>
        </h1>
    </section>

    <section class="content">
        <div class="tw-mb-4 text-right">
            @can('morgue.manage_slots')
                <a class="tw-dw-btn tw-dw-btn-outline" href="{{ action([\App\Http\Controllers\MorgueController::class, 'slots']) }}"><i class="fa fa-th"></i> @lang('lang_v1.manage_slots')</a>
            @endcan
            @can('morgue.receive')
                <a class="tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-font-bold tw-text-white tw-border-none tw-rounded-full tw-dw-btn" href="{{ action([\App\Http\Controllers\MorgueController::class, 'create']) }}"><i class="fa fa-plus"></i> @lang('lang_v1.receive_body')</a>
            @endcan
        </div>

        {{-- Slot occupancy grid --}}
        @if($slots->count() > 0)
            <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
                <p class="tw-font-semibold tw-mb-3">@lang('lang_v1.slot_occupancy')</p>
                <div class="tw-flex tw-flex-wrap tw-gap-2">
                    @foreach($slots as $slot)
                        @php
                            $rec = $slot->currentRecord;
                            $cls = $slot->status === 'occupied' ? 'tw-bg-red-100 tw-text-red-700 tw-ring-red-300'
                                : ($slot->status === 'maintenance' ? 'tw-bg-gray-100 tw-text-gray-500 tw-ring-gray-300' : 'tw-bg-green-100 tw-text-green-700 tw-ring-green-300');
                        @endphp
                        <div class="tw-inline-flex tw-flex-col tw-items-center tw-justify-center tw-min-w-[72px] tw-px-2 tw-py-2 tw-rounded-lg tw-ring-1 {{ $cls }}"
                            title="{{ $rec ? $rec->deceased_name : __('lang_v1.morgue_status_' . ($slot->status === 'occupied' ? 'in_storage' : 'available')) }}">
                            <i class="fas fa-snowflake"></i>
                            <span class="tw-text-xs tw-font-semibold tw-mt-1">{{ $slot->slot_number }}</span>
                            @if($rec)<span class="tw-text-[10px] tw-truncate tw-max-w-[80px]">{{ \Illuminate\Support\Str::limit($rec->deceased_name, 12) }}</span>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @component('components.filters', ['title' => __('report.filters')])
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('morgue_status_filter', __('lang_v1.status') . ':') !!}
                    {!! Form::select('morgue_status_filter', ['' => __('lang_v1.all'), 'in_storage' => __('lang_v1.morgue_status_in_storage'), 'released' => __('lang_v1.morgue_status_released')], 'in_storage', ['class' => 'form-control select2']) !!}
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group">
                    {!! Form::label('morgue_source_filter', __('lang_v1.source') . ':') !!}
                    {!! Form::select('morgue_source_filter', ['' => __('lang_v1.all'), 'hospital' => __('lang_v1.source_hospital'), 'external' => __('lang_v1.source_external')], '', ['class' => 'form-control select2']) !!}
                </div>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.morgue_register')])
            <div class="table-responsive">
                <table class="table table-bordered table-striped dt-responsive" id="morgue_table" style="width:100%">
                    <thead>
                        <tr>
                            <th>@lang('purchase.ref_no')</th>
                            <th>@lang('lang_v1.deceased')</th>
                            <th>@lang('lang_v1.slot')</th>
                            <th>@lang('lang_v1.received_at')</th>
                            <th>@lang('lang_v1.days_stored')</th>
                            <th>@lang('lang_v1.family_contact')</th>
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
    var t = $('#morgue_table').DataTable({
        processing: true, serverSide: true,
        ajax: {
            url: '{{ action([\App\Http\Controllers\MorgueController::class, 'index']) }}',
            data: function (d) { d.status = $('#morgue_status_filter').val(); d.source = $('#morgue_source_filter').val(); },
        },
        columns: [
            { data: 'ref_no', name: 'morgue_records.ref_no' },
            { data: 'deceased_name', name: 'morgue_records.deceased_name' },
            { data: 'slot_number', name: 's.slot_number' },
            { data: 'received_at', name: 'morgue_records.received_at' },
            { data: 'days_stored', name: 'days_stored', searchable: false, orderable: false },
            { data: 'family_contact_name', name: 'morgue_records.family_contact_name' },
            { data: 'status', name: 'morgue_records.status' },
            { data: 'action', name: 'action', searchable: false, orderable: false },
        ],
        order: [[3, 'desc']],
        responsive: true,
        createdRow: function (row, data) {
            // Long-stay highlight: amber > 7 days, red > 14 (in storage only).
            if (data.status && data.status.indexOf('in_storage') === -1) return;
            var d = parseInt(data.days_stored || 0, 10);
            if (d > 14) $(row).css('background-color', '#f8d7da');
            else if (d > 7) $(row).css('background-color', '#fff3cd');
        },
    });
    $('#morgue_status_filter, #morgue_source_filter').change(function () { t.ajax.reload(); });
});
</script>
@endsection
