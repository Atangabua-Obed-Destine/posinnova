@forelse($products as $product)
	@php
		// PosInnova: stock state drives the card's left stripe and its pill.
		// 0 or no record = out, 5 or fewer = low. Products that do not track
		// stock are neither.
		$pi_qty = $product->enable_stock ? (float) $product->qty_available : null;
		$pi_state = !$product->enable_stock ? 'none' : ($pi_qty > 5 ? 'ok' : ($pi_qty > 0 ? 'low' : 'out'));
		$pi_image = null;
		if (count($product->media) > 0) {
			$pi_image = $product->media->first()->display_url;
		} elseif (!empty($product->product_image)) {
			$pi_image = asset('/uploads/img/' . rawurlencode($product->product_image));
		}
	@endphp
	<div class="col-md-3 col-xs-4 product_list no-print">
		<div class="product_box pi-prod pi-prod-{{ $pi_state }}" data-variation_id="{{ $product->id }}"
			title="{{ $product->name }} @if ($product->type == 'variable') - {{ $product->variation }} @endif {{ '(' . $product->sub_sku . ')' }} @if (!empty($show_prices)) @lang('lang_v1.default') - @format_currency($product->selling_price) @foreach ($product->group_prices as $group_price) @if (array_key_exists($group_price->price_group_id, $allowed_group_prices)) {{ $allowed_group_prices[$group_price->price_group_id] }} - @format_currency($group_price->price_inc_tax) @endif @endforeach @endif">

			{{-- PosInnova: the image box is only drawn when the product actually has
			     one. It used to reserve 50px on every card and show a grey
			     placeholder, which on a pharmacy catalogue is every single card. --}}
			@if ($pi_image)
				<div class="image-container pi-prod-img" style="background-image: url({{ $pi_image }});"></div>
			@endif

			<div class="text_div pi-prod-body">
				<span class="pi-prod-name">{{ $product->name }}@if ($product->type == 'variable') - {{ $product->variation }} @endif</span>
				<span class="pi-prod-sku">{{ $product->sub_sku }}</span>

				<span class="pi-prod-foot">
					@if (!empty($show_prices))
						<span class="pi-prod-price">@format_currency($product->selling_price)</span>
					@else
						<span></span>
					@endif

					<span class="pi-prod-stock">
						@if ($product->enable_stock)
							@if ($pi_state == 'out')
								@lang('lang_v1.out_of_stock')
							@else
								{{ @num_format($product->qty_available) }} {{ $product->unit }}
							@endif
						@else
							&ndash;&ndash;
						@endif
					</span>
				</span>
			</div>

		</div>
	</div>
@empty
	<input type="hidden" id="no_products_found">
	<div class="col-md-12">
		<h4 class="text-center">
			@lang('lang_v1.no_products_to_display')
		</h4>
	</div>
@endforelse
