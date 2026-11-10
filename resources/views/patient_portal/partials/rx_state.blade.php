{{--
    Given vs prescribed for one prescription line, in the patient portal.
    Needs Dispensing::attach(). Inline styles: the portal's compiled Tailwind
    file does not include these colour / size utilities.
--}}
@php
    $rx_portal_style = [
        'waiting' => 'background:#fef3c7;color:#b45309;',
        'partly_given' => 'background:#e0f2fe;color:#0369a1;',
        'given' => 'background:#dcfce7;color:#15803d;',
        'not_given' => 'background:#f3f4f6;color:#6b7280;',
    ];
@endphp
<span style="display:flex;flex-direction:column;align-items:flex-end;flex-shrink:0;max-width:42%;text-align:right;gap:2px;">
    <span style="font-size:10px;line-height:1.4;padding:2px 8px;border-radius:9999px;white-space:nowrap;{{ $rx_portal_style[$rx->dispense_state] ?? $rx_portal_style['not_given'] }}">{{ __('clinic.rx_state_' . $rx->dispense_state) }}</span>
    <span style="font-size:10px;color:#9ca3af;">{{ __('clinic.rx_given_of', ['given' => $rx->given_qty + 0, 'qty' => $rx->quantity + 0]) }}</span>
    @if ($rx->closed_at)
        <span style="font-size:10px;color:#9ca3af;">{{ \App\Utils\Dispensing::reasonLabel($rx->close_reason) }}</span>
    @endif
</span>
