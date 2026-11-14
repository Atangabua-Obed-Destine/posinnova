{{--
    PosInnova: the doctors' lists on a page (App\Utils\ClinicOptions):
      .dl-picker      one-click chips under a text field (consultation.partials.picker)
      .dl-ac          suggestions while typing in an input (data-list), with "+ Add ... to the list"
      .dl-add-btn     "+" next to a select (data-list, data-target): a dialog adds a new entry
    Needs $doctor_lists. Exposes window.DoctorLists.
--}}
@php
    $dl_js = [
        'lists' => $doctor_lists,
        'add_url' => action([\App\Http\Controllers\ClinicOptionController::class, 'quickAdd']),
        'labels' => collect(\App\Utils\ClinicOptions::LISTS)->map(fn ($l) => __($l))->all(),
        't' => [
            'add' => __('clinic.dl_add'),
            'add_to_list' => __('clinic.dl_add_to_list'),
            'more' => __('clinic.dl_more'),
            'less' => __('clinic.dl_less'),
            'title' => __('clinic.dl_add_title'),
            'placeholder' => __('clinic.dl_add_one_placeholder'),
            'save' => __('messages.save'),
            'cancel' => __('messages.cancel'),
            'error' => __('messages.something_went_wrong'),
        ],
    ];
@endphp
<style>
    .dl-picker { display:flex; flex-wrap:wrap; gap:5px; margin-top:6px; }
    .dl-chip { border:1px solid #d1d5db; background:#fff; color:#374151; border-radius:999px; padding:2px 10px; font-size:12px; line-height:1.6; cursor:pointer; }
    .dl-chip:hover { border-color:#6366f1; color:#4338ca; }
    .dl-chip.dl-on { background:#eef2ff; border-color:#6366f1; color:#3730a3; font-weight:600; }
    .dl-chip.dl-on::before { content:"\2713  "; }
    .dl-chip.dl-more, .dl-chip.dl-plus { border-style:dashed; color:#6b7280; }
    .dl-chip.dl-plus { color:#4338ca; }
    .dl-picker.dl-collapsed .dl-chip.dl-extra { display:none; }
    .ui-autocomplete .dl-ac-add { color:#4338ca; font-style:italic; }
</style>
<div class="modal fade" id="dl_add_modal" tabindex="-1" role="dialog" aria-labelledby="dl_add_title">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('messages.close') }}"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="dl_add_title"></h4>
            </div>
            <div class="modal-body">
                <input type="text" class="form-control" id="dl_add_value" maxlength="191" autocomplete="off">
                <p class="help-block" style="margin-bottom:0;">@lang('clinic.dl_add_modal_help')</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang('messages.cancel')</button>
                <button type="button" class="btn btn-primary" id="dl_add_save">@lang('messages.save')</button>
            </div>
        </div>
    </div>
</div>
<script>
window.DoctorLists = (function ($) {
    var C = @json($dl_js);
    var VISIBLE = 12; // chips shown before "+ N more"
    var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };

    function splitter(sep) { return sep === 'nl' ? /\n+/ : /\s*[,;]\s*/; }
    function joiner(sep) { return sep === 'nl' ? '\n' : ', '; }
    function tokens(val, sep) {
        return String(val || '').split(splitter(sep)).map(function (t) { return t.trim(); }).filter(Boolean);
    }
    function has(val, v, sep) {
        var lv = v.toLowerCase();
        return tokens(val, sep).some(function (t) { return t.toLowerCase() === lv; });
    }

    /* ---- save a new entry (quick add) ---- */
    function save(list, value, done) {
        value = $.trim(value || '');
        if (!value) { return; }
        $.ajax({ url: C.add_url, method: 'POST', dataType: 'json',
            data: { list: list, value: value, _token: $('meta[name="csrf-token"]').attr('content') } })
            .done(function (r) {
                if (!r.success) { toastr.error(r.msg || C.t.error); return; }
                var arr = C.lists[list] = C.lists[list] || [];
                if (arr.map(function (x) { return x.toLowerCase(); }).indexOf(r.value.toLowerCase()) < 0) { arr.push(r.value); }
                renderPickers(list);
                toastr.success(r.msg);
                if (done) { done(r.value); }
            })
            .fail(function (x) { toastr.error((x.responseJSON && x.responseJSON.msg) || C.t.error); });
    }

    /* ---- the "add" dialog ---- */
    var modalDone = null, modalList = null, closing = false;
    $(document).on('hide.bs.modal', '#dl_add_modal', function () { closing = true; });
    $(document).on('hidden.bs.modal', '#dl_add_modal', function () { closing = false; });
    function openAdd(list, initial, done) {
        modalList = list; modalDone = done;
        $('#dl_add_title').text(C.t.title.replace(':list', C.labels[list] || list));
        $('#dl_add_value').val(initial || '').attr('placeholder', C.t.placeholder);
        var $m = $('#dl_add_modal');
        if (closing) {
            // still closing from the last entry: open again once it is gone
            $m.one('hidden.bs.modal', function () { $m.modal('show'); });
        } else {
            $m.modal('show');
        }
    }
    $(document).on('shown.bs.modal', '#dl_add_modal', function () { $('#dl_add_value').trigger('focus'); });
    $(document).on('click', '#dl_add_save', function () {
        var v = $('#dl_add_value').val();
        if (!$.trim(v)) { $('#dl_add_value').trigger('focus'); return; }
        save(modalList, v, function (stored) { $('#dl_add_modal').modal('hide'); if (modalDone) { modalDone(stored); } });
    });
    $(document).on('keydown', '#dl_add_value', function (e) { if (e.which === 13) { e.preventDefault(); $('#dl_add_save').trigger('click'); } });

    /* ---- chips under a field ---- */
    function renderPicker($p) {
        var list = $p.data('list'), sep = $p.data('sep'), $f = $($p.data('target'));
        var vals = C.lists[list] || [], cur = $f.val();
        var expanded = $p.hasClass('dl-expanded');
        var html = '';
        vals.forEach(function (v, i) {
            html += '<button type="button" class="dl-chip' + (has(cur, v, sep) ? ' dl-on' : '') + (i >= VISIBLE ? ' dl-extra' : '') + '" data-v="' + esc(v) + '">' + esc(v) + '</button>';
        });
        if (vals.length > VISIBLE) {
            html += '<button type="button" class="dl-chip dl-more">' + esc(expanded ? C.t.less : C.t.more.replace(':n', vals.length - VISIBLE)) + '</button>';
        }
        html += '<button type="button" class="dl-chip dl-plus" title="' + esc(C.t.title.replace(':list', C.labels[list] || list)) + '"><i class="fas fa-plus"></i> ' + esc(C.t.add) + '</button>';
        $p.html(html).toggleClass('dl-collapsed', !expanded);
    }
    function renderPickers(list) {
        $('.dl-picker').each(function () { if (!list || $(this).data('list') === list) { renderPicker($(this)); } });
    }
    function refreshOn($p) {
        var sep = $p.data('sep'), cur = $($p.data('target')).val();
        $p.find('.dl-chip[data-v]').each(function () { $(this).toggleClass('dl-on', has(cur, $(this).attr('data-v'), sep)); });
    }
    function toggleValue($p, v) {
        var sep = $p.data('sep'), $f = $($p.data('target'));
        var t = tokens($f.val(), sep), lv = v.toLowerCase();
        var at = -1;
        t.forEach(function (x, i) { if (x.toLowerCase() === lv) { at = i; } });
        if (at >= 0) { t.splice(at, 1); } else { t.push(v); }
        $f.val(t.join(joiner(sep))).trigger('input').trigger('change');
        refreshOn($p);
    }
    $(document).on('click', '.dl-picker .dl-chip[data-v]', function () { toggleValue($(this).closest('.dl-picker'), $(this).attr('data-v')); });
    $(document).on('click', '.dl-picker .dl-more', function () { var $p = $(this).closest('.dl-picker'); $p.toggleClass('dl-expanded'); renderPicker($p); });
    $(document).on('click', '.dl-picker .dl-plus', function () {
        var $p = $(this).closest('.dl-picker');
        openAdd($p.data('list'), '', function (stored) {
            var $f = $($p.data('target'));
            if (!has($f.val(), stored, $p.data('sep'))) { toggleValue($p, stored); }
        });
    });

    /* ---- "+" next to a select ---- */
    $(document).on('click', '.dl-add-btn', function () {
        var list = $(this).data('list'), $sel = $($(this).data('target'));
        openAdd(list, '', function (stored) {
            if (!$sel.find('option').filter(function () { return this.value === stored; }).length) {
                $sel.append(new Option(stored, stored, false, false));
            }
            $sel.val(stored).trigger('change');
        });
    });

    /* ---- suggestions while typing ---- */
    function attachAc($in) {
        if ($in.data('dlAc')) { return; }
        $in.data('dlAc', 1).attr('autocomplete', 'off');
        var list = $in.data('list');
        $in.autocomplete({
            minLength: 0, delay: 0,
            source: function (req, resp) {
                var term = $.trim(req.term), t = term.toLowerCase();
                var vals = C.lists[list] || [];
                var items = vals.filter(function (v) { return !t || v.toLowerCase().indexOf(t) >= 0; }).slice(0, 40)
                    .map(function (v) { return { label: v, value: v }; });
                if (term && !vals.some(function (v) { return v.toLowerCase() === t; })) {
                    items.push({ label: C.t.add_to_list.replace(':value', term), value: term, dlAdd: true });
                }
                resp(items);
            },
            focus: function () { return false; },
            select: function (e, ui) {
                if (ui.item.dlAdd) {
                    save(list, ui.item.value, function (stored) { $in.val(stored).trigger('change'); });
                } else {
                    $in.val(ui.item.value).trigger('change');
                }
                return false;
            },
        });
        $in.autocomplete('instance')._renderItem = function (ul, item) {
            return $('<li>').append($('<div>').addClass(item.dlAdd ? 'dl-ac-add' : '').html((item.dlAdd ? '<i class="fas fa-plus"></i> ' : '') + esc(item.label))).appendTo(ul);
        };
    }
    $(document).on('focusin', 'input.dl-ac', function () {
        var $in = $(this);
        attachAc($in);
        if (!$in.val()) { $in.autocomplete('search', ''); }
    });
    $(document).on('click', 'input.dl-ac', function () {
        var $in = $(this);
        attachAc($in);
        if (!$in.autocomplete('widget').is(':visible')) { $in.autocomplete('search', $in.val() ? $in.val() : ''); }
    });

    $(function () {
        renderPickers();
        $('.dl-picker').each(function () {
            var $p = $(this);
            $($p.data('target')).on('input change', function () { refreshOn($p); });
        });
    });

    return { lists: C.lists, save: save, openAdd: openAdd, renderPickers: renderPickers };
})(jQuery);
</script>
