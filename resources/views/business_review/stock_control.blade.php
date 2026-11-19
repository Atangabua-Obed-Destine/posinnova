@extends('layouts.app')
@section('title', __('review.sc_menu'))

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('review.sc_menu') }}
        <small class="tw-text-gray-600">{{ __('review.sc_lead') }}</small>
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
        .br-notes-box textarea { width: 100%; border-radius: 10px; border: 1px solid #d0d5dd; padding: 10px 12px; min-height: 80px; font-size: 13px; }
        @media print {
            .no-print, .br-filters, .br-notes-box, .main-footer, .posinnova-ticker { display: none !important; }
            .br-sheet { border: 0; box-shadow: none; padding: 0; }
            .content, .content-wrapper { padding: 0 !important; margin: 0 !important; }
        }
    </style>

    <form method="GET" action="{{ action([\App\Http\Controllers\BusinessReviewController::class, 'stockControl']) }}" class="br-filters no-print" id="br_filters">
        <div class="form-group">
            <label for="sc_quarter">{{ __('review.quarter') }}</label>
            <select name="quarter" id="sc_quarter" class="form-control">
                @foreach ($quarters as $key => $label)
                    <option value="{{ $key }}" {{ $key === $quarter_key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
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
            @can('inventory_count.create')
                <a href="{{ action([\App\Http\Controllers\InventoryCountController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="height:38px;" id="sc_go_counts"><i class="fas fa-clipboard-check"></i> {{ __('review.sc_go_counts') }}</a>
            @endcan
            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="height:38px;" id="br_print"><i class="fas fa-print"></i> {{ __('review.print') }}</button>
            <button type="submit" form="br_pdf_form" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="height:38px;" id="br_pdf"><i class="fas fa-file-pdf"></i> {{ __('review.pdf') }}</button>
        </div>
    </form>

    <form method="POST" action="{{ action([\App\Http\Controllers\BusinessReviewController::class, 'stockControlPdf']) }}" id="br_pdf_form" class="no-print">
        @csrf
        <input type="hidden" name="quarter" value="{{ $quarter_key }}">
        <input type="hidden" name="location_id" value="{{ $location_id }}">
        <div class="br-notes-box" style="margin-bottom:14px;">
            <label for="br_notes" style="font-size:12px;color:#475467;font-weight:600;">{{ __('review.notes_label') }}</label>
            <textarea name="notes" id="br_notes" maxlength="5000" placeholder="{{ __('review.sc_notes_placeholder') }}"></textarea>
        </div>
    </form>

    <div class="br-sheet" id="br_report">
        @include('business_review.partials.stock_body', ['notes' => ''])
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $('#sc_quarter, #br_location').on('change', function () { $('#br_filters').trigger('submit'); });
        var key = 'br.scnotes.' + @json($quarter_key . '_' . $location_id);
        try { $('#br_notes').val(localStorage.getItem(key) || ''); } catch (e) {}
        var sync = function () { $('#br_notes_print').text($('#br_notes').val()); try { localStorage.setItem(key, $('#br_notes').val()); } catch (e) {} };
        $('#br_notes').on('input', sync);
        sync();
        $('#br_print').on('click', function () { sync(); window.print(); });
    });
</script>
@endsection
