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
    /* a value out of range: said under the field at once, not in the browser's bubble on Save */
    .vt-field .form-control.vt-bad { border: 2px solid #d92d20; background: #fff5f4; box-shadow: 0 0 0 3px rgba(217, 45, 32, .15); }
    .vt-err { display: flex; flex-wrap: wrap; gap: 6px 8px; align-items: center; margin-top: 6px; padding: 8px 10px; border-radius: 8px; background: #fee4e2; color: #912018; font-weight: 600; font-size: 14px; line-height: 1.35; }
    .vt-err[hidden] { display: none; }
    .vt-err .fa { flex: none; }
    .vt-err span { flex: 1 1 120px; }
    .vt-err button { flex: none; min-height: 38px; padding: 6px 12px; border-radius: 8px; border: 0; background: #912018; color: #fff; font-weight: 700; cursor: pointer; }
    .vt-fix { margin: 0 0 12px; padding: 10px 14px; border-radius: 8px; background: #fee4e2; color: #912018; font-weight: 700; text-align: center; }
    .vt-fix[hidden] { display: none; }
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

    <form method="POST" action="{{ action([$V, 'saveVitals'], [$visit->id]) }}" id="vitals_form" novalidate>
        @csrf
        <input type="hidden" name="return" value="{{ $return }}">
        @component('components.widget', ['class' => 'box-primary'])
            <div class="vt-grid">
                <div class="vt-field">
                    <label for="vitals_weight">@lang('clinic.vit_weight') <span class="vt-unit">kg</span></label>
                    <input type="number" name="vitals_weight" id="vitals_weight" class="form-control js-bmi" step="0.1" min="0.5" max="400" inputmode="decimal" value="{{ $val('vitals_weight') }}" autofocus data-label="@lang('clinic.vit_weight')" data-unit="kg" aria-describedby="vitals_weight_err">
                    <div class="vt-err" id="vitals_weight_err" role="alert" @if (! $errors->has('vitals_weight')) hidden @endif><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $errors->first('vitals_weight') }}</span></div>
                    @if ($last && $last->vitals_weight)<div class="vt-last">{{ __('clinic.vit_last', ['v' => $num($last->vitals_weight)]) }}</div>@endif
                </div>
                <div class="vt-field">
                    <label for="vitals_height">@lang('clinic.vit_height') <span class="vt-unit">cm</span></label>
                    <input type="number" name="vitals_height" id="vitals_height" class="form-control js-bmi" step="0.1" min="20" max="260" inputmode="decimal" value="{{ $val('vitals_height') }}" data-label="@lang('clinic.vit_height')" data-unit="cm" aria-describedby="vitals_height_err">
                    <div class="vt-err" id="vitals_height_err" role="alert" @if (! $errors->has('vitals_height')) hidden @endif><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $errors->first('vitals_height') }}</span></div>
                    @if ($last && $last->vitals_height)<div class="vt-last">{{ __('clinic.vit_last', ['v' => $num($last->vitals_height)]) }}</div>@endif
                </div>
                <div class="vt-field">
                    <label>@lang('lang_v1.bmi')</label>
                    <div class="vt-bmi" id="vt_bmi">–</div>
                </div>
                <div class="vt-field" style="grid-column: span 2; min-width: 0;">
                    <label for="vitals_bp_systolic">@lang('lang_v1.blood_pressure') <span class="vt-unit">mmHg</span></label>
                    <div class="vt-bp">
                        <input type="number" name="vitals_bp_systolic" id="vitals_bp_systolic" class="form-control" min="40" max="300" inputmode="numeric" placeholder="@lang('lang_v1.systolic')" value="{{ $val('vitals_bp_systolic') }}" aria-label="@lang('lang_v1.systolic')" data-label="@lang('lang_v1.systolic')" data-unit="mmHg" data-x10="1" aria-describedby="vitals_bp_systolic_err">
                        <span aria-hidden="true">/</span>
                        <input type="number" name="vitals_bp_diastolic" id="vitals_bp_diastolic" class="form-control" min="20" max="200" inputmode="numeric" placeholder="@lang('lang_v1.diastolic')" value="{{ $val('vitals_bp_diastolic') }}" aria-label="@lang('lang_v1.diastolic')" data-label="@lang('lang_v1.diastolic')" data-unit="mmHg" data-x10="1" aria-describedby="vitals_bp_diastolic_err">
                    </div>
                    <div class="vt-err" id="vitals_bp_systolic_err" role="alert" @if (! $errors->has('vitals_bp_systolic')) hidden @endif><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $errors->first('vitals_bp_systolic') }}</span></div>
                    <div class="vt-err" id="vitals_bp_diastolic_err" role="alert" @if (! $errors->has('vitals_bp_diastolic')) hidden @endif><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $errors->first('vitals_bp_diastolic') }}</span></div>
                    @if ($last && $last->vitals_bp_systolic)<div class="vt-last">{{ __('clinic.vit_last', ['v' => $last->vitals_bp_systolic.'/'.$last->vitals_bp_diastolic]) }}</div>@endif
                </div>
                <div class="vt-field">
                    <label for="vitals_temperature">@lang('clinic.vit_temperature') <span class="vt-unit">°C</span></label>
                    <input type="number" name="vitals_temperature" id="vitals_temperature" class="form-control" step="0.1" min="30" max="45" inputmode="decimal" value="{{ $val('vitals_temperature') }}" data-label="@lang('clinic.vit_temperature')" data-unit="°C" aria-describedby="vitals_temperature_err">
                    <div class="vt-err" id="vitals_temperature_err" role="alert" @if (! $errors->has('vitals_temperature')) hidden @endif><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $errors->first('vitals_temperature') }}</span></div>
                </div>
                <div class="vt-field">
                    <label for="vitals_pulse">@lang('clinic.vit_pulse') <span class="vt-unit">/min</span></label>
                    <input type="number" name="vitals_pulse" id="vitals_pulse" class="form-control" min="20" max="250" inputmode="numeric" value="{{ $val('vitals_pulse') }}" data-label="@lang('clinic.vit_pulse')" data-unit="/min" aria-describedby="vitals_pulse_err">
                    <div class="vt-err" id="vitals_pulse_err" role="alert" @if (! $errors->has('vitals_pulse')) hidden @endif><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $errors->first('vitals_pulse') }}</span></div>
                </div>
                <div class="vt-field">
                    <label for="vitals_spo2">SpO₂ <span class="vt-unit">%</span></label>
                    <input type="number" name="vitals_spo2" id="vitals_spo2" class="form-control" min="50" max="100" inputmode="numeric" value="{{ $val('vitals_spo2') }}" data-label="SpO₂" data-unit="%" aria-describedby="vitals_spo2_err">
                    <div class="vt-err" id="vitals_spo2_err" role="alert" @if (! $errors->has('vitals_spo2')) hidden @endif><i class="fa fa-exclamation-triangle" aria-hidden="true"></i><span>{{ $errors->first('vitals_spo2') }}</span></div>
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

        <div class="vt-fix" id="vt_fix" role="alert" hidden><i class="fa fa-exclamation-triangle" aria-hidden="true"></i> @lang('clinic.vit_err_fix')</div>
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

        // Values out of range are said under the field while typing: too high at once, too low after a
        // short pause or on leaving the field (12 may be on its way to 120). Save is refused until fixed.
        var T = {!! json_encode(['range' => __('clinic.vit_err_range'), 'number' => __('clinic.vit_err_number'), 'whole' => __('clinic.vit_err_whole'), 'pair' => __('clinic.vit_err_pair'), 'mean' => __('clinic.vit_err_mean')]) !!};
        var form = document.getElementById('vitals_form');
        var fields = [].slice.call(form.querySelectorAll('input[type=number]'));
        var timers = {};
        function say(text, o) { return text.replace(/:(\w+)/g, function (m, k) { return o[k] !== undefined ? o[k] : m; }); }
        function problem(el, final) {
            var o = { field: el.getAttribute('data-label'), min: el.min, max: el.max, unit: el.getAttribute('data-unit') };
            if (el.validity && el.validity.badInput) { return { text: say(T.number, o) }; }
            var v = el.value.trim();
            if (v === '') { return null; }
            var n = parseFloat(v), min = parseFloat(el.min), max = parseFloat(el.max);
            if (n > max) { return { text: say(T.range, o) }; }
            if (n < min) {
                if (!final) { return null; }
                var guess = n * 10;
                return { text: say(T.range, o), guess: el.getAttribute('data-x10') && guess >= min && guess <= max ? guess : null };
            }
            if (!el.step && n % 1 !== 0) { return { text: say(T.whole, o) }; }
            return null;
        }
        function show(el, p) {
            var box = document.getElementById(el.id + '_err');
            el.classList.toggle('vt-bad', !!p);
            el.setAttribute('aria-invalid', p ? 'true' : 'false');
            box.hidden = !p;
            if (!p) { return; }
            box.querySelector('span').textContent = p.text;
            var old = box.querySelector('button');
            if (old) { old.remove(); }
            if (p.guess) {
                var b = document.createElement('button');
                b.type = 'button';
                b.textContent = say(T.mean, { v: p.guess });
                b.addEventListener('click', function () { el.value = p.guess; check(el, true); el.focus(); });
                box.appendChild(b);
            }
        }
        function check(el, final) {
            var p = problem(el, final);
            // while typing, a message already shown stays until the value is right
            if (!p && !final && el.classList.contains('vt-bad')) { p = problem(el, true); }
            show(el, p);
            if (!form.querySelector('.vt-bad')) { document.getElementById('vt_fix').hidden = true; }
            return !p;
        }
        fields.forEach(function (el) {
            el.addEventListener('input', function () {
                clearTimeout(timers[el.id]);
                check(el, false);
                timers[el.id] = setTimeout(function () { check(el, true); }, 900);
            });
            el.addEventListener('blur', function () { clearTimeout(timers[el.id]); check(el, true); });
            if (el.classList.contains('vt-bad') === false && !document.getElementById(el.id + '_err').hidden) { el.classList.add('vt-bad'); }
        });
        form.addEventListener('submit', function (e) {
            var ok = true;
            fields.forEach(function (el) { clearTimeout(timers[el.id]); if (!check(el, true)) { ok = false; } });
            var sys = document.getElementById('vitals_bp_systolic'), dia = document.getElementById('vitals_bp_diastolic');
            if (ok && (sys.value.trim() === '') !== (dia.value.trim() === '')) {
                show(sys.value.trim() === '' ? sys : dia, { text: T.pair });
                ok = false;
            }
            if (!ok) {
                e.preventDefault();
                e.stopPropagation(); // common.js disables the Save button on every submit that reaches the document
                document.getElementById('vt_fix').hidden = false;
                var first = form.querySelector('.vt-bad');
                first.focus({ preventScroll: true });
                first.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    });
</script>
@endsection
