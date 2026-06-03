{{--
    PosInnova: per-unit selling prices for a single-type product.

    Vars:
      $unit_prices_data : array<int, array{name:string, multiplier:float, exc:?float, inc:?float}>
                          keyed by unit_id. Empty when no sub-units selected yet.

    Submits:
      unit_prices[<unit_id>][exc]
      unit_prices[<unit_id>][inc]

    Server saves a row in `variation_unit_prices` only when exc OR inc is non-empty.
    If left blank, POS falls back to base default_sell_price * unit multiplier.
--}}
@php
    $tax_type = $tax_type ?? 'exclusive';
    $exc_class = $tax_type == 'inclusive' ? 'hide' : '';
    $inc_class = $tax_type == 'inclusive' ? '' : 'hide';
@endphp
<div class="col-sm-12 per_unit_prices_wrapper" id="per_unit_prices_wrapper" @if(empty($unit_prices_data)) style="display:none;" @endif>
    <div class="well well-sm" style="background:#f9fafb;border-left:4px solid #3c8dbc;">
        <h4 style="margin-top:0;">
            <i class="fas fa-tags"></i> @lang('lang_v1.per_unit_selling_prices')
            @show_tooltip(__('lang_v1.per_unit_selling_prices_tooltip'))
        </h4>
        <p class="help-block" style="margin-bottom:8px;">
            @lang('lang_v1.per_unit_selling_prices_help')
        </p>

        <div class="table-responsive">
            <table class="table table-bordered table-condensed" id="per_unit_prices_table">
                <thead style="background:#eef3f7;">
                    <tr>
                        <th style="width:25%;">@lang('product.unit')</th>
                        <th style="width:15%;">@lang('lang_v1.multiplier')</th>
                        <th style="width:30%;" class="per_unit_col_exc {{ $exc_class }}">@lang('product.exc_of_tax')</th>
                        <th style="width:30%;" class="per_unit_col_inc {{ $inc_class }}">@lang('product.inc_of_tax')</th>
                    </tr>
                </thead>
                <tbody>
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
                </tbody>
            </table>
        </div>
    </div>
</div>
