<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('payroll.pay_slip') }} · {{ \App\Payroll\PayrollBook::name($p) }}</title>
@php
    $fmt = fn ($v) => \App\Utils\Pressing::format($p->business_id, $v);
    $allow = $p->details->where('status', 1);
    $deduct = $p->details->where('status', 0);
    $empLines = $p->taxLines->filter(fn ($t) => (float) $t->employee_amount > 0);
    $erLines = $p->taxLines->filter(fn ($t) => (float) $t->employer_amount > 0);
@endphp
<style>
  @page { size: A4; margin: 14mm; }
  body { font-family: "Segoe UI", Arial, sans-serif; color: #101828; font-size: 10.5pt; margin: 0; background: #fff; }
  .page { max-width: 180mm; margin: 0 auto; padding: 8mm 0; }
  .hd { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #101828; padding-bottom: 4mm; margin-bottom: 5mm; }
  .hd h1 { margin: 0; font-size: 18pt; }
  .muted { color: #475467; }
  h2 { font-size: 11pt; margin: 6mm 0 2mm; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 1.6mm 2mm; border-bottom: 1px solid #eaecf0; text-align: left; vertical-align: top; }
  th { background: #f2f4f7; font-weight: 600; }
  .num { text-align: right; white-space: nowrap; }
  .cols { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; }
  .tot td { font-weight: 700; border-top: 2px solid #101828; }
  .net { margin-top: 5mm; background: #ecfdf3; border: 1px solid #abefc6; padding: 4mm; display: flex; justify-content: space-between; font-size: 14pt; font-weight: 700; }
  .sig { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8mm; margin-top: 14mm; }
  .sig div { border-top: 1px solid #101828; padding-top: 2mm; font-size: 9pt; }
  .noprint { text-align: center; margin: 4mm 0; }
  .noprint button { min-height: 40px; padding: 0 18px; font-size: 14px; }
  @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint"><button type="button" onclick="window.print()">{{ __('payroll.print_slip') }}</button></div>
<div class="page" id="py_payslip">
    <div class="hd">
        <div><h1>{{ __('payroll.pay_slip') }}</h1><div class="muted">{{ $business->name }}</div></div>
        <div style="text-align:right"><strong>{{ \App\Payroll\PayrollBook::monthLabel($p->salary_month) }}</strong><div class="muted">{{ __('payroll.ref') }} PS-{{ str_pad($p->id, 5, '0', STR_PAD_LEFT) }}</div></div>
    </div>

    <h2>{{ __('payroll.employee_info') }}</h2>
    <table>
        <tr><th style="width:25%">{{ __('payroll.name') }}</th><td>{{ \App\Payroll\PayrollBook::name($p) }}</td><th style="width:22%">{{ __('payroll.job_title') }}</th><td>{{ optional($staff)->job_title ?: '—' }}</td></tr>
        <tr><th>{{ __('payroll.cnps_no') }}</th><td>{{ optional($staff)->cnps_no ?: '—' }}</td><th>{{ __('payroll.tax_no') }}</th><td>{{ optional($staff)->tax_no ?: '—' }}</td></tr>
        <tr><th>{{ __('payroll.basic_salary') }}</th><td>{{ $fmt($p->basic_salary) }}</td><th>{{ __('payroll.status') }}</th><td>{{ $p->isPaid() ? __('payroll.st_paid') : __('payroll.st_unpaid') }}</td></tr>
        <tr><th>{{ __('payroll.pay_date') }}</th><td>{{ optional($p->pay_date)->format('d/m/Y') ?: '—' }}</td><th>{{ __('payroll.paid_from') }}</th><td>{{ $account ?: '—' }}</td></tr>
        @if (optional($staff)->bank_account_no)<tr><th>{{ __('payroll.bank_account') }}</th><td colspan="3">{{ $staff->bank_name }} {{ $staff->bank_account_no }}</td></tr>@endif
    </table>

    <div class="cols">
        <div>
            <h2>▲ {{ __('payroll.earnings') }}</h2>
            <table>
                <tr><td>{{ $p->paid_days && $p->period_days && $p->paid_days < $p->period_days ? __('payroll.basic_days', ['x' => (float) $p->paid_days, 'y' => (float) $p->period_days]) : __('payroll.basic_total') }}</td><td class="num">{{ $fmt($p->total_earning) }}</td></tr>
                @forelse ($allow as $a)<tr><td>{{ $a->title }}@if ($a->is_taxable) <span class="muted">({{ __('payroll.taxed') }})</span>@endif</td><td class="num">{{ $fmt($a->amount) }}</td></tr>
                @empty<tr><td colspan="2" class="muted">{{ __('payroll.no_additional') }}</td></tr>@endforelse
                <tr class="tot"><td>{{ __('payroll.total_earnings') }}</td><td class="num">{{ $fmt($p->total_earning + $p->total_allowance) }}</td></tr>
            </table>
        </div>
        <div>
            <h2>▼ {{ __('payroll.deductions') }}</h2>
            <table>
                @foreach ($empLines as $t)<tr><td>{{ $t->label }}@if ((float) $t->employer_amount > 0) <span class="muted">({{ __('payroll.employee_part') }})</span>@endif</td><td class="num">{{ $fmt($t->employee_amount) }}</td></tr>@endforeach
                @foreach ($deduct as $d)<tr><td>{{ $d->title }}</td><td class="num">{{ $fmt($d->amount) }}</td></tr>@endforeach
                @if ($empLines->isEmpty() && $deduct->isEmpty())<tr><td colspan="2" class="muted">—</td></tr>@endif
                <tr class="tot"><td>{{ __('payroll.total_deductions') }}</td><td class="num">{{ $fmt($p->tax + $p->total_deduction) }}</td></tr>
            </table>
        </div>
    </div>
    <p class="muted" style="margin:3mm 0 0">{{ __('payroll.taxed_on_slip', ['amount' => $fmt($p->taxable_salary)]) }}</p>
    <div class="net"><span>✔ {{ __('payroll.net_pay') }}</span><span>{{ $fmt($p->net_salary) }}</span></div>

    <h2>{{ __('payroll.employer_contributions') }}</h2>
    <table>
        @forelse ($erLines as $t)<tr><td>{{ $t->label }} <span class="muted">({{ __('payroll.employer_part') }})</span></td><td class="num">{{ $fmt($t->employer_amount) }}</td></tr>
        @empty<tr><td colspan="2" class="muted">—</td></tr>@endforelse
        <tr class="tot"><td>{{ __('payroll.total_employer') }}</td><td class="num">{{ $fmt($p->employer_tax) }}</td></tr>
        <tr class="tot"><td>■ {{ __('payroll.total_cost_slip') }}</td><td class="num">{{ $fmt($p->total_cost) }}</td></tr>
    </table>
    @if ($p->note)<p style="margin-top:4mm"><strong>{{ __('payroll.note') }}:</strong> {{ $p->note }}</p>@endif
    <div class="sig"><div>{{ __('payroll.prepared_by') }}</div><div>{{ __('payroll.approved_by') }} {{ optional($p->approver)->first_name }}</div><div>{{ __('payroll.employee_signature') }}</div></div>
    <p class="muted" style="font-size:8.5pt;margin-top:8mm">{{ __('payroll.slip_footer', ['business' => $business->name]) }}</p>
</div>
</body>
</html>
