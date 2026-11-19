{{-- PosInnova: a product's line in the activity log (App\Observers\ProductChangeObserver) --}}
@php
    $props = $activity->properties ? $activity->properties->toArray() : [];
    $changes = (array) ($props['changes'] ?? []);
    $via = (string) ($props['via'] ?? '');
    $vias = [
        'products' => 'pa_via_product_form', 'update-product-price' => 'pa_via_bulk_price', 'import-products' => 'pa_via_import',
        'inventory-count' => 'pa_via_inventory_count', 'purchases' => 'pa_via_purchase', 'purchase-return' => 'pa_via_purchase',
        'selling-price-group' => 'pa_via_price_group', 'import-opening-stock' => 'pa_via_import', 'opening-stock' => 'pa_via_opening_stock',
        'pos' => 'pa_via_sale', 'sells' => 'pa_via_sale', 'console' => 'pa_via_system',
    ];
    $parts = explode('/', $via);
    $first = $parts[0] ?? '';
    // the products list: deactivate, activate, delete several
    if ($first === 'products' && (in_array($parts[1] ?? '', ['mass-deactivate', 'activate', 'mass-delete', 'mass-destroy'], true) || $activity->description === 'product_deleted')) {
        $first = 'products-list';
    }
    $vias['products-list'] = 'pa_via_product_list';
    $via_label = isset($vias[$first]) ? __('lang_v1.' . $vias[$first]) : $via;
    $field = function ($c) {
        $f = $c['f'] ?? '';
        $key = 'lang_v1.pa_f_' . str_replace('.', '_', $f);
        $label = __($key);

        return $label === $key ? $f : $label;
    };
    $val = function ($v) {
        if ($v === null || $v === '') {
            return '—';
        }
        if ($v === 'yes' || $v === 'no') {
            return __('lang_v1.pa_' . $v);
        }
        if (is_numeric($v)) {
            return rtrim(rtrim(number_format((float) $v, 4, '.', ','), '0'), '.');
        }

        return $v;
    };
@endphp
<div class="pa-note">
    <strong>{{ $props['name'] ?? '' }}</strong>@if (! empty($props['sku'])) <span class="text-muted">· {{ $props['sku'] }}</span>@endif
    @if ($changes)
        <ul style="margin:4px 0 0;padding-left:16px;">
            @foreach (array_slice($changes, 0, 25) as $c)
                @php $f = $c['f'] ?? ''; @endphp
                <li>
                    @if ($f === 'v.added')
                        {{ __('lang_v1.pa_f_v_added') }}@if (! empty($c['v'])) ({{ $c['v'] }})@endif: {{ $c['to'] ?? '' }}@if (! empty($c['buy'])) · {{ __('lang_v1.pa_f_v_default_purchase_price') }} {{ $val($c['buy']) }}@endif
                    @elseif ($f === 'v.removed')
                        {{ __('lang_v1.pa_f_v_removed') }}@if (! empty($c['v'])) ({{ $c['v'] }})@endif @if (! empty($c['from'])) {{ $c['from'] }}@endif
                    @else
                        @php
                            $label = $field($c) . (! empty($c['g']) ? ' «' . $c['g'] . '»' : '') . (! empty($c['u']) ? ' (' . __('lang_v1.pa_per') . ' ' . $c['u'] . ')' : '') . (! empty($c['v']) ? ' [' . $c['v'] . ']' : '');
                        @endphp
                        {{ $label }}:
                        <span style="text-decoration:line-through;color:#98a2b3;">{{ $val($c['from'] ?? null) }}</span> → <strong>{{ $val($c['to'] ?? null) }}</strong>
                    @endif
                </li>
            @endforeach
            @if (count($changes) > 25)
                <li class="text-muted">{{ __('lang_v1.pa_more', ['n' => count($changes) - 25]) }}</li>
            @endif
        </ul>
    @endif
    @if ($via_label)
        <div class="text-muted" style="font-size:11.5px;margin-top:2px;">{{ __('lang_v1.pa_via', ['where' => $via_label]) }}</div>
    @endif
</div>
