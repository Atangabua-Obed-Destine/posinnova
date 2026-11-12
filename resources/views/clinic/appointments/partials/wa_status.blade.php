{{--
    One appointment's WhatsApp state: the patient's answer to the reminder, and
    the delivery of the last automatic message (sent / delivered / read / failed).
--}}
@php
    $wa_last = $ap->lastWhatsappOut();
    $wa_icon = ['queued' => ['fas fa-clock', '#94a3b8'], 'sent' => ['fas fa-check', '#94a3b8'], 'delivered' => ['fas fa-check-double', '#94a3b8'],
        'read' => ['fas fa-check-double', '#2563eb'], 'failed' => ['fas fa-times-circle', '#dc2626']];
@endphp
@if ($ap->patient_reply == 'coming')
    <span class="label label-success" title="{{ optional($ap->patient_reply_at)->format('d/m H:i') }}"><i class="fab fa-whatsapp"></i> @lang('clinic.wa_reply_coming')</span>
@elseif ($ap->patient_reply == 'not_coming')
    <span class="label label-danger" title="{{ optional($ap->patient_reply_at)->format('d/m H:i') }}"><i class="fab fa-whatsapp"></i> @lang('clinic.wa_reply_not_coming')</span>
@endif
@if ($wa_last && isset($wa_icon[$wa_last->status]))
    <span class="wa-tick" style="font-size:12px; color:{{ $wa_icon[$wa_last->status][1] }}; white-space:nowrap;"
        title="{{ $wa_last->status == 'failed' ? $wa_last->error_message : __('clinic.wa_status_' . $wa_last->status) }}">
        <i class="fab fa-whatsapp"></i> <i class="{{ $wa_icon[$wa_last->status][0] }}"></i>
        {{ __('clinic.wa_kind_' . $wa_last->kind) }} · {{ __('clinic.wa_status_' . $wa_last->status) }}
    </span>
@endif
