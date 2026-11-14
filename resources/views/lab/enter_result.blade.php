@extends('layouts.app')
@section('title', __('lang_v1.enter_results'))

@php
    $lr = $lab_request;
    $pd = optional($lr->patient)->patientDetails;
    $flags = ['' => '-', 'normal' => __('lang_v1.lab_flag_normal'), 'high' => __('lang_v1.lab_flag_high'), 'low' => __('lang_v1.lab_flag_low'), 'critical' => __('lang_v1.lab_flag_critical')];
    // PosInnova: each test's usual answers ("Negative, Positive"), offered as one click
    $result_options = \App\LabTest::whereIn('id', $lr->lines->pluck('lab_test_id')->filter())->pluck('result_options', 'id')
        ->map(fn ($o) => collect(preg_split('/\s*[,;\n]\s*/', (string) $o))->map(fn ($x) => trim($x))->filter()->unique()->values()->all());
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
                        <tr data-line="{{ $line->id }}" data-test="{{ $line->test_name }}" data-unit="{{ $line->unit }}" data-range="{{ $line->normal_range }}">
                            <td><strong>{{ $line->test_name }}</strong></td>
                            <td>{{ $line->normal_range ?: '-' }}</td>
                            <td>{{ $line->unit ?: '-' }}</td>
                            <td>{!! Form::text("results[{$line->id}][value]", $line->result_value, ['class' => 'form-control input-sm lab-value', 'aria-label' => __('lang_v1.result')]) !!}
                                @if (! empty($result_options[$line->lab_test_id]))
                                    <div class="lab-opts" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:4px;">
                                        @foreach ($result_options[$line->lab_test_id] as $opt)
                                            <button type="button" class="btn btn-xs btn-default lab-opt" data-v="{{ $opt }}">{{ $opt }}</button>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                            <td>{!! Form::select("results[{$line->id}][flag]", $flags, $line->result_flag, ['class' => 'form-control input-sm lab-flag', 'aria-label' => __('lang_v1.flag')]) !!}
                                <small class="text-muted lab-flag-auto" style="display:none;">@lang('clinic.lab_flag_suggested')</small></td>
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

    {{-- photos / scans / PDFs (e.g. a printout from an analyser): uploaded as soon as they are chosen --}}
    @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.att_title')])
        @include('clinic.partials.attachments', ['type' => 'lab_request', 'owner' => $lr])
    @endcomponent
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
    // PosInnova: one click puts a usual answer in; a number outside the normal range suggests High / Low
    // (only while the flag was not chosen by hand)
    (function () {
        function bounds(range) {
            var r = String(range || '').replace(/,/g, '.').replace(/\s+/g, ' ').trim(), m;
            if ((m = r.match(/(-?\d+(?:\.\d+)?)\s*(?:-|–|—|to|à|a)\s*(-?\d+(?:\.\d+)?)/i))) { return [parseFloat(m[1]), parseFloat(m[2])]; }
            if ((m = r.match(/(?:<=?|≤|up to|jusqu'à|max\.?)\s*(-?\d+(?:\.\d+)?)/i))) { return [null, parseFloat(m[1])]; }
            if ((m = r.match(/(?:>=?|≥|min\.?)\s*(-?\d+(?:\.\d+)?)/i))) { return [parseFloat(m[1]), null]; }
            return null;
        }
        function suggest(tr) {
            var flag = tr.querySelector('.lab-flag'), hint = tr.querySelector('.lab-flag-auto');
            if (!flag || flag.getAttribute('data-manual') === '1') { return; }
            var b = bounds(tr.getAttribute('data-range'));
            var v = String(tr.querySelector('.lab-value').value || '').replace(',', '.').match(/^\s*(-?\d+(?:\.\d+)?)\s*$/);
            if (!b || !v) {
                if (flag.getAttribute('data-auto') === '1') { flag.value = ''; flag.removeAttribute('data-auto'); hint.style.display = 'none'; }
                return;
            }
            var n = parseFloat(v[1]);
            flag.value = (b[0] !== null && n < b[0]) ? 'low' : ((b[1] !== null && n > b[1]) ? 'high' : 'normal');
            flag.setAttribute('data-auto', '1');
            hint.style.display = '';
        }
        document.addEventListener('input', function (e) {
            if (e.target.classList && e.target.classList.contains('lab-value')) { suggest(e.target.closest('tr')); }
        });
        document.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains('lab-flag') && e.isTrusted) {
                e.target.setAttribute('data-manual', '1');
                e.target.closest('tr').querySelector('.lab-flag-auto').style.display = 'none';
            }
        });
        document.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('.lab-opt');
            if (!b) { return; }
            var tr = b.closest('tr'), input = tr.querySelector('.lab-value');
            input.value = b.getAttribute('data-v');
            input.dispatchEvent(new Event('input', { bubbles: true }));
            [].forEach.call(tr.querySelectorAll('.lab-opt'), function (x) { x.classList.toggle('btn-primary', x === b); x.classList.toggle('btn-default', x !== b); });
        });
    })();

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
