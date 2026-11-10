<div class="row" id="featured_products_box" style="display: none;">
    @if (!empty($featured_products))
        @include('sale_pos.partials.featured_products')
    @endif
</div>

{{--
    PosInnova: the category and brand pickers used to be DaisyUI drawers that slid
    a half-screen overlay across the cart. They are now panels that expand in place
    under their own button, so the cashier never loses sight of the sale.

    The hooks pos.js binds to are unchanged on purpose:
      .main-category[data-parent=0]  apply this category
      .main-category[data-parent=1]  drill into its sub-categories
      .all-sub-category              the hidden sub-category block
      .category-back / .category_heading
      .product_category / .product_brand
      .overlay-category / .overlay-brand   "close the picker" - now collapses the panel
--}}
@php
    $pi_has_facets = !empty($categories) || !empty($brands);
    // The collapse control only belongs on the desktop split screen. Phones get
    // this same partial inside a modal, where there is no cart beside it to
    // widen - and create/edit only render the side pane when !isMobile().
    $pi_can_collapse = !isMobile();
@endphp
@if ($pi_has_facets || $pi_can_collapse)
<div class="pi-browser-head">
{{-- Nothing of the facet bar renders when the business has both pickers turned
     off: an empty #pi_facet_bar would still add its own margin above the
     product grid, and the pos.js facet module keys its early return off the
     absence of that element. --}}
@if ($pi_has_facets)
<div class="pi-facets" id="pi_facet_bar">

        <div class="pi-facet-btns">
            @if (!empty($categories))
                <button type="button" class="pi-facet-btn" id="pi_cat_btn" aria-expanded="false"
                    aria-controls="pi_cat_panel" data-panel="cat">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" />
                        <rect x="3" y="14" width="7" height="7" rx="1" /><path d="M17.5 14v7M14 17.5h7" />
                    </svg>
                    <span>@lang('category.category')</span>
                    <svg class="pi-cv" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                </button>
            @endif

            @if (!empty($brands))
                <button type="button" class="pi-facet-btn" id="pi_brand_btn" aria-expanded="false"
                    aria-controls="pi_brand_panel" data-panel="brand">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 11V5a2 2 0 0 1 2-2h6l10 10-8 8z" /><circle cx="7.5" cy="7.5" r="1.3" />
                    </svg>
                    <span>@lang('brand.brands')</span>
                    <svg class="pi-cv" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6" /></svg>
                </button>
            @endif
        </div>

    @if (!empty($categories))
        <div class="pi-facet-panel" id="pi_cat_panel">
            <div>
                <div class="pi-facet-panel-in">
                    <div class="pi-facet-head">
                        <button type="button" class="pi-facet-back category-back" style="display: none">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m15 6-6 6 6 6" /></svg>
                            @lang('messages.back')
                        </button>
                        <span class="pi-facet-title category_heading">@lang('category.category')</span>
                        <span class="pi-facet-find">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.2-3.2" /></svg>
                            <input type="text" class="pi-facet-filter" data-scope="#pi_cat_panel"
                                placeholder="@lang('lang_v1.search')" autocomplete="off">
                        </span>
                        {{-- pos.js closes the picker with $('.overlay-category').trigger('click') --}}
                        <span class="overlay-category" style="display:none"></span>
                    </div>

                    <div class="pi-facet-opts">
                        <button type="button" class="pi-opt main-category-div main-category no-print" data-value="all"
                            data-parent="0">@lang('lang_v1.all_category')</button>

                        @foreach ($categories as $category)
                            @php $has_subs = !empty($category['sub_categories']); @endphp
                            <button type="button"
                                class="pi-opt main-category-div main-category no-print @if ($has_subs) pi-opt-more @endif"
                                data-value="{{ $category['id'] }}" data-name="{{ $category['name'] }}"
                                data-parent="{{ $has_subs ? 1 : 0 }}">
                                <span>{{ $category['name'] }}</span>
                                @if ($has_subs)
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="m9 6 6 6-6 6" /></svg>
                                @endif
                            </button>
                        @endforeach

                        @foreach ($categories as $category)
                            @if (!empty($category['sub_categories']))
                                <div class="all-sub-category" data-category-id="{{ $category['id'] }}"
                                    style="display: none">
                                    <button type="button" class="pi-opt main-category no-print" data-value="{{ $category['id'] }}"
                                        data-parent="0">@lang('lang_v1.all') &mdash; {{ $category['name'] }}</button>
                                    @foreach ($category['sub_categories'] as $sc)
                                        @if ($sc['parent_id'] != 0)
                                            <button type="button" class="pi-opt product_category no-print"
                                                data-value="{{ $sc['id'] }}">{{ $sc['name'] }}</button>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if (!empty($brands))
        <div class="pi-facet-panel" id="pi_brand_panel">
            <div>
                <div class="pi-facet-panel-in">
                    <div class="pi-facet-head">
                        <span class="pi-facet-title">@lang('brand.brands')</span>
                        <span class="pi-facet-find">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="m20 20-3.2-3.2" /></svg>
                            <input type="text" class="pi-facet-filter" data-scope="#pi_brand_panel"
                                placeholder="@lang('lang_v1.search')" autocomplete="off">
                        </span>
                        <span class="overlay-brand" style="display:none"></span>
                    </div>

                    @php
                        // Brands::forDropdown()->prepend('All Brands','all') means this list is
                        // never empty: without the 'all' entry there may be no brands at all.
                        $pi_real_brands = collect($brands)->filter(fn($v, $k) => (string) $k !== 'all');
                    @endphp

                    @if ($pi_real_brands->isEmpty())
                        <div class="pi-facet-empty">@lang('lang_v1.no_brands_configured')</div>
                    @else
                        <div class="pi-facet-opts">
                            @foreach ($brands as $key => $brand)
                                <button type="button" class="pi-opt product_brand no-print"
                                    data-value="{{ $key }}">{{ $brand }}</button>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif

    <div class="pi-active-row" id="pi_active_row"></div>
</div>
@endif

@if ($pi_can_collapse)
    {{-- Hides this whole pane so the cart takes the full width. The way back is
         the "Products" tab on the right edge (sale_pos/partials/pos_browser_toggle). --}}
    <button type="button" class="pi-browser-toggle no-print" id="pi_browser_collapse"
        aria-controls="pi_pos_browser_pane" aria-expanded="true"
        title="@lang('lang_v1.hide_product_panel')" aria-label="@lang('lang_v1.hide_product_panel')">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="16" rx="2" /><path d="M15 4v16" /><path d="m8 9 3 3-3 3" />
        </svg>
    </button>
@endif
</div>
@endif

<!-- used in repair : filter for service/product -->
<div class="row">
    <div class="col-md-6 hide" id="product_service_div">
        {!! Form::select(
            'is_enabled_stock',
            ['' => __('messages.all'), 'product' => __('sale.product'), 'service' => __('lang_v1.service')],
            null,
            ['id' => 'is_enabled_stock', 'class' => 'select2', 'name' => null, 'style' => 'width:100% !important'],
        ) !!}
    </div>

    <div class="col-sm-4 @if (empty($featured_products)) hide @endif" id="feature_product_div">
        <button type="button" class="btn btn-primary btn-flat"
            id="show_featured_products">@lang('lang_v1.featured_products')</button>
    </div>
</div>

<div class="row">
    <input type="hidden" id="suggestion_page" value="1">
    <div class="col-md-12">
        <div class="eq-height-row" id="product_list_body"></div>
    </div>
    <div class="col-md-12 text-center" id="suggestion_page_loader" style="display: none;">
        <i class="fa fa-spinner fa-spin fa-2x"></i>
    </div>
</div>
