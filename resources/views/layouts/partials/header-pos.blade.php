<!-- default value -->
@php
    $go_back_url = action([\App\Http\Controllers\SellPosController::class, 'index']);
    $transaction_sub_type = '';
    $view_suspended_sell_url = action([\App\Http\Controllers\SellController::class, 'index']) . '?suspended=1';
    $pos_redirect_url = action([\App\Http\Controllers\SellPosController::class, 'create']);
@endphp

@if (!empty($pos_module_data))
    @foreach ($pos_module_data as $key => $value)
        @php
            if (!empty($value['go_back_url'])) {
                $go_back_url = $value['go_back_url'];
            }

            if (!empty($value['transaction_sub_type'])) {
                $transaction_sub_type = $value['transaction_sub_type'];
                $view_suspended_sell_url .= '&transaction_sub_type=' . $transaction_sub_type;
                $pos_redirect_url .= '?sub_type=' . $transaction_sub_type;
            }
        @endphp
    @endforeach
@endif
<input type="hidden" name="transaction_sub_type" id="transaction_sub_type" value="{{ $transaction_sub_type }}">
@inject('request', 'Illuminate\Http\Request')
@php
    // PosInnova: shared classes for the header icon buttons so styling stays
    // consistent. Uniform 40x40 square pill, subtle border, soft hover.
    $pi_icon_btn = 'pi-pos-icon-btn tw-inline-flex tw-items-center tw-justify-center '
        . 'tw-w-10 tw-h-10 tw-rounded-lg tw-bg-white tw-border tw-border-gray-200 '
        . 'hover:tw-bg-gray-50 hover:tw-border-gray-300 hover:tw-shadow-sm '
        . 'tw-text-gray-600 tw-transition tw-cursor-pointer focus:tw-outline-none '
        . 'focus:tw-ring-2 focus:tw-ring-blue-200';
    $pi_icon = 'fa-lg !tw-text-base';
@endphp
<style>
    .pos-header .pi-pos-icon-btn { line-height: 1; padding: 0; }
    .pos-header .pi-pos-icon-btn i { font-size: 16px; }
    .pos-header .pi-pos-icon-group { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
    .pos-header .pi-add-expense-btn {
        display: inline-flex; align-items: center; gap: 6px;
        height: 40px; padding: 0 14px; border-radius: 8px;
        background: #fff; border: 1px solid #e5e7eb; color: #111827;
        font-weight: 600; font-size: 13px; line-height: 1; cursor: pointer;
        white-space: nowrap; transition: all .15s ease;
    }
    .pos-header .pi-add-expense-btn:hover { background: #f9fafb; border-color: #d1d5db; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    .pos-header .pi-add-expense-btn .fa { color: #ef4b53; }
    .pos-header .pi-datetime-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 10px; border-radius: 8px; background: #4f5bd5; color: #fff;
        font-weight: 600; font-size: 12px; line-height: 1;
    }
    .pos-header .pi-datetime-pill .fa { color: #fff; opacity: .85; }
    .pos-header .pi-location-label { font-weight: 700; color: #111827; margin: 0; }
    .pos-header .pi-cashier-pill {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 10px; border-radius: 8px; background: #00935F; color: #fff;
        font-weight: 600; font-size: 12px; line-height: 1; white-space: nowrap;
    }
    .pos-header .pi-cashier-pill .fa { color: #fff; opacity: .9; }
    /* PosInnova: pending drafts indicator — pulses to catch the cashier's eye */
    .pos-header .pi-draft-indicator {
        display: inline-flex; align-items: center; gap: 6px;
        height: 40px; padding: 0 14px; border-radius: 8px;
        background: #F59E0B; border: 1px solid #D97706; color: #fff;
        font-weight: 700; font-size: 12px; line-height: 1; cursor: pointer;
        white-space: nowrap;
        animation: pi-draft-blink 1.4s ease-in-out infinite;
    }
    .pos-header .pi-draft-indicator:hover { animation-play-state: paused; background: #D97706; }
    .pos-header .pi-draft-indicator .pi-draft-count {
        background: #fff; color: #D97706; border-radius: 999px;
        min-width: 20px; height: 20px; display: inline-flex;
        align-items: center; justify-content: center;
        font-size: 12px; font-weight: 800; padding: 0 6px;
    }
    @keyframes pi-draft-blink {
        0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, .55); opacity: 1; }
        50%      { box-shadow: 0 0 0 7px rgba(245, 158, 11, 0);  opacity: .75; }
    }
</style>
<div class="col-md-12 no-print pos-header">
    <input type="hidden" id="pos_redirect_url" value="{{ $pos_redirect_url }}">
    <div class="tw-flex tw-flex-col md:tw-flex-row tw-items-center tw-justify-between tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-bg-white tw-rounded-xl tw-mx-0 tw-mt-1 tw-mb-0 md:tw-mb-0 tw-p-3 tw-gap-3">

        {{-- LEFT: Location + datetime pill + mobile menu toggle --}}
        <div class="tw-w-full md:tw-w-auto tw-flex tw-items-center tw-gap-3 tw-flex-wrap">
            <p class="pi-location-label">@lang('sale.location'):</p>
            <div style="min-width: 160px;">
                @if (empty($transaction->location_id))
                    @if (count($business_locations) > 1)
                        {!! Form::select(
                            'select_location_id',
                            $business_locations,
                            $default_location->id ?? null,
                            ['class' => 'form-control input-sm', 'id' => 'select_location_id', 'required', 'autofocus'],
                            $bl_attributes,
                        ) !!}
                    @else
                        <span class="tw-font-semibold tw-text-gray-800">{{ $default_location->name }}</span>
                    @endif
                @else
                    <span class="tw-font-semibold tw-text-gray-800">{{ $transaction->location->name }}</span>
                @endif
            </div>
            <div class="pi-datetime-pill tw-hidden md:tw-inline-flex">
                <i class="fa fa-calendar-alt"></i>
                <span class="curr_datetime">{{ @format_datetime('now') }}</span>
                <i class="fa fa-keyboard hover-q" aria-hidden="true" data-container="body"
                    data-toggle="popover" data-placement="bottom" data-content="@include('sale_pos.partials.keyboard_shortcuts_details')"
                    data-html="true" data-trigger="hover" title=""></i>
            </div>

            {{-- PosInnova: who is at the till — current cashier's name --}}
            <div class="pi-cashier-pill" title="@lang('lang_v1.signed_in_as')">
                <i class="fa fa-user-circle"></i>
                <span>{{ trim(preg_replace('/\s+/', ' ', auth()->user()->user_full_name)) }}</span>
            </div>

            {{-- PosInnova: pending drafts indicator — opens Recent Transactions on the Draft tab --}}
            @if (!empty($pos_draft_count) && (!isset($pos_settings['hide_recent_trans']) || $pos_settings['hide_recent_trans'] == 0))
                <button type="button" id="pos_draft_indicator" class="pi-draft-indicator"
                    title="@lang('lang_v1.draft_indicator_help')">
                    <i class="fa fa-file-alt"></i>
                    <span class="pi-draft-count">{{ $pos_draft_count }}</span>
                    <span>@lang('lang_v1.drafts_pending')</span>
                </button>
            @endif

            @if (!empty($shift_enabled) && !empty($current_shift))
                {{-- PosInnova hospital shifts: current shift badge + live countdown (just after the keyboard shortcut icon) --}}
                <span id="pos_shift_badge"
                    class="tw-inline-flex tw-items-center tw-gap-1.5 tw-px-3 tw-h-10 tw-rounded-lg tw-text-white tw-font-semibold tw-text-xs tw-whitespace-nowrap"
                    style="{{ $current_shift == 'morning' ? 'background:#f39c12;' : 'background:#34495e;' }}"
                    data-shift="{{ $current_shift }}"
                    data-ends-at="{{ $shift_ends_at }}"
                    data-server-now="{{ $shift_server_now }}"
                    data-extended="{{ !empty($shift_extended) ? 1 : 0 }}">
                    @if ($current_shift == 'morning')<i class="fa fa-sun-o"></i>@else<i class="fa fa-moon-o"></i>@endif
                    <span>{{ $current_shift_label }}</span>
                    @if (!empty($current_shift_start) && !empty($current_shift_end))
                        <span class="tw-font-normal tw-opacity-90">{{ $current_shift_start }}–{{ $current_shift_end }}</span>
                    @endif
                    <span id="pos_shift_countdown"></span>
                </span>
            @endif

            {{-- PosInnova Stock Doctor: detect & heal display/purchase stock drift
                 that blocks sales ("Mismatch between sold and purchase quantity").
                 Placed right after the shift badge so it is always visible. --}}
            <button type="button" id="stock_doctor_btn" title="@lang('lang_v1.stock_doctor')"
                class="tw-relative tw-inline-flex tw-items-center tw-gap-1.5 tw-px-3 tw-h-10 tw-rounded-lg tw-whitespace-nowrap tw-cursor-pointer"
                style="display:none; background:#fff; border:1px solid #EF4B53; color:#EF4B53; font-weight:600; font-size:12px; line-height:1;">
                <i class="fa fa-stethoscope" style="color:#EF4B53;"></i>
                <span>@lang('lang_v1.stock_doctor')</span>
                <span id="stock_doctor_badge"
                    style="display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px; padding:0 5px; border-radius:999px; background:#EF4B53; color:#fff; font-size:10px; font-weight:700; line-height:1;"></span>
            </button>

            @if (empty($pos_settings['hide_product_suggestion']))
                <button type="button" title="{{ __('lang_v1.view_products') }}" data-placement="bottom"
                    class="{{ $pi_icon_btn }} tw-block md:tw-hidden btn-modal"
                    data-toggle="modal" data-target="#mobile_product_suggestion_modal">
                    <i class="fa fa-cubes tw-text-[#00935F]"></i>
                </button>
            @endif

            <span class="tw-block md:tw-hidden">
                <i class="fas hamburger fa-bars tw-mx-3 tw-cursor-pointer"
                    onclick="document.getElementById('pos_header_more_options').classList.toggle('tw-hidden')"></i>
            </span>
        </div>

        {{-- RIGHT: Icon shortcut group --}}
        <div class="pi-icon-group tw-hidden md:tw-flex tw-flex-nowrap tw-items-center tw-justify-end tw-gap-1.5 tw-overflow-x-auto"
            id="pos_header_more_options">

            <a href="{{ $go_back_url }}" title="{{ __('lang_v1.go_back') }}" class="{{ $pi_icon_btn }}">
                <i class="fa fa-chevron-left tw-text-[#009EE4]"></i>
            </a>

            @if (!isset($pos_settings['hide_recent_trans']) || $pos_settings['hide_recent_trans'] == 0)
                <button type="button" class="{{ $pi_icon_btn }} md:tw-hidden"
                    data-toggle="modal" data-target="#recent_transactions_modal" id="recent-transactions"
                    title="{{ __('lang_v1.recent_transactions') }}">
                    <i class="fa fa-clock tw-text-[#646EE4]"></i>
                </button>
            @endif

            @if (!empty($pos_settings['inline_service_staff']))
                <button type="button" id="show_service_staff_availability"
                    title="{{ __('lang_v1.service_staff_availability') }}"
                    class="{{ $pi_icon_btn }}"
                    data-container=".view_modal"
                    data-href="{{ action([\App\Http\Controllers\SellPosController::class, 'showServiceStaffAvailibility']) }}">
                    <i class="fa fa-users tw-text-[#646EE4]"></i>
                </button>
            @endif

            <button type="button" class="{{ $pi_icon_btn }}"
                id="return_sale" title="@lang('lang_v1.sell_return')" data-toggle="popover" data-trigger="click"
                data-content='<div class="m-8"><input type="text" class="form-control" placeholder="@lang('sale.invoice_no')" id="send_for_sell_return_invoice_no"></div><div class="w-100 text-center"><button type="button" class="tw-dw-btn tw-dw-btn-error tw-text-white tw-dw-btn-sm" id="send_for_sell_return">@lang('lang_v1.send')</button></div>'
                data-html="true" data-placement="bottom">
                <i class="fas fa-undo tw-text-[#EF4B53]"></i>
            </button>

            <button type="button" id="view_suspended_sales" title="{{ __('lang_v1.view_suspended_sales') }}"
                class="{{ $pi_icon_btn }} btn-modal"
                data-container=".view_modal" data-href="{{ $view_suspended_sell_url }}">
                <i class="fa fa-pause-circle tw-text-[#F59E0B]"></i>
            </button>

            @can('view_cash_register')
                <button type="button" id="register_details" title="{{ __('cash_register.register_details') }}"
                    class="{{ $pi_icon_btn }} btn-modal"
                    data-container=".register_details_modal"
                    data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getRegisterDetails']) }}">
                    <i class="fa fa-briefcase tw-text-[#00935F]" aria-hidden="true"></i>
                </button>
            @endcan

            @can('close_cash_register')
                <button type="button" id="close_register" title="{{ __('cash_register.close_register') }}"
                    class="{{ $pi_icon_btn }} btn-modal"
                    data-container=".close_register_modal"
                    data-href="{{ action([\App\Http\Controllers\CashRegisterController::class, 'getCloseRegister']) }}">
                    <i class="fa fa-window-close tw-text-[#EF4B53]"></i>
                </button>
            @endcan

            @if (
                !empty($pos_settings['inline_service_staff']) ||
                    (in_array('tables', $enabled_modules) || in_array('service_staff', $enabled_modules)))
                <button type="button"
                    class="{{ $pi_icon_btn }} popover-default"
                    id="service_staff_replacement" title="{{ __('restaurant.service_staff_replacement') }}"
                    data-toggle="popover" data-trigger="click"
                    data-content='<div class="m-8"><input type="text" class="form-control" placeholder="@lang('sale.invoice_no')" id="send_for_sell_service_staff_invoice_no"></div><div class="w-100 text-center"><button type="button" class="tw-dw-btn tw-dw-btn-xs tw-dw-btn-outline tw-dw-btn-error" id="send_for_sercice_staff_replacement">@lang('lang_v1.send')</button></div>'
                    data-html="true" data-placement="bottom">
                    <i class="fa fa-user-plus tw-text-[#646EE4]"></i>
                </button>
            @endif

            <button title="@lang('lang_v1.calculator')" id="btnCalculator" type="button"
                class="{{ $pi_icon_btn }} popover-default"
                data-toggle="popover" data-trigger="click" data-content='@include('layouts.partials.calculator')' data-html="true"
                data-placement="bottom">
                <i class="fa fa-calculator tw-text-[#00935F]" aria-hidden="true"></i>
            </button>

            <button type="button" title="{{ __('lang_v1.full_screen') }}"
                class="{{ $pi_icon_btn }}"
                id="full_screen">
                <i class="fa fa-expand tw-text-[#646EE4]"></i>
            </button>

            @if (!empty($pos_settings['customer_display_screen']))
                <a href="{{route('pos_display')}}" id="customer_display_screen"
                    onclick="window.open(this.href, 'customer_display', 'width='+screen.width+',height='+screen.height+',top=0,left=0'); return false;"
                    title="{{ __('lang_v1.customer_display_screen') }}"
                    class="{{ $pi_icon_btn }}">
                    <i class="fa fa-tv tw-text-[#646EE4]"></i>
                </a>
            @endif

            @if (Module::has('Repair') && $transaction_sub_type != 'repair')
                @include('repair::layouts.partials.pos_header')
            @endif

            {{-- PosInnova: quick links to Products list & Add Purchase --}}
            @can('product.view')
                <a href="{{ action([\App\Http\Controllers\ProductController::class, 'index']) }}"
                    target="_blank" rel="noopener"
                    title="@lang('lang_v1.products')"
                    class="{{ $pi_icon_btn }}">
                    <i class="fa fa-cubes tw-text-[#00935F]"></i>
                </a>
            @endcan

            @can('purchase.create')
                <a href="{{ action([\App\Http\Controllers\PurchaseController::class, 'create']) }}"
                    target="_blank" rel="noopener"
                    title="@lang('purchase.add_purchase')"
                    class="{{ $pi_icon_btn }}">
                    <i class="fa fa-cart-plus tw-text-[#4f5bd5]"></i>
                </a>
            @endcan

            @if (in_array('pos_sale', $enabled_modules) && !empty($transaction_sub_type))
                @can('sell.create')
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}"
                        title="@lang('sale.pos_sale')"
                        class="pi-add-expense-btn">
                        <i class="fa fa-th-large tw-text-[#00935F]"></i>
                        <span>@lang('sale.pos_sale')</span>
                    </a>
                @endcan
            @endif

            @can('expense.add')
                <button type="button" title="{{ __('expense.add_expense') }}" data-placement="bottom"
                    class="pi-add-expense-btn btn-modal"
                    id="add_expense">
                    <i class="fas fa-minus-circle"></i>
                    <span>@lang('expense.add_expense')</span>
                </button>
            @endcan

            {{-- PosInnova: sign out — same logout action as the main header --}}
            <a href="{{ action([\App\Http\Controllers\Auth\LoginController::class, 'logout']) }}"
                title="@lang('lang_v1.sign_out')"
                class="{{ $pi_icon_btn }}">
                <i class="fas fa-sign-out-alt tw-text-[#EF4B53]"></i>
            </a>

        </div>
    </div>
</div>

<div class="modal fade" id="service_staff_modal" tabindex="-1" role="dialog"
    aria-labelledby="gridSystemModalLabel">
</div>
