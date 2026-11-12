{{--
    PosInnova: "Check this before it is recorded" (public/js/record-confirm.js).
    The dialog, its texts per kind of operation, and the switches from Business
    settings (common_settings: record_confirm_off turns it all off,
    record_confirm_off_pos only for POS sales). On unless turned off.
--}}
@php
    $rc_settings = (array) session('business.common_settings', []);
    $rc_kinds = [];
    foreach (['default', 'payment', 'payment_edit', 'pay_due', 'sale', 'sell', 'purchase', 'expense', 'sell_return', 'purchase_return',
              'register_open', 'register_close', 'transfer', 'deposit', 'checkin', 'opening_balance', 'stock_adjustment', 'clinic_pay', 'account_edit', 'ledger_discount', 'claim_settle', 'plan', 'plan_bill'] as $rc_k) {
        $rc_kinds[$rc_k] = [
            'title' => \Illuminate\Support\Facades\Lang::has('lang_v1.rc_title_' . $rc_k) ? __('lang_v1.rc_title_' . $rc_k) : null,
            'intro' => \Illuminate\Support\Facades\Lang::has('lang_v1.rc_intro_' . $rc_k) ? __('lang_v1.rc_intro_' . $rc_k) : null,
            'amount' => \Illuminate\Support\Facades\Lang::has('lang_v1.rc_amount_' . $rc_k) ? __('lang_v1.rc_amount_' . $rc_k) : null,
            'yes' => \Illuminate\Support\Facades\Lang::has('lang_v1.rc_yes_' . $rc_k) ? __('lang_v1.rc_yes_' . $rc_k) : null,
        ];
        $rc_kinds[$rc_k] = array_filter($rc_kinds[$rc_k]);
    }
    $rc_config = [
        'enabled' => empty($rc_settings['record_confirm_off']),
        'off' => ['sale' => ! empty($rc_settings['record_confirm_off_pos'])],
        'texts' => [
            'title' => __('lang_v1.rc_title'),
            'intro' => __('lang_v1.rc_intro'),
            'amount' => __('lang_v1.rc_amount'),
            'check' => __('lang_v1.rc_check'),
            'yes_record' => __('lang_v1.rc_yes'),
            'yes' => __('messages.yes'),
            'no' => __('messages.no'),
            // labels the scripts use in summaries
            'customer' => __('contact.customer'),
            'supplier' => __('purchase.supplier'),
            'paid' => __('lang_v1.rc_amount_payment'),
            'method' => __('purchase.payment_method'),
            'account' => __('lang_v1.payment_account'),
            'date' => __('lang_v1.date'),
            'location' => __('purchase.business_location'),
            'due' => __('lang_v1.balance_due'),
            'ref' => __('purchase.ref_no'),
            'items' => __('lang_v1.rc_items'),
            'status' => __('sale.status'),
            'note' => __('brand.note'),
            'category' => __('expense.expense_category'),
            // the full preview's sections
            'sec_details' => __('lang_v1.rc_sec_details'),
            'sec_products' => __('lang_v1.rc_sec_products'),
            'sec_totals' => __('lang_v1.rc_sec_totals'),
            'sec_payment' => __('lang_v1.rc_sec_payment'),
            'lines_units' => __('lang_v1.rc_lines_units'),
            'nothing_paid' => __('lang_v1.rc_nothing_paid'),
            'goes_to' => __('lang_v1.rc_goes_to'),
            'expense' => __('lang_v1.rc_expense'),
            'none' => __('lang_v1.rc_none'),
            // purchase preview labels (the same words as the purchase screen)
            'p_date' => __('purchase.purchase_date'),
            'p_status' => __('purchase.purchase_status'),
            'p_pay_term' => __('contact.pay_term'),
            'p_exchange' => __('purchase.p_exchange_rate'),
            'p_product' => __('product.product_name'),
            'p_qty' => __('lang_v1.quantity'),
            'p_cost_before_disc' => __('lang_v1.unit_cost_before_discount'),
            'p_disc_pct' => __('lang_v1.discount_percent'),
            'p_cost_before_tax' => __('purchase.unit_cost_before_tax'),
            'p_product_tax' => __('purchase.product_tax'),
            'p_cost_after_tax' => __('purchase.unit_cost_after_tax'),
            'p_line_total' => __('purchase.line_total'),
            'p_selling_price' => __('lang_v1.selling_price'),
            'p_lot' => __('lang_v1.lot_number'),
            'p_mfg' => __('product.mfg_date'),
            'p_exp' => __('product.exp_date'),
            'p_total_qty' => __('lang_v1.total_quantity'),
            'p_net_total' => __('purchase.net_total_amount'),
            'p_discount' => __('purchase.discount'),
            'p_tax' => __('purchase.purchase_tax'),
            'p_shipping_details' => __('purchase.shipping_details'),
            'p_shipping' => __('purchase.additional_shipping_charges'),
            'p_total' => __('purchase.purchase_total'),
            'p_payment_due' => __('purchase.payment_due'),
            'p_advance' => __('lang_v1.advance_balance'),
            'p_paid_on' => __('lang_v1.paid_on'),
            'p_payment_note' => __('purchase.payment_note'),
            'p_notes' => __('purchase.additional_notes'),
        ],
        'kinds' => $rc_kinds,
    ];
@endphp
<div class="modal fade" id="record_confirm_modal" tabindex="-1" role="dialog" aria-labelledby="rc_title" aria-modal="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content rc-content">
            <div class="modal-header rc-head">
                <h4 class="modal-title" id="rc_title"><i class="fas fa-clipboard-check"></i> <span class="rc-title-text"></span></h4>
            </div>
            <div class="modal-body">
                <div class="rc-intro"></div>
                <div class="rc-amount-box">
                    <div class="rc-amount-label"></div>
                    <div class="rc-amount"></div>
                </div>
                <div class="rc-sec-title rc-rows-title"></div>
                <table class="rc-rows rc-main"></table>
                <div class="rc-items-wrap">
                    <div class="rc-sec-title rc-items-title"></div>
                    <div class="rc-items-scroll"><table class="rc-items"></table></div>
                    <div class="rc-items-foot"></div>
                </div>
                <div class="rc-groups"></div>
                <div class="rc-note"></div>
                <label class="rc-check" for="rc_checkbox">
                    <input type="checkbox" id="rc_checkbox">
                    <span class="rc-check-text"></span>
                </label>
            </div>
            <div class="modal-footer rc-foot">
                <button type="button" class="btn btn-default rc-back"><i class="fas fa-arrow-left"></i> @lang('lang_v1.rc_back')</button>
                <button type="button" class="btn btn-success rc-yes" disabled><i class="fas fa-check"></i> <span class="rc-yes-text"></span></button>
            </div>
        </div>
    </div>
</div>
<style>
    #record_confirm_modal { z-index: 1065; }
    #record_confirm_modal + .modal-backdrop, .modal-backdrop.rc-backdrop { z-index: 1060; }
    #record_confirm_modal .modal-dialog { max-width: 520px; }
    #record_confirm_modal .rc-content { border-radius: 10px; overflow: hidden; border: 0; }
    #record_confirm_modal .rc-head { background: #3c8dbc; color: #fff; padding: 12px 18px; border: 0; }
    #record_confirm_modal .rc-head .modal-title { font-size: 18px; font-weight: 600; color: #fff; }
    #record_confirm_modal .rc-intro { background: #eef6fc; color: #1e5a8a; border-radius: 8px; padding: 10px 14px; font-size: 14px; line-height: 1.5; margin-bottom: 14px; }
    #record_confirm_modal .rc-amount-box { text-align: center; margin: 4px 0 12px; }
    #record_confirm_modal .rc-amount-label { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #64748b; }
    #record_confirm_modal .rc-amount { font-size: 32px; font-weight: 700; color: #0f172a; line-height: 1.2; }
    #record_confirm_modal .rc-rows { width: 100%; margin-bottom: 10px; font-size: 14px; }
    #record_confirm_modal .rc-rows th { color: #64748b; font-weight: normal; padding: 6px 8px 6px 0; width: 38%; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    #record_confirm_modal .rc-rows td { color: #0f172a; font-weight: 600; padding: 6px 0; border-bottom: 1px solid #f1f5f9; word-break: break-word; }
    #record_confirm_modal .rc-note { display: none; background: #fffbeb; border-left: 4px solid #f59e0b; padding: 8px 12px; border-radius: 6px; font-size: 13px; margin-bottom: 10px; }
    #record_confirm_modal .rc-check { display: flex; gap: 10px; align-items: flex-start; font-weight: 600; font-size: 14px; color: #0f172a; cursor: pointer; margin: 6px 0 0; padding: 10px 12px; border: 1px dashed #cbd5e1; border-radius: 8px; }
    #record_confirm_modal .rc-check input { width: 18px; height: 18px; margin: 1px 0 0; flex-shrink: 0; cursor: pointer; }
    #record_confirm_modal .rc-foot { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
    #record_confirm_modal .rc-foot .btn { min-height: 38px; }
    #record_confirm_modal .rc-yes[disabled] { opacity: .5; cursor: not-allowed; }
    /* the full preview (purchases): wider, details in two columns, a product table, totals beside payment */
    #record_confirm_modal.rc-wide .modal-dialog { max-width: 980px; width: auto; margin-left: auto; margin-right: auto; }
    @media (min-width: 768px) { #record_confirm_modal.rc-wide .modal-dialog { width: 94%; } }
    #record_confirm_modal .rc-sec-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #3c8dbc; margin: 14px 0 6px; padding-bottom: 4px; border-bottom: 2px solid #eef6fc; }
    #record_confirm_modal .rc-sec-title:empty { display: none; }
    #record_confirm_modal .rc-rows tr.rc-strong th, #record_confirm_modal .rc-rows tr.rc-strong td { font-size: 16px; font-weight: 700; color: #0f172a; border-top: 2px solid #cbd5e1; }
    #record_confirm_modal .rc-rows tr.rc-minus td { color: #b91c1c; }
    #record_confirm_modal .rc-rows tr.rc-warn td { color: #b45309; }
    #record_confirm_modal .rc-rows td.rc-num { text-align: right; white-space: nowrap; }
    @media (min-width: 768px) {
        #record_confirm_modal.rc-wide .rc-rows.rc-main { display: grid; grid-template-columns: 1fr 1fr; column-gap: 28px; }
        #record_confirm_modal.rc-wide .rc-rows.rc-main tr { display: flex; }
        #record_confirm_modal.rc-wide .rc-rows.rc-main th { flex: 0 0 42%; }
        #record_confirm_modal.rc-wide .rc-rows.rc-main td { flex: 1 1 auto; }
        #record_confirm_modal .rc-groups { display: grid; grid-template-columns: 1fr 1fr; column-gap: 28px; }
    }
    #record_confirm_modal .rc-items-wrap { display: none; }
    #record_confirm_modal .rc-items-scroll { max-height: 340px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
    #record_confirm_modal .rc-items { width: 100%; font-size: 13px; border-collapse: collapse; }
    #record_confirm_modal .rc-items th { position: sticky; top: 0; background: #f8fafc; color: #475569; font-weight: 600; padding: 7px 8px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; text-align: left; z-index: 1; }
    #record_confirm_modal .rc-items td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; color: #0f172a; vertical-align: top; }
    #record_confirm_modal .rc-items tr:last-child td { border-bottom: 0; }
    #record_confirm_modal .rc-items .rc-num { text-align: right; white-space: nowrap; }
    #record_confirm_modal .rc-items td.rc-strong { font-weight: 700; }
    #record_confirm_modal .rc-items .rc-sub { display: block; font-size: 11.5px; color: #64748b; margin-top: 2px; }
    #record_confirm_modal .rc-items-foot { font-size: 12.5px; color: #64748b; text-align: right; margin-top: 4px; }
    #record_confirm_modal .rc-items-foot:empty { display: none; }
    @media (max-width: 480px) {
        #record_confirm_modal .rc-foot .btn { flex: 1 1 100%; margin: 0 !important; }
        #record_confirm_modal .rc-amount { font-size: 28px; }
    }
</style>
<script>
    window.PosConfirmConfig = @json($rc_config);
</script>
<script src="{{ asset('js/record-confirm.js?v=' . $asset_v) }}"></script>
