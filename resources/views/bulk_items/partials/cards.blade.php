{{-- PosInnova bulk items: the containers in use at a location (Stock > Bulk items, and the POS "Bags" window).
     Actions: public/js/bulk_items.js --}}
@php
    $dt = fn ($c) => $c ? $c->format(session('business.date_format', 'd/m/Y') . ' H:i') : '';
    $qf = fn ($n) => rtrim(rtrim(number_format((float) $n, 2, '.', ','), '0'), '.');
@endphp
<div class="bk-cards {{ ! empty($compact) ? 'bk-compact' : '' }}" data-location="{{ $location_id }}">
    @forelse ($current as $row)
        @php
            $item = $row['item'];
            $label = __('bulk.label_' . $item->container_label);
            $unit = optional(optional($item->product)->unit)->actual_name;
            $share = $row['share'];
            $pct = $share === null ? 0 : $share * 100;
            $last = $row['last'];
            $flag = $last ? \App\Utils\BulkItems::flag($last, $item) : null;
        @endphp
        <div class="bk-card" data-id="{{ $item->id }}" id="bk_card_{{ $item->id }}">
            <div class="bk-card-head">
                <div>
                    <div class="bk-name">{{ $item->name() }}</div>
                    <div class="bk-sub">{{ __('bulk.one_is', ['label' => $label, 'size' => $qf($item->container_size), 'unit' => $unit]) }}</div>
                </div>
                @if ($last)
                    <span class="bk-pill {{ $flag === 'low' ? 'bad' : ($flag === 'high' ? 'warn' : 'ok') }}" title="{{ __('bulk.last_title', ['date' => $dt($last->closed_at), 'name' => trim(optional($last->closer)->first_name . ' ' . optional($last->closer)->last_name)]) }}">
                        {{ __('bulk.last_yield', ['label' => $label, 'pct' => number_format((float) $last->yield_pct, 0)]) }}
                    </span>
                @endif
            </div>

            @if ($row['cycle'])
                <div class="bk-open">{{ __('bulk.opened_by', ['label' => $label, 'date' => $dt($row['cycle']->opened_at), 'name' => trim(optional($row['cycle']->opener)->first_name . ' ' . optional($row['cycle']->opener)->last_name)]) }}</div>
                <div class="bk-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round(min(100, $pct)) }}" aria-label="{{ __('bulk.used_so_far') }}">
                    <span class="{{ $pct > 100 ? 'over' : '' }}" style="width: {{ round(min(100, $pct), 1) }}%"></span>
                </div>
                <div class="bk-used">
                    {{ __('bulk.so_far', ['share' => $qf($share), 'label' => $label]) }}
                    @if (! empty($row['so_far']['items']))
                        <span class="bk-muted">· {{ collect($row['so_far']['items'])->map(fn ($i) => $qf($i['sold']) . ' × ' . $i['name'])->implode(', ') }}</span>
                    @else
                        <span class="bk-muted">· {{ __('bulk.none_sold') }}</span>
                    @endif
                </div>
                @if (! empty($can_tap))
                    <div class="bk-actions">
                        <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none bk-finish"
                            data-id="{{ $item->id }}" data-label="{{ $label }}" data-name="{{ $item->name() }}">
                            <i class="fas fa-check"></i> {{ __('bulk.finish_btn', ['label' => $label]) }}</button>
                        @if ($row['can_undo'])
                            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline bk-undo" data-cycle="{{ $last->id }}">
                                <i class="fas fa-undo"></i> {{ __('bulk.undo_btn') }}</button>
                        @endif
                    </div>
                @endif
            @else
                <div class="bk-open bk-muted">{{ __('bulk.not_started', ['label' => $label]) }}</div>
                @if (! empty($can_tap))
                    <div class="bk-actions">
                        <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline bk-start" data-id="{{ $item->id }}" data-label="{{ $label }}" data-name="{{ $item->name() }}">
                            <i class="fas fa-play"></i> {{ __('bulk.start_btn', ['label' => $label]) }}</button>
                        @if ($row['can_undo'])
                            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline bk-undo" data-cycle="{{ $last->id }}">
                                <i class="fas fa-undo"></i> {{ __('bulk.undo_btn') }}</button>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    @empty
        <div class="bk-empty">{{ __('bulk.no_items') }}</div>
    @endforelse
</div>
