{{-- Infinite-scroll footer. Prop: $paginator (a LengthAwarePaginator). --}}
@if($paginator->hasMorePages())
    <div class="portal-infinite tw-mt-3" data-next="{{ $paginator->nextPageUrl() }}">
        <div class="portal-skel-row tw-space-y-3 tw-hidden">
            @for($i = 0; $i < 3; $i++)
                <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-flex tw-items-center tw-gap-3">
                    <span class="portal-skeleton tw-w-10 tw-h-10 tw-rounded-xl tw-shrink-0"></span>
                    <span class="tw-flex-1"><span class="portal-skeleton tw-block tw-h-3.5 tw-w-2/3 tw-mb-2"></span><span class="portal-skeleton tw-block tw-h-3 tw-w-1/3"></span></span>
                </div>
            @endfor
        </div>
        <button type="button" class="portal-loadmore portal-press tw-w-full tw-rounded-xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-py-3 tw-text-sm tw-font-medium tw-text-indigo-600">
            <i class="fas fa-chevron-down"></i> @lang('lang_v1.load_more')
        </button>
    </div>
@endif
