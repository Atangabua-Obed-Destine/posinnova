@extends('layouts.app')
@section('title', __('lang_v1.enter_report'))

@php
    $ir = $imaging_request;
    $pd = optional($ir->patient)->patientDetails;
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.enter_report')
        <small class="tw-text-gray-600">{{ $ir->ref_no }} · {{ $ir->study_name }}</small>
    </h1>
</section>

<section class="content">
    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 tw-mb-4">
        <span class="tw-text-lg tw-font-semibold">{{ optional($ir->patient)->name }}</span>
        <span class="tw-text-sm tw-text-gray-600 tw-ml-3"><strong>@lang('lang_v1.file_number'):</strong> {{ optional($pd)->file_number ?: '-' }}</span>
        @if($ir->clinical_notes)
            <p class="tw-mt-2 tw-text-sm tw-text-gray-600"><strong>@lang('lang_v1.clinical_notes'):</strong> {{ $ir->clinical_notes }}</p>
        @endif
    </div>

    {!! Form::open(['url' => action([\App\Http\Controllers\ImagingController::class, 'saveReport'], [$ir->id]), 'method' => 'post', 'id' => 'imaging_report_form']) !!}

    {{-- PosInnova: is the study paid? an unpaid one asks before the report goes in --}}
    @include('clinic.partials.request_payment', ['pay' => $pay, 'req' => $ir, 'ack' => true])

    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.radiologist_report')])
        @php $img_template = optional($ir->catalogue)->report_template; @endphp
        @if ($img_template)
            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" id="insert_normal_report" style="margin-bottom:8px;"
                data-template="{{ $img_template }}"><i class="fas fa-file-medical"></i> @lang('clinic.img_insert_template')</button>
        @endif
        <div class="form-group">
            {!! Form::label('report', __('lang_v1.report') . ':*') !!}
            {!! Form::textarea('report', $ir->report, ['class' => 'form-control', 'rows' => 10, 'required', 'placeholder' => __('lang_v1.report_findings_placeholder')]) !!}
        </div>
    @endcomponent

    <div class="row">
        <div class="col-md-12 text-center" style="margin-bottom:20px;">
            <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">
                <i class="fas fa-check"></i> @lang('lang_v1.save_report')
            </button>
            <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'show'], [$ir->id]) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </div>

    {!! Form::close() !!}

    {{-- photos / scans / PDFs: uploaded as soon as they are chosen --}}
    @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.att_title')])
        @include('clinic.partials.attachments', ['type' => 'imaging_request', 'owner' => $ir])
    @endcomponent
</section>

@php
    $img_js_labels = ['patient' => __('lang_v1.patient'), 'name' => optional($ir->patient)->name, 'study' => __('lang_v1.study'), 'study_name' => $ir->study_name, 'report' => __('lang_v1.report')];
@endphp
<script>
    // PosInnova: the study's normal report in one click (added below what is already written)
    (function () {
        var b = document.getElementById('insert_normal_report');
        if (!b) { return; }
        b.addEventListener('click', function () {
            var ta = document.querySelector('#imaging_report_form textarea[name=report]');
            var t = b.getAttribute('data-template');
            ta.value = ta.value.trim() ? ta.value.replace(/\s+$/, '') + '\n\n' + t : t;
            ta.focus();
        });
    })();

    // PosInnova: the report is checked in the "check before recording" dialog before it is written
    (function () {
        var form = document.getElementById('imaging_report_form');
        var L = @json($img_js_labels);
        document.addEventListener('submit', function (e) {
            if (e.target !== form) { return; }
            if (form.getAttribute('data-rc-ok') === '1') { form.removeAttribute('data-rc-ok'); return; }
            var report = (form.querySelector('textarea[name=report]').value || '').trim();
            if (!report || !window.PosConfirm) { return; }
            e.preventDefault();
            e.stopImmediatePropagation();
            var btn = e.submitter && e.submitter.form === form ? e.submitter : null;
            PosConfirm.ask({ kind: 'imaging_report', rows: [[L.patient, L.name], [L.study, L.study_name], [L.report, report.length > 400 ? report.slice(0, 400) + '…' : report]] }, function () {
                form.setAttribute('data-rc-ok', '1');
                if (form.requestSubmit) { form.requestSubmit(btn || undefined); } else { form.submit(); }
            });
        }, true);
    })();
</script>
@endsection
