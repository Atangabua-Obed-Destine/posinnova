@extends('layouts.app')
@section('title', __('lang_v1.mortality_register'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.mortality_register')
    <small class="tw-text-sm tw-text-gray-600">@lang('lang_v1.mortality_register_sub')</small></h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('mr_date_range', __('report.date_range') . ':') !!}{!! Form::text('mr_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'mr_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('mr_source', __('lang_v1.source') . ':') !!}{!! Form::select('mr_source', ['' => __('lang_v1.all'), 'hospital' => __('lang_v1.source_hospital'), 'external' => __('lang_v1.source_external')], '', ['class' => 'form-control select2', 'id' => 'mr_source']) !!}</div></div>
    @endcomponent

    <div class="tw-grid tw-grid-cols-3 tw-gap-3 tw-mb-4">
        @include('report.hospital.partials.stat', ['id' => 'mr_total', 'label' => __('lang_v1.total_deaths'), 'icon' => 'fa-book-dead', 'fg' => 'tw-text-slate-600'])
        @include('report.hospital.partials.stat', ['id' => 'mr_males', 'label' => __('lang_v1.male'), 'icon' => 'fa-mars', 'fg' => 'tw-text-sky-600'])
        @include('report.hospital.partials.stat', ['id' => 'mr_females', 'label' => __('lang_v1.female'), 'icon' => 'fa-venus', 'fg' => 'tw-text-pink-600'])
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.mortality_register')])
        @slot('tool')<button class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline pull-right" onclick="window.print()"><i class="fas fa-print"></i> @lang('lang_v1.print')</button>@endslot
        <table class="table table-bordered table-striped" id="mr_report_table" style="width:100%;">
            <thead><tr>
                <th>@lang('purchase.ref_no')</th><th>@lang('lang_v1.deceased_name')</th><th>@lang('lang_v1.age')</th>
                <th>@lang('lang_v1.sex')</th><th>@lang('lang_v1.date_of_death')</th><th>@lang('lang_v1.cause_of_death')</th>
                <th>@lang('lang_v1.source')</th><th>@lang('lang_v1.certified_by')</th>
            </tr></thead>
        </table>
    @endcomponent
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'mr_date_range', 'table' => 'mr_report_table'])
    var mr_report_table = $('#mr_report_table').DataTable({
        processing: true, serverSide: true, order: [[4, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'mortality']) }}',
            data: function (d) {
                d.source = $('#mr_source').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'mr_date_range'])
            } },
        columns: [
            { data: 'ref_no', name: 'morgue_records.ref_no' },
            { data: 'deceased_name', name: 'morgue_records.deceased_name' },
            { data: 'deceased_age', name: 'morgue_records.deceased_age' },
            { data: 'deceased_sex', name: 'morgue_records.deceased_sex' },
            { data: 'date_of_death', name: 'morgue_records.date_of_death' },
            { data: 'cause_of_death', name: 'morgue_records.cause_of_death' },
            { data: 'source', name: 'morgue_records.source' },
            { data: 'certified_by_name', name: 'u.first_name' },
        ],
    });
    mr_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#mr_total').text(json.total_deaths || 0);
        $('#mr_males').text(json.males || 0);
        $('#mr_females').text(json.females || 0);
    });
    $('#mr_source').change(function () { mr_report_table.ajax.reload(); });
});
</script>
@endsection
