{{--
    Photos / scans / PDFs of a lab request, an imaging request or a consultation (App\ClinicAttachment).
    $type: lab_request | imaging_request | consultation; $owner: the request / consultation (attachments loaded or
    loadable), or null for a consultation not saved yet: then $draft_token and $visit_id.
    $readonly: no upload / delete / camera buttons; $inner: only the list (AJAX refresh)
--}}
@php
    $owner = $owner ?? null;
    $draft_token = $draft_token ?? null;
    $visit_id = $visit_id ?? null;
    if ($owner) {
        $att_list = $owner->relationLoaded('attachments') ? $owner->attachments : $owner->attachments()->with('uploadedBy')->get();
    } else {
        $att_list = \App\ClinicAttachment::where('business_id', session('user.business_id'))->where('attachable_type', $type)
            ->where('attachable_id', 0)->where('draft_token', (string) $draft_token)->where('uploaded_by', auth()->id())
            ->orderBy('id')->get();
    }
    $att_can = empty($readonly) && \App\Http\Controllers\ClinicAttachmentController::canUpload($type);
    $att_box = 'att_' . $type . '_' . ($owner ? $owner->id : 'draft');
    $att_upload = action([\App\Http\Controllers\ClinicAttachmentController::class, 'store'], [$type, $owner ? $owner->id : 0])
        . ($owner ? '' : '?' . http_build_query(['token' => $draft_token, 'visit_id' => $visit_id]));
@endphp
@if (empty($inner))
<div class="clinic-attachments" id="{{ $att_box }}" data-type="{{ $type }}" data-id="{{ $owner ? $owner->id : 0 }}" data-visit="{{ $visit_id }}"
     data-upload="{{ $att_upload }}">
    @once
        <style>
            .clinic-attachments .att-grid { display:flex; flex-wrap:wrap; gap:10px; margin:6px 0; }
            .clinic-attachments .att-item { width:132px; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; background:#fff; position:relative; }
            .clinic-attachments .att-thumb { display:flex; align-items:center; justify-content:center; height:96px; background:#f8fafc; color:#64748b; font-size:30px; position:relative; }
            .clinic-attachments .att-thumb img { width:100%; height:96px; object-fit:cover; }
            .clinic-attachments .att-name { font-size:11px; padding:4px 6px; line-height:1.3; word-break:break-all; }
            .clinic-attachments .att-del { position:absolute; top:4px; right:4px; background:rgba(255,255,255,.9); border:0; border-radius:6px; color:#dc2626; padding:2px 6px; z-index:2; }
            .clinic-attachments .att-empty { color:#94a3b8; font-size:12.5px; }
            .clinic-attachments .att-actions { display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
            #att_cam_modal video { width:100%; max-height:60vh; background:#000; border-radius:8px; }
            #att_cam_modal .att-cam-shots { display:flex; gap:6px; flex-wrap:wrap; margin-top:8px; }
            #att_cam_modal .att-cam-shots img { height:54px; border-radius:6px; border:1px solid #e5e7eb; }
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
        <div class="att-actions">
            <label class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline att-upload-btn" style="margin:0;">
                <i class="fas fa-upload"></i> @lang('clinic.att_add')
                <input type="file" class="att-input" multiple accept="image/*,application/pdf" style="display:none;">
            </label>
            <button type="button" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline att-camera-btn" style="display:none;" title="{{ __('clinic.att_camera_help') }}">
                <i class="fas fa-camera"></i> @lang('clinic.att_camera')
            </button>
            <span class="text-muted att-hint" style="font-size:12px;">@lang('clinic.att_hint', ['mb' => \App\ClinicAttachment::MAX_KB / 1024])</span>
        </div>
    @endif
</div>
@once
    @php
        $att_cam_t = [
            'title' => __('clinic.att_cam_title'), 'device' => __('clinic.att_cam_device'), 'photo' => __('clinic.att_cam_photo'),
            'close' => __('messages.close'), 'denied' => __('clinic.att_cam_denied'), 'none' => __('clinic.att_cam_none'),
            'help' => __('clinic.att_cam_modal_help'),
            'too_big' => __('clinic.att_too_big', ['mb' => \App\ClinicAttachment::MAX_KB / 1024]),
        ];
    @endphp
    <script>
        // upload as soon as files are chosen (or taken with the camera); the list refreshes in place
        (function () {
            if (window.__clinicAttBound) { return; }
            window.__clinicAttBound = true;
            var T = @json($att_cam_t);
            var token = function () { return (document.querySelector('meta[name="csrf-token"]') || {}).content || ''; };
            var esc = function (s) { return $('<div>').text(s == null ? '' : String(s)).html(); };

            function upload(box, files, done) {
                var fd = new FormData();
                files.forEach(function (f) { fd.append('files[]', f); });
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
                        if (x.status === 413) { msg = T.too_big; }
                        if (window.toastr) { toastr.error(msg); }
                    })
                    .always(function () {
                        btn.classList.remove('disabled');
                        var s = btn.querySelector('.att-spin'); if (s) { s.remove(); }
                        if (done) { done(); }
                    });
            }
            window.ClinicAttachments = { upload: upload };

            document.addEventListener('change', function (e) {
                var input = e.target;
                if (!input.classList || !input.classList.contains('att-input') || !input.files.length) { return; }
                upload(input.closest('.clinic-attachments'), [].slice.call(input.files), function () { input.value = ''; });
            });
            document.addEventListener('click', function (e) {
                var del = e.target.closest && e.target.closest('.att-del');
                if (!del) { return; }
                e.preventDefault();
                var box = del.closest('.clinic-attachments');
                var go = function () {
                    $.ajax({ url: del.getAttribute('data-url'), method: 'POST', data: { _token: token(), visit_id: box.getAttribute('data-visit') || '' },
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                        .done(function (r) { box.querySelector('.att-list').innerHTML = r.html; if (window.toastr) { toastr.success(r.msg); } });
                };
                if (window.swal) {
                    swal({ title: (window.LANG && LANG.sure) || 'Sure?', icon: 'warning', buttons: true, dangerMode: true }).then(function (ok) { if (ok) { go(); } });
                } else if (confirm('?')) { go(); }
            });

            /* ---- camera / endoscope: any video source the computer sees (webcam, USB endoscope, capture card) ---- */
            // (this script can come before jQuery on the page: nothing below runs before the page is ready)
            var camOk = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
            var showCam = function () { if (camOk) { [].forEach.call(document.querySelectorAll('.att-camera-btn'), function (b) { b.style.display = ''; }); } };
            var onReady = function () {
                showCam();
                if (window.jQuery) { jQuery(document).ajaxComplete(showCam); }
            };
            if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', onReady); } else { onReady(); }

            var cam = { box: null, stream: null };
            function modal() {
                var $m = $('#att_cam_modal');
                if ($m.length) { return $m; }
                $m = $('<div class="modal fade" id="att_cam_modal" tabindex="-1" role="dialog" aria-labelledby="att_cam_title">' +
                    '<div class="modal-dialog modal-lg" role="document"><div class="modal-content">' +
                    '<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="' + esc(T.close) + '"><span aria-hidden="true">&times;</span></button>' +
                    '<h4 class="modal-title" id="att_cam_title"><i class="fas fa-camera"></i> ' + esc(T.title) + '</h4></div>' +
                    '<div class="modal-body">' +
                    '<div class="form-group" style="display:flex;gap:8px;align-items:center;"><label for="att_cam_device" style="margin:0;white-space:nowrap;">' + esc(T.device) + '</label>' +
                    '<select id="att_cam_device" class="form-control input-sm"></select></div>' +
                    '<video id="att_cam_video" autoplay playsinline muted></video>' +
                    '<p class="help-block" style="margin:6px 0 0;">' + esc(T.help) + '</p>' +
                    '<div class="att-cam-shots"></div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-primary" id="att_cam_photo"><i class="fas fa-camera"></i> ' + esc(T.photo) + '</button> ' +
                    '<button type="button" class="btn btn-default" data-dismiss="modal">' + esc(T.close) + '</button>' +
                    '</div></div></div></div>').appendTo('body');
                $m.on('hidden.bs.modal', stopAll);
                $m.on('change', '#att_cam_device', function () { start(this.value); });
                $m.on('click', '#att_cam_photo', photo);
                return $m;
            }
            function stopStream() {
                if (cam.stream) { cam.stream.getTracks().forEach(function (t) { t.stop(); }); cam.stream = null; }
            }
            function stopAll() {
                stopStream();
                $('#att_cam_modal .att-cam-shots').empty();
            }
            function start(deviceId) {
                stopStream();
                var c = { video: deviceId ? { deviceId: { exact: deviceId }, width: { ideal: 1920 }, height: { ideal: 1080 } } : { width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false };
                return navigator.mediaDevices.getUserMedia(c).then(function (s) {
                    cam.stream = s;
                    document.getElementById('att_cam_video').srcObject = s;
                    return navigator.mediaDevices.enumerateDevices();
                }).then(function (list) {
                    var cams = list.filter(function (d) { return d.kind === 'videoinput'; });
                    var cur = cam.stream && cam.stream.getVideoTracks()[0] ? cam.stream.getVideoTracks()[0].getSettings().deviceId : '';
                    var $sel = $('#att_cam_device').empty();
                    cams.forEach(function (d, i) { $sel.append(new Option(d.label || ('Camera ' + (i + 1)), d.deviceId, false, d.deviceId === cur)); });
                    if (!cams.length) { toastr.error(T.none); }
                }).catch(function (err) {
                    toastr.error(err && err.name === 'NotFoundError' ? T.none : T.denied);
                });
            }
            function stamp() { var d = new Date(), p = function (n) { return (n < 10 ? '0' : '') + n; }; return d.getFullYear() + p(d.getMonth() + 1) + p(d.getDate()) + '_' + p(d.getHours()) + p(d.getMinutes()) + p(d.getSeconds()); }
            function photo() {
                var v = document.getElementById('att_cam_video');
                if (!v.videoWidth) { return; }
                var c = document.createElement('canvas');
                c.width = v.videoWidth; c.height = v.videoHeight;
                c.getContext('2d').drawImage(v, 0, 0);
                c.toBlob(function (blob) {
                    $('#att_cam_modal .att-cam-shots').append($('<img alt="">').attr('src', URL.createObjectURL(blob)));
                    upload(cam.box, [new File([blob], 'photo_' + stamp() + '.jpg', { type: 'image/jpeg' })]);
                }, 'image/jpeg', 0.92);
            }
            document.addEventListener('click', function (e) {
                var btn = e.target.closest && e.target.closest('.att-camera-btn');
                if (!btn) { return; }
                cam.box = btn.closest('.clinic-attachments');
                var $m = modal();
                $m.modal('show');
                start($('#att_cam_device').val() || '');
            });
        })();
    </script>
@endonce
@endif
