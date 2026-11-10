@extends('layouts.app')
@section('title', __('lang_v1.insurance_claims'))

@section('content')
<section class="content-header"><h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.insurance_claims')
    <small class="tw-text-gray-600">@lang('lang_v1.insurance_claims_sub')</small></h1></section>

<section class="content">
    @component('components.filters', ['title' => __('report.filters')])
        <div class="col-md-3"><div class="form-group">{!! Form::label('mc_date_range', __('report.date_range') . ':') !!}{!! Form::text('mc_date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'mc_date_range', 'readonly']) !!}</div></div>
        <div class="col-md-3"><div class="form-group">{!! Form::label('mc_provider', __('lang_v1.insurance_provider') . ':') !!}{!! Form::text('mc_provider', null, ['class' => 'form-control', 'id' => 'mc_provider', 'placeholder' => __('lang_v1.all')]) !!}</div></div>
    @endcomponent

    <div class="row">
        <div class="col-sm-4">
            @component('components.widget', ['title' => __('lang_v1.claim_summary')])
                <h3 class="text-muted">@lang('lang_v1.total_claimable'): <span id="mc_grand"><i class="fas fa-sync fa-spin fa-fw"></i></span></h3>
                <p class="text-muted">@lang('lang_v1.claims'): <span id="mc_count">0</span></p>
                <table class="table table-condensed">
                    <thead><tr><th>@lang('lang_v1.insurance_provider')</th><th class="text-right">@lang('lang_v1.total_claimable')</th></tr></thead>
                    <tbody id="mc_by_provider"></tbody>
                </table>
            @endcomponent
        </div>
        <div class="col-sm-8">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.insurance_claims')])
                <table class="table table-bordered table-striped" id="mc_table" style="width:100%;">
                    <thead><tr>
                        <th>@lang('lang_v1.date')</th><th>@lang('lang_v1.patient')</th><th>@lang('lang_v1.insurance_provider')</th>
                        <th>@lang('lang_v1.description')</th><th class="text-right">@lang('lang_v1.amount')</th>
                        <th class="text-right">@lang('lang_v1.insurance_covered')</th><th class="text-right">@lang('lang_v1.patient_owes')</th>
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
    @include('report.hospital.partials.daterange_js', ['id' => 'mc_date_range', 'table' => 'mc_table'])
    var mc_table = $('#mc_table').DataTable({
        processing: true, serverSide: true, order: [[0, 'desc']],
        ajax: { url: '{{ action([\App\Http\Controllers\HospitalReportController::class, 'mutualClaims']) }}',
            data: function (d) {
                d.provider = $('#mc_provider').val();
                @include('report.hospital.partials.daterange_data', ['id' => 'mc_date_range'])
            } },
        columns: [
            { data: 'created_at', name: 'vc.created_at' },
            { data: 'patient_name', name: 'c.name' },
            { data: 'insurance_provider', name: 'pd.insurance_provider' },
            { data: 'description', name: 'vc.description' },
            { data: 'amount', name: 'vc.amount', className: 'text-right' },
            { data: 'insurance_covered', name: 'vc.insurance_covered', className: 'text-right' },
            { data: 'patient_owes', name: 'vc.patient_owes', className: 'text-right' },
        ],
        fnDrawCallback: function () { __currency_convert_recursively($('#mc_table')); },
    });
    mc_table.on('xhr.dt', function (e, s, json) {
        if (!json) return;
        $('#mc_grand').text(__currency_trans_from_en(json.grand_total || 0, true));
        $('#mc_count').text(json.claim_count || 0);
        var $b = $('#mc_by_provider').empty();
        (json.by_provider || []).forEach(function (r) {
            $b.append('<tr><td>' + $('<div>').text(r.provider).html() + ' (' + r.cnt + ')</td><td class="text-right">' + __currency_trans_from_en(r.total, true) + '</td></tr>');
        });
        if (!(json.by_provider || []).length) $b.append('<tr><td colspan="2" class="text-center text-muted">--</td></tr>');
    });
    var t; $('#mc_provider').on('keyup', function () { clearTimeout(t); t = setTimeout(function () { mc_table.ajax.reload(); }, 500); });
});
</script>
@endsection
