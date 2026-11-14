{{--
    Given vs prescribed for one prescription line (staff screens).
    Needs $rx after App\Utils\Dispensing::attach().
--}}
@php
    $rx_cls = ['waiting' => 'label-warning', 'partly_given' => 'label-info', 'given' => 'label-success', 'elsewhere' => 'label-danger', 'not_given' => 'label-default'];
@endphp
<span class="label {{ $rx_cls[$rx->dispense_state] ?? 'label-default' }}">{{ __('clinic.rx_state_' . $rx->dispense_state) }}</span>
<small class="text-muted" style="white-space:nowrap;">{{ __('clinic.rx_given_of', ['given' => $rx->given_qty + 0, 'qty' => $rx->quantity + 0]) }}</small>
@if ($rx->elsewhere_qty > 0)
    <br><small class="text-danger" style="white-space:nowrap;"><i class="fas fa-store-slash"></i> {{ __('clinic.rx_elsewhere_qty', ['qty' => $rx->elsewhere_qty + 0]) }}</small>
@endif
@if ($rx->closed_at)
    <br><small class="text-muted"><i class="fas fa-ban"></i> {{ \App\Utils\Dispensing::reasonLabel($rx->close_reason) }}</small>
@endif
