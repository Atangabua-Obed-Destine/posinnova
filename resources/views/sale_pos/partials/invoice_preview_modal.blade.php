<div class="modal fade" tabindex="-1" role="dialog" id="invoice_preview_modal" aria-labelledby="invoice_preview_label">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="invoice_preview_label">
                    <i class="fa fa-eye"></i> @lang('lang_v1.invoice_preview')
                </h4>
                <small class="text-muted" id="invoice_preview_notice" data-preview="{{ __('lang_v1.preview_only_notice') }}" data-confirm="{{ __('lang_v1.review_before_finalize_notice') }}" data-alert="{{ __('lang_v1.finalize_saves_notice') }}">@lang('lang_v1.preview_only_notice')</small>
            </div>
            <div class="modal-body" id="invoice_preview_body">
                <!-- Filled by JS -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal" id="pos-preview-cancel">
                    <i class="fa fa-pencil-alt"></i> @lang('messages.close')
                </button>
                <button type="button" class="btn btn-primary hide" id="pos-preview-confirm-finalize">
                    <i class="fa fa-check"></i> @lang('lang_v1.confirm_and_finalize')
                </button>
            </div>
        </div>
    </div>
</div>
