@extends('layouts.app')
@section('title', $title)

@php
    $money = fn ($v) => abs((float) $v) < 0.005 ? '—' : \App\Ledger\Ledger::money($v);
    $m = fn ($v) => \App\Ledger\Ledger::money($v);
    $totals = $summary['totals'];
    $periodName = \App\Ledger\Models\FiscalYear::periodLabel($period);
    $labels = [
        'income' => __('ledger_daybook.sec_income'),
        'expenditure' => __('ledger_daybook.sec_expenditure'),
        'capital' => __('ledger_daybook.sec_capital'),
        'transfers' => __('ledger_daybook.sec_transfers'),
    ];
@endphp

@section('content')
@include('ledger.partials.style')
@include('ledger.daybook.partials.style')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('ledger_daybook.monthly_summary_title') }}</h1>
</section>
<section class="content">

    @include('ledger.daybook._toolbar', ['route' => 'ledger.daybook.summary'])

    <div class="db-guide">
        <button class="db-guide-toggle" type="button" data-toggle="collapse" data-target="#dbMsGuide">
            <span class="grow"><i class="fas fa-lightbulb"></i> {{ __('ledger_daybook.guide_columns_title') }}</span>
            <i class="fas fa-chevron-up"></i>
        </button>
        <div class="collapse in" id="dbMsGuide">
            <div class="db-guide-body">
                <p>{{ __('ledger_daybook.guide_columns_p') }}</p>

                <table class="col-key" style="margin-top: 8px;">
                    <tr><td>{{ __('ledger_daybook.monthly') }}</td><td>{{ __('ledger_daybook.key_monthly') }}</td></tr>
                    <tr><td>{{ __('ledger_daybook.bb_forward') }}</td><td>{{ __('ledger_daybook.key_bb_forward') }}</td></tr>
                    <tr><td>{{ __('ledger_daybook.cumulative') }}</td><td>{{ __('ledger_daybook.key_cumulative') }}</td></tr>
                </table>

                <h6>{{ __('ledger_daybook.guide_grouped') }}</h6>
                <p>{{ __('ledger_daybook.guide_grouped_p') }}</p>

                <h6>{{ __('ledger_daybook.guide_sections') }}</h6>
                <p>{{ __('ledger_daybook.guide_sections_p') }}</p>
            </div>
        </div>
    </div>

    <div class="db-card">
        <div class="db-card-head">
            <div>
                <h5>{{ __('ledger_daybook.monthly_summary_title') }} — {{ $periodName }}{{ $account ? ' — '.$account->label : '' }}</h5>
                {{-- The one thing a reader must understand to trust the columns. --}}
                <small>{{ __('ledger_daybook.summary_sub') }}</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table ms-table" id="ms-table">
                <thead>
                    <tr>
                        <th style="width:80px">{{ __('ledger_daybook.code') }}</th>
                        <th>{{ __('ledger_daybook.description') }}</th>
                        <th style="width:130px" class="ms-num">{{ __('ledger_daybook.monthly') }}</th>
                        <th style="width:130px" class="ms-num">{{ __('ledger_daybook.bb_forward') }}</th>
                        <th style="width:130px" class="ms-num">{{ __('ledger_daybook.cumulative') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($labels as $section => $label)
                        @continue($section === 'transfers' && empty($summary['lines']['transfers']))
                        <tr class="ms-section"><td colspan="5">{{ $label }}</td></tr>

                        @forelse($summary['lines'][$section] as $row)
                            @if($row['kind'] === 'subtotal')
                                <tr class="ms-subtotal">
                                    <td></td>
                                    <td class="ms-name">{{ __('ledger_daybook.total') }} {{ $row['name'] }}</td>
                                    <td class="ms-num">{{ $money($row['monthly']) }}</td>
                                    <td class="ms-num ms-quiet">{{ $money($row['brought']) }}</td>
                                    <td class="ms-num">{{ $money($row['cumulative']) }}</td>
                                </tr>
                            @else
                                <tr class="{{ $row['kind'] === 'header' ? 'ms-header' : ($row['kind'] === 'child' ? 'ms-child' : 'ms-line') }}" @if($row['id']) data-code="{{ $row['code'] }}" @endif>
                                    <td class="ms-code">{{ $row['code'] }}</td>
                                    <td class="ms-name">{{ $row['name'] }}</td>
                                    <td class="ms-num">{{ $money($row['monthly']) }}</td>
                                    <td class="ms-num ms-quiet">{{ $money($row['brought']) }}</td>
                                    <td class="ms-num"><strong>{{ $money($row['cumulative']) }}</strong></td>
                                </tr>
                            @endif
                        @empty
                            <tr><td></td><td class="ms-quiet" colspan="4">{{ __('ledger_daybook.nothing_in_section') }}</td></tr>
                        @endforelse

                        <tr class="ms-total" data-section="{{ $section }}">
                            <td></td>
                            <td>{{ __('ledger_daybook.total') }} {{ $label }}</td>
                            <td class="ms-num">{{ $money($totals[$section]['monthly']) }}</td>
                            <td class="ms-num">{{ $money($totals[$section]['brought']) }}</td>
                            <td class="ms-num">{{ $money($totals[$section]['cumulative']) }}</td>
                        </tr>
                    @endforeach

                    <tr class="ms-total" id="ms-net">
                        <td></td>
                        <td>{{ __('ledger_daybook.net_balance') }}</td>
                        <td class="ms-num">{{ $money($summary['net']['monthly']) }}</td>
                        <td class="ms-num">{{ $money($summary['net']['brought']) }}</td>
                        <td class="ms-num">{{ $money($summary['net']['cumulative']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- The same month by the kind of document behind the money. --}}
    <div class="db-card">
        <div class="db-card-head">
            <h5>{{ __('ledger_daybook.by_document') }}</h5>
            <small>{{ __('ledger_daybook.by_document_help') }}</small>
        </div>
        <div class="table-responsive">
            <table class="table db-table" id="ms-documents">
                <thead>
                    <tr>
                        <th>{{ __('ledger_daybook.document') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.entries') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.debit_in') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.credit_out') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.net_movement') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($summary['by_document'] as $source => $d)
                        <tr data-source="{{ $source }}">
                            <td>{{ __('ledger_daybook.src_'.$source) }}</td>
                            <td class="db-num">{{ number_format($d['count']) }}</td>
                            <td class="db-num db-in">{{ $money($d['in']) }}</td>
                            <td class="db-num db-out">{{ $money($d['out']) }}</td>
                            <td class="db-num">{{ $m($d['in'] - $d['out']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted">{{ __('ledger_daybook.no_cash_moved', ['period' => $periodName]) }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- And by cash box: where each opened, what moved, where it closed. --}}
    <div class="db-card">
        <div class="db-card-head">
            <h5>{{ __('ledger_daybook.by_cash_box') }}</h5>
            <small>{{ __('ledger_daybook.by_cash_box_help') }}</small>
        </div>
        <div class="table-responsive">
            <table class="table db-table">
                <thead>
                    <tr>
                        <th>{{ __('ledger_daybook.cash_box') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.opening_balance') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.debit_in') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.credit_out') }}</th>
                        <th class="db-num">{{ __('ledger_daybook.closing_balance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($summary['boxes'] as $b)
                        <tr>
                            <td>{{ $b['account']->label }}</td>
                            <td class="db-num">{{ $m($b['opening']) }}</td>
                            <td class="db-num db-in">{{ $m($b['in']) }}</td>
                            <td class="db-num db-out">{{ $m($b['out']) }}</td>
                            <td class="db-num"><strong>{{ $m($b['closing']) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
