{{-- A compact summary stat card. Props: $id, $label, $icon, $fg, $money(bool) --}}
<div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4">
    <div class="tw-flex tw-items-center tw-gap-3">
        <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-lg tw-bg-gray-100 {{ $fg ?? 'tw-text-gray-600' }}">
            <i class="fas {{ $icon }}"></i>
        </span>
        <div class="tw-min-w-0">
            <p class="tw-text-xs tw-font-medium tw-text-gray-500 tw-truncate">{{ $label }}</p>
            <p class="tw-text-xl tw-font-bold tw-text-gray-900" id="{{ $id }}"><i class="fas fa-sync fa-spin fa-fw tw-text-sm"></i></p>
        </div>
    </div>
</div>
