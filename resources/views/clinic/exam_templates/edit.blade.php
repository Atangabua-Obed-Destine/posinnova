@extends('layouts.app')
@section('title', $template->exists ? $template->name : __('clinic.ex_tpl_new'))

@php
    $tb = [
        'blocks' => $template->blocks ?: [],
        'library' => $library,
        'exists' => $template->exists,
        'save_url' => $template->exists
            ? action([\App\Http\Controllers\ClinicExamTemplateController::class, 'update'], [$template->id])
            : action([\App\Http\Controllers\ClinicExamTemplateController::class, 'store']),
        'preview_url' => action([\App\Http\Controllers\ClinicExamTemplateController::class, 'preview']),
        'upload_url' => action([\App\Http\Controllers\ClinicExamTemplateController::class, 'uploadImage']),
        'types' => [
            'heading' => ['label' => __('clinic.ex_type_heading'), 'icon' => 'fa-heading', 'new' => __('clinic.ex_new_heading')],
            'drawing' => ['label' => __('clinic.ex_type_drawing'), 'icon' => 'fa-pencil-alt', 'new' => __('clinic.ex_new_drawing')],
            'choice' => ['label' => __('clinic.ex_type_choice'), 'icon' => 'fa-dot-circle', 'new' => __('clinic.ex_new_question')],
            'multi' => ['label' => __('clinic.ex_type_multi'), 'icon' => 'fa-check-square', 'new' => __('clinic.ex_new_question')],
            'text' => ['label' => __('clinic.ex_type_text'), 'icon' => 'fa-i-cursor', 'new' => __('clinic.ex_new_question')],
            'notes' => ['label' => __('clinic.ex_type_notes'), 'icon' => 'fa-align-left', 'new' => __('clinic.ex_new_notes')],
            'number' => ['label' => __('clinic.ex_type_number'), 'icon' => 'fa-hashtag', 'new' => __('clinic.ex_new_question')],
        ],
        'widths' => collect(array_keys(\App\Utils\ClinicExamTemplates::WIDTHS))->mapWithKeys(fn ($w) => [$w => __('clinic.ex_width_' . $w)])->all(),
        'cols' => \App\Utils\ClinicExamTemplates::WIDTHS,
        't' => [
            'no_title' => __('clinic.ex_no_title'), 'blank' => __('clinic.ex_blank_page'), 'sides' => __('clinic.ex_sides_badge'),
            'other' => __('clinic.ex_other_badge'), 'no_options' => __('clinic.ex_no_options'), 'edit' => __('messages.edit'),
            'duplicate' => __('clinic.ex_tpl_duplicate'), 'delete' => __('messages.delete'), 'saved' => __('clinic.ex_tpl_saved'),
            'name_required' => __('clinic.ex_tpl_name_required'), 'error' => __('messages.something_went_wrong'),
            'delete_block' => __('clinic.ex_delete_block'), 'empty' => __('clinic.ex_builder_empty'), 'unit' => __('clinic.ex_unit'),
            'more' => __('clinic.ex_more_options'),
        ],
    ];
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        {{ $template->exists ? $template->name : __('clinic.ex_tpl_new') }}
        <small class="tw-text-gray-600">@lang('clinic.ex_builder_lead')</small>
    </h1>
</section>

<section class="content">
    <style>
        .tb-palette-item { display:flex; gap:10px; align-items:flex-start; padding:9px 10px; margin-bottom:6px; border:1px solid #e5e7eb; border-radius:10px; background:#fff; cursor:grab; user-select:none; }
        .tb-palette-item:hover, .tb-palette-item:focus { border-color:#6366f1; background:#f5f3ff; outline:none; }
        .tb-palette-item i { width:18px; text-align:center; color:#4f46e5; margin-top:2px; }
        .tb-palette-item small { display:block; color:#6b7280; font-size:11.5px; line-height:1.3; }
        .ui-draggable-dragging.tb-palette-item { width:220px; box-shadow:0 8px 20px rgba(0,0,0,.15); z-index:3000; }
        #tb_blocks { display:flex; flex-wrap:wrap; margin:0 -6px; min-height:140px; padding:4px 0; border-radius:10px; }
        #tb_blocks.tb-empty { border:2px dashed #c7d2fe; align-items:center; justify-content:center; }
        #tb_blocks.tb-empty::before { content:attr(data-empty); color:#6366f1; font-size:14px; padding:30px; text-align:center; }
        .tb-block, .tb-placeholder { padding:0 6px 10px; box-sizing:border-box; width:100%; }
        .tb-cols-12 { width:100%; } .tb-cols-9 { width:75%; } .tb-cols-8 { width:66.666%; } .tb-cols-6 { width:50%; } .tb-cols-4 { width:33.333%; } .tb-cols-3 { width:25%; }
        .tb-placeholder::after { content:""; display:block; height:100%; min-height:60px; border:2px dashed #818cf8; border-radius:10px; background:#eef2ff; }
        .tb-card { border:1px solid #e5e7eb; border-radius:10px; background:#fff; height:100%; cursor:move; transition:border-color .15s, box-shadow .15s; }
        .tb-card:hover { border-color:#a5b4fc; box-shadow:0 2px 8px rgba(79,70,229,.08); }
        .tb-block.tb-selected .tb-card { border-color:#4f46e5; box-shadow:0 0 0 2px #c7d2fe; }
        .tb-card-head { display:flex; align-items:center; gap:6px; padding:7px 9px; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .tb-card-head .tb-type { color:#4f46e5; }
        .tb-card-head strong { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .tb-card-actions { display:flex; gap:2px; flex:0 0 auto; }
        .tb-card-actions button { border:0; background:transparent; color:#64748b; padding:2px 5px; border-radius:5px; }
        .tb-card-actions button:hover { background:#f1f5f9; color:#1e293b; }
        .tb-card-actions .tb-del:hover { color:#dc2626; }
        .tb-card-body { padding:7px 9px; font-size:12px; color:#475569; }
        /* narrow cards (a quarter, a third): the whole title, on its own line */
        .tb-cols-3 .tb-card-head, .tb-cols-4 .tb-card-head { flex-wrap:wrap; }
        .tb-cols-3 .tb-card-head strong, .tb-cols-4 .tb-card-head strong { white-space:normal; order:3; flex-basis:100%; }
        .tb-cols-3 .tb-wbadge, .tb-cols-4 .tb-wbadge { display:none; }
        .tb-cols-3 .tb-card-actions, .tb-cols-4 .tb-card-actions { margin-left:auto; }
        .tb-card-body img { max-width:100%; max-height:110px; display:block; margin:0 auto; }
        .tb-card-body .tb-blank { height:60px; border:1px dashed #cbd5e1; border-radius:6px; display:flex; align-items:center; justify-content:center; color:#94a3b8; }
        .tb-chip { display:inline-block; border:1px solid #e2e8f0; border-radius:999px; padding:0 7px; margin:1px; font-size:11px; }
        .tb-badge { font-size:10px; border-radius:5px; padding:1px 5px; background:#e0e7ff; color:#3730a3; font-weight:600; }
        .tb-type-heading .tb-card { background:#eef2ff; border-color:#c7d2fe; }
        .tb-type-heading .tb-card-head { border-bottom:0; font-size:15px; color:#3730a3; }
        .tb-type-heading .tb-card-body { display:none; }
        .tb-lib { display:grid; grid-template-columns:repeat(auto-fill, minmax(104px, 1fr)); gap:8px; max-height:330px; overflow:auto; padding:2px; }
        .tb-lib-item { border:2px solid #e5e7eb; border-radius:8px; padding:4px; cursor:pointer; background:#fff; text-align:center; font-size:11px; color:#475569; }
        .tb-lib-item img { width:100%; height:70px; object-fit:contain; display:block; }
        .tb-lib-item .tb-blank { height:70px; display:flex; align-items:center; justify-content:center; color:#94a3b8; font-size:22px; }
        .tb-lib-item.tb-on { border-color:#4f46e5; background:#eef2ff; }
        .tb-lib-item:focus { outline:2px solid #6366f1; }
        @media (max-width:991px) { .tb-cols-3, .tb-cols-4 { width:50%; } }
        /* the blocks stay in view while the form is scrolled (the column is as tall as the form) */
        .tb-sticky.tb-fixed { position:fixed; top:64px; z-index:20; max-height:calc(100vh - 74px); overflow:auto; }
    </style>

    <div class="row tb-row">
        <div class="col-md-3">
            <div class="box box-solid tb-sticky">
                <div class="box-header with-border"><h3 class="box-title"><i class="fas fa-th-large"></i> @lang('clinic.ex_palette')</h3></div>
                <div class="box-body" id="tb_palette">
                    <p class="help-block" style="margin-top:0;">@lang('clinic.ex_palette_help')</p>
                    @foreach ($tb['types'] as $type => $info)
                        <div class="tb-palette-item" data-type="{{ $type }}" tabindex="0" role="button">
                            <i class="fas {{ $info['icon'] }}"></i>
                            <div><strong>{{ $info['label'] }}</strong><small>@lang('clinic.ex_type_' . $type . '_help')</small></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="col-md-9">
            @component('components.widget', ['class' => 'box-solid'])
                <div class="row">
                    <div class="col-sm-5">
                        <div class="form-group">
                            <label for="tb_name">@lang('clinic.ex_tpl_name'):*</label>
                            <input type="text" id="tb_name" class="form-control" maxlength="191" value="{{ $template->name }}" placeholder="{{ __('clinic.ex_tpl_name_ph') }}" required>
                        </div>
                    </div>
                    <div class="col-sm-5">
                        <div class="form-group">
                            <label for="tb_specialties">@lang('clinic.ex_tpl_specialties'):</label>
                            <select id="tb_specialties" class="form-control" multiple style="width:100%;">
                                @foreach ($specialties as $sp)
                                    <option value="{{ $sp }}" {{ in_array($sp, (array) $template->specialties, true) ? 'selected' : '' }}>{{ $sp }}</option>
                                @endforeach
                            </select>
                            <p class="help-block" style="margin-bottom:0;font-size:12px;">@lang('clinic.ex_tpl_specialties_help')</p>
                        </div>
                    </div>
                    <div class="col-sm-2">
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div class="checkbox" style="margin-top:4px;">
                                <label><input type="checkbox" id="tb_active" {{ $template->is_active ? 'checked' : '' }}> @lang('clinic.ex_tpl_active')</label>
                            </div>
                        </div>
                    </div>
                </div>
            @endcomponent

            <div class="box box-solid">
                <div class="box-header with-border" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <h3 class="box-title"><i class="fas fa-clipboard-list"></i> @lang('clinic.ex_the_form')</h3>
                    <span class="text-muted" style="font-size:12px;">@lang('clinic.ex_builder_hint')</span>
                    <div style="margin-left:auto;display:flex;gap:6px;">
                        <a href="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'guide']) }}#gd_setup" target="_blank" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-book-open"></i> @lang('clinic.ex_guide_btn')</a>
                        <a href="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-arrow-left"></i> @lang('clinic.ex_back')</a>
                        <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" id="tb_preview"><i class="fas fa-eye"></i> @lang('clinic.ex_preview')</button>
                        <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" id="tb_save"><i class="fas fa-save"></i> @lang('messages.save')</button>
                    </div>
                </div>
                <div class="box-body">
                    <div id="tb_blocks" data-empty="{{ __('clinic.ex_builder_empty') }}"></div>
                </div>
            </div>
        </div>
    </div>

    {{-- one block's settings --}}
    <div class="modal fade" id="tb_edit_modal" tabindex="-1" role="dialog" aria-labelledby="tb_edit_title">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="tb_edit_title"></h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="tb_f_label">@lang('clinic.ex_f_label')</label>
                        <input type="text" class="form-control" id="tb_f_label" maxlength="191">
                    </div>
                    <div class="form-group tb-for tb-for-choice tb-for-multi">
                        <label>@lang('clinic.ex_f_answers')</label>
                        <div>
                            <label class="radio-inline"><input type="radio" name="tb_f_kind" value="choice"> @lang('clinic.ex_type_choice')</label>
                            <label class="radio-inline"><input type="radio" name="tb_f_kind" value="multi"> @lang('clinic.ex_type_multi')</label>
                        </div>
                    </div>
                    <div class="form-group tb-for tb-for-choice tb-for-multi">
                        <label for="tb_f_options">@lang('clinic.ex_f_options')</label>
                        <textarea class="form-control" id="tb_f_options" rows="7" placeholder="{{ __('clinic.ex_f_options_ph') }}"></textarea>
                        <div class="checkbox" style="margin-bottom:0;"><label><input type="checkbox" id="tb_f_other"> @lang('clinic.ex_f_other')</label></div>
                    </div>
                    <div class="form-group tb-for tb-for-drawing">
                        <label>@lang('clinic.ex_f_picture')</label>
                        <div class="tb-lib" id="tb_f_lib"></div>
                        <label class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" style="margin-top:8px;">
                            <i class="fas fa-upload"></i> @lang('clinic.ex_f_upload')
                            <input type="file" id="tb_f_upload" accept="image/png,image/jpeg,image/webp,image/gif" style="display:none;">
                        </label>
                        <span class="text-muted" style="font-size:12px;">@lang('clinic.ex_f_upload_help')</span>
                    </div>
                    <div class="row">
                        <div class="col-sm-6 tb-for tb-for-drawing tb-for-choice tb-for-multi tb-for-text tb-for-notes tb-for-number">
                            <div class="form-group">
                                <label for="tb_f_width">@lang('clinic.ex_f_width')</label>
                                <select class="form-control" id="tb_f_width">
                                    @foreach ($tb['widths'] as $w => $wl)
                                        <option value="{{ $w }}">{{ $wl }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-6 tb-for tb-for-number">
                            <div class="form-group">
                                <label for="tb_f_unit">@lang('clinic.ex_unit')</label>
                                <input type="text" class="form-control" id="tb_f_unit" maxlength="20" placeholder="mmHg, cm, dB…">
                            </div>
                        </div>
                    </div>
                    <div class="checkbox tb-for tb-for-choice tb-for-multi tb-for-text tb-for-notes tb-for-number">
                        <label><input type="checkbox" id="tb_f_sides"> @lang('clinic.ex_f_sides')</label>
                    </div>
                    <div class="form-group tb-for tb-for-drawing tb-for-choice tb-for-multi tb-for-text tb-for-notes tb-for-number" style="margin-bottom:0;">
                        <label for="tb_f_help">@lang('clinic.ex_f_help')</label>
                        <input type="text" class="form-control" id="tb_f_help" maxlength="191">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                    <button type="button" class="btn btn-primary" id="tb_f_apply">@lang('clinic.ex_f_apply')</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tb_preview_modal" tabindex="-1" role="dialog" aria-labelledby="tb_preview_title">
        <div class="modal-dialog modal-lg" role="document" style="width:min(1100px, 96vw);">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="tb_preview_title"><i class="fas fa-eye"></i> @lang('clinic.ex_preview_title')</h4>
                </div>
                <div class="modal-body" id="tb_preview_body"></div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
@include('consultation.exam.assets')
<script>
$(function () {
    var C = @json($tb);
    var B = {}; // id => block
    var dirty = false, editing = null;
    var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };
    var $list = $('#tb_blocks');

    function uid() { var id; do { id = 'b' + Math.random().toString(16).slice(2, 10); } while (B[id]); return id; }
    function setDirty() { dirty = true; }
    function emptyState() { $list.toggleClass('tb-empty', $list.children('.tb-block').length === 0); }

    function newBlock(type) {
        var b = { id: uid(), type: type, label: C.types[type]['new'], width: type === 'drawing' ? 'half' : 'full' };
        if (type === 'choice' || type === 'multi') { b.options = []; b.other = true; b.sides = false; }
        if (type === 'text' || type === 'notes') { b.sides = false; }
        if (type === 'number') { b.sides = false; b.unit = ''; }
        if (type === 'drawing') { b.image = null; }
        B[b.id] = b;
        return b;
    }
    function imgUrl(path) {
        var it = C.library.filter(function (x) { return x.path === path; })[0];
        return it ? it.url : null;
    }
    function card(b) {
        var cols = b.type === 'heading' ? 12 : (C.cols[b.width] || 12);
        var badges = '';
        if (b.sides) { badges += ' <span class="tb-badge">' + esc(C.t.sides) + '</span>'; }
        if (b.type !== 'heading') { badges += ' <span class="tb-badge tb-wbadge" style="background:#f1f5f9;color:#475569;">' + esc(C.widths[b.width] || '') + '</span>'; }
        var body = '';
        if (b.type === 'drawing') {
            var u = b.image ? imgUrl(b.image) : null;
            body = u ? '<img src="' + esc(u) + '" alt="">' : '<div class="tb-blank"><i class="fas fa-pencil-alt"></i>&nbsp;' + esc(C.t.blank) + '</div>';
        } else if (b.type === 'choice' || b.type === 'multi') {
            var o = (b.options || []);
            body = o.length ? o.slice(0, 8).map(function (x) { return '<span class="tb-chip">' + esc(x) + '</span>'; }).join('') + (o.length > 8 ? ' <span class="text-muted">' + esc(C.t.more.replace(':n', o.length - 8)) + '</span>' : '')
                : '<span class="text-warning"><i class="fas fa-exclamation-triangle"></i> ' + esc(C.t.no_options) + '</span>';
            if (b.other) { body += ' <span class="tb-chip" style="border-style:dashed;">' + esc(C.t.other) + '</span>'; }
        } else if (b.type === 'number') {
            body = '<span class="text-muted">123</span> ' + esc(b.unit || '');
        } else if (b.type !== 'heading') {
            body = '<span class="text-muted">' + esc(C.types[b.type].label) + '</span>';
        }
        if (b.help) { body += '<div class="text-muted" style="margin-top:3px;font-style:italic;">' + esc(b.help) + '</div>'; }
        return $('<div class="tb-block tb-cols-' + cols + ' tb-type-' + b.type + '" data-id="' + esc(b.id) + '">' +
            '<div class="tb-card">' +
            '<div class="tb-card-head"><i class="fas ' + C.types[b.type].icon + ' tb-type" title="' + esc(C.types[b.type].label) + '"></i>' +
            '<strong>' + esc(b.label || C.t.no_title) + '</strong>' + badges +
            '<span class="tb-card-actions">' +
            '<button type="button" class="tb-edit" title="' + esc(C.t.edit) + '" aria-label="' + esc(C.t.edit) + '"><i class="fas fa-pen"></i></button>' +
            '<button type="button" class="tb-dup" title="' + esc(C.t.duplicate) + '" aria-label="' + esc(C.t.duplicate) + '"><i class="fas fa-copy"></i></button>' +
            '<button type="button" class="tb-del" title="' + esc(C.t.delete) + '" aria-label="' + esc(C.t.delete) + '"><i class="fas fa-trash"></i></button>' +
            '</span></div>' +
            '<div class="tb-card-body">' + body + '</div>' +
            '</div></div>');
    }
    function rerender(id) { var $old = $list.children('[data-id="' + id + '"]'); var $n = card(B[id]); $old.replaceWith($n); }
    function order() { return $list.children('.tb-block').map(function () { return B[$(this).data('id')]; }).get().filter(Boolean); }

    // the saved form
    (C.blocks || []).forEach(function (b) { b = $.extend(true, {}, b); if (!b.id || B[b.id]) { b.id = uid(); } B[b.id] = b; $list.append(card(b)); });
    emptyState();

    // palette blocks dropped into the form become blocks
    function convertPalette() {
        $list.children('.tb-palette-item').each(function () {
            var b = newBlock($(this).data('type'));
            $(this).replaceWith(card(b));
            setDirty();
            openEditor(b.id);
        });
        emptyState();
    }
    $list.sortable({
        items: '> .tb-block, > .tb-palette-item',
        tolerance: 'pointer',
        placeholder: 'tb-placeholder',
        forcePlaceholderSize: true,
        start: function (e, ui) {
            var m = (ui.item.attr('class') || '').match(/tb-cols-\d+/);
            ui.placeholder.addClass(m ? m[0] : 'tb-cols-12').height(ui.item.outerHeight());
        },
        update: function () { setDirty(); },
        receive: function () { setTimeout(convertPalette, 0); },
        stop: function () { setTimeout(convertPalette, 0); },
    });
    $('.tb-palette-item').draggable({ connectToSortable: '#tb_blocks', helper: 'clone', revert: 'invalid', appendTo: 'body', zIndex: 3000,
        stop: function () { setTimeout(convertPalette, 0); } });
    // click (or Enter) on a palette block adds it at the end
    $('#tb_palette').on('click keydown', '.tb-palette-item', function (e) {
        if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) { return; }
        e.preventDefault();
        var b = newBlock($(this).data('type'));
        var $c = card(b).appendTo($list);
        emptyState();
        setDirty();
        $c[0].scrollIntoView({ block: 'center' });
        openEditor(b.id);
    });

    $list.on('click', '.tb-del', function (e) {
        e.stopPropagation();
        var $b = $(this).closest('.tb-block');
        swal({ title: C.t.delete_block, icon: 'warning', buttons: true, dangerMode: true }).then(function (ok) {
            if (!ok) { return; }
            delete B[$b.data('id')]; $b.remove(); emptyState(); setDirty();
        });
    });
    $list.on('click', '.tb-dup', function (e) {
        e.stopPropagation();
        var src = B[$(this).closest('.tb-block').data('id')];
        var b = $.extend(true, {}, src); b.id = uid(); B[b.id] = b;
        $(this).closest('.tb-block').after(card(b));
        setDirty();
    });
    $list.on('click', '.tb-edit', function (e) { e.stopPropagation(); openEditor($(this).closest('.tb-block').data('id')); });
    $list.on('dblclick', '.tb-card', function () { openEditor($(this).closest('.tb-block').data('id')); });

    /* ---- one block's settings ---- */
    function libHtml(sel) {
        var h = '<div class="tb-lib-item' + (!sel ? ' tb-on' : '') + '" data-path="" tabindex="0" role="button"><div class="tb-blank"><i class="fas fa-file"></i></div>' + esc(C.t.blank) + '</div>';
        C.library.forEach(function (it) {
            h += '<div class="tb-lib-item' + (sel === it.path ? ' tb-on' : '') + '" data-path="' + esc(it.path) + '" tabindex="0" role="button" title="' + esc(it.label) + '">' +
                '<img src="' + esc(it.url) + '" alt="">' + esc(it.label) + '</div>';
        });
        return h;
    }
    function openEditor(id) {
        var b = B[id]; if (!b) { return; }
        editing = id;
        $list.children().removeClass('tb-selected');
        $list.children('[data-id="' + id + '"]').addClass('tb-selected');
        var $m = $('#tb_edit_modal');
        $('#tb_edit_title').html('<i class="fas ' + C.types[b.type].icon + '"></i> ' + esc(C.types[b.type].label));
        $m.find('.tb-for').hide();
        $m.find('.tb-for-' + b.type).show();
        $('#tb_f_label').val(b.label || '');
        $('input[name="tb_f_kind"][value="' + b.type + '"]').prop('checked', true);
        $('#tb_f_options').val((b.options || []).join('\n'));
        $('#tb_f_other').prop('checked', b.other !== false);
        $('#tb_f_width').val(b.width || 'full');
        $('#tb_f_unit').val(b.unit || '');
        $('#tb_f_sides').prop('checked', !!b.sides);
        $('#tb_f_help').val(b.help || '');
        $('#tb_f_lib').html(libHtml(b.image || ''));
        $m.modal('show');
    }
    $('#tb_edit_modal').on('shown.bs.modal', function () { $('#tb_f_label').trigger('focus').select(); });
    $('#tb_edit_modal').on('hidden.bs.modal', function () { $list.children().removeClass('tb-selected'); });
    $('#tb_f_lib').on('click keydown', '.tb-lib-item', function (e) {
        if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) { return; }
        e.preventDefault();
        $('#tb_f_lib .tb-lib-item').removeClass('tb-on');
        $(this).addClass('tb-on');
    });
    $('#tb_f_upload').on('change', function () {
        var f = this.files[0]; if (!f) { return; }
        var fd = new FormData(); fd.append('image', f); fd.append('_token', $('meta[name="csrf-token"]').attr('content'));
        var input = this;
        $.ajax({ url: C.upload_url, method: 'POST', data: fd, processData: false, contentType: false, headers: { 'Accept': 'application/json' } })
            .done(function (r) {
                if (!r.success) { toastr.error(r.msg || C.t.error); return; }
                C.library.push({ path: r.path, url: r.url, label: r.label, own: true });
                $('#tb_f_lib').html(libHtml(r.path));
            })
            .fail(function (x) {
                var msg = (x.responseJSON && (x.responseJSON.msg || x.responseJSON.message)) || C.t.error;
                if (x.responseJSON && x.responseJSON.errors) { msg = Object.values(x.responseJSON.errors)[0][0]; }
                toastr.error(msg);
            })
            .always(function () { input.value = ''; });
    });
    function apply() {
        var b = B[editing]; if (!b) { return; }
        b.label = $.trim($('#tb_f_label').val());
        if (b.type === 'choice' || b.type === 'multi') {
            b.type = $('input[name="tb_f_kind"]:checked').val() || b.type;
            b.options = $('#tb_f_options').val().split(/\r?\n/).map(function (x) { return $.trim(x); }).filter(Boolean);
            b.other = $('#tb_f_other').is(':checked');
        }
        if (b.type !== 'heading') {
            b.width = $('#tb_f_width').val() || 'full';
            b.help = $.trim($('#tb_f_help').val());
        }
        if (['choice', 'multi', 'text', 'notes', 'number'].indexOf(b.type) >= 0) { b.sides = $('#tb_f_sides').is(':checked'); }
        if (b.type === 'number') { b.unit = $.trim($('#tb_f_unit').val()); }
        if (b.type === 'drawing') { b.image = $('#tb_f_lib .tb-lib-item.tb-on').data('path') || null; }
        rerender(editing);
        setDirty();
        $('#tb_edit_modal').modal('hide');
    }
    $('#tb_f_apply').on('click', apply);
    $('#tb_f_label, #tb_f_unit, #tb_f_help').on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); apply(); } });

    /* ---- details, preview, save ---- */
    $('#tb_specialties').select2({ tags: true, width: '100%', tokenSeparators: [','] });
    var spLabel = function () { $('#tb_specialties').next('.select2').find('.select2-search__field').attr('aria-label', $('label[for=tb_specialties]').text()); };
    spLabel();
    $('#tb_specialties').on('change select2:open select2:close', spLabel);
    $('#tb_name, #tb_active, #tb_specialties').on('change input', setDirty);

    $('#tb_preview').on('click', function () {
        $.post(C.preview_url, { blocks: JSON.stringify(order()), _token: $('meta[name="csrf-token"]').attr('content') })
            .done(function (html) {
                $('#tb_preview_body').html(html);
                $('#tb_preview_modal').modal('show');
                window.ExamForm.init(document.getElementById('tb_preview_body'));
            })
            .fail(function () { toastr.error(C.t.error); });
    });

    $('#tb_save').on('click', function () {
        var name = $.trim($('#tb_name').val());
        if (!name) { toastr.error(C.t.name_required); $('#tb_name').trigger('focus'); return; }
        var $btn = $(this).prop('disabled', true);
        $.ajax({ url: C.save_url, method: 'POST', dataType: 'json', headers: { 'Accept': 'application/json' }, data: {
            _token: $('meta[name="csrf-token"]').attr('content'),
            name: name, specialties: $('#tb_specialties').val() || [], is_active: $('#tb_active').is(':checked') ? 1 : 0,
            blocks: JSON.stringify(order()),
        } }).done(function (r) {
            if (!r.success) { toastr.error(r.msg || C.t.error); return; }
            dirty = false;
            toastr.success(r.msg);
            if (r.redirect && !C.exists) { window.location = r.redirect; return; }
            // ids as stored
            if (r.blocks) {
                B = {}; $list.empty();
                r.blocks.forEach(function (b) { B[b.id] = b; $list.append(card(b)); });
                emptyState();
            }
        }).fail(function (x) {
            var msg = (x.responseJSON && (x.responseJSON.msg || x.responseJSON.message)) || C.t.error;
            if (x.responseJSON && x.responseJSON.errors) { msg = Object.values(x.responseJSON.errors)[0][0]; }
            toastr.error(msg);
        }).always(function () { $btn.prop('disabled', false); });
    });
    window.onbeforeunload = function () { if (dirty) { return LANG.sure; } };

    // the blocks stay in view while a long form is scrolled (the page scrolls inside the layout, so sticky CSS cannot do it)
    var $pal = $('.tb-sticky'), $col = $pal.parent(), raf = null;
    function keepPalette() {
        raf = null;
        var wide = window.innerWidth >= 992, top = $col[0].getBoundingClientRect().top;
        var fix = wide && top < 64 && $list.outerHeight() > $pal.outerHeight();
        $pal.toggleClass('tb-fixed', fix).css('width', fix ? $col.width() : '');
    }
    document.addEventListener('scroll', function () { if (!raf) { raf = requestAnimationFrame(keepPalette); } }, true);
    $(window).on('resize', keepPalette);
    keepPalette();
});
</script>
@endsection
