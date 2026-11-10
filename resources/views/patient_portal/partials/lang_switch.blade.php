@php
    $variant = $variant ?? 'sidebar';
    $locale = app()->getLocale();
    $langs = ['en' => 'EN', 'fr' => 'FR'];
@endphp

@if($variant === 'compact')
    {{-- Compact pill toggle for the mobile top bar --}}
    <div class="tw-inline-flex tw-rounded-full tw-bg-gray-100 tw-p-0.5 tw-text-[11px]">
        @foreach($langs as $code => $label)
            <a href="{{ route('portal.lang', $code) }}"
               style="min-width:36px;min-height:32px;display:inline-flex;align-items:center;justify-content:center;" class="tw-px-2 tw-py-0.5 tw-rounded-full tw-font-semibold {{ $locale === $code ? 'tw-bg-white tw-text-indigo-600 tw-shadow-sm' : 'tw-text-gray-500' }}">{{ $label }}</a>
        @endforeach
    </div>
@else
    {{-- Sidebar row --}}
    <div class="tw-flex tw-items-center tw-justify-between tw-px-3 tw-py-2">
        <span class="tw-text-xs tw-text-gray-400"><i class="fas fa-language"></i> @lang('lang_v1.language')</span>
        <div class="tw-inline-flex tw-rounded-full tw-bg-gray-100 tw-p-0.5 tw-text-xs">
            @foreach($langs as $code => $label)
                <a href="{{ route('portal.lang', $code) }}"
                   style="min-width:40px;min-height:32px;display:inline-flex;align-items:center;justify-content:center;" class="tw-px-2.5 tw-py-1 tw-rounded-full tw-font-semibold {{ $locale === $code ? 'tw-bg-white tw-text-indigo-600 tw-shadow-sm' : 'tw-text-gray-500' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
@endif
