@php
    $is_mobile = isMobile();

    // PosInnova: the keyboard hint printed on a button comes from the business's
    // own shortcut settings (business.keyboard_shortcuts, decoded into
    // $shortcuts by SellPosController), never from a hardcoded list. That way
    // the chip can only ever show the key Mousetrap is actually bound to in
    // sale_pos/partials/keyboard_shortcuts.blade.php. A business that has
    // cleared a shortcut simply gets no chip.
    $pi_shortcuts = $shortcuts['pos'] ?? [];
    $pi_key = function ($name) use ($pi_shortcuts) {
        return empty($pi_shortcuts[$name]) ? null : strtoupper($pi_shortcuts[$name]);
    };
@endphp
<div class="row">
    <div class="pos-form-actions pi-actbar tw-bg-white">
        <div
            class="pi-actbar-in tw-flex tw-items-center tw-justify-between tw-flex-col sm:tw-flex-row md:tw-flex-row lg:tw-flex-row xl:tw-flex-row tw-overflow-x-auto tw-w-full">

            <div class="md:!tw-w-none !tw-flex md:!tw-hidden !tw-flex-row !tw-items-center !tw-gap-3">
                <div class="tw-pos-total pi-pay tw-flex tw-items-center">
                    <div class="pi-pay-k">
                        <div>@lang('sale.total_payable')</div>
                    </div>
                    <input type="hidden" name="final_total" id="final_total_input" value="0.00">
                    <span id="total_payable" class="number">0.00</span>
                </div>
            </div>

            <div class="!tw-w-full md:!tw-w-none !tw-flex md:!tw-hidden !tw-flex-row !tw-items-center !tw-gap-3">
                @if (!Gate::check('disable_pay_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    <button type="button"
                        class="pi-act pi-act-solid pi-act-pay tw-flex no-print @if ($pos_settings['disable_pay_checkout'] != 0) hide @endif"
                        id="pos-finalize" title="@lang('lang_v1.tooltip_checkout_multi_pay')"><i class="fas fa-money-check-alt"
                            aria-hidden="true"></i> @lang('lang_v1.checkout_multi_pay') </button>
                @endif

                @if (!Gate::check('disable_express_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    <button type="button"
                        class="pi-act pi-act-solid pi-act-cash tw-flex no-print @if ($pos_settings['disable_express_checkout'] != 0 || !array_key_exists('cash', $payment_types)) hide @endif pos-express-finalize @if ($is_mobile) col-xs-6 @endif"
                        data-pay_method="cash" title="@lang('tooltip.express_checkout')"> <i class="fas fa-money-bill-alt"
                            aria-hidden="true"></i> @lang('lang_v1.express_checkout_cash')</button>
                @endif
                <button type="button"
                    class="pi-act pi-act-solid pi-act-preview tw-flex"
                    id="pos-preview-invoice" title="@lang('lang_v1.preview_invoice')"><i class="fas fa-eye"></i> @lang('lang_v1.preview_invoice')</button>
                @if (empty($edit))
                    <button type="button" class="pi-act pi-act-solid pi-act-cancel tw-flex" id="pos-cancel"> <i
                            class="fas fa-window-close"></i> @lang('sale.cancel')</button>
                @else
                    <button type="button" class="pi-act pi-act-solid pi-act-cancel tw-flex hide" id="pos-delete"
                        @if (!empty($only_payment)) disabled @endif> <i class="fas fa-trash-alt"></i>
                        @lang('messages.delete')</button>
                @endif
            </div>
            <div class="tw-flex tw-items-center tw-gap-4 tw-flex-row tw-overflow-x-auto">

                @if (!Gate::check('disable_draft') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    <button type="button"
                        class="pi-act tw-flex @if ($pos_settings['disable_draft'] != 0) hide @endif"
                        id="pos-draft" @if (!empty($only_payment)) disabled @endif><i
                            class="fas fa-edit tw-text-[#009ce4]"></i> @lang('sale.draft')
                        @if ($pi_key('draft'))<span class="pi-act-key">{{ $pi_key('draft') }}</span>@endif
                    </button>
                @endif

                @if (!Gate::check('disable_quotation') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    <button type="button"
                        class="pi-act tw-flex @if ($is_mobile) col-xs-6 @endif"
                        id="pos-quotation" @if (!empty($only_payment)) disabled @endif><i
                            class="fas fa-edit tw-text-[#E7A500]"></i> @lang('lang_v1.quotation')</button>
                @endif

                @if (!Gate::check('disable_suspend_sale') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    @if (empty($pos_settings['disable_suspend']))
                        <button type="button"
                            class="pi-act tw-flex no-print pos-express-finalize"
                            data-pay_method="suspend" title="@lang('lang_v1.tooltip_suspend')"
                            @if (!empty($only_payment)) disabled @endif>
                            <i class="fas fa-pause tw-text-[#EF4B51]" aria-hidden="true"></i>
                            @lang('lang_v1.suspend')
                        </button>
                    @endif
                @endif

                @if (!Gate::check('disable_credit_sale') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    @if (empty($pos_settings['disable_credit_sale_button']))
                        <input type="hidden" name="is_credit_sale" value="0" id="is_credit_sale">
                        <button type="button"
                            class="pi-act tw-flex no-print pos-express-finalize @if ($is_mobile) col-xs-6 @endif"
                            data-pay_method="credit_sale" title="@lang('lang_v1.tooltip_credit_sale')"
                            @if (!empty($only_payment)) disabled @endif>
                            <i class="fas fa-check tw-text-[#5E5CA8]" aria-hidden="true"></i> @lang('lang_v1.credit_sale')
                        </button>
                    @endif
                @endif
                @if (!Gate::check('disable_card') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    <button type="button"
                        class="pi-act tw-flex no-print pos-express-finalize @if (!array_key_exists('card', $payment_types)) hide @endif @if ($is_mobile) col-xs-6 @endif"
                        data-pay_method="card" title="@lang('lang_v1.tooltip_express_checkout_card')">
                        <i class="fas fa-credit-card tw-text-[#D61B60]" aria-hidden="true"></i> @lang('lang_v1.express_checkout_card')
                    </button>
                @endif

                @if (!Gate::check('disable_pay_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    <button type="button"
                        class="pi-act pi-act-solid pi-act-pay tw-hidden md:tw-flex no-print @if ($pos_settings['disable_pay_checkout'] != 0) hide @endif"
                        id="pos-finalize" title="@lang('lang_v1.tooltip_checkout_multi_pay')"><i class="fas fa-money-check-alt"
                            aria-hidden="true"></i> @lang('lang_v1.checkout_multi_pay')
                        @if ($pi_key('pay_n_ckeckout'))<span class="pi-act-key">{{ $pi_key('pay_n_ckeckout') }}</span>@endif
                    </button>
                @endif

                @if (!Gate::check('disable_express_checkout') || auth()->user()->can('superadmin') || auth()->user()->can('admin'))
                    <button type="button"
                        class="pi-act pi-act-solid pi-act-cash tw-hidden md:tw-flex no-print @if ($pos_settings['disable_express_checkout'] != 0 || !array_key_exists('cash', $payment_types)) hide @endif pos-express-finalize"
                        data-pay_method="cash" title="@lang('tooltip.express_checkout')"> <i class="fas fa-money-bill-alt"
                            aria-hidden="true"></i> @lang('lang_v1.express_checkout_cash')
                        @if ($pi_key('express_checkout'))<span class="pi-act-key">{{ $pi_key('express_checkout') }}</span>@endif
                    </button>
                @endif


                <button type="button"
                    class="pi-act pi-act-solid pi-act-preview tw-hidden md:tw-flex"
                    id="pos-preview-invoice" title="@lang('lang_v1.preview_invoice')"><i class="fas fa-eye"></i> @lang('lang_v1.preview_invoice')</button>

                @if (empty($edit))
                    <button type="button"
                        class="pi-act pi-act-solid pi-act-cancel tw-hidden md:tw-flex"
                        id="pos-cancel"> <i class="fas fa-window-close"></i> @lang('sale.cancel')
                        @if ($pi_key('cancel'))<span class="pi-act-key">{{ $pi_key('cancel') }}</span>@endif
                    </button>
                @else
                    <button type="button"
                        class="pi-act pi-act-solid pi-act-cancel tw-hidden md:tw-flex hide"
                        id="pos-delete" @if (!empty($only_payment)) disabled @endif> <i
                            class="fas fa-trash-alt"></i> @lang('messages.delete')</button>
                @endif

                @if (!$is_mobile)
                    {{-- <div class="bg-navy pos-total text-white ">
					<span class="text">@lang('sale.total_payable')</span>
					<input type="hidden" name="final_total" 
												id="final_total_input" value=0>
					<span id="total_payable" class="number">0</span>
					</div> --}}
                    <div class="pos-total pi-pay md:tw-flex md:tw-items-center tw-hidden">
                        <div class="pi-pay-k">
                            <div>@lang('sale.total')</div>
                            <div>@lang('lang_v1.payable')</div>
                        </div>
                        <input type="hidden" name="final_total" id="final_total_input" value="0.00">
                        {{-- No currency symbol here on purpose: pos.js multiplies
                             this figure by #exchange_rate, so the session symbol
                             would be wrong on any sale priced in another
                             currency. The number stands alone. --}}
                        <span id="total_payable" class="number">0.00</span>
                    </div>
                @endif
            </div>

            <div class="tw-w-full md:tw-w-fit tw-flex tw-flex-col tw-items-end tw-gap-3 tw-hidden md:tw-block">
                @if (!isset($pos_settings['hide_recent_trans']) || $pos_settings['hide_recent_trans'] == 0)
                    <button type="button"
                        class="pi-act pi-act-recent tw-inline-flex tw-w-full md:tw-w-auto"
                        data-toggle="modal" data-target="#recent_transactions_modal" id="recent-transactions"> <i
                            class="fas fa-clock"></i> @lang('lang_v1.recent_transactions')</button>
                @endif
            </div>
        </div>
    </div>
</div>
@if (isset($transaction))
    @include('sale_pos.partials.edit_discount_modal', [
        'sales_discount' => $transaction->discount_amount,
        'discount_type' => $transaction->discount_type,
        'rp_redeemed' => $transaction->rp_redeemed,
        'rp_redeemed_amount' => $transaction->rp_redeemed_amount,
        'max_available' => !empty($redeem_details['points']) ? $redeem_details['points'] : 0,
    ])
@else
    @include('sale_pos.partials.edit_discount_modal', [
        'sales_discount' => $business_details->default_sales_discount,
        'discount_type' => 'percentage',
        'rp_redeemed' => 0,
        'rp_redeemed_amount' => 0,
        'max_available' => 0,
    ])
@endif

@if (isset($transaction))
    @include('sale_pos.partials.edit_order_tax_modal', ['selected_tax' => $transaction->tax_id])
@else
    @include('sale_pos.partials.edit_order_tax_modal', [
        'selected_tax' => $business_details->default_sales_tax,
    ])
@endif

@include('sale_pos.partials.edit_shipping_modal')
