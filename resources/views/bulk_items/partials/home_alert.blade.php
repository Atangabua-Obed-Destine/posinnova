{{-- PosInnova bulk items: on the shop home, "N bags gave less than expected" (last 7 days) --}}
@php
    $bk_low = 0;
    if (\App\Http\Controllers\BulkItemController::canView() && \Illuminate\Support\Facades\Schema::hasTable('bulk_item_cycles')) {
        try {
            $bk_low = (new \App\Utils\BulkItems(new \App\Utils\ProductUtil(), new \App\Utils\TransactionUtil()))
                ->recentLow(session('user.business_id'), auth()->user()->permitted_locations());
        } catch (\Throwable $e) {
            $bk_low = 0;
        }
    }
@endphp
@if ($bk_low > 0)
    <a href="{{ action([\App\Http\Controllers\BulkItemController::class, 'index'], ['tab' => 'history', 'flag' => 'low', 'from' => now()->subDays(7)->format('Y-m-d')]) }}" class="ih-chip" id="ih_bulk_low">
        <span class="ih-fig-ico ih-tone-red"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4h12l-1 4l1 12h-12l1 -12z"/><path d="M7 8h10"/></svg></span>
        <span class="ih-chip-n">{{ $bk_low }}</span> {{ trans_choice('bulk.home_low', $bk_low) }}
    </a>
@endif
