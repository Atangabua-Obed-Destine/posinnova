@extends('layouts.app')
@section('title', __('lang_v1.revenue_report'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.revenue_report')</h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('rev_date_range', __('report.date_range') . ':') !!}{!! Form::text('rev_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'rev_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('rev_type', __('lang_v1.charge_type') . ':') !!}
            {!! Form::select('rev_type', ['' => __('lang_v1.all'), 'consultation' => __('lang_v1.charge_type_consultation'), 'lab' => __('lang_v1.charge_type_lab'), 'imaging' => __('lang_v1.charge_type_imaging'), 'pharmacy' => __('lang_v1.charge_type_pharmacy'), 'ward_dispense' => __('lang_v1.charge_type_ward_dispense'), 'procedure' => __('lang_v1.charge_type_procedure'), 'anc' => __('lang_v1.charge_type_anc')], '', ['class' => 'form-control select2', 'id' => 'rev_type']) !!}</div></div>
    @endcomponent

    <div class="row">
        <div class="col-sm-4">
            @component('components.widget', ['title' => __('lang_v1.total_revenue')])
                <h3 class="text-muted">@lang('lang_v1.total_revenue'): <span id="rev_grand"><i class="fas fa-sync fa-spin fa-fw"></i></span></h3>
                <table class="table table-condensed">
                    <thead><tr><th>@lang('lang_v1.charge_type')</th><th class="text-right">@lang('lang_v1.amount')</th></tr></thead>
                    <tbody id="rev_by_type"></tbody>
                </table>
            @endcomponent
        </div>
        <div class="col-sm-8">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.revenue_report')])
                <table class="table table-bordered table-striped" id="rev_report_table" style="width:100%;">
                    <thead><tr>
                        <th>@lang('lang_v1.date')</th><th>@lang('lang_v1.patient')</th><th>@lang('lang_v1.charge_type')</th>
                        <th>@lang('lang_v1.description')</th><th class="text-right">@lang('lang_v1.amount')</th><th>@lang('lang_v1.status')</th>
                    </tr></thead>
                </table>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @include('report.hospital.partials.daterange_js', ['id' => 'rev_date_range', 'table' => 'rev_report_table'])
    var rev_report_table = $('#rev_report_table').DataTable({
        processing: true, serverSide: true, order: [[0, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'revenue']) }}',
            data: function (d) {
                d.charge_type = $('#rev_type').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'rev_date_range'])
            } },
        columns: [
            { data: 'created_at', name: 'vc.created_at' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'charge_type', name: 'vc.charge_type' },
            { data: 'description', name: 'vc.description' },
            { data: 'amount', name: 'vc.amount', className: 'text-right' },
            { data: 'is_paid', name: 'vc.is_paid' },
        ],
        fnDrawCallback: function () { __currency_convert_recursively($('#rev_report_table')); },
    });
    rev_report_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#rev_grand').text(__currency_trans_from_en(json.grand_total || 0, true));
        var $b = $('#rev_by_type').empty();
        (json.by_type || []).forEach(function (r) {
            $b.append('<tr><td>' + $('<div>').text(r.charge_type).html() + '</td><td class="text-right">' + __currency_trans_from_en(r.total, true) + '</td></tr>');
        });
        if (!(json.by_type || []).length) $b.append('<tr><td colspan="2" class="text-center text-muted">--</td></tr>');
    });
    $('#rev_type').change(function () { rev_report_table.ajax.reload(); });
});
</script>
@endsection
