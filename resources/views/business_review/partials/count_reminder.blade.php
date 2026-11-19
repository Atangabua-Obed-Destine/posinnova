{{-- PosInnova: "Quarterly stock count due" (App\Utils\BusinessReview::countReminder).
     $style: 'chip' (shop home, "Needs attention") or 'banner' (Inventory Count page). --}}
@php
    $u = auth()->user();
    $rem = null;
    if (in_array('stock_adjustment', (array) (session('business.enabled_modules') ?: [])) && ($u->can('inventory_count.view') || $u->can('inventory_count.create'))) {
        try {
            $rem = (new \App\Utils\BusinessReview(new \App\Utils\TransactionUtil()))->countReminder(session('user.business_id'), $u->permitted_locations());
        } catch (\Throwable $e) {
            $rem = null;
        }
    }
@endphp
@if ($rem)
    @php
        $names = implode(', ', $rem['missing']);
        $text = __('review.rem_count_due_n', ['locations' => $names, 'days' => $rem['days_left']]);
        $url = action([\App\Http\Controllers\BusinessReviewController::class, 'stockControl']);
        $urgent = $rem['days_left'] <= 21;
    @endphp
    @if (($style ?? 'chip') === 'chip')
        <a href="{{ $url }}" class="ih-chip" id="ih_count_due" title="{{ $text }}">
            <span class="ih-fig-ico {{ $urgent ? 'ih-tone-red' : 'ih-tone-amber' }}"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2"/><path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z"/><path d="M9 12l2 2l4 -4"/></svg></span>
            {{ __('review.rem_count_due') }} · <span class="ih-chip-n">{{ trans_choice('review.days_n', $rem['days_left'], ['n' => $rem['days_left']]) }}</span>
        </a>
    @else
        <div class="alert {{ $urgent ? 'alert-danger' : 'alert-warning' }}" id="ic_count_due" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <i class="fas fa-calendar-check"></i>
            <strong>{{ __('review.rem_count_due') }} ({{ __('review.quarter_long', ['q' => $rem['q'], 'year' => $rem['year']]) }})</strong>
            <span>{{ $text }}</span>
            <a href="{{ $url }}" style="margin-left:auto;font-weight:600;">{{ __('review.sc_menu') }} →</a>
        </div>
    @endif
@endif
