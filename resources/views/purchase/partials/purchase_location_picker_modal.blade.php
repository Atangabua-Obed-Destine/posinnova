{{-- PosInnova: per-line destination location picker for purchases.
Click-based list (mirrors the POS picker) so the user just taps a location instead of using a dropdown. --}}
<div class="modal fade" id="pi_purchase_location_picker_modal" tabindex="-1" role="dialog" aria-labelledby="piPurchaseLocLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="piPurchaseLocLabel">
                    <i class="fa fa-map-marker"></i>
                    @lang('purchase.business_location')
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted small">@lang('lang_v1.select_business_location')</p>
                <h5 id="pi_purchase_loc_product_name" style="margin-top:0;font-weight:600;"></h5>
                <div id="pi_purchase_loc_list" class="list-group" style="margin-top:10px;"></div>
                {{-- internal state --}}
                <input type="hidden" id="pi_pending_product_id">
                <input type="hidden" id="pi_pending_variation_id">
                <input type="hidden" id="pi_editing_row_index" value="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
            </div>
        </div>
    </div>
</div>
