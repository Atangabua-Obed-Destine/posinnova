@extends('layouts.app')

@section('title', __('accounting::lang.trial_balance'))

@section('content')

@include('accounting::layouts.nav')

<style>
    @media print {
        .no-print, header, .main-header, .main-sidebar, footer, .breadcrumb, .nav-pills { display:none !important; }
        .content-wrapper { margin:0 !important; }
        body { background:#fff !important; }
        .tb-table { font-size: 11px; }
    }
    .tb-table th, .tb-table td { vertical-align: middle !important; }
    .tb-group-header td {
        background:#f5f5f5;
        font-weight:bold;
        text-transform:uppercase;
        letter-spacing:0.5px;
    }
    .tb-subtotal td {
        background:#fafafa;
        font-weight:bold;
        border-top:1px solid #ccc;
    }
    .tb-grand-total td {
        background:#222;
        color:#fff;
        font-weight:bold;
        font-size:1.05em;
    }
    .tb-balanced { color:#2e7d32; font-weight:bold; }
    .tb-out-of-balance { color:#c62828; font-weight:bold; }
    .tb-num { text-align:right; font-variant-numeric: tabular-nums; }
</style>

<section class="content">
    <div class="row no-print">
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('date_range_filter', __('report.date_range') . ':') !!}
                {!! Form::text('date_range_filter', null,
                    ['placeholder' => __('lang_v1.select_a_date_range'),
                    'class' => 'form-control', 'readonly', 'id' => 'date_range_filter']); !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('location_id_filter', __('purchase.business_location') . ':') !!}
                <select id="location_id_filter" class="form-control">
                    <option value="">@lang('kardex.all_business_locations')</option>
                    @foreach($business_locations as $id => $name)
                        <option value="{{$id}}" {{ (string)$location_id === (string)$id ? 'selected' : '' }}>{{$name}}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-6 text-right">
            <label>&nbsp;</label>
            <div>
                <a href="{{ route('accounting.trialBalance', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                   target="_blank"
                   class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-error">
                    <i class="fas fa-file-pdf"></i> @lang('messages.pdf')
                </a>
                <a href="{{ route('accounting.trialBalance', array_merge(request()->query(), ['format' => 'csv'])) }}"
                   class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-success">
                    <i class="fas fa-file-csv"></i> CSV
                </a>
                <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-info" onclick="window.print();">
                    <i class="fas fa-print"></i> @lang('messages.print')
                </button>
            </div>
        </div>
    </div>

    <div class="box box-warning">
        <div class="box-header with-border text-center">
            <h2 class="box-title">@lang('accounting::lang.trial_balance')</h2>
            <p class="text-muted">{{@format_date($start_date)}} ~ {{@format_date($end_date)}}</p>
            <div>
                @if($is_balanced)
                    <span class="tb-balanced">
                        <i class="fas fa-check-circle"></i>
                        Balanced
                    </span>
                @else
                    <span class="tb-out-of-balance">
                        <i class="fas fa-exclamation-triangle"></i>
                        Out of balance by @format_currency($imbalance)
                    </span>
                @endif
            </div>
        </div>

        <div class="box-body table-responsive">
            <table class="table table-condensed table-bordered tb-table">
                <thead>
                    <tr style="background:#fafafa;">
                        <th rowspan="2" style="width:90px;">Code</th>
                        <th rowspan="2">@lang('accounting::lang.account_name')</th>
                        <th rowspan="2">@lang('accounting::lang.account_type')</th>
                        <th colspan="2" class="text-center">Opening Balance</th>
                        <th colspan="2" class="text-center">Period Activity</th>
                        <th colspan="2" class="text-center">Closing Balance</th>
                    </tr>
                    <tr style="background:#fafafa;">
                        <th class="tb-num">@lang('accounting::lang.debit')</th>
                        <th class="tb-num">@lang('accounting::lang.credit')</th>
                        <th class="tb-num">@lang('accounting::lang.debit')</th>
                        <th class="tb-num">@lang('accounting::lang.credit')</th>
                        <th class="tb-num">@lang('accounting::lang.debit')</th>
                        <th class="tb-num">@lang('accounting::lang.credit')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ordered_groups as $pt => $g)
                        <tr class="tb-group-header">
                            <td colspan="9">{{ $g['label'] }}</td>
                        </tr>
                        @foreach($g['rows'] as $row)
                            <tr>
                                <td>{{ $row->gl_code ?: '—' }}</td>
                                <td>
                                    <a href="{{ route('accounting.ledger', $row->id) }}?start_date={{$start_date}}&end_date={{$end_date}}">
                                        {{ $row->name }}
                                    </a>
                                    @if($row->status !== 'active')
                                        <small class="text-muted">({{$row->status}})</small>
                                    @endif
                                </td>
                                <td><small class="text-muted">{{ $row->sub_type }}</small></td>
                                <td class="tb-num">@if($row->opening_debit  != 0)@format_currency($row->opening_debit)@endif</td>
                                <td class="tb-num">@if($row->opening_credit != 0)@format_currency($row->opening_credit)@endif</td>
                                <td class="tb-num">@if((float)$row->period_debit  != 0)@format_currency($row->period_debit)@endif</td>
                                <td class="tb-num">@if((float)$row->period_credit != 0)@format_currency($row->period_credit)@endif</td>
                                <td class="tb-num">@if($row->closing_debit  != 0)@format_currency($row->closing_debit)@endif</td>
                                <td class="tb-num">@if($row->closing_credit != 0)@format_currency($row->closing_credit)@endif</td>
                            </tr>
                        @endforeach
                        <tr class="tb-subtotal">
                            <td colspan="3">Subtotal — {{ $g['label'] }}</td>
                            <td class="tb-num">@format_currency($g['subtotals']['opening_debit'])</td>
                            <td class="tb-num">@format_currency($g['subtotals']['opening_credit'])</td>
                            <td class="tb-num">@format_currency($g['subtotals']['period_debit'])</td>
                            <td class="tb-num">@format_currency($g['subtotals']['period_credit'])</td>
                            <td class="tb-num">@format_currency($g['subtotals']['closing_debit'])</td>
                            <td class="tb-num">@format_currency($g['subtotals']['closing_credit'])</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted">@lang('messages.no_records_found')</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="tb-grand-total">
                        <td colspan="3">@lang('report.total')</td>
                        <td class="tb-num">@format_currency($totals['opening_debit'])</td>
                        <td class="tb-num">@format_currency($totals['opening_credit'])</td>
                        <td class="tb-num">@format_currency($totals['period_debit'])</td>
                        <td class="tb-num">@format_currency($totals['period_credit'])</td>
                        <td class="tb-num total_debit">@format_currency($totals['closing_debit'])</td>
                        <td class="tb-num total_credit">@format_currency($totals['closing_credit'])</td>
                    </tr>
                </tfoot>
            </table>

            <div class="text-muted small">
                <i class="fas fa-info-circle"></i>
                A trial balance is in balance when total Closing Debit equals total Closing Credit.
                Differences indicate posting errors that should be investigated.
            </div>
        </div>
    </div>
</section>

@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        dateRangeSettings.startDate = moment('{{$start_date}}');
        dateRangeSettings.endDate   = moment('{{$end_date}}');

        $('#date_range_filter').daterangepicker(
            dateRangeSettings,
            function (start, end) {
                $('#date_range_filter').val(start.format(moment_date_format) + ' ~ ' + end.format(moment_date_format));
                apply_filter();
            }
        );
        $('#date_range_filter').on('cancel.daterangepicker', function() {
            $('#date_range_filter').val('');
            apply_filter();
        });

        $('#location_id_filter').on('change', apply_filter);

        function apply_filter() {
            var start = '';
            var end = '';
            if ($('#date_range_filter').val()) {
                start = $('input#date_range_filter').data('daterangepicker').startDate.format('YYYY-MM-DD');
                end   = $('input#date_range_filter').data('daterangepicker').endDate.format('YYYY-MM-DD');
            }
            var locId = $('#location_id_filter').val() || '';

            var params = new URLSearchParams(window.location.search);
            params.set('start_date', start);
            params.set('end_date', end);
            if (locId) { params.set('location_id', locId); } else { params.delete('location_id'); }
            params.delete('format');
            window.location.search = params.toString();
        }
    });
</script>
@stop
