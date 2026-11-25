@extends('layouts.app')
@section('title', __('payroll.generate_title'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PayrollController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
    $u = $staff->user;
@endphp
<style>
.py-lines .ln { display: grid; grid-template-columns: minmax(0, 1.5fr) minmax(90px, 1fr) 36px; gap: 8px; margin-bottom: 8px; align-items: center; }
.py-calc .ht-row span:first-child small { display: block; color: #667085; font-size: 12px; font-weight: 400; }
.py-calc .big { font-size: 18px; }
</style>
<section class="content ht" id="py_generate">
    <div class="ht-head">
        <div><h1>{{ __('payroll.generate_title') }}</h1><p>{{ __('payroll.generate_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'index'], ['month' => $month->month, 'year' => $month->year]) }}">{{ __('payroll.back') }}</a></div>
    </div>

    <div class="ht-card" style="display:flex;gap:24px;flex-wrap:wrap">
        <div><div class="ht-muted ht-small">{{ __('payroll.name') }}</div><strong style="font-size:17px">{{ trim($u->first_name.' '.$u->last_name) }}</strong> <span class="ht-pill {{ $p ? 'ht-p-occ' : 'ht-p-tentative' }}">{{ $p ? __('payroll.draft') : __('payroll.new_payroll') }}</span></div>
        <div><div class="ht-muted ht-small">{{ __('payroll.job_title') }}</div>{{ $staff->job_title ?: '—' }}</div>
        <div><div class="ht-muted ht-small">{{ __('payroll.payroll_period') }}</div>{{ \App\Payroll\PayrollBook::monthLabel($month) }}</div>
        <div><div class="ht-muted ht-small">{{ __('payroll.basic_salary') }}</div>{{ $fmt($staff->basic_salary) }} {{ __('payroll.per_month') }}</div>
        <div><div class="ht-muted ht-small">{{ __('payroll.cnps_no') }}</div>{{ $staff->cnps_no ?: '—' }}</div>
    </div>

    @if ($start['from'])
        <div class="ht-note blue">{{ __('payroll.carried_over', ['month' => \App\Payroll\PayrollBook::monthLabel($start['from'])]) }}</div>
    @endif

    <form method="post" action="{{ action([$P, 'store']) }}" id="py_form">@csrf
        <input type="hidden" name="user_id" value="{{ $staff->user_id }}"><input type="hidden" name="month" value="{{ $month->month }}"><input type="hidden" name="year" value="{{ $month->year }}">
        <div class="ht-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); align-items: start">
            <div class="ht-card">
                <h2 style="display:flex;justify-content:space-between;align-items:center">{{ __('payroll.allowance') }} <button class="ht-btn sm" type="button" id="py_add_allow">+ {{ __('payroll.add') }}</button></h2>
                <p class="ht-muted ht-small">{{ __('payroll.allowance_hint') }}</p>
                <div class="py-lines" id="py_allow"></div>
                <p class="ht-muted ht-small" id="py_allow_empty">{{ __('payroll.no_allowances') }}</p>
            </div>
            <div class="ht-card">
                <h2 style="display:flex;justify-content:space-between;align-items:center">{{ __('payroll.deduction') }} <button class="ht-btn sm" type="button" id="py_add_deduct">+ {{ __('payroll.add') }}</button></h2>
                <p class="ht-muted ht-small">{{ __('payroll.deduction_hint') }}</p>
                <div class="py-lines" id="py_deduct"></div>
                <p class="ht-muted ht-small" id="py_deduct_empty">{{ __('payroll.no_deductions') }}</p>
            </div>
            <div class="ht-card py-calc" id="py_calc">
                <h2 style="display:flex;justify-content:space-between;align-items:center">{{ __('payroll.calculate') }} <button class="ht-btn sm" type="button" id="py_recalc">{{ __('payroll.recalculate') }}</button></h2>
                <p class="ht-muted ht-small">{{ __('payroll.calculate_hint') }}</p>
                <div class="ht-row"><span>{{ __('payroll.earning') }}<small>{{ __('payroll.earning_hint') }}</small><small id="py_note"></small></span><strong data-f="earning"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.allowance') }}<small>{{ __('payroll.allowance_sum') }}</small></span><strong data-f="total_allowance"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.deduction') }}<small>{{ __('payroll.deduction_sum') }}</small></span><strong data-f="total_deduction"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.gross_salary') }}<small>{{ __('payroll.gross_hint') }}</small></span><strong data-f="gross"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.employee_tax') }}<small id="py_rate"></small><small id="py_taxed_on"></small></span><strong data-f="tax"></strong></div>
                <div id="py_tax_lines" class="ht-small" style="background:#f9fafb;border-radius:8px;padding:8px 10px;margin:6px 0"></div>
                <div class="ht-row"><span>{{ __('payroll.employer_tax') }}<small>{{ __('payroll.employer_hint') }}</small></span><strong data-f="employer_tax"></strong></div>
                <div class="ht-row big"><span><strong>{{ __('payroll.net_salary') }}</strong><small>{{ __('payroll.net_hint') }}</small></span><strong data-f="net" style="color:#067647"></strong></div>
                <div class="ht-row"><span>{{ __('payroll.total_cost') }}<small>{{ __('payroll.cost_hint') }}</small></span><strong data-f="total_cost"></strong></div>
                <div class="ht-note red" id="py_problems" hidden><strong>{{ __('payroll.cannot_save') }}</strong><ul style="margin:6px 0 0" id="py_problem_list"></ul></div>
                <button type="submit" class="ht-btn pri" id="py_save" style="width:100%;min-height:48px;margin-top:10px">{{ __('payroll.save_payroll') }}</button>
            </div>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
(function () {
    var ALLOW = {!! json_encode($allowanceTypes->map(fn ($t) => ['title' => $t->title, 'taxed' => (bool) $t->is_taxable])->values()) !!};
    var DEDUCT = {!! json_encode($deductionTypes->pluck('title')->values()) !!};
    var START = {!! json_encode(['allowances' => $start['allowances'], 'deductions' => $start['deductions']]) !!};
    var T = {!! json_encode(['taxed' => __('payroll.taxed'), 'remove' => __('payroll.remove'), 'title' => __('payroll.line_title'), 'amount' => __('payroll.amount'),
        'none' => __('payroll.no_tax'), 'staff' => __('payroll.staff_share'), 'employer' => __('payroll.employer_share'), 'rate' => __('payroll.effective_rate'),
        'taxed_on' => __('payroll.taxed_on'), 'failed' => __('payroll.preview_failed'), 'pick' => __('payroll.pick_type'),
        'check' => __('payroll.cf_check'), 'intro' => __('payroll.cf_intro'), 'yes' => __('payroll.cf_yes'), 'net' => __('payroll.net_salary'),
        'gross' => __('payroll.gross_salary'), 'tax' => __('payroll.employee_tax'), 'emp' => __('payroll.employer_tax'), 'cost' => __('payroll.total_cost')]) !!};
    var URL = {!! json_encode(action([\App\Http\Controllers\PayrollController::class, 'preview'])) !!};
    var form = document.getElementById('py_form');
    var money = function (n) { return window.__currency_trans_from_en && typeof __currency_precision !== 'undefined' ? __currency_trans_from_en(Math.round(n), true) : Math.round(n).toLocaleString(); };
    var last = null, timer = null;

    function row(box, kind, title, amount) {
        var r = document.createElement('div'); r.className = 'ln';
        var sel;
        if (kind === 'allowances') {
            sel = document.createElement('select'); sel.className = 'ht-in';
            var o0 = document.createElement('option'); o0.value = ''; o0.textContent = T.pick; sel.appendChild(o0);
            var found = false;
            ALLOW.forEach(function (a) { var o = document.createElement('option'); o.value = a.title; o.textContent = a.title + (a.taxed ? ' · ' + T.taxed : ''); if (a.title === title) { o.selected = true; found = true; } sel.appendChild(o); });
            if (title && !found) { var ox = document.createElement('option'); ox.value = title; ox.textContent = title; ox.selected = true; sel.appendChild(ox); }
        } else {
            sel = document.createElement('input'); sel.className = 'ht-in'; sel.value = title || ''; sel.setAttribute('list', 'py_deduct_types');
        }
        sel.name = kind + '[' + box.children.length + '][title]'; sel.setAttribute('aria-label', T.title);
        var amt = document.createElement('input'); amt.className = 'ht-in'; amt.inputMode = 'decimal'; amt.value = amount || ''; amt.setAttribute('aria-label', T.amount);
        amt.name = kind + '[' + box.children.length + '][amount]';
        var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'ht-btn sm danger'; rm.textContent = '×'; rm.setAttribute('aria-label', T.remove);
        rm.addEventListener('click', function () { r.remove(); renumber(); later(); });
        sel.addEventListener('change', later); amt.addEventListener('input', later);
        r.appendChild(sel); r.appendChild(amt); r.appendChild(rm); box.appendChild(r);
        empties();
    }
    function renumber() {
        ['allowances', 'deductions'].forEach(function (k) {
            var box = document.getElementById(k === 'allowances' ? 'py_allow' : 'py_deduct');
            [].slice.call(box.children).forEach(function (r, i) { r.children[0].name = k + '[' + i + '][title]'; r.children[1].name = k + '[' + i + '][amount]'; });
        });
        empties();
    }
    function empties() {
        document.getElementById('py_allow_empty').hidden = document.getElementById('py_allow').children.length > 0;
        document.getElementById('py_deduct_empty').hidden = document.getElementById('py_deduct').children.length > 0;
    }
    function later() { clearTimeout(timer); timer = setTimeout(preview, 350); }
    function preview() {
        var fd = new FormData(form);
        fetch(URL, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(show).catch(function () { problems([T.failed]); });
    }
    function show(c) {
        last = c;
        document.querySelectorAll('#py_calc [data-f]').forEach(function (el) { el.textContent = money(c[el.getAttribute('data-f')] || 0); });
        document.getElementById('py_rate').textContent = T.rate.replace(':rate', c.effective_rate);
        document.getElementById('py_taxed_on').textContent = T.taxed_on.replace(':amount', money(c.taxable));
        document.getElementById('py_note').textContent = (c.notes || []).join(' ');
        var box = document.getElementById('py_tax_lines'); box.innerHTML = '';
        if (!c.tax_lines.length) { box.textContent = T.none; }
        c.tax_lines.forEach(function (l) {
            // one tax a block: its name, then only the shares that are charged
            var d = document.createElement('div'); d.style.padding = '3px 0'; d.style.borderBottom = '1px solid #eaecf0';
            var a = document.createElement('div'); a.style.fontWeight = '600'; a.textContent = l.label;
            var parts = [];
            if (l.employee > 0) { parts.push(T.staff + ' ' + money(l.employee)); }
            if (l.employer > 0) { parts.push(T.employer + ' ' + money(l.employer)); }
            var b = document.createElement('div'); b.style.color = '#667085'; b.textContent = parts.join(' · ');
            d.appendChild(a); d.appendChild(b); box.appendChild(d);
        });
        problems(c.problems || []);
    }
    function problems(list) {
        var p = document.getElementById('py_problems'), ul = document.getElementById('py_problem_list');
        ul.innerHTML = ''; list.forEach(function (t) { var li = document.createElement('li'); li.textContent = t; ul.appendChild(li); });
        p.hidden = !list.length;
        document.getElementById('py_save').disabled = list.length > 0;
    }
    document.getElementById('py_add_allow').addEventListener('click', function () { row(document.getElementById('py_allow'), 'allowances', '', ''); });
    document.getElementById('py_add_deduct').addEventListener('click', function () { row(document.getElementById('py_deduct'), 'deductions', '', ''); });
    document.getElementById('py_recalc').addEventListener('click', preview);
    START.allowances.forEach(function (l) { row(document.getElementById('py_allow'), 'allowances', l.title, l.amount); });
    START.deductions.forEach(function (l) { row(document.getElementById('py_deduct'), 'deductions', l.title, l.amount); });
    var dl = document.createElement('datalist'); dl.id = 'py_deduct_types'; DEDUCT.forEach(function (t) { var o = document.createElement('option'); o.value = t; dl.appendChild(o); }); document.body.appendChild(dl);
    empties();
    show({!! json_encode($calc) !!});

    // paxhitestbed's "Confirm Payroll Submission", through PosInnova's confirmation step
    form.addEventListener('submit', function (e) {
        if (form.__go || !last) { form.__go = false; return; }
        e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation();
        setTimeout(function () { document.getElementById('py_save').disabled = (last.problems || []).length > 0; }, 0);
        var go = function () { form.__go = true; if (form.requestSubmit) form.requestSubmit(); else HTMLFormElement.prototype.submit.call(form); };
        if (!window.PosConfirm || !PosConfirm.enabled('payroll')) { go(); return; }
        PosConfirm.ask({ kind: 'payroll', title: T.check, intro: T.intro, amount: last.net, amountLabel: T.net, yes: T.yes, check: T.check,
            rows: [[T.gross, money(last.gross)], [T.tax, money(last.tax)], [T.emp, money(last.employer_tax)], [T.cost, money(last.total_cost), 'strong']] }, go);
    }, true);
})();
</script>
@endsection
