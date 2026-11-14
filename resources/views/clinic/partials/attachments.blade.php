{{--
    Photos / scans / PDFs of a lab or imaging request (App\ClinicAttachment).
    $type: lab_request | imaging_request; $owner: the request (attachments loaded or loadable)
    $readonly: no upload / delete buttons; $inner: only the list (AJAX refresh)
--}}
@php
    $att_list = $owner->relationLoaded('attachments') ? $owner->attachments : $owner->attachments()->with('uploadedBy')->get();
    $att_can = empty($readonly) && \App\Http\Controllers\ClinicAttachmentController::canUpload($type);
    $att_box = 'att_' . $type . '_' . $owner->id;
@endphp
@if (empty($inner))
<div class="clinic-attachments" id="{{ $att_box }}" data-type="{{ $type }}" data-id="{{ $owner->id }}"
     data-upload="{{ action([\App\Http\Controllers\ClinicAttachmentController::class, 'store'], [$type, $owner->id]) }}">
    @once
        <style>
            .clinic-attachments .att-grid { display:flex; flex-wrap:wrap; gap:10px; margin:6px 0; }
            .clinic-attachments .att-item { width:132px; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background:#fff; position:relative; }
            .clinic-attachments .att-thumb { display:flex; align-items:center; justify-content:center; height:96px; background:#f8fafc; color:#64748b; font-size:30px; }
            .clinic-attachments .att-thumb img { width:100%; height:96px; object-fit:cover; }
            .clinic-attachments .att-name { font-size:11px; padding:4px 6px; line-height:1.3; word-break:break-all; }
            .clinic-attachments .att-del { position:absolute; top:4px; right:4px; background:rgba(255,255,255,.9); border:0; border-radius:6px; color:#dc2626; padding:2px 6px; }
            .clinic-attachments .att-empty { color:#94a3b8; font-size:12.5px; }
        </style>
    @endonce
    <div class="att-list">
@endif
        @if ($att_list->isEmpty())
            <p class="att-empty"><i class="fas fa-paperclip"></i> @lang('clinic.att_none')</p>
        @else
            <div class="att-grid">
                @foreach ($att_list as $a)
                    <div class="att-item" data-att="{{ $a->id }}">
                        <a href="{{ $a->url() }}" target="_blank" rel="noopener" class="att-thumb" title="{{ $a->original_name }}">
                            @if ($a->isImage())
                                <img src="{{ $a->url() }}" alt="{{ $a->original_name }}" loading="lazy">
                            @else
                                <i class="fas {{ $a->isPdf() ? 'fa-file-pdf' : 'fa-file' }}"></i>
                            @endif
                        </a>
                        <div class="att-name">{{ \Illuminate\Support\Str::limit($a->original_name, 40) }}<br>
                            <span class="text-muted">{{ $a->sizeLabel() }} · {{ @format_date($a->created_at) }}</span></div>
                        @if ($att_can)
                            <button type="button" class="att-del" data-url="{{ action([\App\Http\Controllers\ClinicAttachmentController::class, 'destroy'], [$a->id]) }}" title="{{ __('messages.delete') }}" aria-label="{{ __('messages.delete') }}"><i class="fas fa-trash"></i></button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
@if (empty($inner))
    </div>
    @if ($att_can)
        <label class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline att-upload-btn" style="margin:0;">
            <i class="fas fa-camera"></i> @lang('clinic.att_add')
            <input type="file" class="att-input" multiple accept="image/*,application/pdf" style="display:none;">
        </label>
        <span class="text-muted att-hint" style="font-size:12px;margin-left:6px;">@lang('clinic.att_hint', ['mb' => \App\ClinicAttachment::MAX_KB / 1024])</span>
    @endif
</div>
@once
    <script>
        // upload as soon as files are chosen; the list refreshes in place
        (function () {
            if (window.__clinicAttBound) { return; }
            window.__clinicAttBound = true;
            var token = function () { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; };
            document.addEventListener('change', function (e) {
                var input = e.target;
                if (!input.classList || !input.classList.contains('att-input') || !input.files.length) { return; }
                var box = input.closest('.clinic-attachments');
                var fd = new FormData();
                [].slice.call(input.files).forEach(function (f) { fd.append('files[]', f); });
                fd.append('_token', token());
                var btn = box.querySelector('.att-upload-btn');
                btn.classList.add('disabled');
                btn.insertAdjacentHTML('beforeend', ' <i class="fas fa-spinner fa-spin att-spin"></i>');
                $.ajax({ url: box.getAttribute('data-upload'), method: 'POST', data: fd, processData: false, contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                    .done(function (r) {
                        box.querySelector('.att-list').innerHTML = r.html;
                        if (window.toastr) { r.success ? toastr.success(r.msg) : toastr.error(r.msg); }
                    })
                    .fail(function (x) {
                        var msg = (x.responseJSON && (x.responseJSON.message || x.responseJSON.msg)) || 'Upload failed';
                        if (x.responseJSON && x.responseJSON.errors) { msg = Object.values(x.responseJSON.errors)[0][0]; }
                        if (window.toastr) { toastr.error(msg); }
                    })
                    .always(function () {
                        input.value = '';
                        btn.classList.remove('disabled');
                        var s = btn.querySelector('.att-spin'); if (s) { s.remove(); }
                    });
            });
            document.addEventListener('click', function (e) {
                var del = e.target.closest && e.target.closest('.att-del');
                if (!del) { return; }
                e.preventDefault();
                var box = del.closest('.clinic-attachments');
                var go = function () {
                    $.ajax({ url: del.getAttribute('data-url'), method: 'POST', data: { _token: token() },
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .done(function (r) { box.querySelector('.att-list').innerHTML = r.html; if (window.toastr) { toastr.success(r.msg); } });
                };
                if (window.swal) {
                    swal({ title: (window.LANG && LANG.sure) || 'Sure?', icon: 'warning', buttons: true, dangerMode: true }).then(function (ok) { if (ok) { go(); } });
                } else if (confirm('?')) { go(); }
            });
        })();
    </script>
@endonce
@endif
