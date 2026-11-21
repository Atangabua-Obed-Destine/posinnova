<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('hotel.bill_title') }} {{ $res->ref_no }}</title>
<style>
    @page { size: A4; margin: 14mm; }
    body { font-family: "Segoe UI", Arial, sans-serif; color: #101828; font-size: 12.5px; margin: 0; }
    .wrap { max-width: 760px; margin: 0 auto; padding: 18px; }
    .top { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #101828; padding-bottom: 12px; margin-bottom: 14px; }
    .biz { font-size: 18px; font-weight: 700; }
    .muted { color: #667085; }
    h1 { font-size: 18px; margin: 0 0 4px; text-align: right; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 14px; }
    .grid div span { color: #667085; display: inline-block; min-width: 110px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 11px; color: #475467; border-bottom: 1px solid #101828; padding: 6px; }
    td { padding: 6px; border-bottom: 1px solid #eaecf0; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .tot { margin-top: 10px; margin-left: auto; width: 300px; }
    .tot div { display: flex; justify-content: space-between; padding: 4px 0; }
    .tot .big { font-size: 15px; font-weight: 700; border-top: 2px solid #101828; padding-top: 7px; }
    .sign { display: flex; justify-content: space-between; margin-top: 46px; }
    .sign div { width: 40%; border-top: 1px solid #98a2b3; padding-top: 4px; text-align: center; color: #667085; }
    .noprint { margin: 12px auto; text-align: center; }
    .noprint button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
    @media print { .noprint { display: none; } .wrap { padding: 0; } }
</style>
</head>
<body>
<div class="noprint"><button type="button" onclick="window.print()">{{ __('hotel.print') }}</button></div>
<div class="wrap">
    <div class="top">
        <div>
            <div class="biz">{{ optional($location)->name ?: $business->name }}</div>
            <div class="muted">{{ $business->name }}</div>
            @if ($location)<div class="muted">{{ collect([$location->landmark, $location->city, $location->country])->filter()->implode(', ') }}</div>
                @if ($location->mobile)<div class="muted">{{ $location->mobile }}</div>@endif @endif
            @if ($business->tax_number_1)<div class="muted">{{ $business->tax_label_1 }}: {{ $business->tax_number_1 }}</div>@endif
        </div>
        <div>
            <h1>{{ __('hotel.bill_title') }}</h1>
            <div class="muted" style="text-align:right;">{{ $res->ref_no }} · {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    <div class="grid">
        <div><span>{{ __('hotel.guest') }}</span><strong>{{ optional($res->contact)->name }}</strong></div>
        <div><span>{{ __('hotel.room') }}</span><strong>{{ optional($res->room)->number }} · {{ optional($res->roomType)->name }}</strong></div>
        <div><span>{{ __('hotel.phone') }}</span>{{ optional($res->contact)->mobile }}</div>
        <div><span>{{ __('hotel.stay') }}</span>{{ $res->arrival->format('d/m/Y') }} → {{ $res->departure->format('d/m/Y') }} ({{ trans_choice('hotel.n_nights', $res->nights(), ['n' => $res->nights()]) }})</div>
        @if ($guest && $guest->id_number)<div><span>{{ __('hotel.f_id_number') }}</span>{{ __('hotel.idt_' . ($guest->id_type ?: 'other')) }} {{ $guest->id_number }}</div>@endif
        <div><span>{{ __('hotel.rate_plan') }}</span>{{ __('hotel.plan_' . $res->rate_plan) }} · {{ $fmt($res->rate) }}</div>
    </div>
    <table>
        <thead><tr><th>{{ __('hotel.date') }}</th><th>{{ __('hotel.what') }}</th><th>{{ __('hotel.invoice') }}</th><th class="num">{{ __('hotel.charges') }}</th><th class="num">{{ __('hotel.payments') }}</th></tr></thead>
        <tbody>
        @foreach ($statement['lines'] as $l)
            @continue($l['voided'])
            <tr><td>{{ $l['night'] ? $l['night']->format('d/m/Y') : $l['at']->format('d/m/Y') }}</td><td>{{ $l['what'] }}@if (! empty($l['discount']) && $l['discount'] > 0) <span class="muted">({{ __('hotel.discounted', ['amount' => $num($l['discount'])]) }})</span>@endif</td>
                <td class="muted">{{ $l['invoice_no'] ?? ($l['payment_ref'] ?? '') }}</td>
                <td class="num">{{ $l['charge'] ? $num($l['charge']) : '' }}</td><td class="num">{{ $l['pay'] ? $num($l['pay']) : '' }}</td></tr>
        @endforeach
        </tbody>
    </table>
    <div class="tot">
        <div><span>{{ __('hotel.charges') }}</span><strong>{{ $fmt($statement['charges']) }}</strong></div>
        <div><span>{{ __('hotel.payments') }}</span><strong>{{ $fmt($statement['payments']) }}</strong></div>
        @if ($balance < -0.004)
            <div class="big"><span>{{ __('hotel.credit') }}</span><span>{{ $fmt(-$balance) }}</span></div>
        @else
            <div class="big"><span>{{ __('hotel.balance_due') }}</span><span>{{ $fmt(max(0, $balance)) }}</span></div>
        @endif
    </div>
    <p class="muted" style="margin-top:14px;">{{ __('hotel.bill_vat_note') }}</p>
    <div class="sign"><div>{{ __('hotel.guest_signature') }}</div><div>{{ __('hotel.front_desk_signature') }}</div></div>
</div>
</body>
</html>
