{{--
    Files of a lab / imaging request in the portal (shown only once the request is paid: the caller checks,
    and ClinicAttachmentController::portalShow checks again). $files: collection of App\ClinicAttachment
--}}
@if ($files->isNotEmpty())
    <div style="margin-top:10px;">
        <p style="font-size:11px;color:#6b7280;font-weight:600;margin:0 0 6px;"><i class="fas fa-paperclip"></i> @lang('clinic.att_title')</p>
        <div style="display:flex;flex-wrap:wrap;gap:8px;">
            @foreach ($files as $a)
                <a href="{{ $a->portalUrl() }}" target="_blank" rel="noopener" class="portal-att" title="{{ $a->original_name }}"
                   style="width:96px;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;background:#fff;text-decoration:none;color:#374151;">
                    <span style="display:flex;align-items:center;justify-content:center;height:72px;background:#f8fafc;font-size:26px;color:#64748b;">
                        @if ($a->isImage())
                            <img src="{{ $a->portalUrl() }}" alt="{{ $a->original_name }}" loading="lazy" style="width:100%;height:72px;object-fit:cover;">
                        @else
                            <i class="fas {{ $a->isPdf() ? 'fa-file-pdf' : 'fa-file' }}"></i>
                        @endif
                    </span>
                    <span style="display:block;font-size:10px;padding:3px 5px;word-break:break-all;">{{ \Illuminate\Support\Str::limit($a->original_name, 28) }}</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
