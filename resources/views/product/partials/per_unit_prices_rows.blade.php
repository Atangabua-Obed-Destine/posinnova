{{-- PosInnova: tbody rows only, returned by AJAX. Base unit (multiplier=1) is skipped because it IS the Default Selling Price. --}}
@php
    $tax_type = $tax_type ?? 'exclusive';
    $exc_class = $tax_type == 'inclusive' ? 'hide' : '';
    $inc_class = $tax_type == 'inclusive' ? '' : 'hide';
@endphp
@foreach($unit_prices_data as $unit_id => $u)
    @if((float) $u['multiplier'] == 1.0)
        @continue
    @endif
    <tr data-unit_id="{{ $unit_id }}">
        <td><strong>{{ $u['name'] }}</strong></td>
        <td><code>×{{ $u['multiplier'] }}</code></td>
        <td class="per_unit_col_exc {{ $exc_class }}">
            <input type="text" name="unit_prices[{{ $unit_id }}][exc]"
                value="{{ !empty($u['exc']) ? @num_format($u['exc']) : '' }}"
                class="form-control input-sm input_number per_unit_price_exc"
                data-unit_id="{{ $unit_id }}"
                data-multiplier="{{ $u['multiplier'] }}"
                placeholder="@lang('lang_v1.auto')">
        </td>
        <td class="per_unit_col_inc {{ $inc_class }}">
            <input type="text" name="unit_prices[{{ $unit_id }}][inc]"
                value="{{ !empty($u['inc']) ? @num_format($u['inc']) : '' }}"
                class="form-control input-sm input_number per_unit_price_inc"
                data-unit_id="{{ $unit_id }}"
                data-multiplier="{{ $u['multiplier'] }}"
                placeholder="@lang('lang_v1.auto')">
        </td>
    </tr>
@endforeach
