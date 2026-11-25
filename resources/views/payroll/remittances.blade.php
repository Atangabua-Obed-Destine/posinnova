@extends('layouts.app')
@section('title', __('payroll.menu_remittances'))

@section('content')
@include('pressing.partials.styles')
@include('ledger.partials.style')
@php
    $P = \App\Http\Controllers\PayrollController::class;
    $can = fn ($p) => \App\Http\Controllers\PayrollController::can($p);
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
    $pill = ['owed' => 'ht-p-bad', 'part_paid' => 'ht-p-dirty', 'settled' => 'ht-p-free', 'overpaid' => 'ht-p-occ'];
    $open = collect($months)->filter(fn ($m) => abs($m['outstanding']) >= 0.01);
@endphp
<section class="content ht" id="py_remit">
    <div class="ht-head">
        <div><h1>{{ __('payroll.menu_remittances') }}</h1><p>{{ __('payroll.remit_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'index']) }}">{{ __('payroll.menu_payrolls') }}</a><a class="ht-btn" href="{{ action([$P, 'report']) }}">{{ __('payroll.menu_report') }}</a></div>
    </div>

    <div class="ht-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-bottom:14px">
        <div class="ht-card" style="margin:0"><div class="ht-muted ht-small">{{ __('payroll.remit_outstanding') }}</div><div style="font-size:22px;font-weight:700" id="py_remit_total">{{ $fmt($open->where('outstanding', '>', 0)->sum('outstanding')) }}</div></div>
        <div class="ht-card" style="margin:0"><div class="ht-muted ht-small">{{ __('payroll.remit_months_open') }}</div><div style="font-size:22px;font-weight:700">{{ $open->where('outstanding', '>', 0)->count() }}</div></div>
    </div>

    <div class="ht-card" id="py_remit_months">
        <h2>{{ __('payroll.remit_owed') }}</h2>
        <p class="ht-muted ht-small">{{ __('payroll.remit_owed_hint') }}</p>
        @if (! $months)
            <p class="ht-muted">{{ __('payroll.remit_nothing') }}</p>
        @else
        <div class="ht-scroll"><table class="ht-t" style="min-width:960px">
            <thead><tr><th>{{ __('payroll.authority') }}</th><th>{{ __('payroll.salary_month') }}</th><th class="num">{{ __('payroll.employee_part') }}</th><th class="num">{{ __('payroll.employer_part') }}</th><th class="num">{{ __('payroll.remit_due') }}</th><th class="num">{{ __('payroll.remit_paid') }}</th><th class="num">{{ __('payroll.remit_left') }}</th><th>{{ __('payroll.status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($months as $m)
                @php $key = $m['code'].'|'.$m['month']; @endphp
                <tr data-remit="{{ $key }}">
                    <td><strong>{{ $m['label'] }}</strong><div class="ht-muted ht-small">{{ trans_choice('payroll.n_staff', $m['staff'], ['n' => $m['staff']]) }}</div></td>
                    <td>{{ \App\Payroll\PayrollBook::monthLabel($m['month']) }}</td>
                    <td class="num">{{ $fmt($m['employee']) }}</td>
                    <td class="num">{{ $fmt($m['employer']) }}</td>
                    <td class="num">{{ $fmt($m['due']) }}</td>
                    <td class="num">{{ $fmt($m['paid']) }}</td>
                    <td class="num"><strong>{{ $fmt($m['outstanding']) }}</strong></td>
                    <td><span class="ht-pill {{ $pill[$m['status']] }}">{{ __('payroll.remit_st_'.$m['status']) }}</span></td>
                    <td style="text-align:right;white-space:nowrap">
                        <button class="ht-btn sm py-breakdown" type="button" data-key="{{ $key }}">{{ __('payroll.remit_details') }}</button>
                        @if ($can('payroll-remit') && $m['outstanding'] > 0)
                            <button class="ht-btn sm ok py-remit" type="button" data-code="{{ $m['code'] }}" data-month="{{ $m['month'] }}" data-label="{{ $m['label'] }}" data-month-label="{{ \App\Payroll\PayrollBook::monthLabel($m['month']) }}" data-amount="{{ round($m['outstanding'], 2) }}" data-paid="{{ $m['paid'] > 0 ? 1 : 0 }}">{{ __('payroll.remit_pay') }}</button>
                        @endif
                    </td>
                </tr>
                <tr class="py-breakdown-row" data-for="{{ $key }}" hidden><td colspan="9" style="background:#f9fafb">
                    <table class="ht-t" style="margin:0"><thead><tr><th>{{ __('payroll.tax') }}</th><th class="num">{{ __('payroll.employee_part') }}</th><th class="num">{{ __('payroll.employer_part') }}</th><th class="num">{{ __('payroll.total') }}</th></tr></thead><tbody>
                        @forelse ($breakdown[$key] as $b)
                            <tr><td>{{ $b->label }} <span class="ht-muted ht-small">· {{ trans_choice('payroll.n_staff', $b->staff, ['n' => $b->staff]) }}</span></td><td class="num">{{ $fmt($b->employee) }}</td><td class="num">{{ $fmt($b->employer) }}</td><td class="num">{{ $fmt($b->employee + $b->employer) }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="ht-muted">{{ __('payroll.remit_no_lines') }}</td></tr>
                        @endforelse
                    </tbody></table>
                </td></tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </div>

    @if ($reconciliation)
    <div class="ht-card" id="py_remit_recon">
        <h2>{{ __('payroll.recon_title') }}</h2>
        <p class="ht-muted ht-small">{{ __('payroll.recon_hint') }}</p>
        <table class="ht-t"><thead><tr><th>{{ __('payroll.authority') }}</th><th class="num">{{ __('payroll.recon_months') }}</th><th class="num">{{ __('payroll.recon_ledger') }}</th><th class="num">{{ __('payroll.recon_difference') }}</th><th></th></tr></thead><tbody>
            @foreach ($reconciliation as $r)
                <tr><td>{{ $r['label'] }}</td><td class="num">{{ $fmt($r['outstanding']) }}</td><td class="num">{{ $fmt($r['ledger']) }}</td><td class="num">{{ $fmt($r['difference']) }}</td>
                    <td>@if ($r['agrees'])<span class="ht-pill ht-p-free">{{ __('payroll.recon_agrees') }}</span>@else<span class="ht-pill ht-p-dirty">{{ __('payroll.recon_differs') }}</span>@endif</td></tr>
            @endforeach
        </tbody></table>
    </div>
    @endif

    <div class="ht-card" id="py_remit_history">
        <h2>{{ __('payroll.remit_history') }}</h2>
        @if ($history->isEmpty())
            <p class="ht-muted">{{ __('payroll.remit_none_yet') }}</p>
        @else
        <div class="ht-scroll"><table class="ht-t" style="min-width:860px">
            <thead><tr><th>{{ __('payroll.pay_date') }}</th><th>{{ __('payroll.authority') }}</th><th>{{ __('payroll.salary_month') }}</th><th class="num">{{ __('payroll.amount') }}</th><th>{{ __('payroll.paid_from') }}</th><th>{{ __('payroll.remit_reference') }}</th><th>{{ __('payroll.status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($history as $h)
                <tr data-remittance="{{ $h->id }}" style="{{ $h->isVoided() ? 'opacity:.55' : '' }}">
                    <td>{{ $h->payment_date->format('d/m/Y') }}</td>
                    <td>{{ \App\Payroll\PayrollRemittances::authority($h->business_id, $h->liability_code) }}</td>
                    <td>{{ \App\Payroll\PayrollBook::monthLabel($h->salary_month) }}</td>
                    <td class="num">{{ $fmt($h->amount) }}</td>
                    <td>{{ $accounts[$h->payment_account_id] ?? '—' }}</td>
                    <td>{{ $h->reference ?: '—' }}@if ($h->note)<div class="ht-muted ht-small">{{ $h->note }}</div>@endif</td>
                    <td>@if ($h->isVoided())<span class="ht-pill ht-p-bad">{{ __('payroll.remit_st_voided') }}</span>@if ($h->void_reason)<div class="ht-muted ht-small">{{ $h->void_reason }}</div>@endif @else<span class="ht-pill ht-p-free">{{ __('payroll.remit_st_paid') }}</span>@endif</td>
                    <td style="text-align:right">
                        @if ($can('payroll-remit') && ! $h->isVoided())
                            <form method="post" action="{{ action([$P, 'voidRemit'], [$h->id]) }}" data-review="{{ __('payroll.remit_void_check') }}" data-review-label="{{ __('payroll.remit_void') }}" data-review-class="btn-danger" data-review-reason="1">@csrf<button class="ht-btn sm danger" type="submit">{{ __('payroll.remit_void') }}</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </div>
</section>

{{-- paxhitestbed's "Record payment": the money leaves a cash box or bank, through the review --}}
<div class="modal fade" id="py_remit_modal" tabindex="-1" role="dialog" aria-labelledby="py_remit_title">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" action="{{ action([$P, 'remit']) }}" id="py_remit_form" data-review="{{ __('payroll.remit_check') }}" data-review-label="{{ __('payroll.remit_pay_post') }}">@csrf
            <input type="hidden" name="code" id="rm_code"><input type="hidden" name="month" id="rm_month">
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="{{ __('payroll.close') }}">&times;</button><h4 class="modal-title" id="py_remit_title">{{ __('payroll.remit_pay') }}</h4></div>
            <div class="modal-body ht">
                <div class="ht-row"><span>{{ __('payroll.authority') }}</span><strong id="rm_label"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.salary_month') }}</span><span id="rm_month_label"></span></div>
                <div class="ht-f" style="margin-top:10px"><label for="rm_amount">{{ __('payroll.amount') }} *</label><input id="rm_amount" name="amount" class="ht-in" inputmode="decimal" required></div>
                <div class="ht-f"><label for="rm_date">{{ __('payroll.pay_date') }} *</label><input id="rm_date" type="date" name="payment_date" class="ht-in" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="ht-f"><label for="rm_acc">{{ __('payroll.paid_from') }} *</label><select id="rm_acc" name="payment_account_id" class="ht-in" required><option value="">{{ __('payroll.pick_account') }}</option>@foreach ($accounts as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></div>
                <div class="ht-f"><label for="rm_ref">{{ __('payroll.remit_reference') }}</label><input id="rm_ref" name="reference" class="ht-in"><span class="ht-muted ht-small">{{ __('payroll.remit_reference_hint') }}</span></div>
                <div class="ht-f"><label for="rm_note">{{ __('payroll.note') }}</label><input id="rm_note" name="note" class="ht-in"></div>
                <label style="display:flex;gap:8px;align-items:center;margin:4px 0" id="rm_additional_box" hidden><input type="checkbox" name="allow_additional" value="1" id="rm_additional"> {{ __('payroll.remit_additional') }}</label>
                <label style="display:flex;gap:8px;align-items:center;margin:4px 0"><input type="checkbox" name="allow_overpay" value="1" id="rm_overpay"> {{ __('payroll.remit_overpay') }}</label>
            </div>
            <div class="modal-footer"><button type="button" class="ht-btn" data-dismiss="modal">{{ __('payroll.close') }}</button><button type="submit" class="ht-btn ok">{{ __('payroll.remit_pay') }}</button></div>
        </form>
    </div></div>
</div>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
(function () {
    $(document).on('click', '.py-breakdown', function () {
        var row = document.querySelector('.py-breakdown-row[data-for="' + this.getAttribute('data-key') + '"]');
        if (row) { row.hidden = !row.hidden; }
    });
    $(document).on('click', '.py-remit', function () {
        var b = this;
        document.getElementById('rm_code').value = b.getAttribute('data-code');
        document.getElementById('rm_month').value = b.getAttribute('data-month');
        document.getElementById('rm_label').textContent = b.getAttribute('data-label');
        document.getElementById('rm_month_label').textContent = b.getAttribute('data-month-label');
        document.getElementById('rm_amount').value = b.getAttribute('data-amount');
        var again = b.getAttribute('data-paid') === '1';
        document.getElementById('rm_additional_box').hidden = !again;
        document.getElementById('rm_additional').checked = again;
        $('#py_remit_modal').modal('show');
    });
})();
</script>
@endsection
