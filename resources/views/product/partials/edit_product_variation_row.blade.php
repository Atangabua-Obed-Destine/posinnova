@if(!session('business.enable_price_tax')) 
    @php
        $default = 0;
        $class = 'hide';
    @endphp
@else
    @php
        $default = null;
        $class = '';
    @endphp
@endif

@php
 $array_name = 'product_variation_edit';
 $variation_array_name = 'variations_edit';
 if($action == 'duplicate'){
    $array_name = 'product_variation';
    $variation_array_name = 'variations';
 }

    $common_settings = session()->get('business.common_settings');

    // PosInnova: Per-unit selling price overrides (variable products, edit only)
    $pi_show_unit_prices = ($action == 'edit' && !empty($product));
    $pi_unit_options = collect();
    if ($pi_show_unit_prices) {
        $pi_unit_ids = [];
        if (!empty($product->unit_id)) { $pi_unit_ids[] = $product->unit_id; }
        if (is_array($product->sub_unit_ids)) {
            foreach ($product->sub_unit_ids as $suid) {
                if (!in_array($suid, $pi_unit_ids)) { $pi_unit_ids[] = $suid; }
            }
        }
        if (!empty($pi_unit_ids)) {
            $pi_unit_options = \App\Unit::whereIn('id', $pi_unit_ids)->get();
        }
    }
@endphp

<tr class="variation_row">
    <td>
        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-error delete_complete_row"><i class="fa fa-trash"></i></button>
    </td>
    <td>
        {!! Form::text($array_name . '[' . $row_index .'][name]', $product_variation->name, ['class' => 'form-control input-sm variation_name', 'required', 'readonly']); !!}

        {!! Form::hidden($array_name . '[' . $row_index .'][variation_template_id]', $product_variation->variation_template_id); !!}

        <input type="hidden" class="row_index" value="@if($action == 'edit'){{$row_index}}@else{{$loop->index}}@endif">
        <input type="hidden" class="row_edit" value="edit">
    </td>

    <td>
        <table class="table table-condensed table-bordered blue-header variation_value_table">
            <thead>
            <tr>
                <th>@lang('product.sku') @show_tooltip(__('tooltip.sub_sku'))</th>
                <th>@lang('product.value')</th>
                <th class="{{$class}}">@lang('product.default_purchase_price') 
                    <br/>
                    <span class="pull-left"><small><i>@lang('product.exc_of_tax')</i></small></span>

                    <span class="pull-right"><small><i>@lang('product.inc_of_tax')</i></small></span>
                </th>
                <th class="{{$class}}">@lang('product.profit_percent')</th>
                <th class="{{$class}}">@lang('product.default_selling_price') 
                <br/>
                <small><i><span class="dsp_label"></span></i></small>
                </th>
                <th>@lang('lang_v1.variation_images')</th>
                <th><button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-accent add_variation_value_row">+</button></th>
            </tr>
            </thead>

            <tbody>

            @forelse ($product_variation->variations as $variation)
                @php
                    $variation_row_index = $variation->id;
                    $sub_sku_required = 'required';
                    if($action == 'duplicate'){
                        $variation_row_index = $loop->index;
                        $sub_sku_required = '';
                    }
                @endphp
                <tr>
                    <td>
                        @if($action != 'duplicate')
                            <input type="hidden" class="row_variation_id" value="{{$variation->id}}">
                        @endif
                        {!! Form::text($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][sub_sku]', $action == 'edit' ? $variation->sub_sku : null, ['class' => 'form-control input-sm input_sub_sku', $sub_sku_required]); !!}
                    </td>
                    <td>
                        {!! Form::text($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][value]', $variation->name, ['class' => 'form-control input-sm variation_value_name', 'required', 'readonly']); !!}

                        {!! Form::hidden($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][variation_value_id]', $variation->variation_value_id); !!}
                    </td>
                    <td class="{{$class}}">
                        <div class="width-50 f-left">
                            {!! Form::text($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][default_purchase_price]', @num_format($variation->default_purchase_price), ['class' => 'form-control input-sm variable_dpp input_number', 'placeholder' => __('product.exc_of_tax'), 'required']); !!}
                        </div>

                        <div class="width-50 f-left">
                            {!! Form::text($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][dpp_inc_tax]', @num_format($variation->dpp_inc_tax), ['class' => 'form-control input-sm variable_dpp_inc_tax input_number', 'placeholder' => __('product.inc_of_tax'), 'required']); !!}
                        </div>
                    </td>
                    <td class="{{$class}}">
                        {!! Form::text($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][profit_percent]', @num_format($variation->profit_percent), ['class' => 'form-control input-sm variable_profit_percent input_number', 'required']); !!}
                    </td>
                    <td class="{{$class}}">
                        {!! Form::text($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][default_sell_price]', @num_format($variation->default_sell_price), ['class' => 'form-control input-sm variable_dsp input_number', 'placeholder' => __('product.exc_of_tax'), 'required']); !!}

                        {!! Form::text($array_name . '[' . $row_index .'][' . $variation_array_name . '][' . $variation_row_index . '][sell_price_inc_tax]', @num_format($variation->sell_price_inc_tax), ['class' => 'form-control input-sm variable_dsp_inc_tax input_number', 'placeholder' => __('product.inc_of_tax'), 'required']); !!}
                    </td>
                    <td class="variation-image-cell">
                        @php 
                            $action = !empty($action) ? $action : '';
                        @endphp
                        <div class="variation-image-upload">
                            {{-- Existing images --}}
                            @if($action !== 'duplicate')
                                <div class="existing-images" style="margin-bottom: 5px;">
                                    @foreach($variation->media as $media)
                                        <div class="img-thumbnail" style="display: inline-block; position: relative; margin-right: 3px; margin-bottom: 3px;">
                                            <span class="badge bg-red delete-media" style="position: absolute; top: -5px; right: -5px; cursor: pointer;" data-href="{{ action([\App\Http\Controllers\ProductController::class, 'deleteMedia'], ['media_id' => $media->id])}}"><i class="fas fa-times"></i></span>
                                            {!! $media->thumbnail() !!}
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            
                            {{-- Hidden file input --}}
                            {!! Form::file('edit_variation_images_' . $row_index . '_' . $variation_row_index . '[]', [
                                'class' => 'variation_images variation-file-input', 
                                'accept' => 'image/*', 
                                'multiple',
                                'id' => 'edit_variation_img_' . $row_index . '_' . $variation_row_index,
                                'style' => 'display: none;'
                            ]); !!}
                            
                            {{-- Upload button --}}
                            <label for="edit_variation_img_{{$row_index}}_{{$variation_row_index}}" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-primary variation-upload-btn" style="cursor: pointer; margin: 0;">
                                <i class="fas fa-camera"></i>
                                <span class="image-count-badge" style="display: none;"></span>
                            </label>
                            
                            {{-- Image preview container for new uploads --}}
                            <div class="variation-image-preview" style="display: none; margin-top: 5px;"></div>
                        </div>
                    </td>
                    <td>
                        <button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline  tw-dw-btn-error remove_variation_value_row"><i class="fa fa-trash"></i></button>
                        <input type="hidden" class="variation_row_index" value="@if($action == 'duplicate'){{$loop->index}}@else{{0}}@endif">
                        @if($pi_show_unit_prices && $pi_unit_options->count() > 1)
                            <br>
                            <button type="button" class="btn btn-xs btn-link toggle_var_unit_prices" data-target="#var_unit_prices_{{ $variation->id }}" style="padding:2px 0;">
                                <i class="fas fa-tags"></i> @lang('lang_v1.per_unit_selling_prices')
                            </button>
                        @endif
                    </td>
                </tr>
                @if($pi_show_unit_prices && $pi_unit_options->count() > 1)
                    @php
                        $pi_existing = \App\VariationUnitPrice::where('variation_id', $variation->id)->get()->keyBy('unit_id');
                    @endphp
                    <tr class="var_unit_prices_row" id="var_unit_prices_{{ $variation->id }}" style="display:none; background:#f9fafb;">
                        <td colspan="7">
                            <div style="border-left:3px solid #3c8dbc; padding:6px 10px;">
                                <strong><i class="fas fa-tags"></i> @lang('lang_v1.per_unit_selling_prices')</strong>
                                <small class="help-block" style="margin:0 0 6px;">@lang('lang_v1.per_unit_selling_prices_help')</small>
                                <table class="table table-condensed table-bordered" style="margin-bottom:0;">
                                    <thead style="background:#eef3f7;">
                                        <tr>
                                            <th style="width:25%;">@lang('product.unit')</th>
                                            <th style="width:15%;">@lang('lang_v1.multiplier')</th>
                                            <th style="width:30%;">@lang('product.exc_of_tax')</th>
                                            <th style="width:30%;">@lang('product.inc_of_tax')</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($pi_unit_options as $pi_unit)
                                            @php
                                                $pi_mult = $pi_unit->base_unit_multiplier ?: 1;
                                                $pi_existing_row = $pi_existing[$pi_unit->id] ?? null;
                                                $pi_exc = $pi_existing_row ? $pi_existing_row->price_exc_tax : null;
                                                $pi_inc = $pi_existing_row ? $pi_existing_row->price_inc_tax : null;
                                            @endphp
                                            <tr>
                                                <td><strong>{{ $pi_unit->actual_name }}</strong></td>
                                                <td><code>×{{ $pi_mult }}</code></td>
                                                <td>
                                                    <input type="text" name="unit_prices_var[{{ $variation->id }}][{{ $pi_unit->id }}][exc]"
                                                        value="{{ !empty($pi_exc) ? @num_format($pi_exc) : '' }}"
                                                        class="form-control input-sm input_number"
                                                        placeholder="@lang('lang_v1.auto')">
                                                </td>
                                                <td>
                                                    <input type="text" name="unit_prices_var[{{ $variation->id }}][{{ $pi_unit->id }}][inc]"
                                                        value="{{ !empty($pi_inc) ? @num_format($pi_inc) : '' }}"
                                                        class="form-control input-sm input_number"
                                                        placeholder="@lang('lang_v1.auto')">
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                &nbsp;
            @endforelse
            </tbody>
        </table>
    </td>
</tr>