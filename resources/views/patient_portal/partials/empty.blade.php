{{-- Friendly empty state. Prop: $msg, optional $icon --}}
<div class="tw-rounded-3xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-10 tw-text-center tw-shadow-sm">
    <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-14 tw-h-14 tw-rounded-2xl tw-bg-gray-50 tw-text-gray-300 tw-mb-2">
        <i class="fas {{ $icon ?? 'fa-folder-open' }} tw-text-2xl"></i>
    </span>
    <p class="tw-text-sm tw-text-gray-400">{{ $msg }}</p>
</div>
