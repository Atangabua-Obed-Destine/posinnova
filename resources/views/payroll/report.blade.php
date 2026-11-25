@extends('layouts.app')
@section('title', __('payroll.menu_report'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PayrollController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
    $sum = fn ($k) => $rows->sum($k);
@endphp
<section class="content ht" id="py_report">
    <div class="ht-head"><div><h1>{{ __('payroll.menu_report') }}</h1><p>{{ \App\Payroll\PayrollBook::monthLabel($month) }}</p></div>
        <form method="get" class="ht-actions" style="align-items:flex-end">
            <div class="ht-f" style="margin:0"><label for="r_m">{{ __('payroll.month') }}</label><select id="r_m" name="month" class="ht-in">@for ($i = 1; $i <= 12; $i++)<option value="{{ $i }}" {{ $month->month === $i ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000, $i, 1)->locale(app()->getLocale())->isoFormat('MMMM') }}</option>@endfor</select></div>
            <div class="ht-f" style="margin:0"><label for="r_y">{{ __('payroll.year') }}</label><select id="r_y" name="year" class="ht-in">@for ($y = now()->year; $y >= now()->year - 9; $y--)<option value="{{ $y }}" {{ $month->year === $y ? 'selected' : '' }}>{{ $y }}</option>@endfor</select></div>
            <button class="ht-btn" type="submit">{{ __('payroll.filter') }}</button>
        </form></div>
    <div class="ht-card"><div class="ht-scroll"><table class="ht-t" style="min-width:1000px">
        <thead><tr><th>{{ __('payroll.name') }}</th><th class="num">{{ __('payroll.earning') }}</th><th class="num">{{ __('payroll.allowance') }}</th><th class="num">{{ __('payroll.deduction') }}</th><th class="num">{{ __('payroll.gross_salary') }}</th><th class="num">{{ __('payroll.employee_tax') }}</th><th class="num">{{ __('payroll.net_salary') }}</th><th class="num">{{ __('payroll.employer_tax') }}</th><th>{{ __('payroll.status') }}</th><th>{{ __('payroll.pay_date') }}</th></tr></thead>
        <tbody>
            @forelse ($rows as $p)
                <tr><td><a class="ht-link" href="{{ action([$P, 'payslip'], [$p->id]) }}" target="_blank">{{ \App\Payroll\PayrollBook::name($p) }}</a></td><td class="num">{{ $fmt($p->total_earning) }}</td><td class="num">{{ $fmt($p->total_allowance) }}</td><td class="num">{{ $fmt($p->total_deduction) }}</td>
                    <td class="num">{{ $fmt($p->gross_salary) }}</td><td class="num">{{ $fmt($p->tax) }}</td><td class="num"><strong>{{ $fmt($p->net_salary) }}</strong></td><td class="num">{{ $fmt($p->employer_tax) }}</td>
                    <td>{{ $p->isPaid() ? __('payroll.st_paid') : __('payroll.st_unpaid') }}</td><td>{{ optional($p->pay_date)->format('d/m/Y') }}</td></tr>
            @empty
                <tr><td colspan="10" class="ht-muted">{{ __('payroll.nothing_month') }}</td></tr>
            @endforelse
            @if ($rows->isNotEmpty())
                <tr style="font-weight:700;border-top:2px solid #101828"><td>{{ __('payroll.grand_total') }}</td><td class="num">{{ $fmt($sum('total_earning')) }}</td><td class="num">{{ $fmt($sum('total_allowance')) }}</td><td class="num">{{ $fmt($sum('total_deduction')) }}</td>
                    <td class="num">{{ $fmt($sum('gross_salary')) }}</td><td class="num">{{ $fmt($sum('tax')) }}</td><td class="num">{{ $fmt($sum('net_salary')) }}</td><td class="num">{{ $fmt($sum('employer_tax')) }}</td><td colspan="2"></td></tr>
            @endif
        </tbody></table></div></div>
    <div class="ht-card"><h2>{{ __('payroll.by_tax') }}</h2><p class="ht-muted ht-small">{{ __('payroll.by_tax_hint') }}</p>
        <table class="ht-t"><thead><tr><th>{{ __('payroll.tax') }}</th><th>{{ __('payroll.owed_on') }}</th><th class="num">{{ __('payroll.staff_share') }}</th><th class="num">{{ __('payroll.employer_share') }}</th><th class="num">{{ __('payroll.total') }}</th></tr></thead><tbody>
            @forelse ($byTax as $label => $t)<tr><td>{{ $label }}</td><td>{{ $t['code'] }}</td><td class="num">{{ $fmt($t['employee']) }}</td><td class="num">{{ $fmt($t['employer']) }}</td><td class="num"><strong>{{ $fmt($t['employee'] + $t['employer']) }}</strong></td></tr>
            @empty<tr><td colspan="5" class="ht-muted">{{ __('payroll.nothing_month') }}</td></tr>@endforelse
        </tbody></table></div>
</section>
@endsection
