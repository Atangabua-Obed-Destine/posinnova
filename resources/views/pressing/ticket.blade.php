<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $o->ticket_no }}</title>
@php $fmt = fn ($v) => \App\Utils\Pressing::format($o->business_id, $v); @endphp
<style>
  @page { size: 80mm auto; margin: 3mm; }
  body { margin: 0; font-family: Consolas, "Courier New", monospace; font-size: 12px; color: #000; background: #fff; }
  .t { width: 72mm; margin: 0 auto; }
  .c { text-align: center; }
  .b { font-weight: 700; }
  .big { font-size: 20px; font-weight: 700; }
  .r { display: flex; justify-content: space-between; gap: 6px; }
  .hr { border-top: 1px dashed #000; margin: 6px 0; }
  .tag { border: 1px dashed #000; padding: 5px 6px; margin: 4px 0; display: flex; justify-content: space-between; font-size: 13px; page-break-inside: avoid; }
  .room { font-size: 18px; font-weight: 700; }
  .cut { page-break-before: always; }
  .noprint { margin: 10px auto; width: 72mm; display: flex; gap: 8px; font-family: sans-serif; }
  .noprint button { flex: 1; min-height: 40px; font-size: 14px; }
  @media print { .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint"><button type="button" onclick="window.print()">{{ __('pressing.print') }}</button><button type="button" onclick="window.close()">{{ __('pressing.close') }}</button></div>
<div class="t" id="pr_ticket">
    <div class="c b" style="font-size:14px">{{ $business->name }} · {{ __('pressing.module') }}</div>
    @if ($location)<div class="c">{{ trim(($location->landmark ? $location->landmark.', ' : '').$location->city) }} {{ $location->mobile }}</div>@endif
    <div class="hr"></div>
    <div class="r"><span>{{ __('pressing.ticket') }}</span><span class="big">{{ $o->ticket_no }}</span></div>
    <div class="r"><span>{{ __('pressing.customer') }}</span><span>{{ optional($o->contact)->name }}</span></div>
    @if ($o->reservation)<div class="r"><span>{{ __('pressing.room_lbl') }}</span><span class="room">{{ optional($o->reservation->room)->number }}</span></div>@endif
    @if ($o->phone)<div class="r"><span>WhatsApp</span><span>{{ $o->phone }}</span></div>@endif
    <div class="r"><span>{{ __('pressing.dropped_lbl') }}</span><span>{{ $o->received_at->format('d/m H:i') }}</span></div>
    <div class="r b"><span>{{ __('pressing.ready_on_label') }}</span><span>{{ $o->ready_at->format('d/m H:i') }}</span></div>
    @if ($o->express)<div class="c b">*** {{ __('pressing.express_up') }} ***</div>@endif
    <div class="hr"></div>
    @foreach ($o->pieces as $p)
        <div class="r"><span>#{{ $p->tag }} {{ optional($p->item)->name }} · {{ optional($p->service)->name }}</span><span>{{ number_format($p->price, 0, '.', ' ') }}</span></div>
        @if ($p->note)<div style="padding-left:12px">{{ $p->note }}</div>@endif
    @endforeach
    <div class="hr"></div>
    <div class="r b"><span>{{ __('pressing.total') }} ({{ $o->pieces->count() }})</span><span>{{ $fmt($m['total']) }}</span></div>
    <div class="r"><span>{{ __('pressing.paid_lbl') }}</span><span>{{ $fmt($m['paid']) }}</span></div>
    @if ($o->on_room_bill)
        <div class="r b"><span>{{ __('pressing.on_room_bill') }}</span><span>{{ $fmt($m['due']) }}</span></div>
    @else
        <div class="r b"><span>{{ __('pressing.to_pay_collect') }}</span><span>{{ $fmt($m['due']) }}</span></div>
    @endif
    <div class="hr"></div>
    <div class="c">{{ __('pressing.ticket_foot') }}</div>

    <div class="cut"></div>
    <div class="c b">{{ __('pressing.tags') }} · {{ $o->ticket_no }}</div>
    @foreach ($o->pieces as $p)
        <div class="tag"><strong>{{ $o->ticket_no }} #{{ $p->tag }}</strong><span>{{ optional($p->item)->name }}@if ($o->reservation) · <span class="room">{{ optional($o->reservation->room)->number }}</span>@endif</span></div>
    @endforeach
</div>
<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });</script>
</body>
</html>
