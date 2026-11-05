@php
    $cls = ['draft' => 'label-default', 'submitted' => 'label-info', 'partial' => 'label-warning', 'settled' => 'label-success'][$state] ?? 'label-default';
@endphp
<span class="label {{ $cls }}">@lang('clinic.ins_state_' . $state)</span>
