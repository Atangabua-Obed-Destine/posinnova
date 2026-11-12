{{-- After a booking / confirmation / move: did WhatsApp go out by itself, or does reception send it by hand? --}}
@if (session('appt_whatsapp_auto'))
    <div class="callout callout-success" id="wa_auto_sent">
        <i class="fab fa-whatsapp"></i> @lang('clinic.wa_auto_sent')
    </div>
@elseif (session('appt_whatsapp'))
    <div class="callout {{ session('appt_whatsapp_error') ? 'callout-warning' : 'callout-success' }}" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
        <span>
            @if (session('appt_whatsapp_error'))
                <i class="fas fa-exclamation-triangle"></i> {{ __('clinic.wa_auto_failed', ['error' => session('appt_whatsapp_error')]) }}
            @else
                <i class="fas fa-check"></i> @lang('clinic.appt_tell_patient')
            @endif
        </span>
        <a href="{{ session('appt_whatsapp') }}" target="_blank" rel="noopener" class="btn btn-sm btn-success"><i class="fab fa-whatsapp"></i> @lang('clinic.appt_send_whatsapp')</a>
    </div>
@endif
