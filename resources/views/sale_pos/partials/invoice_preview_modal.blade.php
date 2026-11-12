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
                {{-- PosInnova: "check before recording" - the totals, and a tick before the sale can be finalized --}}
                <div id="pos_preview_check" class="hide" style="text-align:left; margin-bottom:10px;">
                    <div style="display:flex; flex-wrap:wrap; gap:6px 18px; align-items:baseline; background:#eef6fc; border-radius:8px; padding:10px 14px; margin-bottom:8px;">
                        <span>@lang('lang_v1.rc_amount_sale'): <strong id="pos_preview_total" style="font-size:22px; color:#0f172a;"></strong></span>
                        <span>@lang('lang_v1.total_paying'): <strong id="pos_preview_paying"></strong></span>
                        <span id="pos_preview_change_wrap">@lang('lang_v1.change_return'): <strong id="pos_preview_change"></strong></span>
                        <span id="pos_preview_due_wrap" class="text-danger">@lang('lang_v1.balance_due'): <strong id="pos_preview_due"></strong></span>
                    </div>
                    <label for="pos_preview_checkbox" style="display:flex; gap:10px; align-items:flex-start; font-weight:600; cursor:pointer; margin:0; padding:10px 12px; border:1px dashed #cbd5e1; border-radius:8px;">
                        <input type="checkbox" id="pos_preview_checkbox" style="width:18px; height:18px; margin:1px 0 0; flex-shrink:0;">
                        <span>@lang('lang_v1.rc_check')</span>
                    </label>
                </div>
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
