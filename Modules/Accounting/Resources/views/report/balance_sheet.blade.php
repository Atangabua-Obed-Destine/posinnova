@extends('layouts.app')

@section('title', __('accounting::lang.balance_sheet'))

@section('content')

@include('accounting::layouts.nav')

<style>
    @media print {
        .no-print, header, .main-header, .main-sidebar, footer, .breadcrumb, .nav-pills { display:none !important; }
        .content-wrapper { margin:0 !important; }
        body { background:#fff !important; }
        .bs-table { font-size: 11px; }
    }
    .bs-section-header td {
        background:#222; color:#fff; font-weight:bold;
        text-transform:uppercase; letter-spacing:0.5px;
    }
    .bs-subgroup-header td {
        background:#f0f0f0; font-weight:bold; color:#444;
    }
    .bs-subgroup-subtotal td {
        background:#fafafa; font-style: italic; border-top:1px solid #ddd;
    }
    .bs-section-total td {
        background:#e8eaf6; font-weight:bold; font-size:1.05em;
        border-top:2px solid #444;
    }
    .bs-grand td { background:#222; color:#fff; font-weight:bold; font-size:1.1em; }
    .bs-balanced { color:#2e7d32; font-weight:bold; }
    .bs-out-of-balance { color:#c62828; font-weight:bold; }
    .bs-num { text-align:right; font-variant-numeric: tabular-nums; }
    .bs-acct-row td { padding-left: 20px; }
    .bs-syn-row td { padding-left: 20px; font-style: italic; color:#1565c0; }
</style>

<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('accounting::lang.balance_sheet')</h1>
</section>

<section class="content">

    <div class="row no-print">
        <div class="col-md-3">
            <div class="form-group">
                <label for="as_of_date_filter">As of Date:</label>
                {!! Form::text('as_of_date_filter', \Carbon::parse($as_of_date)->format(session('business.date_format', 'Y-m-d')),
                    ['class' => 'form-control', 'readonly', 'id' => 'as_of_date_filter']); !!}
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
                <a href="{{ route('accounting.balanceSheet', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                   target="_blank"
                   class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-dw-btn-error">
                    <i class="fas fa-file-pdf"></i> @lang('messages.pdf')
                </a>
                <a href="{{ route('accounting.balanceSheet', array_merge(request()->query(), ['format' => 'csv'])) }}"
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
            <h2 class="box-title">@lang('accounting::lang.balance_sheet')</h2>
            <p class="text-muted">As of {{ @format_date($as_of_date) }}</p>
            <div>
                @if($is_balanced)
                    <span class="bs-balanced">
                        <i class="fas fa-check-circle"></i> Balanced — Assets = Liabilities + Equity
                    </span>
                @else
                    <span class="bs-out-of-balance">
                        <i class="fas fa-exclamation-triangle"></i>
                        Out of balance by @format_currency($imbalance)
                    </span>
                @endif
            </div>
        </div>

        <div class="box-body table-responsive">
            <table class="table table-condensed table-bordered bs-table">
                <thead>
                    <tr style="background:#fafafa;">
                        <th style="width:90px;">Code</th>
                        <th>Account</th>
                        <th class="bs-num" style="width:160px;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(['asset', 'liability', 'equity'] as $pt)
                        <tr class="bs-section-header">
                            <td colspan="3">{{ $bs[$pt]['label'] }}</td>
                        </tr>

                        @forelse($bs[$pt]['subgroups'] as $sub_name => $g)
                            <tr class="bs-subgroup-header">
                                <td colspan="3">{{ $sub_name }}</td>
                            </tr>
                            @foreach($g['rows'] as $row)
                                <tr class="{{ !empty($row->__synthetic) ? 'bs-syn-row' : 'bs-acct-row' }}">
                                    <td>{{ $row->gl_code ?: '—' }}</td>
                                    <td>
                                        @if(!empty($row->__synthetic))
                                            {{ $row->name }}
                                            <small class="text-muted">(auto)</small>
                                        @elseif($row->id)
                                            <a href="{{ route('accounting.ledger', $row->id) }}?end_date={{$as_of_date}}">
                                                {{ $row->name }}
                                            </a>
                                            @if($row->status !== 'active')
                                                <small class="text-muted">({{$row->status}})</small>
                                            @endif
                                        @else
                                            {{ $row->name }}
                                        @endif
                                    </td>
                                    <td class="bs-num">@format_currency($row->balance)</td>
                                </tr>
                            @endforeach
                            <tr class="bs-subgroup-subtotal">
                                <td colspan="2">Subtotal — {{ $sub_name }}</td>
                                <td class="bs-num">@format_currency($g['subtotal'])</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center"><em>No accounts</em></td></tr>
                        @endforelse

                        <tr class="bs-section-total">
                            <td colspan="2">Total {{ $bs[$pt]['label'] }}</td>
                            <td class="bs-num">@format_currency($bs[$pt]['total'])</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bs-grand">
                        <td colspan="2">TOTAL ASSETS</td>
                        <td class="bs-num">@format_currency($total_assets)</td>
                    </tr>
                    <tr class="bs-grand">
                        <td colspan="2">TOTAL LIABILITIES + EQUITY</td>
                        <td class="bs-num">@format_currency($total_liab_owners)</td>
                    </tr>
                </tfoot>
            </table>

            <div class="text-muted small">
                <i class="fas fa-info-circle"></i>
                The Balance Sheet shows the financial position as of a single date.
                "Net income (current period)" is computed automatically from income minus expense activity since the start of the financial year and is shown as an equity line so the sheet can balance while the books are still open.
            </div>
        </div>
    </div>

</section>

@stop

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        $('#as_of_date_filter').daterangepicker({
            singleDatePicker: true,
            showDropdowns: true,
            startDate: moment('{{$as_of_date}}'),
            locale: { format: moment_date_format },
        }, function(start) {
            apply_filter();
        });

        $('#location_id_filter').on('change', apply_filter);

        function apply_filter() {
            var d = $('input#as_of_date_filter').data('daterangepicker').startDate.format('YYYY-MM-DD');
            var locId = $('#location_id_filter').val() || '';
            var params = new URLSearchParams(window.location.search);
            params.set('as_of_date', d);
            params.delete('start_date');
            params.delete('end_date');
            if (locId) { params.set('location_id', locId); } else { params.delete('location_id'); }
            params.delete('format');
            window.location.search = params.toString();
        }
    });
</script>
@stop
