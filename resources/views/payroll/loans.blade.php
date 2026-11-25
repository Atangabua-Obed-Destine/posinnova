@extends('layouts.app')
@section('title', __('payroll.menu_loans'))

@section('content')
@include('pressing.partials.styles')
@include('ledger.partials.style')
@php
    $P = \App\Http\Controllers\PayrollController::class;
    $can = fn ($p) => \App\Http\Controllers\PayrollController::can($p);
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
@endphp
<section class="content ht" id="py_loans">
    <div class="ht-head">
        <div><h1>{{ __('payroll.menu_loans') }}</h1><p>{{ __('payroll.loans_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'index']) }}">{{ __('payroll.menu_payrolls') }}</a></div>
    </div>

    @if ($can('payroll-loans'))
    <details class="ht-card" id="py_give" {{ $errors->any() || old('amount') ? 'open' : '' }}>
        <summary style="cursor:pointer;font-weight:700;font-size:16px">+ {{ __('payroll.give_loan') }}</summary>
        @if ($staff->isEmpty())
            <p class="ht-muted" style="margin-top:10px">{{ __('payroll.needs_staff') }}</p>
        @else
        <form method="post" action="{{ action([$P, 'giveLoan']) }}" id="py_give_form" style="margin-top:12px" data-review="{{ __('payroll.loan_check') }}" data-review-label="{{ __('payroll.give_and_post') }}">@csrf
            <div class="ht-fields">
                <div class="ht-f"><label for="ln_user">{{ __('payroll.staff_member') }} *</label><select id="ln_user" name="user_id" class="ht-in" required><option value="">—</option>@foreach ($staff as $st)<option value="{{ $st->user_id }}" data-account="{{ $st->payment_account_id }}" {{ (int) old('user_id') === (int) $st->user_id ? 'selected' : '' }}>{{ trim($st->user->first_name.' '.$st->user->last_name) }}</option>@endforeach</select></div>
                <div class="ht-f"><label for="ln_kind">{{ __('payroll.kind') }}</label><select id="ln_kind" name="kind" class="ht-in"><option value="advance" {{ old('kind') === 'advance' ? 'selected' : '' }}>{{ __('payroll.kind_advance') }}</option><option value="loan" {{ old('kind') === 'loan' ? 'selected' : '' }}>{{ __('payroll.kind_loan') }}</option></select></div>
                <div class="ht-f"><label for="ln_title">{{ __('payroll.line_title') }}</label><input id="ln_title" name="title" class="ht-in" value="{{ old('title') }}" placeholder="{{ __('payroll.loan_title_hint') }}"></div>
                <div class="ht-f"><label for="ln_amount">{{ __('payroll.amount') }} *</label><input id="ln_amount" name="amount" class="ht-in" inputmode="decimal" value="{{ old('amount') }}" required></div>
                <div class="ht-f"><label for="ln_inst">{{ __('payroll.instalment') }} *</label><input id="ln_inst" name="instalment" class="ht-in" inputmode="decimal" value="{{ old('instalment') }}" required><span class="ht-muted ht-small">{{ __('payroll.instalment_hint') }}</span></div>
                <div class="ht-f"><label for="ln_start">{{ __('payroll.start_month') }} *</label><input id="ln_start" type="month" name="start_month" class="ht-in" value="{{ old('start_month', now()->format('Y-m')) }}" required></div>
                <div class="ht-f"><label for="ln_given">{{ __('payroll.given_on') }} *</label><input id="ln_given" type="date" name="given_on" class="ht-in" value="{{ old('given_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
                <div class="ht-f"><label for="ln_acc">{{ __('payroll.paid_from') }} *</label><select id="ln_acc" name="payment_account_id" class="ht-in" required><option value="">{{ __('payroll.pick_account') }}</option>@foreach ($accounts as $id => $n)<option value="{{ $id }}" {{ (int) old('payment_account_id') === (int) $id ? 'selected' : '' }}>{{ $n }}</option>@endforeach</select></div>
                <div class="ht-f"><label for="ln_note">{{ __('payroll.note') }}</label><input id="ln_note" name="note" class="ht-in" value="{{ old('note') }}"></div>
            </div>
            <p class="ht-muted ht-small">{{ $books ? __('payroll.loan_posting', ['code' => $advance]) : __('payroll.loan_no_books') }}</p>
            <button class="ht-btn pri" type="submit" id="py_give_btn">{{ __('payroll.give_loan') }}</button>
        </form>
        @endif
    </details>
    @endif

    <div class="ht-card">
        <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
            @foreach (['active', 'cleared', 'cancelled', 'all'] as $st)
                <a class="ht-btn sm {{ $status === $st ? 'pri' : '' }}" href="{{ action([$P, 'loans'], ['status' => $st]) }}">{{ __('payroll.loans_'.$st) }}</a>
            @endforeach
        </div>
        @if ($loans->isEmpty())
            <p class="ht-muted">{{ __('payroll.no_loans') }}</p>
        @else
        <div class="ht-scroll"><table class="ht-t" style="min-width:900px">
            <thead><tr><th>{{ __('payroll.staff_member') }}</th><th>{{ __('payroll.line_title') }}</th><th>{{ __('payroll.given_on') }}</th><th class="num">{{ __('payroll.amount') }}</th><th class="num">{{ __('payroll.instalment') }}</th><th class="num">{{ __('payroll.repaid') }}</th><th class="num">{{ __('payroll.balance') }}</th><th>{{ __('payroll.status') }}</th><th></th></tr></thead>
            <tbody>
            @foreach ($loans as $l)
                @php $cleared = $l->isCleared(); @endphp
                <tr data-loan="{{ $l->id }}">
                    <td><strong>{{ trim(optional($l->user)->first_name.' '.optional($l->user)->last_name) }}</strong></td>
                    <td>{{ $l->title }} <div class="ht-muted ht-small">{{ __('payroll.kind_'.$l->kind) }} · {{ __('payroll.from_month', ['month' => \App\Payroll\PayrollBook::monthLabel($l->start_month)]) }}</div></td>
                    <td>{{ $l->given_on->format('d/m/Y') }}</td>
                    <td class="num">{{ $fmt($l->amount) }}</td>
                    <td class="num">{{ $fmt($l->instalment) }}</td>
                    <td class="num">{{ $fmt($l->repaid()) }}</td>
                    <td class="num"><strong>{{ $fmt($l->balance()) }}</strong></td>
                    <td>@if ($l->status === 'cancelled')<span class="ht-pill ht-p-bad">{{ __('payroll.loan_st_cancelled') }}</span>@elseif ($cleared)<span class="ht-pill ht-p-free">{{ __('payroll.loan_st_cleared') }}</span>@else<span class="ht-pill ht-p-occ">{{ __('payroll.loan_st_active') }}</span>@endif</td>
                    <td style="text-align:right">
                        @if ($can('payroll-loans') && $l->status === 'active' && ! $l->lines()->exists())
                            <form method="post" action="{{ action([$P, 'cancelLoan'], [$l->id]) }}" data-review="{{ __('payroll.loan_cancel_check') }}" data-review-label="{{ __('payroll.loan_cancel') }}" data-review-class="btn-danger">@csrf<button class="ht-btn sm danger" type="submit">{{ __('payroll.loan_cancel') }}</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </div>
</section>
@include('ledger.partials.review_modal')
@endsection

@section('javascript')
@include('ledger.partials.review_script')
<script>
// the staff member's default payment account, picked for them
(function () {
    var u = document.getElementById('ln_user'), a = document.getElementById('ln_acc');
    if (!u || !a) { return; }
    u.addEventListener('change', function () {
        var acc = u.options[u.selectedIndex] && u.options[u.selectedIndex].getAttribute('data-account');
        if (acc && !a.value) { a.value = acc; }
    });
})();
</script>
@endsection
