@extends('layouts.app')
@section('title', __('review.fin_menu'))

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('review.fin_menu') }}
        <small class="tw-text-gray-600">{{ __('review.fin_lead') }}</small>
    </h1>
</section>

<section class="content">
    @include('business_review.partials.styles')
    <style>
        .br-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 14px; }
        .br-filters .form-group { margin: 0; }
        .br-filters label { font-size: 12px; color: #475467; font-weight: 600; margin-bottom: 3px; display: block; }
        .br-filters .form-control { height: 38px; border-radius: 8px; }
        .br-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-left: auto; }
        #br_custom_dates { display: none; gap: 10px; }
        #br_custom_dates.on { display: flex; }
        .br-notes-box textarea { width: 100%; border-radius: 10px; border: 1px solid #d0d5dd; padding: 10px 12px; min-height: 80px; font-size: 13px; }
        @media print {
            .no-print, .br-filters, .br-notes-box, .main-footer, .posinnova-ticker { display: none !important; }
            .br-sheet { border: 0; box-shadow: none; padding: 0; }
            .content, .content-wrapper { padding: 0 !important; margin: 0 !important; }
            .br-keep { page-break-inside: avoid; }
        }
    </style>

    <form method="GET" action="{{ action([\App\Http\Controllers\BusinessReviewController::class, 'financial']) }}" class="br-filters no-print" id="br_filters">
        <div class="form-group">
            <label for="br_period">{{ __('review.period') }}</label>
            <select name="period" id="br_period" class="form-control">
                @foreach ($presets as $key => $range)
                    <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>{{ __('review.p_' . $key) }} ({{ $tu->format_date($range[0]->format('Y-m-d')) }} – {{ $tu->format_date($range[1]->format('Y-m-d')) }})</option>
                @endforeach
                <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>{{ __('review.p_custom') }}</option>
            </select>
        </div>
        <div id="br_custom_dates" class="{{ $period === 'custom' ? 'on' : '' }}">
            <div class="form-group">
                <label for="br_start">{{ __('review.from') }}</label>
                <input type="date" name="start" id="br_start" class="form-control" value="{{ $d['start']->format('Y-m-d') }}">
            </div>
            <div class="form-group">
                <label for="br_end">{{ __('review.to') }}</label>
                <input type="date" name="end" id="br_end" class="form-control" value="{{ $d['end']->format('Y-m-d') }}">
            </div>
        </div>
        @if (count($locations) > 1)
            <div class="form-group">
                <label for="br_location">{{ __('business.location') }}</label>
                <select name="location_id" id="br_location" class="form-control">
                    <option value="">{{ __('review.all_locations') }}</option>
                    @foreach ($locations as $id => $name)
                        <option value="{{ $id }}" {{ (string) $location_id === (string) $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" style="height:38px;" id="br_show">
            <i class="fas fa-sync-alt"></i> {{ __('review.show') }}</button>
        <div class="br-actions">
            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="height:38px;" id="br_print"><i class="fas fa-print"></i> {{ __('review.print') }}</button>
            <button type="submit" form="br_pdf_form" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="height:38px;" id="br_pdf"><i class="fas fa-file-pdf"></i> {{ __('review.pdf') }}</button>
        </div>
    </form>

    <form method="POST" action="{{ action([\App\Http\Controllers\BusinessReviewController::class, 'financialPdf']) }}" id="br_pdf_form" class="no-print">
        @csrf
        <input type="hidden" name="period" value="{{ $period }}">
        <input type="hidden" name="start" value="{{ $d['start']->format('Y-m-d') }}">
        <input type="hidden" name="end" value="{{ $d['end']->format('Y-m-d') }}">
        <input type="hidden" name="location_id" value="{{ $location_id }}">
        <div class="br-notes-box" style="margin-bottom:14px;">
            <label for="br_notes" style="font-size:12px;color:#475467;font-weight:600;">{{ __('review.notes_label') }}</label>
            <textarea name="notes" id="br_notes" maxlength="5000" placeholder="{{ __('review.notes_placeholder') }}"></textarea>
        </div>
    </form>

    <div class="br-sheet" id="br_report">
        @include('business_review.partials.financial_body', ['notes' => ''])
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $('#br_period').on('change', function () {
            $('#br_custom_dates').toggleClass('on', this.value === 'custom');
            if (this.value !== 'custom') $('#br_filters').trigger('submit');
        });
        $('#br_location').on('change', function () { $('#br_filters').trigger('submit'); });
        // the assessor's notes go on the paper and in the PDF
        var key = 'br.notes.' + @json($d['start']->format('Y-m-d') . '_' . $d['end']->format('Y-m-d') . '_' . $location_id);
        try { $('#br_notes').val(localStorage.getItem(key) || ''); } catch (e) {}
        var sync = function () { $('#br_notes_print').text($('#br_notes').val()); try { localStorage.setItem(key, $('#br_notes').val()); } catch (e) {} };
        $('#br_notes').on('input', sync);
        sync();
        $('#br_print').on('click', function () { sync(); window.print(); });
    });
</script>
@endsection
