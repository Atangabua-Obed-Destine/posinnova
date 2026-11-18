{{-- A picture to draw on (mouse, finger, pen): $b (drawing block), $exam (its earlier drawing, if any) --}}
@php
    $prev = $exam ? $exam->drawingUrl($b['id']) : null;
@endphp
<div class="ex-draw" data-block="{{ $b['id'] }}" data-prev="{{ $prev }}">
    <div class="ex-label">{{ $b['label'] }}</div>
    <div class="ex-draw-stage">
        @if (! empty($b['image']))
            <img class="ex-draw-base" src="{{ asset($b['image']) }}" alt="" draggable="false">
        @else
            <div class="ex-draw-blank"></div>
        @endif
        <canvas class="ex-draw-canvas" aria-label="{{ __('clinic.ex_draw_on', ['name' => $b['label']]) }}"></canvas>
    </div>
    <div class="ex-draw-tools" role="toolbar" aria-label="{{ __('clinic.ex_draw_tools') }}">
        <button type="button" class="ex-tool ex-on" data-tool="pen" data-color="#dc2626" title="{{ __('clinic.ex_pen_red') }}" aria-label="{{ __('clinic.ex_pen_red') }}"><span class="ex-dot" style="background:#dc2626"></span></button>
        <button type="button" class="ex-tool" data-tool="pen" data-color="#2563eb" title="{{ __('clinic.ex_pen_blue') }}" aria-label="{{ __('clinic.ex_pen_blue') }}"><span class="ex-dot" style="background:#2563eb"></span></button>
        <button type="button" class="ex-tool" data-tool="pen" data-color="#111827" title="{{ __('clinic.ex_pen_black') }}" aria-label="{{ __('clinic.ex_pen_black') }}"><span class="ex-dot" style="background:#111827"></span></button>
        <button type="button" class="ex-tool" data-tool="eraser" title="{{ __('clinic.ex_eraser') }}" aria-label="{{ __('clinic.ex_eraser') }}"><i class="fas fa-eraser"></i></button>
        <button type="button" class="ex-tool" data-tool="text" title="{{ __('clinic.ex_text') }}" aria-label="{{ __('clinic.ex_text') }}"><i class="fas fa-font"></i></button>
        <button type="button" class="ex-act" data-act="size" title="{{ __('clinic.ex_thick') }}" aria-label="{{ __('clinic.ex_thick') }}" aria-pressed="false"><i class="fas fa-grip-lines"></i></button>
        <button type="button" class="ex-act" data-act="undo" title="{{ __('clinic.ex_undo') }}" aria-label="{{ __('clinic.ex_undo') }}"><i class="fas fa-undo"></i></button>
        <button type="button" class="ex-act" data-act="clear" title="{{ __('clinic.ex_clear') }}" aria-label="{{ __('clinic.ex_clear') }}"><i class="fas fa-trash-alt"></i></button>
        <button type="button" class="ex-act" data-act="full" title="{{ __('clinic.ex_full') }}" aria-label="{{ __('clinic.ex_full') }}"><i class="fas fa-expand"></i></button>
    </div>
    <input type="hidden" name="exam[draw][{{ $b['id'] }}]" class="ex-draw-data" value="">
</div>
