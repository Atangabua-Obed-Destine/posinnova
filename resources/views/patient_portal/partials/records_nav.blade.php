{{-- Horizontal scrollable sub-nav for the records section. Prop: $active --}}
@php
    $items = [
        ['key' => 'visits', 'route' => 'portal.visits', 'label' => __('lang_v1.my_visits'), 'icon' => 'fa-stethoscope'],
        ['key' => 'prescriptions', 'route' => 'portal.prescriptions', 'label' => __('lang_v1.my_prescriptions'), 'icon' => 'fa-prescription'],
        ['key' => 'labs', 'route' => 'portal.lab-results', 'label' => __('lang_v1.my_lab_results'), 'icon' => 'fa-vial'],
        ['key' => 'imaging', 'route' => 'portal.imaging', 'label' => __('lang_v1.my_imaging'), 'icon' => 'fa-x-ray'],
        ['key' => 'admissions', 'route' => 'portal.admissions', 'label' => __('lang_v1.my_admissions'), 'icon' => 'fa-procedures'],
    ];
@endphp
<div class="portal-chipnav tw-flex tw-gap-2 tw-overflow-x-auto tw-pb-1 tw--mx-1 tw-px-1 tw-mb-4">
    @foreach($items as $it)
        <a href="{{ route($it['route']) }}"
           class="portal-press tw-shrink-0 tw-flex tw-items-center tw-gap-1.5 tw-px-3.5 tw-py-2 tw-rounded-full tw-text-xs tw-font-semibold tw-whitespace-nowrap tw-transition-colors {{ ($active ?? '') === $it['key'] ? 'tw-bg-indigo-600 tw-text-white tw-shadow-sm' : 'tw-bg-white tw-text-gray-600 tw-ring-1 tw-ring-gray-200' }}">
            <i class="fas {{ $it['icon'] }}"></i> {{ $it['label'] }}
        </a>
    @endforeach
</div>
