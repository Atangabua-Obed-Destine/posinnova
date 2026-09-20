@if(session('status'))
    @php $st = session('status'); $ok = is_array($st) ? ($st['success'] ?? true) : true; $msg = is_array($st) ? ($st['msg'] ?? '') : $st; @endphp
    @if($msg)
        <div id="portal_toast" class="portal-toast tw-fixed tw-top-3 tw-inset-x-0 tw-z-50 tw-flex tw-justify-center tw-px-4" style="top: calc(0.75rem + env(safe-area-inset-top));">
            <div class="tw-flex tw-items-center tw-gap-2 tw-rounded-xl tw-px-4 tw-py-2.5 tw-shadow-lg tw-text-sm tw-text-white {{ $ok ? 'tw-bg-green-600' : 'tw-bg-red-600' }}">
                <i class="fas {{ $ok ? 'fa-circle-check' : 'fa-circle-exclamation' }}"></i>
                <span>{{ $msg }}</span>
            </div>
        </div>
        <script>
            setTimeout(function () {
                var t = document.getElementById('portal_toast');
                if (t) { t.style.transition = 'opacity .3s'; t.style.opacity = '0'; setTimeout(function(){ t.remove(); }, 300); }
            }, 3200);
        </script>
    @endif
@endif
