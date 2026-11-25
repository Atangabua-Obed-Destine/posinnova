@extends('layouts.app')
@section('title', __('payroll.menu_payrolls'))

@section('content')
@include('pressing.partials.styles')
@include('ledger.partials.style')
@php
    $P = \App\Http\Controllers\PayrollController::class;
    $can = fn ($p) => \App\Http\Controllers\PayrollController::can($p);
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
    $approval = ! empty($s['require_approval']);
    $skipped = session('payroll_skipped', []);
@endphp
<section class="content ht" id="py_index">
    <div class="ht-head">
        <div><h1>{{ __('payroll.menu_payrolls') }}</h1><p>{{ __('payroll.index_lead') }}</p></div>
        <div class="ht-actions">
            <a class="ht-btn" href="{{ action([$P, 'report'], ['month' => $month->month, 'year' => $month->year]) }}">{{ __('payroll.menu_report') }}</a>
            @if ($can('payroll-setting') || $can('payroll-action'))<a class="ht-btn" href="{{ action([$P, 'staff']) }}">{{ __('payroll.menu_staff') }}</a>@endif
            @if ($can('payroll-loans') || $can('payroll-view'))<a class="ht-btn" href="{{ action([$P, 'loans']) }}">{{ __('payroll.menu_loans') }}</a>@endif
            @if ($can('payroll-remit') || $can('payroll-report'))<a class="ht-btn" href="{{ action([$P, 'remittances']) }}">{{ __('payroll.menu_remittances') }}</a>@endif
        </div>
    </div>

    @if (! $hasTaxes)
        <a class="ht-note amber" href="{{ action([$P, 'settings']) }}">{{ __('payroll.needs_taxes') }}</a>
    @endif
    @if ($staff->isEmpty())
        <a class="ht-note blue" href="{{ action([$P, 'staff']) }}">{{ __('payroll.needs_staff') }}</a>
    @endif
    @if ($skipped)
        <div class="ht-note amber"><strong>{{ __('payroll.skipped_title') }}</strong><ul style="margin:6px 0 0">@foreach ($skipped as $line)<li>{{ $line }}</li>@endforeach</ul></div>
    @endif

    <form method="get" action="{{ action([$P, 'index']) }}" class="ht-card" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
        <div class="ht-f" style="margin:0"><label for="py_m">{{ __('payroll.month') }} *</label>
            <select id="py_m" name="month" class="ht-in">@for ($i = 1; $i <= 12; $i++)<option value="{{ $i }}" {{ $month->month === $i ? 'selected' : '' }}>{{ \Carbon\Carbon::create(2000, $i, 1)->locale(app()->getLocale())->isoFormat('MMMM') }}</option>@endfor</select></div>
        <div class="ht-f" style="margin:0"><label for="py_y">{{ __('payroll.year') }} *</label>
            <select id="py_y" name="year" class="ht-in">@for ($y = now()->year; $y >= now()->year - 9; $y--)<option value="{{ $y }}" {{ $month->year === $y ? 'selected' : '' }}>{{ $y }}</option>@endfor</select></div>
        <button class="ht-btn pri" type="submit">{{ __('payroll.filter') }}</button>
        <span class="ht-muted ht-small" style="margin-left:auto">{{ __('payroll.month_totals', ['paid' => $totals['paid'], 'net' => $fmt($totals['net']), 'cost' => $fmt($totals['cost'])]) }}</span>
    </form>

    @if (($can('payroll-action') || $can('payroll-report')) && $payrolls->isNotEmpty())
    {{-- paxhitestbed's bank file: the net pay of each payroll, for the bank to pay --}}
    <form method="get" action="{{ action([$P, 'bankFile']) }}" class="ht-card" id="py_bank_file" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;padding:12px 18px">
        <input type="hidden" name="month" value="{{ $month->month }}"><input type="hidden" name="year" value="{{ $month->year }}">
        <div style="flex:1 1 260px"><strong>{{ __('payroll.bank_file') }}</strong><div class="ht-muted ht-small">{{ __('payroll.bank_file_hint') }}</div></div>
        <div class="ht-f" style="margin:0"><label for="py_which">{{ __('payroll.bank_which') }}</label>
            <select id="py_which" name="which" class="ht-in"><option value="unpaid">{{ __('payroll.bank_unpaid') }}</option><option value="paid">{{ __('payroll.bank_paid') }}</option><option value="all">{{ __('payroll.bank_all') }}</option></select></div>
        <button class="ht-btn" type="submit">{{ __('payroll.bank_download') }}</button>
    </form>
    @endif

    <div class="ht-card">
        <form method="post" action="{{ action([$P, 'bulk']) }}" id="py_bulk">@csrf
            <input type="hidden" name="month" value="{{ $month->month }}"><input type="hidden" name="year" value="{{ $month->year }}">
            @if ($can('payroll-action'))
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px">
                <button class="ht-btn" type="submit" name="action" value="generate" onclick="return confirm({{ json_encode(__('payroll.confirm_generate')) }})">{{ __('payroll.generate_ticked') }}</button>
                @if ($approval && $can('payroll-approve'))
                    <button class="ht-btn" type="submit" name="action" value="approve" onclick="return confirm({{ json_encode(__('payroll.confirm_approve')) }})">{{ __('payroll.approve_ticked') }}</button>
                @endif
                <button class="ht-btn ok" type="button" data-toggle="modal" data-target="#py_bulk_pay_modal" id="py_pay_ticked">{{ __('payroll.pay_ticked') }}</button>
                <span class="ht-muted ht-small" id="py_ticked">{{ __('payroll.tick_hint') }}</span>
            </div>
            @endif
            <div class="ht-scroll"><table class="ht-t" style="min-width:860px">
                <thead><tr><th><input type="checkbox" id="py_all" title="{{ __('payroll.tick_all') }}" aria-label="{{ __('payroll.tick_all') }}"></th><th>{{ __('payroll.name') }}</th><th>{{ __('payroll.job_title') }}</th><th class="num">{{ __('payroll.basic_salary') }}</th><th class="num">{{ __('payroll.net_salary') }}</th><th>{{ __('payroll.status') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($staff as $st)
                        @php $p = $payrolls->get($st->user_id); @endphp
                        <tr data-user="{{ $st->user_id }}">
                            <td><input type="checkbox" name="users[]" value="{{ $st->user_id }}" class="py-tick" aria-label="{{ $st->user->first_name }}"></td>
                            <td><strong>{{ trim($st->user->first_name.' '.$st->user->last_name) }}</strong><div class="ht-muted ht-small">{{ $st->user->username }}</div></td>
                            <td>{{ $st->job_title ?: '—' }}</td>
                            <td class="num">@if ($st->pay_type === 'hourly'){{ $fmt($st->hourly_rate) }} <span class="ht-muted ht-small">{{ __('payroll.per_hour') }}</span>@else{{ $fmt($st->basic_salary) }}@endif</td>
                            <td class="num">{{ $p ? $fmt($p->net_salary) : '—' }}</td>
                            <td>
                                @if (! $p)<span class="ht-pill ht-p-bad">{{ __('payroll.st_not_generated') }}</span>
                                @elseif ($p->isPaid())<span class="ht-pill ht-p-free">{{ __('payroll.st_paid') }}</span> <span class="ht-muted ht-small">{{ optional($p->pay_date)->format('d/m/Y') }}</span>
                                @elseif ($approval)<span class="ht-pill {{ $p->isApproved() ? 'ht-p-occ' : 'ht-p-dirty' }}">{{ $p->isApproved() ? __('payroll.st_approved') : __('payroll.st_awaiting') }}</span>
                                @else<span class="ht-pill ht-p-occ">{{ __('payroll.st_generated') }}</span>@endif
                            </td>
                            <td style="text-align:right;white-space:nowrap">
                                @if (! $p)
                                    @if ($can('payroll-action'))<a class="ht-btn sm" href="{{ action([$P, 'generate'], [$st->user_id, $month->month, $month->year]) }}">+ {{ __('payroll.generate') }}</a>@endif
                                @elseif (! $p->isPaid())
                                    @if ($approval && ! $p->isApproved())
                                        @if ($can('payroll-approve'))<button class="ht-btn sm" type="submit" form="py_approve_{{ $p->id }}">{{ __('payroll.approve') }}</button>@endif
                                    @elseif ($can('payroll-action'))
                                        <button class="ht-btn sm ok py-pay" type="button" data-id="{{ $p->id }}" data-name="{{ trim($st->user->first_name.' '.$st->user->last_name) }}"
                                            data-net="{{ $fmt($p->net_salary) }}" data-account="{{ $st->payment_account_id }}">{{ __('payroll.pay_now') }}</button>
                                    @endif
                                    @if ($can('payroll-action'))<a class="ht-btn sm" href="{{ action([$P, 'generate'], [$st->user_id, $month->month, $month->year]) }}" title="{{ __('payroll.edit') }}">{{ __('payroll.edit') }}</a>@endif
                                @else
                                    <a class="ht-btn sm" href="{{ action([$P, 'payslip'], [$p->id]) }}" target="_blank">{{ __('payroll.payslip') }}</a>
                                    @if ($can('payroll-action'))<button class="ht-btn sm danger" type="submit" form="py_unpay_{{ $p->id }}">{{ __('payroll.unpay') }}</button>@endif
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        </form>
        @foreach ($payrolls as $p)
            @if (! $p->isPaid())<form method="post" action="{{ action([$P, 'approve'], [$p->id]) }}" id="py_approve_{{ $p->id }}">@csrf</form>@endif
            @if ($p->isPaid())
                <form method="post" action="{{ action([$P, 'unpay'], [$p->id]) }}" id="py_unpay_{{ $p->id }}" data-review="{{ __('payroll.unpay_check') }}" data-review-label="{{ __('payroll.unpay_btn') }}" data-review-class="btn-danger">@csrf</form>
            @endif
        @endforeach
    </div>
</section>

{{-- Pay one payroll: paxhitestbed's "Pay Now", then the review --}}
<div class="modal fade" id="py_pay_modal" tabindex="-1" role="dialog" aria-labelledby="py_pay_title">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" id="py_pay_form" data-review="{{ __('payroll.pay_check') }}" data-review-label="{{ __('payroll.pay_and_post') }}">@csrf
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="{{ __('payroll.close') }}">&times;</button><h4 class="modal-title" id="py_pay_title">{{ __('payroll.pay_now') }}</h4></div>
            <div class="modal-body ht">
                <div class="ht-row"><span>{{ __('payroll.name') }}</span><strong id="py_pay_name"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.net_salary') }}</span><strong id="py_pay_net"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.salary_month') }}</span><span>{{ \App\Payroll\PayrollBook::monthLabel($month) }}</span></div>
                <div class="ht-f" style="margin-top:10px"><label for="py_pay_date">{{ __('payroll.pay_date') }} *</label><input id="py_pay_date" type="date" name="pay_date" class="ht-in" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="ht-f"><label for="py_pay_acc">{{ __('payroll.paid_from') }} *</label><select id="py_pay_acc" name="payment_account_id" class="ht-in" required><option value="">{{ __('payroll.pick_account') }}</option>@foreach ($accounts as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select><span class="ht-muted ht-small">{{ __('payroll.paid_from_hint') }}</span></div>
                <div class="ht-f"><label for="py_pay_note">{{ __('payroll.note') }}</label><input id="py_pay_note" name="note" class="ht-in"></div>
            </div>
            <div class="modal-footer"><button type="button" class="ht-btn" data-dismiss="modal">{{ __('payroll.close') }}</button><button type="submit" class="ht-btn ok">{{ __('payroll.pay_now') }}</button></div>
        </form>
    </div></div>
</div>

{{-- Pay every ticked payroll --}}
<div class="modal fade" id="py_bulk_pay_modal" tabindex="-1" role="dialog" aria-labelledby="py_bulk_title">
    <div class="modal-dialog" role="document"><div class="modal-content">
        <form method="post" action="{{ action([$P, 'bulkPay']) }}" id="py_bulk_pay_form" data-review="{{ __('payroll.bulk_pay_check') }}" data-review-label="{{ __('payroll.pay_and_post') }}">@csrf
            <input type="hidden" name="month" value="{{ $month->month }}"><input type="hidden" name="year" value="{{ $month->year }}">
            <div id="py_bulk_users"></div>
            <div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="{{ __('payroll.close') }}">&times;</button><h4 class="modal-title" id="py_bulk_title">{{ __('payroll.pay_ticked_title') }}</h4></div>
            <div class="modal-body ht">
                <p class="ht-muted">{{ __('payroll.pay_ticked_help') }}</p>
                <div class="ht-f"><label for="py_bulk_date">{{ __('payroll.pay_date') }} *</label><input id="py_bulk_date" type="date" name="pay_date" class="ht-in" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
                <div class="ht-f"><label for="py_bulk_note">{{ __('payroll.note') }}</label><input id="py_bulk_note" name="note" class="ht-in"></div>
            </div>
            <div class="modal-footer"><button type="button" class="ht-btn" data-dismiss="modal">{{ __('payroll.close') }}</button><button type="submit" class="ht-btn ok">{{ __('payroll.pay_ticked') }}</button></div>
        </form>
    </div></div>
</div>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
(function () {
    var ticks = function () { return [].slice.call(document.querySelectorAll('.py-tick')); };
    var T = {!! json_encode(['n' => __('payroll.n_ticked'), 'hint' => __('payroll.tick_hint')]) !!};
    var count = function () {
        var n = ticks().filter(function (c) { return c.checked; }).length, el = document.getElementById('py_ticked');
        if (el) el.textContent = n ? T.n.replace(':n', n) : T.hint;
    };
    var all = document.getElementById('py_all');
    if (all) all.addEventListener('change', function () { ticks().forEach(function (c) { c.checked = all.checked; }); count(); });
    ticks().forEach(function (c) { c.addEventListener('change', count); });
    // the ticked staff go with the "Pay ticked" form
    $('#py_bulk_pay_modal').on('show.bs.modal', function () {
        var box = document.getElementById('py_bulk_users'); box.innerHTML = '';
        ticks().filter(function (c) { return c.checked; }).forEach(function (c) {
            var i = document.createElement('input'); i.type = 'hidden'; i.name = 'users[]'; i.value = c.value; box.appendChild(i);
        });
    });
    var base = {!! json_encode(url('/payroll')) !!};
    $(document).on('click', '.py-pay', function () {
        var b = this, f = document.getElementById('py_pay_form');
        f.action = base + '/' + b.getAttribute('data-id') + '/pay';
        document.getElementById('py_pay_name').textContent = b.getAttribute('data-name');
        document.getElementById('py_pay_net').textContent = b.getAttribute('data-net');
        if (b.getAttribute('data-account')) document.getElementById('py_pay_acc').value = b.getAttribute('data-account');
        $('#py_pay_modal').modal('show');
    });
    count();
})();
</script>
@endsection
