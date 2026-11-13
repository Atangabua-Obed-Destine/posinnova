@extends('layouts.app')
@section('title', __('lang_v1.enter_results'))

@php
    $lr = $lab_request;
    $pd = optional($lr->patient)->patientDetails;
    $flags = ['' => '-', 'normal' => __('lang_v1.lab_flag_normal'), 'high' => __('lang_v1.lab_flag_high'), 'low' => __('lang_v1.lab_flag_low'), 'critical' => __('lang_v1.lab_flag_critical')];
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.enter_results')
        <small class="tw-text-gray-600">{{ $lr->ref_no }}</small>
    </h1>
</section>

<section class="content">
    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
        <span class="tw-text-lg tw-font-semibold">{{ optional($lr->patient)->name }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.file_number'):</strong> {{ optional($pd)->file_number ?: '-' }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.sex'):</strong> {{ optional($pd)->sex ?: '-' }}</span>
    </div>

    {!! Form::open(['url' => action([\App\Http\Controllers\LabRequestController::class, 'saveResult'], [$lr->id]), 'method' => 'post', 'id' => 'lab_result_form']) !!}

    {{-- PosInnova: is the test paid? an unpaid one asks before results go in --}}
    @include('clinic.partials.request_payment', ['pay' => $pay, 'req' => $lr, 'ack' => true])

    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.test_results')])
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th style="width:22%;">@lang('lang_v1.test_name')</th>
                        <th style="width:14%;">@lang('lang_v1.normal_range')</th>
                        <th style="width:8%;">@lang('lang_v1.unit')</th>
                        <th style="width:22%;">@lang('lang_v1.result')</th>
                        <th style="width:14%;">@lang('lang_v1.flag')</th>
                        <th style="width:20%;">@lang('lang_v1.notes')</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lr->lines as $line)
                        <tr data-line="{{ $line->id }}" data-test="{{ $line->test_name }}" data-unit="{{ $line->unit }}">
                            <td><strong>{{ $line->test_name }}</strong></td>
                            <td>{{ $line->normal_range ?: '-' }}</td>
                            <td>{{ $line->unit ?: '-' }}</td>
                            <td>{!! Form::text("results[{$line->id}][value]", $line->result_value, ['class' => 'form-control input-sm', 'aria-label' => __('lang_v1.result')]) !!}</td>
                            <td>{!! Form::select("results[{$line->id}][flag]", $flags, $line->result_flag, ['class' => 'form-control input-sm', 'aria-label' => __('lang_v1.flag')]) !!}</td>
                            <td>{!! Form::text("results[{$line->id}][notes]", $line->result_notes, ['class' => 'form-control input-sm', 'aria-label' => __('lang_v1.notes')]) !!}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12 text-center" style="margin-bottom:20px;">
            <button type="submit" name="action" value="save" class="tw-dw-btn tw-dw-btn-outline tw-font-bold">
                <i class="fas fa-save"></i> @lang('lang_v1.save_results')
            </button>
            @can('lab_result.validate')
                <button type="submit" name="action" value="validate" class="tw-dw-btn tw-bg-green-600 tw-text-white tw-border-none tw-font-bold">
                    <i class="fas fa-check-double"></i> @lang('lang_v1.save_and_validate')
                </button>
            @endcan
            <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lr->id]) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </div>

    {!! Form::close() !!}
</section>

@php
    $lab_js_labels = [
        'enter_one' => __('clinic.lab_enter_one'),
        'fill_all' => __('clinic.lab_fill_all'),
        'patient' => __('lang_v1.patient'),
        'name' => optional($lr->patient)->name,
        'partial' => __('clinic.lab_partial_note'),
    ];
@endphp
<script>
    // PosInnova: nothing is saved without a result, validating needs every test filled in, and the
    // results are checked in the "check before recording" dialog before they are written.
    (function () {
        var form = document.getElementById('lab_result_form');
        var L = @json($lab_js_labels);
        document.addEventListener('submit', function (e) {
            if (e.target !== form) { return; }
            if (form.getAttribute('data-rc-ok') === '1') { form.removeAttribute('data-rc-ok'); return; }
            var btn = e.submitter && e.submitter.form === form ? e.submitter : null;
            var validate = btn && btn.value === 'validate';
            var rows = [[L.patient, L.name]], missing = [], filled = 0;
            [].slice.call(form.querySelectorAll('tr[data-line]')).forEach(function (tr) {
                var input = tr.querySelector('input[name$="[value]"]'), flag = tr.querySelector('select[name$="[flag]"]');
                var v = input.value.trim();
                input.style.borderColor = '';
                if (v) {
                    filled++;
                    var unit = tr.getAttribute('data-unit') || '';
                    var f = flag && flag.value ? ' (' + flag.options[flag.selectedIndex].text + ')' : '';
                    rows.push([tr.getAttribute('data-test'), v + (unit ? ' ' + unit : '') + f]);
                } else {
                    missing.push(input);
                }
            });
            var stop = function (msg, inputs) {
                e.preventDefault();
                e.stopImmediatePropagation();
                inputs.forEach(function (i) { i.style.borderColor = '#dc2626'; });
                if (inputs[0]) { inputs[0].focus(); }
                if (window.toastr) { toastr.error(msg); }
            };
            if (!filled) { stop(L.enter_one, missing); return; }
            if (validate && missing.length) { stop(L.fill_all.replace(':n', missing.length), missing); return; }
            if (!window.PosConfirm) { return; }
            e.preventDefault();
            e.stopImmediatePropagation();
            PosConfirm.ask({ kind: validate ? 'lab_validate' : 'lab_result', rows: rows, note: missing.length ? L.partial.replace(':n', missing.length) : '' }, function () {
                form.setAttribute('data-rc-ok', '1');
                if (form.requestSubmit) { form.requestSubmit(btn || undefined); } else { form.submit(); }
            });
        }, true);
    })();
</script>
@endsection
