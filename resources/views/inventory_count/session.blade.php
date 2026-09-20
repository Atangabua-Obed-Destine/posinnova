@extends('layouts.app')
@section('title', __('inventory_count.session_for_location', ['location' => $session->location->name ?? '']))

@section('content')

@php
    $is_draft = $session->status === 'draft';
    $is_editable = $is_draft && $can_create;
@endphp

<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @lang('inventory_count.inventory_count')
        <small class="tw-block tw-text-sm tw-font-normal tw-text-gray-500 tw-mt-1">
            {{ $session->reference_no }} —
            @if($session->location)
                {{ $session->location->name }}
            @endif
            —
            @if($session->status === 'draft')
                <span class="label label-warning">@lang('inventory_count.status_draft')</span>
            @elseif($session->status === 'pending_approval')
                <span class="label label-primary">@lang('inventory_count.status_pending_approval')</span>
            @elseif($session->status === 'posted')
                <span class="label label-success">@lang('inventory_count.status_posted')</span>
            @else
                <span class="label label-default">@lang('inventory_count.status_cancelled')</span>
            @endif
            @if($session->scope_type && $session->scope_type !== 'all')
                <span class="label label-info" title="{{ $session->scope_label }}">
                    @lang('inventory_count.scope_' . $session->scope_type): {{ \Illuminate\Support\Str::limit($session->scope_label, 40) }}
                </span>
            @endif
        </small>
    </h1>
</section>

<section class="content">
    {{-- ============ SESSION RIBBON ============ --}}
    @component('components.widget', ['class' => 'box-primary'])
        <div class="row">
            <div class="col-md-3">
                <strong>@lang('inventory_count.started'):</strong><br>
                {{ $session->started_at->format('Y-m-d H:i') }}<br>
                <small class="text-muted">
                    {{ optional($session->startedBy)->first_name }} {{ optional($session->startedBy)->surname }}
                </small>
            </div>
            <div class="col-md-3">
                <strong>@lang('inventory_count.progress'):</strong><br>
                <span id="ic_counted">{{ $session->lines_counted }}</span>
                / <span id="ic_total">{{ $session->lines_total }}</span> @lang('inventory_count.lines')
                <div class="progress" style="height:6px; margin-top:4px;">
                    <div id="ic_progress_bar" class="progress-bar progress-bar-warning" role="progressbar"
                        style="width: {{ $session->lines_total > 0 ? round(($session->lines_counted / $session->lines_total) * 100) : 0 }}%;"></div>
                </div>
            </div>
            <div class="col-md-3">
                <strong>@lang('inventory_count.status'):</strong><br>
                <span id="ic_autosave_status" class="text-muted">—</span>
            </div>
            <div class="col-md-3 text-right">
                <a href="{{ url('inventory-count?location_id='.$session->location_id) }}" class="btn btn-default btn-sm">
                    <i class="fa fa-arrow-left"></i> @lang('inventory_count.back_to_list')
                </a>
                <a href="{{ url('inventory-count/blank-pdf?session_id='.$session->id) }}"
                   class="btn btn-default btn-sm ic_blank_pdf_btn" target="_blank"
                   title="@lang('inventory_count.download_blank_pdf')">
                    <i class="fa fa-file-pdf-o"></i> @lang('inventory_count.download_blank_pdf')
                </a>
                <a href="{{ url('inventory-count/blank-pdf?session_id='.$session->id.'&blind=1') }}"
                   class="btn btn-default btn-sm ic_blank_pdf_btn" data-blind="1" target="_blank"
                   title="@lang('inventory_count.blind_count_pdf')">
                    <i class="fa fa-eye-slash"></i> @lang('inventory_count.blind_count_pdf')
                </a>
                @if(in_array($session->status, ['posted', 'pending_approval']))
                    <a href="{{ url('inventory-count/'.$session->id.'/variance-pdf') }}"
                       class="btn btn-info btn-sm" target="_blank">
                        <i class="fa fa-file-pdf-o"></i> @lang('inventory_count.download_variance_report')
                    </a>
                @endif
            </div>
        </div>

        @if($session->status === 'draft' && $session->rejection_notes)
            <hr>
            <div class="alert alert-danger" style="margin-bottom:0;">
                <strong><i class="fa fa-times-circle"></i> @lang('inventory_count.previously_rejected'):</strong>
                {{ optional($session->rejected_by)->first_name }} {{ optional($session->rejected_by)->surname }}
                @ {{ optional($session->rejected_at)->format('Y-m-d H:i') }}
                <br>
                <em>{{ $session->rejection_notes }}</em>
            </div>
        @endif

        @if($session->status === 'pending_approval')
            <hr>
            <div class="alert alert-info" style="margin-bottom:0;">
                <strong><i class="fa fa-hourglass-half"></i> @lang('inventory_count.awaiting_approval')</strong> —
                @lang('inventory_count.submitted_at_by', [
                    'at' => optional($session->posted_at)->format('Y-m-d H:i'),
                    'by' => trim(optional($session->postedBy)->first_name.' '.optional($session->postedBy)->surname),
                ])
                @if($session->reason)
                    <br><strong>@lang('inventory_count.reason'):</strong> {{ $reasons[$session->reason] ?? $session->reason }}
                    @if($session->reason === 'other' && $session->reason_other) — {{ $session->reason_other }} @endif
                @endif
                @if($session->notes)
                    <br><em class="text-muted">{{ $session->notes }}</em>
                @endif
                @if($can_approve)
                    <div class="tw-mt-2">
                        <button type="button" id="ic_btn_approve" class="btn btn-success">
                            <i class="fa fa-check"></i> @lang('inventory_count.approve')
                        </button>
                        <button type="button" id="ic_btn_reject" class="btn btn-danger">
                            <i class="fa fa-times"></i> @lang('inventory_count.reject')
                        </button>
                    </div>
                @endif
            </div>
        @endif

        @if($session->status === 'posted')
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <strong>@lang('inventory_count.posted_at'):</strong>
                    {{ optional($session->posted_at)->format('Y-m-d H:i') }}
                    by {{ optional($session->postedBy)->first_name }} {{ optional($session->postedBy)->surname }}
                    @if($session->approved_by)
                        <br>
                        <strong>@lang('inventory_count.approved_at'):</strong>
                        {{ optional($session->approved_at)->format('Y-m-d H:i') }}
                        by {{ optional($session->approvedBy)->first_name }} {{ optional($session->approvedBy)->surname }}
                    @endif
                </div>
                <div class="col-md-3 text-danger">
                    <strong>@lang('inventory_count.shortages'):</strong>
                    {{ number_format($session->shortage_value, 2) }}
                    @if(($variance_summary['shortage_lines'] ?? 0) > 0)
                        <br><small>
                            {{ $variance_summary['shortage_lines'] }} @lang('inventory_count.line_s_short'),
                            −{{ rtrim(rtrim(number_format($variance_summary['shortage_units'], 4), '0'), '.') }} @lang('inventory_count.units')
                        </small>
                    @endif
                    @if($session->shortage_transaction_id)
                        <br><small>@lang('inventory_count.shortage_txn') #{{ $session->shortage_transaction_id }}</small>
                    @endif
                </div>
                <div class="col-md-3 text-success">
                    <strong>@lang('inventory_count.surpluses'):</strong>
                    {{ number_format($session->surplus_value, 2) }}
                    @if(($variance_summary['surplus_lines'] ?? 0) > 0)
                        <br><small>
                            {{ $variance_summary['surplus_lines'] }} @lang('inventory_count.line_s_surplus'),
                            +{{ rtrim(rtrim(number_format($variance_summary['surplus_units'], 4), '0'), '.') }} @lang('inventory_count.units')
                        </small>
                    @endif
                    @if($session->surplus_transaction_id)
                        <br><small>@lang('inventory_count.surplus_txn') #{{ $session->surplus_transaction_id }}</small>
                    @endif
                </div>
            </div>
            @if(($variance_summary['zero_cost_lines'] ?? 0) > 0)
                <div class="alert alert-warning tw-mt-2 tw-mb-0">
                    <i class="fa fa-exclamation-triangle"></i>
                    @lang('inventory_count.zero_cost_warning', ['count' => $variance_summary['zero_cost_lines']])
                </div>
            @endif
        @endif

        @if($session->notes)
            <hr>
            <strong>@lang('inventory_count.reason'):</strong>
            {{ $reasons[$session->reason] ?? $session->reason }}
            @if($session->reason === 'other' && $session->reason_other)
                — {{ $session->reason_other }}
            @endif
            <br>
            <em class="text-muted">{{ $session->notes }}</em>
        @endif
    @endcomponent

    {{-- ============ PHASE 4 FAST DATA ENTRY ============ --}}
    @if($is_editable)
    @component('components.widget', ['class' => 'box-info', 'title' => __('inventory_count.fast_entry')])
        <div class="row">
            <div class="col-md-7">
                <label>@lang('inventory_count.scan_sku')</label>
                <div class="input-group">
                    <input type="text" id="ic_scan_input" class="form-control input-lg"
                           placeholder="@lang('inventory_count.scan_placeholder')"
                           autocomplete="off">
                    <span class="input-group-addon">
                        <label style="margin:0;font-weight:normal;">
                            <input type="checkbox" id="ic_scan_mode_increment"> @lang('inventory_count.scan_mode_increment')
                        </label>
                    </span>
                </div>
                <p class="text-muted tw-mt-1"><small>@lang('inventory_count.scan_help')</small></p>
                <div id="ic_scan_status" class="tw-mt-1"></div>
            </div>
            <div class="col-md-5">
                <label>@lang('inventory_count.csv_import')</label>
                <div>
                    <a href="{{ url('inventory-count/'.$session->id.'/csv-template') }}"
                       class="btn btn-default btn-sm" target="_blank">
                        <i class="fa fa-download"></i> @lang('inventory_count.csv_download_template')
                    </a>
                </div>
                <form id="ic_csv_form" enctype="multipart/form-data" class="tw-mt-2">
                    @csrf
                    <div class="input-group">
                        <input type="file" name="csv_file" id="ic_csv_file" accept=".csv,text/csv" class="form-control">
                        <span class="input-group-btn">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-upload"></i> @lang('inventory_count.csv_upload')
                            </button>
                        </span>
                    </div>
                </form>
                <p class="text-muted tw-mt-1"><small>@lang('inventory_count.csv_help')</small></p>
                <div id="ic_csv_result" class="tw-mt-1"></div>
            </div>
        </div>
    @endcomponent
    @endif

    {{-- ============ FILTERS ============ --}}
    @component('components.widget', ['class' => 'box-default', 'title' => __('report.filters')])
        <div class="row">
            <div class="col-md-4 form-group">
                <label>@lang('lang_v1.search_product')</label>
                <input type="text" class="form-control" id="ic_search_term" placeholder="@lang('lang_v1.search')...">
            </div>
            <div class="col-md-4 form-group">
                <label>@lang('inventory_count.status')</label>
                <select id="ic_filter" class="form-control">
                    <option value="">@lang('inventory_count.all')</option>
                    <option value="uncounted">@lang('inventory_count.uncounted')</option>
                    <option value="counted">@lang('inventory_count.counted')</option>
                    <option value="variance">@lang('inventory_count.variances')</option>
                </select>
            </div>
        </div>
    @endcomponent

    {{-- ============ TABLE ============ --}}
    @component('components.widget', ['class' => 'box-primary', 'title' => __('inventory_count.count_sheet')])
        @slot('tool')
            @if($can_edit_price)
                <label class="tw-inline-flex tw-items-center tw-text-sm tw-font-normal tw-mr-3" title="@lang('lang_v1.prices_include_tax_help')">
                    <input type="checkbox" id="ic_price_inc_tax" class="tw-mr-1"> @lang('lang_v1.prices_include_tax')
                </label>
            @endif
            @if($is_editable && $can_post)
                <button type="button" id="ic_btn_post" class="btn btn-success btn-sm">
                    <i class="fa fa-paper-plane"></i> @lang('inventory_count.submit_for_approval')
                </button>
            @endif
            @if($is_editable)
                <button type="button" id="ic_btn_add_lot" class="btn btn-warning btn-sm"
                        title="@lang('inventory_count.add_lot_help')">
                    <i class="fa fa-calendar-plus-o"></i> @lang('inventory_count.add_lot')
                </button>
            @endif
            @if(in_array($session->status, ['draft', 'pending_approval']) && $can_cancel)
                <button type="button" id="ic_btn_cancel" class="btn btn-danger btn-sm">
                    <i class="fa fa-times"></i> @lang('inventory_count.cancel_session')
                </button>
            @endif
        @endslot

        <div class="table-responsive">
            <table id="ic_session_table" class="table table-striped table-hover" style="width:100%;">
                <thead>
                    <tr>
                        <th>@lang('product.sku')</th>
                        <th>@lang('sale.product')</th>
                        <th>@lang('product.category')</th>
                        <th>@lang('product.unit')</th>
                        @if(session('business.enable_lot_number') == 1 || session('business.enable_product_expiry') == 1)
                            <th>@lang('lang_v1.lot') / @lang('lang_v1.expiry')</th>
                        @endif
                        <th class="text-right">@lang('inventory_count.unit_cost')</th>
                        <th class="text-right">@lang('inventory_count.unit_sell')</th>
                        <th class="text-right">@lang('lang_v1.profit_margin')</th>
                        <th class="text-right">@lang('inventory_count.theoretical_stock')</th>
                        <th class="text-right" style="width:160px;">@lang('product.alert_quantity')</th>
                        <th class="text-right" style="width:120px;">@lang('inventory_count.actual_stock')</th>
                        <th class="text-right">@lang('inventory_count.variance')</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>

{{-- ============ POST MODAL ============ --}}
@if($is_editable && $can_post)
<div class="modal fade" id="ic_post_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">@lang('inventory_count.submit_for_approval_title')</h4>
            </div>
            <div class="modal-body">
                <p>@lang('inventory_count.submit_for_approval_intro')</p>

                <div class="alert alert-info">
                    <strong><i class="fa fa-info-circle"></i> @lang('inventory_count.approval_workflow_title'):</strong>
                    @lang('inventory_count.approval_workflow_intro')
                </div>

                <div class="form-group">
                    <label>@lang('inventory_count.reason') *</label>
                    <select id="ic_post_reason" class="form-control" required>
                        @foreach($reasons as $k => $v)
                            <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" id="ic_reason_other_wrap" style="display:none;">
                    <label>@lang('inventory_count.reason_other_detail') *</label>
                    <input type="text" id="ic_post_reason_other" class="form-control" maxlength="255">
                </div>
                <div class="form-group">
                    <label>@lang('lang_v1.additional_notes')</label>
                    <textarea id="ic_post_notes" class="form-control" rows="3" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                <button type="button" class="btn btn-success" id="ic_post_confirm">
                    <i class="fa fa-paper-plane"></i> @lang('inventory_count.confirm_submit')
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ============ APPROVE MODAL ============ --}}
@if($session->status === 'pending_approval' && $can_approve)
<div class="modal fade" id="ic_approve_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">@lang('inventory_count.confirm_approve_title')</h4>
            </div>
            <div class="modal-body">
                <p>@lang('inventory_count.confirm_approve_intro')</p>
                <div class="alert alert-warning">
                    <strong>@lang('inventory_count.irreversible_warning_title'):</strong>
                    @lang('inventory_count.irreversible_warning')
                </div>
                @if(auth()->id() == $session->posted_by)
                    <div class="alert alert-danger">
                        <strong><i class="fa fa-exclamation-triangle"></i> @lang('inventory_count.self_approval_warning')</strong>
                    </div>
                @endif
                <div class="form-group">
                    <label>@lang('inventory_count.count_purpose')</label>
                    <div class="radio">
                        <label>
                            <input type="radio" name="ic_count_purpose" value="routine" checked>
                            <strong>@lang('inventory_count.count_purpose_routine')</strong>
                            — <small class="text-muted">@lang('inventory_count.count_purpose_routine_help')</small>
                        </label>
                    </div>
                    <div class="radio">
                        <label>
                            <input type="radio" name="ic_count_purpose" value="migration">
                            <strong>@lang('inventory_count.count_purpose_migration')</strong>
                            — <small class="text-muted">@lang('inventory_count.count_purpose_migration_help')</small>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>@lang('inventory_count.approval_notes')</label>
                    <textarea id="ic_approval_notes" class="form-control" rows="3" maxlength="2000"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                <button type="button" class="btn btn-success" id="ic_approve_confirm">
                    <i class="fa fa-check"></i> @lang('inventory_count.confirm_approve')
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ic_reject_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">@lang('inventory_count.confirm_reject_title')</h4>
            </div>
            <div class="modal-body">
                <p>@lang('inventory_count.confirm_reject_intro')</p>
                <div class="form-group">
                    <label>@lang('inventory_count.rejection_notes') *</label>
                    <textarea id="ic_rejection_notes" class="form-control" rows="3" maxlength="2000" required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                <button type="button" class="btn btn-danger" id="ic_reject_confirm">
                    <i class="fa fa-times"></i> @lang('inventory_count.confirm_reject')
                </button>
            </div>
        </div>
    </div>
</div>
@endif

@if($can_edit_price)
{{-- ============ PER-UNIT PRICES MODAL ============ --}}
<div class="modal fade" id="ic_unit_prices_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-tags"></i> @lang('lang_v1.per_unit_selling_prices')
                </h4>
            </div>
            <div class="modal-body">
                <p class="text-muted"><small>@lang('lang_v1.per_unit_selling_prices_tooltip')</small></p>
                <p class="text-muted"><small><i class="fa fa-info-circle"></i> @lang('lang_v1.per_unit_selling_prices_help')</small></p>
                <div id="ic_unit_prices_meta" class="tw-mb-2"></div>
                <div id="ic_unit_prices_loading" class="text-center" style="display:none;">
                    <i class="fa fa-spinner fa-spin fa-2x text-muted"></i>
                </div>
                <div class="table-responsive">
                    <table class="table table-condensed table-bordered" id="ic_unit_prices_table">
                        <thead>
                            <tr>
                                <th>@lang('product.unit')</th>
                                <th class="text-right">@lang('lang_v1.multiplier')</th>
                                <th class="ic_unit_col_exc">@lang('product.default_selling_price') (@lang('lang_v1.exc_tax'))</th>
                                <th class="ic_unit_col_inc">@lang('product.default_selling_price') (@lang('lang_v1.inc_tax'))</th>
                                <th class="text-center" style="width:90px;">@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ============ ADD LOT MODAL ============ --}}
@if($is_editable)
<div class="modal fade" id="ic_add_lot_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-calendar-plus-o"></i> @lang('inventory_count.add_lot')
                </h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" style="padding:6px 10px;">
                    <small><i class="fa fa-info-circle"></i> @lang('inventory_count.add_lot_help')</small>
                </div>
                <div class="form-group">
                    <label>@lang('inventory_count.add_lot_product') *</label>
                    <select id="ic_add_lot_variation" class="form-control" style="width:100%;"></select>
                </div>
                <div class="row">
                    <div class="col-sm-6 form-group">
                        <label>@lang('inventory_count.add_lot_qty') *</label>
                        <input type="number" id="ic_add_lot_qty" class="form-control" step="any" min="0">
                    </div>
                    <div class="col-sm-6 form-group">
                        <label>@lang('inventory_count.add_lot_unit') *</label>
                        <select id="ic_add_lot_unit" class="form-control"></select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6 form-group">
                        <label>@lang('inventory_count.add_lot_exp_date')</label>
                        <input type="date" id="ic_add_lot_exp" class="form-control">
                    </div>
                    <div class="col-sm-6 form-group">
                        <label>@lang('inventory_count.add_lot_lot_number')</label>
                        <input type="text" id="ic_add_lot_lot_no" class="form-control" maxlength="191">
                    </div>
                </div>
                <div class="form-group">
                    <label>@lang('inventory_count.add_lot_unit_cost')</label>
                    <input type="number" id="ic_add_lot_cost" class="form-control" step="any" min="0">
                    <small class="text-muted">@lang('inventory_count.add_lot_unit_cost_help')</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                <button type="button" id="ic_add_lot_save" class="btn btn-warning">
                    <i class="fa fa-plus"></i> @lang('inventory_count.add_lot_save')
                </button>
            </div>
        </div>
    </div>
</div>
@endif

{{-- ============ EDIT LOT/EXPIRY MODAL ============ --}}
@if($is_editable)
<div class="modal fade" id="ic_edit_lot_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">
                    <i class="fa fa-pencil"></i> @lang('inventory_count.edit_lot_exp_title')
                </h4>
            </div>
            <div class="modal-body">
                <div class="alert alert-info" style="padding:6px 10px;">
                    <small><i class="fa fa-info-circle"></i> @lang('inventory_count.edit_lot_exp_help')</small>
                </div>
                <input type="hidden" id="ic_edit_lot_line_id">
                <div class="form-group">
                    <label>@lang('inventory_count.add_lot_lot_number')</label>
                    <input type="text" id="ic_edit_lot_lot_no" class="form-control" maxlength="191">
                </div>
                <div class="form-group" id="ic_edit_lot_exp_group">
                    <label>@lang('inventory_count.add_lot_exp_date')</label>
                    <input type="date" id="ic_edit_lot_exp" class="form-control">
                    <span class="help-block" id="ic_edit_lot_exp_err" style="display:none;color:#a94442;"></span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.close')</button>
                <button type="button" id="ic_edit_lot_save" class="btn btn-primary">
                    <i class="fa fa-check"></i> @lang('messages.save')
                </button>
            </div>
        </div>
    </div>
</div>

@endif

@endsection

@section('javascript')
<script>
$(function () {
    var SESSION_ID = {{ (int) $session->id }};
    var IS_EDITABLE = @json($is_editable);
    var BASE_URL = '{{ url('inventory-count') }}';

    // ---------- DataTable ----------
    var dt = $('#ic_session_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: BASE_URL + '/' + SESSION_ID + '/data',
            data: function (d) {
                d.filter = $('#ic_filter').val();
                d.search_term = $('#ic_search_term').val();
            }
        },
        columns: [
            // SKU column becomes visible (with inline editor) while the session is editable
            { data: 'sku', name: 'v.sub_sku', visible: IS_EDITABLE && @json(auth()->user()->can('product.update')) },
            { data: 'product', name: 'p.name' },
            { data: 'category', name: 'cat.name', defaultContent: '', visible: false },
            { data: 'unit', orderable: false },
            @if(session('business.enable_lot_number') == 1 || session('business.enable_product_expiry') == 1)
            { data: 'lot_exp', orderable: false, searchable: false },
            @endif
            { data: 'unit_cost', name: 'l.unit_cost', className: 'text-right' },
            { data: 'unit_sell', name: 'v.sell_price_inc_tax', className: 'text-right' },
            { data: 'margin', orderable: false, searchable: false, className: 'text-right' },
            { data: 'theoretical_at_start', name: 'l.theoretical_at_start', className: 'text-right' },
            { data: 'alert_quantity', name: 'p.alert_quantity', className: 'text-right' },
            { data: 'counted', orderable: false, searchable: false, className: 'text-right' },
            { data: 'variance', orderable: false, searchable: false, className: 'text-right' }
        ],
        order: [[1, 'asc']],
        pageLength: 50,
        lengthMenu: [[25, 50, 100, 250], [25, 50, 100, 250]],
        // Hide DT's built-in search box: we use our own #ic_search_term filter
        // above the table (the built-in one was confusing users because the
        // server-side handler only honors our custom search_term param).
        initComplete: function () {
            $('#ic_session_table_wrapper .dataTables_filter').hide();
        },
        drawCallback: function () {
            // Cells that came back with existing mix-rows must lock the
            // primary input/unit so it doesn't desync with the breakdown.
            $('#ic_session_table .counted-cell').each(function () {
                var $cell = $(this);
                if ($cell.find('.ic-mix-row').length > 0) {
                    $cell.find('.actual-qty').prop('readonly', true).addClass('ic-mix-display');
                    $cell.find('.counted-unit').prop('disabled', true);
                }
            });
        }
    });

    $('#ic_filter').on('change', function () { dt.ajax.reload(); });

    var searchTimer = null;
    $('#ic_search_term').on('keyup input search', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { dt.ajax.reload(); }, 300);
    });

    // ---------- PDF buttons reflect current table state ----------
    $('.ic_blank_pdf_btn').on('click', function (e) {
        e.preventDefault();
        var base = BASE_URL + '/blank-pdf';
        var params = { session_id: SESSION_ID };
        if ($(this).data('blind')) { params.blind = 1; }
        var filter = $('#ic_filter').val();
        if (filter) { params.filter = filter; }
        var term = $('#ic_search_term').val();
        if (term) { params.search_term = term; }
        var order = dt.order();
        if (order && order.length) {
            var col = dt.column(order[0][0]);
            var colName = col.dataSrc();
            // map DT data keys to controller column names
            var colMap = {
                sku: 'sku',
                product: 'product',
                category: 'category',
                unit: 'unit',
                theoretical_at_start: 'theoretical',
                lot_exp: 'expiry'
            };
            if (colMap[colName]) {
                params.order_by = colMap[colName];
                params.order_dir = order[0][1] === 'desc' ? 'desc' : 'asc';
            }
        }
        var qs = $.param(params);
        window.open(base + '?' + qs, '_blank');
    });
    // ---------- Autosave ----------
    var saveQueue = {};
    var saveTimer = null;
    var inflight = 0;

    function setStatus(text, cls) {
        $('#ic_autosave_status').removeClass('text-muted text-success text-danger').addClass(cls || 'text-muted').text(text);
    }

    function flushSaves() {
        var lineIds = Object.keys(saveQueue);
        if (lineIds.length === 0) return;

        var pending = saveQueue;
        saveQueue = {};

        setStatus(@json(__('inventory_count.autosaving')), 'text-muted');

        lineIds.forEach(function (lineId) {
            inflight++;
            var payload = pending[lineId];
            // payload may be {qty, unit_id} or {breakdown:[...]} or a scalar
            // (legacy / scan path).
            var counted_qty, unit_id, breakdown;
            if (payload !== null && typeof payload === 'object') {
                counted_qty = payload.qty;
                unit_id = payload.unit_id;
                breakdown = payload.breakdown || null;
            } else {
                counted_qty = payload;
                unit_id = null;
                breakdown = null;
            }
            $.ajax({
                url: BASE_URL + '/' + SESSION_ID + '/save-line',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    line_id: lineId,
                    counted_qty: counted_qty,
                    unit_id: unit_id,
                    breakdown: breakdown
                },
                dataType: 'json'
            }).done(function (resp) {
                if (resp.success) {
                    $('#ic_counted').text(resp.lines_counted);
                    var total = parseInt($('#ic_total').text(), 10) || 0;
                    var pct = total > 0 ? Math.round((resp.lines_counted / total) * 100) : 0;
                    $('#ic_progress_bar').css('width', pct + '%');
                    inflight--;
                    if (inflight === 0) setStatus(@json(__('inventory_count.autosaved')), 'text-success');
                } else {
                    inflight--;
                    setStatus(resp.msg || 'Error', 'text-danger');
                }
            }).fail(function () {
                inflight--;
                // requeue for retry
                saveQueue[lineId] = pending[lineId];
                setStatus(@json(__('inventory_count.save_failed')), 'text-danger');
                setTimeout(flushSaves, 3000);
            });
        });
    }

    if (IS_EDITABLE) {
        // Reads the currently selected unit's multiplier from the dropdown
        // adjacent to the given .actual-qty input. Defaults to 1.
        function multiplierFor($input) {
            var $sel = $input.closest('.counted-cell').find('.counted-unit');
            if (! $sel.length) return 1;
            var m = parseFloat($sel.find('option:selected').data('multiplier'));
            return (isNaN(m) || m <= 0) ? 1 : m;
        }

        function currentUnitId($input) {
            var $sel = $input.closest('.counted-cell').find('.counted-unit');
            if (! $sel.length) return null;
            return $sel.val();
        }

        // Recompute and render the live variance for a single row from the
        // raw input value × the currently chosen unit's multiplier.
        function refreshVariance($input) {
            var theo = parseFloat($input.data('theoretical')) || 0;
            var raw = $input.val();
            var mult = multiplierFor($input);
            var counted = raw === '' ? null : parseFloat(raw);
            var $variance = $input.closest('tr').find('.variance-cell');

            if (counted === null || isNaN(counted)) {
                $variance.removeClass('text-success text-warning text-danger').addClass('text-muted').text('—');
                return;
            }
            var base = counted * mult;
            var diff = +(base - theo).toFixed(4);
            if (Math.abs(diff) < 0.0001) {
                $variance.removeClass('text-warning text-danger text-muted').addClass('text-success').html('<i class="fa fa-check"></i>');
            } else {
                var cls = diff < 0 ? 'text-danger' : 'text-warning';
                var sign = diff > 0 ? '+' : '';
                $variance.removeClass('text-success text-muted text-warning text-danger').addClass(cls).text(sign + diff.toFixed(2));
            }
        }

        $(document).on('input', '#ic_session_table .actual-qty', function () {
            var $input = $(this);
            var $cell = $input.closest('.counted-cell');
            // If the user is in mixed-unit mode, the primary input is just
            // a read-only display of the total — ignore manual edits here.
            if ($cell.find('.ic-mix-row').length > 0) {
                return;
            }
            var lineId = $input.data('line-id');
            var raw = $input.val();
            saveQueue[lineId] = {
                qty: raw === '' ? null : raw,
                unit_id: currentUnitId($input)
            };
            // Keep data-multiplier on the input fresh for scan/increment users.
            $input.data('multiplier', multiplierFor($input));

            refreshVariance($input);

            clearTimeout(saveTimer);
            saveTimer = setTimeout(flushSaves, 400);
        });

        // Switching the unit re-converts the existing entry without losing it.
        $(document).on('change', '#ic_session_table .counted-unit', function () {
            var $sel = $(this);
            var $input = $sel.closest('.counted-cell').find('.actual-qty');
            $input.data('multiplier', multiplierFor($input));
            refreshVariance($input);

            // Only persist when there's actually a number to convert; otherwise
            // we'd write a NULL count.
            var raw = $input.val();
            if (raw !== '' && raw !== null) {
                saveQueue[$input.data('line-id')] = {
                    qty: raw,
                    unit_id: $sel.val()
                };
                clearTimeout(saveTimer);
                saveTimer = setTimeout(flushSaves, 200);
            }
        });

        // ---------- Mixed-unit count ----------
        // Builds <option> HTML from the embedded JSON for the current cell.
        function mixUnitOptionsHtml($cell, selectedId) {
            var opts = [];
            try {
                opts = JSON.parse($cell.find('script.ic-unit-opts').text() || '[]');
            } catch (e) { opts = []; }
            return opts.map(function (o) {
                var sel = (parseInt(selectedId, 10) === parseInt(o.id, 10)) ? ' selected' : '';
                return '<option value="' + o.id + '" data-multiplier="' + o.multiplier + '"' + sel + '>'
                    + $('<div>').text(o.label).html() + '</option>';
            }).join('');
        }

        function newMixRowHtml($cell, defaultUnitId) {
            return '<div class="ic-mix-row input-group input-group-sm" style="margin-top:3px;">'
                + '<input type="number" step="any" min="0" class="form-control input-sm ic-mix-qty" placeholder="qty" />'
                + '<select class="form-control input-sm ic-mix-unit" style="max-width:90px;">'
                + mixUnitOptionsHtml($cell, defaultUnitId)
                + '</select>'
                + '<span class="input-group-btn">'
                + '<button type="button" class="btn btn-default btn-sm ic-mix-remove" title="Remove"><i class="fa fa-times"></i></button>'
                + '</span></div>';
        }

        // Read all qty+unit parts (primary input + mix rows) and submit them
        // as a breakdown, recomputing the live total.
        function flushMixToQueue($cell) {
            var lineId = $cell.data('line-id');
            var $input = $cell.find('.actual-qty');
            var theo = parseFloat($input.data('theoretical')) || 0;
            var parts = [];
            var total = 0;

            $cell.find('.ic-mix-row').each(function () {
                var q = parseFloat($(this).find('.ic-mix-qty').val());
                if (isNaN(q) || q <= 0) return;
                var $u = $(this).find('.ic-mix-unit');
                var uid = parseInt($u.val(), 10);
                var m = parseFloat($u.find('option:selected').data('multiplier')) || 1;
                parts.push({ qty: q, unit_id: uid });
                total += q * m;
            });

            var $totalEl = $cell.find('.ic-mix-total');
            if (parts.length === 0) {
                $totalEl.text('');
                // Nothing in mix area: revert to single-input path so the
                // user can still type directly in the primary input.
                return;
            }

            // Show running total in base unit; mirror in primary input as
            // a read-only display.
            $input.val(+total.toFixed(4));
            refreshVariance($input);
            $totalEl.text('= ' + (+total.toFixed(4)) + ' (base unit)');

            saveQueue[lineId] = {
                breakdown: parts
            };
            clearTimeout(saveTimer);
            saveTimer = setTimeout(flushSaves, 400);
        }

        // Toggle / add a mix row.
        $(document).on('click', '#ic_session_table .ic-mix-toggle', function () {
            var $cell = $(this).closest('.counted-cell');
            var $parts = $cell.find('.ic-mix-parts');
            var $rows = $parts.find('.ic-mix-row');

            if ($parts.is(':hidden')) {
                $parts.show();
            }

            // First open: seed one row from the primary input (so the user
            // doesn't lose what they already typed). If there was no value,
            // start with one empty row. Subsequent clicks add empty rows.
            if ($rows.length === 0) {
                if ($parts.find('.ic-mix-total').length === 0) {
                    $parts.append('<div class="ic-mix-total small text-muted" style="margin-top:2px;"></div>');
                }
                var $primaryInput = $cell.find('.actual-qty');
                var $primaryUnit = $cell.find('.counted-unit');
                var primaryQty = parseFloat($primaryInput.val());
                var primaryUnitId = $primaryUnit.length ? $primaryUnit.val() : $cell.data('base-unit-id');

                $parts.find('.ic-mix-total').before(newMixRowHtml($cell, primaryUnitId));
                if (!isNaN(primaryQty) && primaryQty > 0) {
                    $parts.find('.ic-mix-row:first .ic-mix-qty').val(primaryQty);
                }
            } else {
                // Subsequent clicks just add another empty row.
                $parts.find('.ic-mix-total').before(newMixRowHtml($cell, $cell.data('base-unit-id')));
            }

            // Primary input becomes a read-only running total while mix is active.
            $cell.find('.actual-qty').prop('readonly', true).addClass('ic-mix-display');
            $cell.find('.counted-unit').prop('disabled', true);

            flushMixToQueue($cell);
        });

        $(document).on('input change', '#ic_session_table .ic-mix-qty, #ic_session_table .ic-mix-unit', function () {
            flushMixToQueue($(this).closest('.counted-cell'));
        });

        $(document).on('click', '#ic_session_table .ic-mix-remove', function () {
            var $cell = $(this).closest('.counted-cell');
            $(this).closest('.ic-mix-row').remove();
            // If no rows left, hide and clear so user can type single-unit again.
            if ($cell.find('.ic-mix-row').length === 0) {
                $cell.find('.ic-mix-parts').hide().find('.ic-mix-total').text('');
                // Re-enable primary input/unit for direct single-unit edits.
                $cell.find('.actual-qty').prop('readonly', false).removeClass('ic-mix-display');
                $cell.find('.counted-unit').prop('disabled', false);
                // Push a NULL save to clear the line (user removed everything).
                saveQueue[$cell.data('line-id')] = { qty: null, unit_id: null };
                $cell.find('.actual-qty').val('');
                refreshVariance($cell.find('.actual-qty'));
                clearTimeout(saveTimer);
                saveTimer = setTimeout(flushSaves, 200);
            } else {
                flushMixToQueue($cell);
            }
        });
    }

    // ---------- Add Lot ----------
    if (IS_EDITABLE) {
        var lotUnitOptions = []; // cached units for currently picked variation

        function renderLotUnitOptions(units) {
            lotUnitOptions = units || [];
            var html = lotUnitOptions.map(function (u) {
                return '<option value="' + u.id + '" data-multiplier="' + u.multiplier + '">'
                    + $('<div>').text(u.label).html() + '</option>';
            }).join('');
            $('#ic_add_lot_unit').html(html);
        }

        function resetAddLotForm() {
            $('#ic_add_lot_variation').val(null).trigger('change');
            $('#ic_add_lot_qty').val('');
            $('#ic_add_lot_exp').val('');
            $('#ic_add_lot_lot_no').val('');
            $('#ic_add_lot_cost').val('').attr('placeholder', '');
            renderLotUnitOptions([]);
        }

        // Initialise Select2 once on page load so the row-button flow can
        // programmatically pre-select a variation without a race condition.
        $('#ic_add_lot_variation').select2({
            dropdownParent: $('#ic_add_lot_modal'),
            placeholder: @json(__('inventory_count.add_lot_product_placeholder')),
            allowClear: true,
            ajax: {
                url: BASE_URL + '/' + SESSION_ID + '/variation-search',
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term || '' }; },
                processResults: function (data) { return { results: data.results || [] }; }
            },
            minimumInputLength: 0,
            width: '100%'
        }).on('select2:select', function (e) {
            var d = e.params.data || {};
            renderLotUnitOptions(d.units || []);
            if (d.unit_cost > 0 && !$('#ic_add_lot_cost').val()) {
                $('#ic_add_lot_cost').attr('placeholder', d.unit_cost);
            } else {
                $('#ic_add_lot_cost').attr('placeholder', '');
            }
        }).on('select2:clear', function () {
            renderLotUnitOptions([]);
            $('#ic_add_lot_cost').attr('placeholder', '');
        });

        // Generic top-right button: blank form.
        $('#ic_btn_add_lot').on('click', function () {
            resetAddLotForm();
            $('#ic_add_lot_modal').modal('show');
        });

        // Per-row button: pre-select the variation and reuse the row's
        // embedded unit options (no extra round trip).
        $(document).on('click', '#ic_session_table .ic-row-add-lot', function () {
            var $btn = $(this);
            var vid = parseInt($btn.data('variation-id'), 10);
            var label = $btn.data('label') || '';
            var $row = $btn.closest('tr');
            var $cell = $row.find('.counted-cell');
            var units = [];
            try {
                units = JSON.parse($cell.find('script.ic-unit-opts').text() || '[]');
            } catch (e) { units = []; }
            // Fallback: build a single base-unit option from the row.
            if (units.length === 0) {
                var baseUnitId = parseInt($cell.data('base-unit-id'), 10);
                var $unitLabel = $row.find('td').eq(3); // unit column
                units = [{ id: baseUnitId, label: $unitLabel.text().trim(), multiplier: 1 }];
            }
            // Hint cost from the row's data-unit-cost.
            var rowCost = parseFloat($cell.find('.actual-qty').data('unit-cost'));

            resetAddLotForm();
            // Pre-create the option in Select2 so it can show the label.
            var $sel = $('#ic_add_lot_variation');
            // Remove any old pre-seeded option for this id.
            $sel.find('option[value="' + vid + '"]').remove();
            var opt = new Option(label, vid, true, true);
            $sel.append(opt).trigger('change');
            renderLotUnitOptions(units);
            if (!isNaN(rowCost) && rowCost > 0) {
                $('#ic_add_lot_cost').attr('placeholder', rowCost);
            }
            $('#ic_add_lot_modal').modal('show');
        });

        $('#ic_add_lot_save').on('click', function () {
            var $btn = $(this);
            var variation_id = $('#ic_add_lot_variation').val();
            var qty = $('#ic_add_lot_qty').val();
            var unit_id = $('#ic_add_lot_unit').val();
            var exp_date = $('#ic_add_lot_exp').val();
            var lot_number = $('#ic_add_lot_lot_no').val();
            var unit_cost = $('#ic_add_lot_cost').val();

            if (!variation_id) { toastr.error(@json(__('inventory_count.add_lot_product')) + ' *'); return; }
            if (!qty || parseFloat(qty) <= 0) { toastr.error(@json(__('inventory_count.add_lot_qty')) + ' *'); return; }
            if (!unit_id) { toastr.error(@json(__('inventory_count.add_lot_unit')) + ' *'); return; }

            $btn.prop('disabled', true);
            $.ajax({
                url: BASE_URL + '/' + SESSION_ID + '/add-lot',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    variation_id: variation_id,
                    counted_qty: qty,
                    unit_id: unit_id,
                    exp_date: exp_date,
                    lot_number: lot_number,
                    unit_cost: unit_cost
                },
                dataType: 'json'
            }).done(function (resp) {
                $btn.prop('disabled', false);
                if (resp.success) {
                    toastr.success(resp.msg);
                    $('#ic_total').text(resp.lines_total);
                    $('#ic_counted').text(resp.lines_counted);
                    var pct = resp.lines_total > 0 ? Math.round((resp.lines_counted / resp.lines_total) * 100) : 0;
                    $('#ic_progress_bar').css('width', pct + '%');
                    $('#ic_add_lot_modal').modal('hide');
                    dt.ajax.reload(null, false);
                } else {
                    toastr.error(resp.msg || 'Error');
                }
            }).fail(function (xhr) {
                $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && (xhr.responseJSON.msg || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Error';
                toastr.error(msg);
            });
        });
    }

    // ---------- Edit Lot/Expiry ----------
    if (IS_EDITABLE) {
        $(document).on('click', '#ic_session_table .ic-lot-edit', function () {
            var $btn = $(this);
            $('#ic_edit_lot_line_id').val($btn.data('line-id'));
            $('#ic_edit_lot_lot_no').val($btn.data('lot') || '');
            $('#ic_edit_lot_exp').val($btn.data('exp') || '');
            $('#ic_edit_lot_modal').modal('show');
        });

        // Validate a YYYY-MM-DD string represents a real calendar date (e.g. rejects 2026-02-31).
        // Returns true for empty string (expiry is optional).
        function icIsValidExpDate(s) {
            if (!s) return true;
            var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
            if (!m) return false;
            var y = parseInt(m[1], 10), mo = parseInt(m[2], 10), d = parseInt(m[3], 10);
            if (y < 1900 || y > 9999 || mo < 1 || mo > 12 || d < 1 || d > 31) return false;
            var dt = new Date(y, mo - 1, d);
            return dt.getFullYear() === y && (dt.getMonth() + 1) === mo && dt.getDate() === d;
        }

        function icShowExpError(msg) {
            $('#ic_edit_lot_exp_group').addClass('has-error');
            $('#ic_edit_lot_exp_err').text(msg).show();
        }
        function icClearExpError() {
            $('#ic_edit_lot_exp_group').removeClass('has-error');
            $('#ic_edit_lot_exp_err').hide().text('');
        }

        $(document).on('input change blur', '#ic_edit_lot_exp', function () {
            var v = $(this).val();
            // Read raw typed text on Chrome when invalid: the input returns '' but the
            // bad-input flag is set on the DOM element.
            var nativeInvalid = this.validity && this.validity.badInput;
            if (nativeInvalid || !icIsValidExpDate(v)) {
                icShowExpError(@json(__('inventory_count.invalid_exp_date')));
            } else {
                icClearExpError();
            }
        });

        $('#ic_edit_lot_modal').on('hidden.bs.modal', function () { icClearExpError(); });

        $('#ic_edit_lot_save').on('click', function () {
            var expVal = $('#ic_edit_lot_exp').val();
            var expEl = document.getElementById('ic_edit_lot_exp');
            var nativeInvalid = expEl && expEl.validity && expEl.validity.badInput;
            if (nativeInvalid || !icIsValidExpDate(expVal)) {
                icShowExpError(@json(__('inventory_count.invalid_exp_date')));
                $('#ic_edit_lot_exp').focus();
                return;
            }
            var $btn = $(this).prop('disabled', true);
            $.ajax({
                url: BASE_URL + '/' + SESSION_ID + '/update-lot',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    line_id: $('#ic_edit_lot_line_id').val(),
                    lot_number: $('#ic_edit_lot_lot_no').val(),
                    exp_date: expVal
                },
                dataType: 'json'
            }).done(function (resp) {
                $btn.prop('disabled', false);
                if (resp.success) {
                    toastr.success(resp.msg);
                    $('#ic_edit_lot_modal').modal('hide');
                    dt.ajax.reload(null, false);
                } else {
                    toastr.error(resp.msg || 'Error');
                }
            }).fail(function (xhr) {
                $btn.prop('disabled', false);
                var msg = (xhr.responseJSON && (xhr.responseJSON.msg || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Error';
                toastr.error(msg);
            });
        });

        // ---------- Inline product name / SKU editors (update the product master) ----------
        // Both save buttons post name+sku together: values are read from the row's
        // two inputs (name input in the product cell, sku input in the SKU cell).
        function icSaveProductInfo($btn) {
            var $row = $btn.closest('tr');
            var $nameInput = $row.find('.ic-name-input');
            var $skuInput = $row.find('.ic-sku-input');
            var name = ($nameInput.val() || '').toString().trim();
            var sku = $skuInput.length ? ($skuInput.val() || '').toString().trim() : ($nameInput.data('orig-sku') || '');
            if (!name) {
                toastr.warning(@json(__('validation.required', ['attribute' => __('product.product_name')])));
                $nameInput.focus();
                return;
            }
            $btn.prop('disabled', true).find('i').removeClass('fa-check').addClass('fa-spinner fa-spin');
            $.ajax({
                url: BASE_URL + '/' + SESSION_ID + '/update-product-info',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    product_id: $nameInput.data('product-id'),
                    variation_id: $nameInput.data('variation-id'),
                    name: name,
                    sku: sku
                },
                dataType: 'json'
            }).done(function (resp) {
                if (resp.success) {
                    toastr.success(resp.msg);
                    $nameInput.data('orig', resp.name);
                    if ($skuInput.length) { $skuInput.data('orig', resp.sku); }
                    var $cell = $btn.closest('.input-group');
                    $cell.addClass('has-success');
                    setTimeout(function () { $cell.removeClass('has-success'); }, 1500);
                    dt.ajax.reload(null, false);
                } else {
                    toastr.error(resp.msg || 'Error');
                    $nameInput.val($nameInput.data('orig'));
                    if ($skuInput.length) { $skuInput.val($skuInput.data('orig')); }
                }
            }).fail(function (xhr) {
                var msg = (xhr.responseJSON && (xhr.responseJSON.msg || (xhr.responseJSON.errors && Object.values(xhr.responseJSON.errors)[0][0]))) || 'Error';
                toastr.error(msg);
                $nameInput.val($nameInput.data('orig'));
                if ($skuInput.length) { $skuInput.val($skuInput.data('orig')); }
            }).always(function () {
                $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
            });
        }

        $(document).on('click', '.ic-name-save, .ic-sku-save', function () {
            icSaveProductInfo($(this));
        });
        $(document).on('keydown', '.ic-name-input', function (e) {
            if (e.which === 13) { e.preventDefault(); $(this).closest('.ic-name-edit').find('.ic-name-save').click(); }
        });
        $(document).on('keydown', '.ic-sku-input', function (e) {
            if (e.which === 13) { e.preventDefault(); $(this).closest('.ic-sku-edit').find('.ic-sku-save').click(); }
        });
    }

    // ---------- Post ----------
    $('#ic_btn_post').on('click', function () {
        // Flush pending edits first
        flushSaves();
        setTimeout(function () { $('#ic_post_modal').modal('show'); }, 200);
    });

    $('#ic_post_reason').on('change', function () {
        $('#ic_reason_other_wrap').toggle($(this).val() === 'other');
    });

    $('#ic_post_confirm').on('click', function () {
        var reason = $('#ic_post_reason').val();
        var reason_other = $('#ic_post_reason_other').val();
        var notes = $('#ic_post_notes').val();

        if (reason === 'other' && !reason_other.trim()) {
            toastr.error(@json(__('inventory_count.reason_other_required')));
            return;
        }

        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/post',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                reason: reason,
                reason_other: reason_other,
                notes: notes
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                setTimeout(function () { window.location.href = resp.redirect; }, 800);
            } else {
                toastr.error(resp.msg || 'Error');
                $btn.prop('disabled', false);
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
            $btn.prop('disabled', false);
        });
    });

    // ---------- Cancel ----------
    $('#ic_btn_cancel').on('click', function () {
        if (! confirm(@json(__('inventory_count.confirm_cancel_session')))) return;
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/cancel',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}' },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) window.location.href = resp.redirect;
            else toastr.error(resp.msg);
        });
    });

    // Warn on unload if queue has pending
    window.addEventListener('beforeunload', function (e) {
        if (Object.keys(saveQueue).length > 0 || inflight > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    // ---------- Phase 4: Scan-to-jump ----------
    if (IS_EDITABLE) {
        $('#ic_scan_input').on('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var $input = $(this);
            var sku = $input.val().trim();
            if (! sku) return;

            $.ajax({
                url: BASE_URL + '/' + SESSION_ID + '/scan',
                type: 'POST',
                data: { _token: '{{ csrf_token() }}', sku: sku },
                dataType: 'json'
            }).done(function (resp) {
                if (! resp.success) {
                    $('#ic_scan_status').html('<span class="text-danger"><i class="fa fa-times"></i> ' + (resp.msg || 'Not found') + '</span>');
                    return;
                }

                $('#ic_scan_status').html('<span class="text-success"><i class="fa fa-check"></i> '
                    + $('<div>').text(resp.product).html() + ' (SKU: ' + $('<div>').text(resp.sku).html() + ')</span>');

                // Find existing visible row
                var $row = $('#ic_session_table .actual-qty[data-line-id="' + resp.line_id + '"]');

                var scanMode = $('#ic_scan_mode_increment').is(':checked');

                if ($row.length) {
                    // Already on this page — scroll, focus, optionally increment
                    $row[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                    $row.closest('tr').css('background', '#fff3cd');
                    setTimeout(function () { $row.closest('tr').css('background', ''); }, 1500);

                    if (scanMode) {
                        var cur = parseFloat($row.val());
                        var nv = (isNaN(cur) ? 0 : cur) + 1;
                        $row.val(nv).trigger('input');
                    } else {
                        $row.focus().select();
                    }
                } else {
                    // Not in current DataTables page — push value directly via save-line
                    if (scanMode) {
                        var newQty = (resp.counted_qty || 0) + 1;
                        saveQueue[resp.line_id] = newQty;
                        clearTimeout(saveTimer);
                        saveTimer = setTimeout(flushSaves, 100);
                        toastr.info(@json(__('inventory_count.scan_incremented')) + ': ' + resp.product + ' → ' + newQty);
                    } else {
                        // Reload table filtered to this SKU so the user can edit
                        $('#ic_search_term').val(resp.sku);
                        dt.ajax.reload();
                    }
                }

                $input.val('').focus();
            }).fail(function () {
                $('#ic_scan_status').html('<span class="text-danger">Network error</span>');
            });
        });
    }

    // ---------- Phase 4: CSV import ----------
    if (IS_EDITABLE) {
        $('#ic_csv_form').on('submit', function (e) {
            e.preventDefault();
            var file = $('#ic_csv_file')[0].files[0];
            if (! file) { toastr.warning('Choose a CSV file first'); return; }

            var fd = new FormData();
            fd.append('_token', '{{ csrf_token() }}');
            fd.append('csv_file', file);

            $('#ic_csv_result').html('<span class="text-muted"><i class="fa fa-spinner fa-spin"></i> '
                + @json(__('inventory_count.autosaving')) + '</span>');

            $.ajax({
                url: BASE_URL + '/' + SESSION_ID + '/csv-import',
                type: 'POST',
                data: fd,
                contentType: false,
                processData: false,
                dataType: 'json'
            }).done(function (resp) {
                if (! resp.success) {
                    $('#ic_csv_result').html('<span class="text-danger">' + (resp.msg || 'Error') + '</span>');
                    return;
                }
                var html = '<div class="alert alert-success" style="padding:6px 10px;margin:0;">'
                    + '<strong>' + resp.msg + '</strong>';
                if (resp.unmatched_count > 0) {
                    html += '<br><small class="text-warning">'
                        + @json(__('inventory_count.csv_unmatched_skus')) + ': '
                        + resp.unmatched_sample.map(function (s) { return $('<div>').text(s).html(); }).join(', ')
                        + (resp.unmatched_count > resp.unmatched_sample.length ? ' …' : '')
                        + '</small>';
                }
                html += '</div>';
                $('#ic_csv_result').html(html);

                // Update progress
                $('#ic_counted').text(resp.lines_counted);
                var total = parseInt($('#ic_total').text(), 10) || 0;
                var pct = total > 0 ? Math.round((resp.lines_counted / total) * 100) : 0;
                $('#ic_progress_bar').css('width', pct + '%');

                // Reload the visible table
                dt.ajax.reload(null, false);
                $('#ic_csv_file').val('');
            }).fail(function (xhr) {
                $('#ic_csv_result').html('<span class="text-danger">'
                    + ((xhr.responseJSON && xhr.responseJSON.msg) || 'Upload failed') + '</span>');
            });
        });
    }

    // ---------- Phase 5: Approve / Reject ----------
    $('#ic_btn_approve').on('click', function () {
        $('#ic_approval_notes').val('');
        $('#ic_approve_modal').modal('show');
    });

    $('#ic_approve_confirm').on('click', function () {
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/approve',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                approval_notes: $('#ic_approval_notes').val(),
                count_purpose: $('input[name="ic_count_purpose"]:checked').val() || 'routine',
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                window.location.href = resp.redirect;
            } else {
                toastr.error(resp.msg || 'Error');
                $btn.prop('disabled', false);
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
            $btn.prop('disabled', false);
        });
    });

    $('#ic_btn_reject').on('click', function () {
        $('#ic_rejection_notes').val('');
        $('#ic_reject_modal').modal('show');
    });

    $('#ic_reject_confirm').on('click', function () {
        var notes = $('#ic_rejection_notes').val().trim();
        if (! notes) {
            toastr.warning(@json(__('inventory_count.rejection_notes')) + ' *');
            return;
        }
        var $btn = $(this).prop('disabled', true);
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/reject',
            type: 'POST',
            data: { _token: '{{ csrf_token() }}', rejection_notes: notes },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                window.location.href = resp.redirect;
            } else {
                toastr.error(resp.msg || 'Error');
                $btn.prop('disabled', false);
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
            $btn.prop('disabled', false);
        });
    });

    @if($can_edit_price)
    // =========================================================
    //  Quick sell-price editor (base price) + per-unit overrides
    //  Migration helper: lets admins fix selling prices during
    //  the first inventory count without leaving the session.
    // =========================================================
    function icPriceIncTax() { return $('#ic_price_inc_tax').is(':checked') ? 1 : 0; }
    function icFmt(n) { return (Math.round((parseFloat(n) || 0) * 10000) / 10000).toString(); }

    // Save base sell price
    $(document).on('click', '.ic-sell-save', function () {
        var $btn = $(this);
        var $cell = $btn.closest('.ic-sell-edit');
        var $input = $cell.find('.ic-sell-input');
        var variation_id = $input.data('variation-id');
        var price = ($input.val() || '').toString().trim();
        if (price === '') { toastr.warning(@json(__('validation.required', ['attribute' => 'price']))); return; }

        $btn.prop('disabled', true).find('i').removeClass('fa-check').addClass('fa-spinner fa-spin');
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/update-sell-price',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                variation_id: variation_id,
                price: price,
                price_includes_tax: icPriceIncTax()
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                $input.data('orig', resp.sell_price_inc_tax);
                $cell.addClass('has-success');
                setTimeout(function () { $cell.removeClass('has-success'); }, 1500);
            } else {
                toastr.error(resp.msg || 'Error');
                $input.val($input.data('orig'));
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
            $input.val($input.data('orig'));
        }).always(function () {
            $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
        });
    });

    // Enter key on sell-price input = save
    $(document).on('keydown', '.ic-sell-input', function (e) {
        if (e.which === 13) { e.preventDefault(); $(this).closest('.ic-sell-edit').find('.ic-sell-save').click(); }
    });

    // Save unit_cost on a single session line (scoped to this count session only —
    // does not modify the linked PurchaseLine or product master cost).
    $(document).on('click', '.ic-cost-save', function () {
        var $btn = $(this);
        var $cell = $btn.closest('.ic-cost-edit');
        var $input = $cell.find('.ic-cost-input');
        var line_id = $input.data('line-id');
        var unit_cost = ($input.val() || '').toString().trim();
        if (unit_cost === '') { toastr.warning(@json(__('validation.required', ['attribute' => 'unit cost']))); return; }

        $btn.prop('disabled', true).find('i').removeClass('fa-check').addClass('fa-spinner fa-spin');
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/update-unit-cost',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                line_id: line_id,
                unit_cost: unit_cost
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                $input.data('orig', resp.unit_cost);
                $cell.addClass('has-success');
                setTimeout(function () { $cell.removeClass('has-success'); }, 1500);
                if (typeof dt !== 'undefined' && dt) {
                    dt.ajax.reload(null, false);
                }
            } else {
                toastr.error(resp.msg || 'Error');
                $input.val($input.data('orig'));
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
            $input.val($input.data('orig'));
        }).always(function () {
            $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
        });
    });

    $(document).on('keydown', '.ic-cost-input', function (e) {
        if (e.which === 13) { e.preventDefault(); $(this).closest('.ic-cost-edit').find('.ic-cost-save').click(); }
    });

    // Save product.alert_quantity (stored in the product's base unit, same as
    // the product edit page). Updates the product master, not just this line.
    $(document).on('click', '.ic-alert-save', function () {
        var $btn = $(this);
        var $cell = $btn.closest('.ic-alert-edit');
        var $input = $cell.find('.ic-alert-input');
        var product_id = $input.data('product-id');
        var alert_quantity = ($input.val() || '').toString().trim();

        $btn.prop('disabled', true).find('i').removeClass('fa-check').addClass('fa-spinner fa-spin');
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/update-alert-quantity',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                product_id: product_id,
                alert_quantity: alert_quantity
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                $input.data('orig', resp.alert_quantity == null ? '' : resp.alert_quantity);
                $cell.addClass('has-success');
                setTimeout(function () { $cell.removeClass('has-success'); }, 1500);
            } else {
                toastr.error(resp.msg || 'Error');
                $input.val($input.data('orig'));
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
            $input.val($input.data('orig'));
        }).always(function () {
            $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
        });
    });

    $(document).on('keydown', '.ic-alert-input', function (e) {
        if (e.which === 13) { e.preventDefault(); $(this).closest('.ic-alert-edit').find('.ic-alert-save').click(); }
    });

    // Change base unit (only rendered for single-unit products)
    $(document).on('change', '.ic-unit-edit', function () {
        var $sel = $(this);
        var product_id = $sel.data('product-id');
        var unit_id = $sel.val();
        var orig = $sel.data('orig');
        if (parseInt(unit_id, 10) === parseInt(orig, 10)) { return; }

        $sel.prop('disabled', true);
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/update-product-unit',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                product_id: product_id,
                unit_id: unit_id
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                $sel.data('orig', resp.unit_id);
                if (typeof dt !== 'undefined' && dt) {
                    dt.ajax.reload(null, false);
                }
            } else {
                toastr.error(resp.msg || 'Error');
                $sel.val(orig);
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
            $sel.val(orig);
        }).always(function () {
            $sel.prop('disabled', false);
        });
    });

    // Toggle exc/inc columns in per-unit prices modal based on checkbox
    function icApplyUnitColVisibility() {
        var inc = $('#ic_price_inc_tax').is(':checked');
        $('#ic_unit_prices_table .ic_unit_col_exc, #ic_unit_prices_table .ic_unit_col_exc_cell').toggle(!inc);
        $('#ic_unit_prices_table .ic_unit_col_inc, #ic_unit_prices_table .ic_unit_col_inc_cell').toggle(inc);
    }
    $(document).on('change', '#ic_price_inc_tax', icApplyUnitColVisibility);

    // Open per-unit prices modal
    $(document).on('click', '.ic-unit-prices-btn', function () {
        var variation_id = $(this).data('variation-id');
        var product_name = $(this).data('product') || '';
        var $modal = $('#ic_unit_prices_modal');
        $modal.data('variation-id', variation_id);
        $('#ic_unit_prices_meta').html('<strong>' + $('<div>').text(product_name).html() + '</strong>');
        $('#ic_unit_prices_table tbody').empty();
        $('#ic_unit_prices_loading').show();
        $modal.modal('show');

        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/unit-prices-grid',
            type: 'GET',
            data: { variation_id: variation_id },
            dataType: 'json'
        }).done(function (resp) {
            $('#ic_unit_prices_loading').hide();
            if (!resp.success) { toastr.error(resp.msg || 'Error'); return; }
            var rows = '';
            $.each(resp.units, function (i, u) {
                var disabled = u.is_base ? 'disabled' : '';
                var hint = u.is_base
                    ? ' <small class="text-muted">(@lang('lang_v1.base_unit'))</small>'
                    : '';
                rows += '<tr data-unit-id="' + u.unit_id + '">'
                    + '<td>' + $('<div>').text(u.name + (u.short_name ? ' (' + u.short_name + ')' : '')).html() + hint + '</td>'
                    + '<td class="text-right">' + (u.multiplier || 1) + '</td>'
                    + '<td class="ic_unit_col_exc_cell"><input type="text" class="form-control input-sm input_number ic-unit-price-input text-right" '
                        + 'data-type="exc" value="' + (u.price_exc_tax !== null ? icFmt(u.price_exc_tax) : '') + '" ' + disabled + '></td>'
                    + '<td class="ic_unit_col_inc_cell"><input type="text" class="form-control input-sm input_number ic-unit-price-input text-right" '
                        + 'data-type="inc" value="' + (u.price_inc_tax !== null ? icFmt(u.price_inc_tax) : '') + '" ' + disabled + '></td>'
                    + '<td class="text-center">'
                        + (u.is_base
                            ? '<span class="text-muted">—</span>'
                            : '<button type="button" class="btn btn-primary btn-xs ic-unit-price-save"><i class="fa fa-check"></i></button> '
                              + '<button type="button" class="btn btn-default btn-xs ic-unit-price-clear" title="@lang('messages.delete')"><i class="fa fa-times"></i></button>')
                    + '</td>'
                    + '</tr>';
            });
            $('#ic_unit_prices_table tbody').html(rows);
            icApplyUnitColVisibility();
        }).fail(function (xhr) {
            $('#ic_unit_prices_loading').hide();
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
        });
    });

    // Save a single unit-price override
    $(document).on('click', '.ic-unit-price-save', function () {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var unit_id = $row.data('unit-id');
        var variation_id = $('#ic_unit_prices_modal').data('variation-id');
        var inc = $('#ic_price_inc_tax').is(':checked');
        var $input = $row.find('.ic-unit-price-input[data-type="' + (inc ? 'inc' : 'exc') + '"]');
        var price = ($input.val() || '').toString().trim();
        if (price === '') { toastr.warning(@json(__('validation.required', ['attribute' => 'price']))); return; }

        $btn.prop('disabled', true).find('i').removeClass('fa-check').addClass('fa-spinner fa-spin');
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/update-sell-price',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                variation_id: variation_id,
                unit_id: unit_id,
                price: price,
                price_includes_tax: inc ? 1 : 0
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                if (resp.price_exc_tax !== null) {
                    $row.find('.ic-unit-price-input[data-type="exc"]').val(icFmt(resp.price_exc_tax));
                    $row.find('.ic-unit-price-input[data-type="inc"]').val(icFmt(resp.price_inc_tax));
                }
            } else {
                toastr.error(resp.msg || 'Error');
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
        }).always(function () {
            $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
        });
    });

    // Clear (delete) a per-unit override
    $(document).on('click', '.ic-unit-price-clear', function () {
        if (!confirm(@json(__('lang_v1.sure_to_delete')))) return;
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var unit_id = $row.data('unit-id');
        var variation_id = $('#ic_unit_prices_modal').data('variation-id');

        $btn.prop('disabled', true).find('i').removeClass('fa-times').addClass('fa-spinner fa-spin');
        $.ajax({
            url: BASE_URL + '/' + SESSION_ID + '/update-sell-price',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                variation_id: variation_id,
                unit_id: unit_id,
                price: '',
                price_includes_tax: icPriceIncTax()
            },
            dataType: 'json'
        }).done(function (resp) {
            if (resp.success) {
                toastr.success(resp.msg);
                $row.find('.ic-unit-price-input').val('');
            } else {
                toastr.error(resp.msg || 'Error');
            }
        }).fail(function (xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.msg) || 'Error');
        }).always(function () {
            $btn.prop('disabled', false).find('i').removeClass('fa-spinner fa-spin').addClass('fa-times');
        });
    });
    @endif
});
</script>
@endsection
