@extends('layouts.app')
@section('title', __('payroll.menu_staff'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PayrollController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
@endphp
<section class="content ht" id="py_staff">
    <div class="ht-head"><div><h1>{{ __('payroll.menu_staff') }}</h1><p>{{ __('payroll.staff_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'index']) }}">{{ __('payroll.menu_payrolls') }}</a></div></div>
    @foreach ($users as $u)
        @php $s = $pay->get($u->id); @endphp
        <details class="ht-card" data-user="{{ $u->id }}" style="padding:12px 18px" {{ $s ? '' : '' }}>
            <summary style="cursor:pointer;display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;list-style:none">
                <span><strong>{{ trim($u->first_name.' '.$u->last_name) }}</strong> <span class="ht-muted ht-small">{{ $u->username }}</span></span>
                <span>@if ($s && $s->is_active)<span class="ht-pill ht-p-free">{{ __('payroll.on_payroll') }}</span> @if ($s->pay_type === 'hourly'){{ $fmt($s->hourly_rate) }} {{ __('payroll.per_hour') }}@else{{ $fmt($s->basic_salary) }} {{ __('payroll.per_month') }}@endif @else<span class="ht-pill ht-p-checked_out">{{ __('payroll.not_on_payroll') }}</span>@endif</span>
            </summary>
            <form method="post" action="{{ action([$P, 'saveStaff'], [$u->id]) }}" style="margin-top:12px">@csrf
                <div class="ht-fields">
                    <div class="ht-f"><label for="pt_{{ $u->id }}">{{ __('payroll.pay_type') }}</label><select id="pt_{{ $u->id }}" name="pay_type" class="ht-in py-pay-type"><option value="monthly">{{ __('payroll.pay_monthly') }}</option><option value="hourly" {{ optional($s)->pay_type === 'hourly' ? 'selected' : '' }}>{{ __('payroll.pay_hourly') }}</option></select></div>
                    <div class="ht-f py-hourly" @if (optional($s)->pay_type !== 'hourly') hidden @endif><label for="hr_{{ $u->id }}">{{ __('payroll.hourly_rate') }} *</label><input id="hr_{{ $u->id }}" name="hourly_rate" class="ht-in" inputmode="decimal" value="{{ $s && (float) $s->hourly_rate ? (float) $s->hourly_rate : '' }}"><span class="ht-muted ht-small">{{ __('payroll.hourly_hint') }}</span></div>
                    <div class="ht-f py-monthly" @if (optional($s)->pay_type === 'hourly') hidden @endif><label for="bs_{{ $u->id }}">{{ __('payroll.basic_salary') }} *</label><input id="bs_{{ $u->id }}" name="basic_salary" class="ht-in" inputmode="decimal" value="{{ $s ? (float) $s->basic_salary : '' }}"><span class="ht-muted ht-small">{{ __('payroll.basic_hint') }}</span></div>
                    <div class="ht-f"><label for="jt_{{ $u->id }}">{{ __('payroll.job_title') }}</label><input id="jt_{{ $u->id }}" name="job_title" class="ht-in" value="{{ optional($s)->job_title }}"></div>
                    <div class="ht-f"><label for="cn_{{ $u->id }}">{{ __('payroll.cnps_no') }}</label><input id="cn_{{ $u->id }}" name="cnps_no" class="ht-in" value="{{ optional($s)->cnps_no }}"></div>
                    <div class="ht-f"><label for="tx_{{ $u->id }}">{{ __('payroll.tax_no') }}</label><input id="tx_{{ $u->id }}" name="tax_no" class="ht-in" value="{{ optional($s)->tax_no }}"></div>
                    <div class="ht-f"><label for="jd_{{ $u->id }}">{{ __('payroll.joining_date') }}</label><input id="jd_{{ $u->id }}" type="date" name="joining_date" class="ht-in" value="{{ optional(optional($s)->joining_date)->toDateString() }}"></div>
                    <div class="ht-f"><label for="ed_{{ $u->id }}">{{ __('payroll.ending_date') }}</label><input id="ed_{{ $u->id }}" type="date" name="ending_date" class="ht-in" value="{{ optional(optional($s)->ending_date)->toDateString() }}"></div>
                    <div class="ht-f"><label for="pa_{{ $u->id }}">{{ __('payroll.default_account') }}</label><select id="pa_{{ $u->id }}" name="payment_account_id" class="ht-in"><option value="">—</option>@foreach ($accounts as $id => $n)<option value="{{ $id }}" {{ (int) optional($s)->payment_account_id === (int) $id ? 'selected' : '' }}>{{ $n }}</option>@endforeach</select><span class="ht-muted ht-small">{{ __('payroll.default_account_hint') }}</span></div>
                    <div class="ht-f"><label for="bn_{{ $u->id }}">{{ __('payroll.bank_name') }}</label><input id="bn_{{ $u->id }}" name="bank_name" class="ht-in" value="{{ optional($s)->bank_name }}"></div>
                    <div class="ht-f"><label for="ba_{{ $u->id }}">{{ __('payroll.bank_account') }}</label><input id="ba_{{ $u->id }}" name="bank_account_no" class="ht-in" value="{{ optional($s)->bank_account_no }}"></div>
                </div>
                <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="is_active" value="1" {{ ! $s || $s->is_active ? 'checked' : '' }} style="width:20px;height:20px"> {{ __('payroll.on_payroll_opt') }}</label>
                <button class="ht-btn pri" type="submit" style="margin-top:8px">{{ __('payroll.save') }}</button>
            </form>
        </details>
    @endforeach
</section>
@endsection

@section('javascript')
<script>
// monthly staff have a salary, hourly staff a rate (their hours are typed on each payroll)
document.querySelectorAll('.py-pay-type').forEach(function (sel) {
    var form = sel.closest('form');
    var show = function () {
        form.querySelector('.py-hourly').hidden = sel.value !== 'hourly';
        form.querySelector('.py-monthly').hidden = sel.value === 'hourly';
    };
    sel.addEventListener('change', show);
});
</script>
@endsection
