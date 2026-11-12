@extends('layouts.app')
@section('title', __('clinic.vit_title'))

@php
    $V = \App\Http\Controllers\VisitController::class;
    $pd = optional($visit->patient)->patientDetails;
    // 72.50 -> 72.5, 120 stays 120 (only zeros after a decimal point go)
    $num = fn ($v) => $v === null ? null : (str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v);
    $val = fn ($f) => old($f, $num($visit->{$f}));
    $back = [
        'visits' => action([$V, 'index']),
        'home' => action([\App\Http\Controllers\HomeController::class, 'index']),
        'account' => action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$visit->contact_id]),
    ][$return] ?? action([$V, 'show'], [$visit->id]);
@endphp

@section('css')
<style>
    .vt-head { display: flex; gap: 14px; align-items: center; flex-wrap: wrap; }
    .vt-no { min-width: 48px; height: 48px; padding: 0 8px; border-radius: 12px; background: #1d4ed8; color: #fff; font-weight: 800; font-size: 20px; display: flex; align-items: center; justify-content: center; }
    .vt-name { font-size: 18px; font-weight: 700; color: #0f172a; }
    .vt-meta { color: #64748b; font-size: 13px; }
    .vt-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 14px; }
    .vt-field label { display: block; font-weight: 600; color: #334155; margin-bottom: 4px; }
    .vt-field .form-control { height: 46px; font-size: 18px; font-weight: 600; }
    .vt-field .vt-unit { color: #94a3b8; font-weight: 400; font-size: 12px; }
    .vt-last { color: #64748b; font-size: 12px; margin-top: 3px; }
    .vt-bp { display: flex; align-items: center; gap: 6px; }
    .vt-bp .form-control { min-width: 0; }
    .vt-bmi { font-size: 18px; font-weight: 700; color: #0f172a; padding-top: 10px; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.vit_title')
        <small class="tw-text-gray-600">@lang('clinic.vit_sub')</small>
    </h1>
</section>

<section class="content">
    @component('components.widget', ['class' => 'box-solid'])
        <div class="vt-head">
            @if ($visit->queue_no)<div class="vt-no">{{ $visit->queue_no }}</div>@endif
            <div>
                <div class="vt-name">{{ optional($visit->patient)->name }}</div>
                <div class="vt-meta">{{ implode(' · ', array_filter([optional($pd)->file_number, $visit->doctor ? trim($visit->doctor->user_full_name) : null, $visit->ref_no])) }}</div>
            </div>
        </div>
        @if ($visit->hasVitals())
            <p class="text-muted" style="margin:10px 0 0;"><i class="fas fa-info-circle"></i>
                {{ __('clinic.vit_taken_by', ['time' => $visit->vitals_taken_at->format('H:i'), 'name' => trim(optional($visit->vitalsTakenBy)->user_full_name)]) }} @lang('clinic.vit_correct_hint')</p>
        @endif
    @endcomponent

    <form method="POST" action="{{ action([$V, 'saveVitals'], [$visit->id]) }}" id="vitals_form">
        @csrf
        <input type="hidden" name="return" value="{{ $return }}">
        @component('components.widget', ['class' => 'box-primary'])
            <div class="vt-grid">
                <div class="vt-field">
                    <label for="vitals_weight">@lang('clinic.vit_weight') <span class="vt-unit">kg</span></label>
                    <input type="number" name="vitals_weight" id="vitals_weight" class="form-control js-bmi" step="0.1" min="0.5" max="400" inputmode="decimal" value="{{ $val('vitals_weight') }}" autofocus>
                    @if ($last && $last->vitals_weight)<div class="vt-last">{{ __('clinic.vit_last', ['v' => $num($last->vitals_weight)]) }}</div>@endif
                </div>
                <div class="vt-field">
                    <label for="vitals_height">@lang('clinic.vit_height') <span class="vt-unit">cm</span></label>
                    <input type="number" name="vitals_height" id="vitals_height" class="form-control js-bmi" step="0.1" min="20" max="260" inputmode="decimal" value="{{ $val('vitals_height') }}">
                    @if ($last && $last->vitals_height)<div class="vt-last">{{ __('clinic.vit_last', ['v' => $num($last->vitals_height)]) }}</div>@endif
                </div>
                <div class="vt-field">
                    <label>@lang('lang_v1.bmi')</label>
                    <div class="vt-bmi" id="vt_bmi">–</div>
                </div>
                <div class="vt-field" style="grid-column: span 2; min-width: 0;">
                    <label for="vitals_bp_systolic">@lang('lang_v1.blood_pressure') <span class="vt-unit">mmHg</span></label>
                    <div class="vt-bp">
                        <input type="number" name="vitals_bp_systolic" id="vitals_bp_systolic" class="form-control" min="40" max="300" inputmode="numeric" placeholder="@lang('lang_v1.systolic')" value="{{ $val('vitals_bp_systolic') }}" aria-label="@lang('lang_v1.systolic')">
                        <span>/</span>
                        <input type="number" name="vitals_bp_diastolic" id="vitals_bp_diastolic" class="form-control" min="20" max="200" inputmode="numeric" placeholder="@lang('lang_v1.diastolic')" value="{{ $val('vitals_bp_diastolic') }}" aria-label="@lang('lang_v1.diastolic')">
                    </div>
                    @if ($last && $last->vitals_bp_systolic)<div class="vt-last">{{ __('clinic.vit_last', ['v' => $last->vitals_bp_systolic.'/'.$last->vitals_bp_diastolic]) }}</div>@endif
                </div>
                <div class="vt-field">
                    <label for="vitals_temperature">@lang('clinic.vit_temperature') <span class="vt-unit">°C</span></label>
                    <input type="number" name="vitals_temperature" id="vitals_temperature" class="form-control" step="0.1" min="30" max="45" inputmode="decimal" value="{{ $val('vitals_temperature') }}">
                </div>
                <div class="vt-field">
                    <label for="vitals_pulse">@lang('clinic.vit_pulse') <span class="vt-unit">/min</span></label>
                    <input type="number" name="vitals_pulse" id="vitals_pulse" class="form-control" min="20" max="250" inputmode="numeric" value="{{ $val('vitals_pulse') }}">
                </div>
                <div class="vt-field">
                    <label for="vitals_spo2">SpO₂ <span class="vt-unit">%</span></label>
                    <input type="number" name="vitals_spo2" id="vitals_spo2" class="form-control" min="50" max="100" inputmode="numeric" value="{{ $val('vitals_spo2') }}">
                </div>
            </div>
            <div class="form-group" style="margin-top:14px; margin-bottom:0;">
                <label for="vitals_note">@lang('clinic.vit_note')</label>
                <input type="text" name="vitals_note" id="vitals_note" class="form-control" maxlength="191" placeholder="@lang('clinic.vit_note_ph')" value="{{ old('vitals_note', $visit->vitals_note) }}">
            </div>
            @if ($last)
                <p class="help-block" style="margin-bottom:0;">{{ __('clinic.vit_last_from', ['date' => @format_date($last->vitals_taken_at)]) }}</p>
            @endif
        @endcomponent

        <div class="text-center" style="margin-bottom:20px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
            <button type="submit" class="tw-dw-btn tw-dw-btn-lg tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold"><i class="fas fa-heartbeat"></i> @lang('clinic.vit_save')</button>
            <a href="{{ $back }}" class="tw-dw-btn tw-dw-btn-lg tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        function bmi() {
            var w = parseFloat($('#vitals_weight').val()), h = parseFloat($('#vitals_height').val()) / 100;
            $('#vt_bmi').text(w > 0 && h > 0 ? (w / (h * h)).toFixed(1) : '–');
        }
        $('.js-bmi').on('input', bmi);
        bmi();
    });
</script>
@endsection
