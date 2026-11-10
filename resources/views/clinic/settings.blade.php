@extends('layouts.app')
@section('title', __('clinic.clinic_settings'))

@php
    $s = $settings;
    $fmt = function ($n) { return app(\App\Utils\Util::class)->num_f((float) $n); };
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.clinic_settings')
        <small class="tw-text-gray-600">@lang('clinic.clinic_settings_help')</small>
    </h1>
</section>

<section class="content">
    {!! Form::open(['url' => action([\App\Http\Controllers\ClinicSettingsController::class, 'update']), 'method' => 'post', 'id' => 'clinic_settings_form']) !!}

    <div class="row">
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.reception')])
                <div class="form-group">
                    {!! Form::label('hospital_file_prefix', __('clinic.file_number_prefix') . ':*') !!}
                    {!! Form::text('hospital_file_prefix', $business->hospital_file_prefix ?: 'PMI', ['class' => 'form-control', 'required', 'maxlength' => 20, 'pattern' => '[A-Za-z0-9]+', 'style' => 'max-width:220px; text-transform:uppercase;']) !!}
                    <p class="help-block">@lang('clinic.file_number_prefix_help', ['example' => $next_file_number])</p>
                    {{-- PosInnova: "PMI" is the column's default for every business; flag it where it is not this clinic's own --}}
                    @if (strtoupper((string) $business->hospital_file_prefix) === 'PMI' && stripos((string) session('business.name'), 'PMI') !== 0)
                        <div class="callout callout-warning" style="margin-bottom:0;" id="prefix_default_warning">@lang('clinic.file_number_prefix_default')</div>
                    @endif
                </div>
                <div class="form-group">
                    {!! Form::label('consultation_fee', __('clinic.consultation_fee_default') . ':') !!}
                    {!! Form::text('consultation_fee', $fmt($business->consultation_fee), ['class' => 'form-control input_number', 'style' => 'max-width:220px;']) !!}
                </div>
                <div class="form-group">
                    <label>@lang('clinic.when_patients_pay'):</label>
                    <div class="radio">
                        <label>{!! Form::radio('pay_first', 0, ! $s['pay_first']) !!} <strong>@lang('clinic.pay_after')</strong></label>
                        <p class="help-block" style="margin-left:20px;">@lang('clinic.pay_after_help')</p>
                    </div>
                    <div class="radio">
                        <label>{!! Form::radio('pay_first', 1, $s['pay_first']) !!} <strong>@lang('clinic.pay_before')</strong></label>
                        <p class="help-block" style="margin-left:20px;">@lang('clinic.pay_before_help')</p>
                    </div>
                </div>
                @if (! empty($admins))
                    <div class="form-group">
                        <label>@lang('clinic.admin_doctors'):</label>
                        <p class="help-block" style="margin-top:0;">@lang('clinic.admin_doctors_help')</p>
                        @foreach ($admins as $admin_id => $admin_name)
                            <div class="checkbox" style="margin-top:0;">
                                <label>{!! Form::checkbox('admin_doctors[]', $admin_id, in_array($admin_id, (array) ($s['admin_doctors'] ?? []))) !!} {{ $admin_name }}</label>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endcomponent

            @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.hospital_sections')])
                <p class="help-block" style="margin-top:0;">@lang('clinic.hospital_sections_help')</p>
                @foreach (\App\Utils\ClinicSettings::SECTIONS as $sec)
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('sections[' . $sec . ']', 1, ! empty($s['sections'][$sec])) !!} @lang('clinic.section_' . $sec)
                        </label>
                    </div>
                @endforeach
                @if (! empty($s['sections']['morgue']))
                    <div class="form-group" style="margin-top:10px;">
                        {!! Form::label('morgue_daily_fee', __('clinic.morgue_daily_fee') . ':') !!}
                        {!! Form::text('morgue_daily_fee', $fmt($business->morgue_daily_fee), ['class' => 'form-control input_number', 'style' => 'max-width:220px;']) !!}
                    </div>
                @endif
            @endcomponent
        </div>

        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.monthly_plans')])
                <div class="form-group">
                    <label>@lang('clinic.bill_each_period'):</label>
                    <div class="radio"><label>{!! Form::radio('plan_billing_anchor', 'start', $s['plan_billing_anchor'] === 'start') !!} @lang('clinic.anchor_start')</label></div>
                    <div class="radio"><label>{!! Form::radio('plan_billing_anchor', 'fixed', $s['plan_billing_anchor'] === 'fixed') !!} @lang('clinic.anchor_fixed')</label></div>
                </div>
                <div class="form-group">
                    {!! Form::label('plan_fixed_day', __('clinic.fixed_day') . ':') !!}
                    {!! Form::number('plan_fixed_day', $s['plan_fixed_day'], ['class' => 'form-control', 'min' => 1, 'max' => 28, 'style' => 'max-width:120px;']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('plan_grace_days', __('clinic.grace_days') . ':') !!}
                    {!! Form::number('plan_grace_days', $s['plan_grace_days'], ['class' => 'form-control', 'min' => 0, 'max' => 60, 'style' => 'max-width:120px;']) !!}
                    <p class="help-block">@lang('clinic.grace_days_help')</p>
                </div>
                <div class="form-group">
                    {!! Form::label('plan_unpaid_rule', __('clinic.unpaid_rule') . ':') !!}
                    {!! Form::select('plan_unpaid_rule', ['charge' => __('clinic.unpaid_charge'), 'block' => __('clinic.unpaid_block'), 'cover' => __('clinic.unpaid_cover')], $s['plan_unpaid_rule'], ['class' => 'form-control']) !!}
                </div>
                <div class="checkbox">
                    <label>{!! Form::checkbox('insurance_covers_plans', 1, $s['insurance_covers_plans']) !!} @lang('clinic.insurance_covers_plans')</label>
                    <p class="help-block">@lang('clinic.insurance_covers_plans_help')</p>
                </div>
            @endcomponent

            @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.appt_menu')])
                <div class="checkbox">
                    <label>{!! Form::checkbox('appointments_enabled', 1, $s['appointments_enabled']) !!} @lang('clinic.appt_setting_enabled')</label>
                </div>
                <div class="checkbox">
                    <label>{!! Form::checkbox('appointments_portal', 1, $s['appointments_portal']) !!} @lang('clinic.appt_setting_portal')</label>
                </div>
                <div class="checkbox">
                    <label>{!! Form::checkbox('appointments_booked_first', 1, $s['appointments_booked_first']) !!} @lang('clinic.appt_setting_booked_first')</label>
                </div>
                <div class="row">
                    <div class="col-xs-6">
                        <div class="form-group">
                            {!! Form::label('appointments_days_ahead', __('clinic.appt_setting_days_ahead') . ':') !!}
                            {!! Form::number('appointments_days_ahead', $s['appointments_days_ahead'], ['class' => 'form-control', 'min' => 1, 'max' => 180]) !!}
                        </div>
                    </div>
                    <div class="col-xs-6">
                        <div class="form-group">
                            {!! Form::label('appointments_min_notice_hours', __('clinic.appt_setting_notice') . ':') !!}
                            {!! Form::number('appointments_min_notice_hours', $s['appointments_min_notice_hours'], ['class' => 'form-control', 'min' => 0, 'max' => 72]) !!}
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    {!! Form::label('whatsapp_country_code', __('clinic.appt_setting_cc') . ':') !!}
                    {!! Form::text('whatsapp_country_code', $s['whatsapp_country_code'], ['class' => 'form-control', 'style' => 'max-width:120px;', 'maxlength' => 5]) !!}
                    <p class="help-block">@lang('clinic.appt_setting_cc_help')</p>
                </div>
            @endcomponent

            @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.patient_portal')])
                <div class="checkbox">
                    <label>{!! Form::checkbox('portal_enabled', 1, $s['portal_enabled']) !!} @lang('clinic.portal_enabled')</label>
                </div>
                <div class="form-group">
                    {!! Form::label('portal_idle_minutes', __('clinic.portal_idle_minutes') . ':') !!}
                    {!! Form::number('portal_idle_minutes', $s['portal_idle_minutes'], ['class' => 'form-control', 'min' => 5, 'max' => 240, 'style' => 'max-width:120px;']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('portal_consultation_detail', __('clinic.portal_consultation_detail') . ':') !!}
                    {!! Form::select('portal_consultation_detail', ['diagnosis' => __('clinic.detail_diagnosis'), 'full' => __('clinic.detail_full')], $s['portal_consultation_detail'], ['class' => 'form-control']) !!}
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12 text-center">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white">@lang('messages.save')</button>
        </div>
    </div>
    {!! Form::close() !!}
</section>
@endsection
