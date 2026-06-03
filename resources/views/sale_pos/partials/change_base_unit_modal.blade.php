{{-- PosInnova: shared modal for changing a product's base unit from POS --}}
<div class="modal fade" id="change_base_unit_modal" tabindex="-1" role="dialog" aria-labelledby="change_base_unit_label">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="change_base_unit_label">
                    <i class="fas fa-pencil-alt"></i> @lang('lang_v1.change_base_unit')
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted small">@lang('lang_v1.change_base_unit_help')</p>
                <div class="form-group">
                    <label>@lang('lang_v1.current_unit'):</label>
                    <span class="text-bold" id="cbu_current_unit_name"></span>
                </div>
                <div class="form-group">
                    <label for="cbu_new_unit_id">@lang('lang_v1.select_new_base_unit') <span class="text-danger">*</span></label>
                    <select id="cbu_new_unit_id" class="form-control" style="width:100%">
                        <option value="">@lang('messages.please_select')</option>
                    </select>
                </div>
                <input type="hidden" id="cbu_product_id">
                <input type="hidden" id="cbu_variation_id">
                <input type="hidden" id="cbu_row_index">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                <button type="button" class="btn btn-primary" id="cbu_save_btn">
                    <i class="fa fa-check"></i> @lang('messages.save')
                </button>
            </div>
        </div>
    </div>
</div>
