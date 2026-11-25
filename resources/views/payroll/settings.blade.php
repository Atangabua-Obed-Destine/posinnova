@extends('layouts.app')
@section('title', __('payroll.menu_settings'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PayrollController::class;
    $yn = fn ($name, $on, $yes, $no) => '';
@endphp
<section class="content ht" id="py_settings">
    <div class="ht-head"><div><h1>{{ __('payroll.menu_settings') }}</h1><p>{{ __('payroll.settings_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'index']) }}">{{ __('payroll.menu_payrolls') }}</a></div></div>

    <form method="post" action="{{ action([$P, 'saveSettings']) }}" class="ht-card" id="py_rules">@csrf
        <h2>{{ __('payroll.approval') }}</h2>
        <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="require_approval" value="1" {{ ! empty($s['require_approval']) ? 'checked' : '' }}> {{ __('payroll.require_approval') }}</label>
        <p class="ht-muted ht-small">{{ __('payroll.require_approval_hint') }}</p>
        <h2>{{ __('payroll.generating') }}</h2>
        <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="carry_over_lines" value="1" {{ ! empty($s['carry_over_lines']) ? 'checked' : '' }}> {{ __('payroll.carry_over') }}</label>
        <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="prorate_partial_months" value="1" {{ ! empty($s['prorate_partial_months']) ? 'checked' : '' }}> {{ __('payroll.prorate') }}</label>
        <p class="ht-muted ht-small">{{ __('payroll.prorate_hint') }}</p>
        <div class="ht-f" style="max-width:320px"><label for="py_basis">{{ __('payroll.count_in') }}</label><select id="py_basis" name="proration_basis" class="ht-in"><option value="calendar_days" {{ $s['proration_basis'] === 'calendar_days' ? 'selected' : '' }}>{{ __('payroll.calendar_days') }}</option><option value="working_days" {{ $s['proration_basis'] === 'working_days' ? 'selected' : '' }}>{{ __('payroll.working_days') }}</option></select></div>

        <h2>{{ __('payroll.menu_loans') }}</h2>
        <div class="ht-f" style="max-width:320px"><label for="py_adv">{{ __('payroll.advance_account') }}</label><input id="py_adv" name="advance_account_code" class="ht-in" inputmode="numeric" value="{{ $s['advance_account_code'] }}"><span class="ht-muted ht-small">{{ __('payroll.advance_account_hint') }}</span></div>

        <h2>{{ __('payroll.bank_file') }}</h2>
        <p class="ht-muted ht-small">{{ __('payroll.bank_settings_hint') }}</p>
        <div style="display:flex;gap:6px 16px;flex-wrap:wrap;margin-bottom:10px" id="py_bank_cols">
            @foreach (\App\Payroll\PayrollRules::BANK_COLUMNS as $col)
                <label style="display:flex;gap:6px;align-items:center;margin:0;font-weight:500"><input type="checkbox" name="bank_file_columns[]" value="{{ $col }}" {{ in_array($col, (array) $s['bank_file_columns'], true) ? 'checked' : '' }}> {{ __('payroll.bank_col_'.$col) }}</label>
            @endforeach
        </div>
        <div class="ht-fields">
            <div class="ht-f"><label for="py_delim">{{ __('payroll.bank_delimiter') }}</label><select id="py_delim" name="bank_file_delimiter" class="ht-in">@foreach ([',' => __('payroll.delim_comma'), ';' => __('payroll.delim_semicolon'), 'tab' => __('payroll.delim_tab')] as $v => $l)<option value="{{ $v }}" {{ $s['bank_file_delimiter'] === $v ? 'selected' : '' }}>{{ $l }}</option>@endforeach</select></div>
            <div class="ht-f"><label for="py_ref">{{ __('payroll.bank_reference') }}</label><input id="py_ref" name="bank_file_reference" class="ht-in" value="{{ $s['bank_file_reference'] }}"><span class="ht-muted ht-small">{{ __('payroll.bank_reference_hint') }}</span></div>
            <div class="ht-f"><label for="py_dec">{{ __('payroll.bank_decimals') }}</label><select id="py_dec" name="bank_file_decimals" class="ht-in"><option value="0">0</option><option value="2" {{ (int) $s['bank_file_decimals'] === 2 ? 'selected' : '' }}>2</option></select></div>
        </div>
        <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="bank_file_header" value="1" {{ ! empty($s['bank_file_header']) ? 'checked' : '' }}> {{ __('payroll.bank_header') }}</label>
        <button class="ht-btn pri" type="submit">{{ __('payroll.save') }}</button>
    </form>

    <div class="ht-card" id="py_types">
        <h2>{{ __('payroll.line_types') }}</h2>
        <p class="ht-muted ht-small">{{ __('payroll.line_types_hint') }}</p>
        <table class="ht-t"><thead><tr><th>{{ __('payroll.line_title') }}</th><th>{{ __('payroll.kind') }}</th><th>{{ __('payroll.taxed') }}</th><th></th></tr></thead><tbody>
            @foreach ($types as $t)
                <tr style="{{ $t->status ? '' : 'opacity:.5' }}"><td>{{ $t->title }}</td><td>{{ __('payroll.'.$t->kind) }}</td><td>{{ $t->kind === 'allowance' ? ($t->is_taxable ? __('payroll.yes') : __('payroll.no')) : '—' }}</td>
                    <td style="text-align:right"><form method="post" action="{{ action([$P, 'toggleType'], [$t->id]) }}">@csrf<button class="ht-btn sm" type="submit">{{ $t->status ? __('payroll.hide') : __('payroll.show_again') }}</button></form></td></tr>
            @endforeach
        </tbody></table>
        <form method="post" action="{{ action([$P, 'addType']) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-top:10px">@csrf
            <div class="ht-f" style="margin:0"><label for="nt_title">{{ __('payroll.line_title') }}</label><input id="nt_title" name="title" class="ht-in"></div>
            <div class="ht-f" style="margin:0"><label for="nt_kind">{{ __('payroll.kind') }}</label><select id="nt_kind" name="kind" class="ht-in"><option value="allowance">{{ __('payroll.allowance') }}</option><option value="deduction">{{ __('payroll.deduction') }}</option></select></div>
            <label style="display:flex;gap:6px;align-items:center;margin:0 0 10px"><input type="checkbox" name="is_taxable" value="1"> {{ __('payroll.taxed') }}</label>
            <button class="ht-btn" type="submit">+ {{ __('payroll.add') }}</button>
        </form>
    </div>

    <div class="ht-card" id="py_taxes">
        <h2>{{ __('payroll.taxes') }}</h2>
        @if (! $hasTaxes)
            <div class="ht-note amber">{{ __('payroll.no_taxes') }}</div>
            <form method="post" action="{{ action([$P, 'installTaxes']) }}">@csrf<button class="ht-btn pri" type="submit" id="py_install_taxes">{{ __('payroll.install_cameroon') }}</button></form>
        @else
            <p class="ht-muted ht-small">{{ __('payroll.taxes_hint') }}</p>
            <form method="post" action="{{ action([$P, 'saveTaxes']) }}">@csrf
                @foreach ($groups as $g)
                    <div style="border:1px solid #eaecf0;border-radius:10px;padding:12px;margin-bottom:12px">
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:8px">
                            <input name="groups[{{ $g->id }}][title]" class="ht-in" value="{{ $g->title }}" style="max-width:280px;font-weight:700" aria-label="{{ __('payroll.tax') }}">
                            <label style="margin:0;display:flex;gap:6px;align-items:center"><input type="checkbox" name="groups[{{ $g->id }}][status]" value="1" {{ $g->status ? 'checked' : '' }}> {{ __('payroll.in_force') }}</label>
                            <label style="margin:0">{{ __('payroll.owed_on') }} <select name="groups[{{ $g->id }}][liability_code]" class="ht-in" style="width:auto;display:inline-block">@foreach (['447', '431', '442'] as $c)<option value="{{ $c }}" {{ $g->liability_code === $c ? 'selected' : '' }}>{{ $c }}</option>@endforeach</select></label>
                            @if ($g->is_income_tax)<span class="ht-pill ht-p-occ">{{ __('payroll.income_tax') }}</span>@endif
                        </div>
                        @if ($g->bands->isEmpty())<p class="ht-muted ht-small">{{ __('payroll.no_bands') }}</p>@else
                        <div class="ht-scroll"><table class="ht-t" style="min-width:760px"><thead><tr><th>{{ __('payroll.from') }}</th><th>{{ __('payroll.to') }}</th><th>{{ $g->bands->first()->tax_type == 2 ? __('payroll.fixed_staff') : __('payroll.rate_staff') }}</th><th>{{ $g->bands->first()->tax_type == 2 ? __('payroll.fixed_employer') : __('payroll.rate_employer') }}</th><th>{{ __('payroll.threshold') }}</th><th>{{ __('payroll.in_force') }}</th></tr></thead><tbody>
                            @foreach ($g->bands as $b)
                                @include('payroll.partials.band', ['b' => $b])
                            @endforeach
                        </tbody></table></div>@endif
                    </div>
                @endforeach
                <div style="border:1px solid #eaecf0;border-radius:10px;padding:12px;margin-bottom:12px">
                    <h3>{{ __('payroll.single_taxes') }}</h3>
                    <div class="ht-scroll"><table class="ht-t" style="min-width:760px"><thead><tr><th>{{ __('payroll.tax') }}</th><th>{{ __('payroll.from') }}</th><th>{{ __('payroll.to') }}</th><th>{{ __('payroll.staff_share') }}</th><th>{{ __('payroll.employer_share') }}</th><th>{{ __('payroll.threshold') }}</th><th>{{ __('payroll.in_force') }}</th></tr></thead><tbody>
                        @foreach ($singles as $b)
                            @include('payroll.partials.band', ['b' => $b, 'named' => true])
                        @endforeach
                    </tbody></table></div>
                </div>
                <button class="ht-btn pri" type="submit" id="py_save_taxes">{{ __('payroll.save_taxes') }}</button>
            </form>
        @endif
    </div>
</section>
@endsection
