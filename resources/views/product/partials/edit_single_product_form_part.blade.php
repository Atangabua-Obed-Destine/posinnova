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
    $common_settings = session()->get('business.common_settings');
@endphp

<div class="col-sm-12"><br>
    <div class="table-responsive">
    <table class="table table-bordered add-product-price-table table-condensed {{$class}}">
        <tr>
          <th>@lang('product.default_purchase_price') <small class="base_unit_hint text-muted" style="font-weight:normal;"></small></th>
          <th>@lang('product.profit_percent') @show_tooltip(__('tooltip.profit_percent'))</th>
          <th>@lang('product.default_selling_price') <small class="base_unit_hint text-muted" style="font-weight:normal;"></small></th>
          <th>@lang('lang_v1.product_image')</th>
        </tr>
        @foreach($product_deatails->variations as $variation )
            @php
                $is_image_required = !empty($common_settings['is_product_image_required']) && count($variation->media) == 0;
            @endphp
            @if($loop->first)
                <tr>
                    <td>
                        <input type="hidden" name="single_variation_id" value="{{$variation->id}}">

                        <div class="col-sm-6">
                          {!! Form::label('single_dpp', trans('product.exc_of_tax') . ':*') !!}

                          {!! Form::text('single_dpp', @num_format($variation->default_purchase_price), ['class' => 'form-control input-sm dpp input_number', 'placeholder' => __('product.exc_of_tax'), 'required']); !!}
                        </div>

                        <div class="col-sm-6">
                          {!! Form::label('single_dpp_inc_tax', trans('product.inc_of_tax') . ':*') !!}
                        
                          {!! Form::text('single_dpp_inc_tax', @num_format($variation->dpp_inc_tax), ['class' => 'form-control input-sm dpp_inc_tax input_number', 'placeholder' => __('product.inc_of_tax'), 'required']); !!}
                        </div>
                    </td>

                    <td>
                        <br/>
                        {!! Form::text('profit_percent', @num_format($variation->profit_percent), ['class' => 'form-control input-sm input_number', 'id' => 'profit_percent', 'required']); !!}
                    </td>

                    <td>
                        <label><span class="dsp_label"></span></label>
                        {!! Form::text('single_dsp', @num_format($variation->default_sell_price), ['class' => 'form-control input-sm dsp input_number', 'placeholder' => __('product.exc_of_tax'), 'id' => 'single_dsp', 'required']); !!}

                        {!! Form::text('single_dsp_inc_tax', @num_format($variation->sell_price_inc_tax), ['class' => 'form-control input-sm hide input_number', 'placeholder' => __('product.inc_of_tax'), 'id' => 'single_dsp_inc_tax', 'required']); !!}
                    </td>
                    <td>
                        @php 
                            $action = !empty($action) ? $action : '';
                        @endphp
                        @if($action !== 'duplicate')
                            @foreach($variation->media as $media)
                                <div class="img-thumbnail">
                                    <span class="badge bg-red delete-media" data-href="{{ action([\App\Http\Controllers\ProductController::class, 'deleteMedia'], ['media_id' => $media->id])}}"><i class="fas fa-times"></i></span>
                                    {!! $media->thumbnail() !!}
                                </div>
                            @endforeach
                        @endif
                        <div class="form-group">
                            {!! Form::label('variation_images', __('lang_v1.product_image') . ':') !!}
                            {!! Form::file('variation_images[]', ['class' => 'variation_images', 
                                'accept' => 'image/*', 'multiple', 'required' => $is_image_required]); !!}
                            <small><p class="help-block">@lang('purchase.max_file_size', ['size' => (config('constants.document_size_limit') / 1000000)]) <br> @lang('lang_v1.aspect_ratio_should_be_1_1')</p></small>
                        </div>
                    </td>
                </tr>
            @endif
        @endforeach
    </table>
    </div>
</div>

@php
    // PosInnova: build initial per-unit prices payload from saved overrides.
    // Source of truth = products.sub_unit_ids (the Related Sub Units picker).
    // We additionally include any unit that already has a saved override row,
    // so historical data is never silently hidden on edit.
    $unit_prices_data = [];
    $tax_type = optional($product_deatails->variations->first())->product->tax_type ?? 'exclusive';
    $first_variation = $product_deatails->variations->first();
    if (!empty($first_variation)) {
        $product = $first_variation->product;
        $related_ids = is_array($product->sub_unit_ids) ? array_map('intval', $product->sub_unit_ids) : [];
        $saved = \App\VariationUnitPrice::where('variation_id', $first_variation->id)->get()->keyBy('unit_id');

        $base_unit = \App\Unit::with(['sub_units'])->find($product->unit_id);
        if (!empty($base_unit)) {
            //Base unit row only if explicitly mapped or has a saved override.
            if (in_array($base_unit->id, $related_ids, true) || $saved->has($base_unit->id)) {
                $row = $saved->get($base_unit->id);
                $unit_prices_data[$base_unit->id] = [
                    'name' => $base_unit->actual_name,
                    'multiplier' => 1,
                    'exc' => $row->price_exc_tax ?? null,
                    'inc' => $row->price_inc_tax ?? null,
                ];
            }
            foreach ($base_unit->sub_units as $sub) {
                if (in_array($sub->id, $related_ids, true) || $saved->has($sub->id)) {
                    $row = $saved->get($sub->id);
                    $unit_prices_data[$sub->id] = [
                        'name' => $sub->actual_name,
                        'multiplier' => $sub->base_unit_multiplier,
                        'exc' => $row->price_exc_tax ?? null,
                        'inc' => $row->price_inc_tax ?? null,
                    ];
                }
            }
        }
    }
@endphp
@include('product.partials.per_unit_prices', ['unit_prices_data' => $unit_prices_data, 'tax_type' => $tax_type])