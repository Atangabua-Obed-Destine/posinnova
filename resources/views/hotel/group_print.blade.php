<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('hotel.grp_bill_title') }} {{ $group->ref_no }}</title>
<style>
    @page { size: A4; margin: 14mm; }
    body { font-family: "Segoe UI", Arial, sans-serif; color: #101828; font-size: 12.5px; margin: 0; }
    .wrap { max-width: 760px; margin: 0 auto; padding: 18px; }
    .top { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #101828; padding-bottom: 12px; margin-bottom: 14px; }
    .biz { font-size: 18px; font-weight: 700; }
    .muted { color: #667085; }
    h1 { font-size: 18px; margin: 0 0 4px; text-align: right; }
    h2 { font-size: 13.5px; margin: 18px 0 4px; display: flex; justify-content: space-between; gap: 12px; border-bottom: 1px solid #101828; padding-bottom: 4px; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 24px; margin-bottom: 8px; }
    .grid div span { color: #667085; display: inline-block; min-width: 110px; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 5px 6px; border-bottom: 1px solid #eaecf0; vertical-align: top; }
    .num { text-align: right; white-space: nowrap; }
    .tot { margin-top: 14px; margin-left: auto; width: 320px; }
    .tot div { display: flex; justify-content: space-between; padding: 4px 0; }
    .tot .big { font-size: 15px; font-weight: 700; border-top: 2px solid #101828; padding-top: 7px; }
    .sign { display: flex; justify-content: space-between; margin-top: 46px; }
    .sign div { width: 40%; border-top: 1px solid #98a2b3; padding-top: 4px; text-align: center; color: #667085; }
    .noprint { margin: 12px auto; text-align: center; }
    .noprint button { padding: 8px 18px; font-size: 14px; cursor: pointer; }
    section { page-break-inside: avoid; }
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
            <h1>{{ __('hotel.grp_bill_title') }}</h1>
            <div class="muted" style="text-align:right;">{{ $group->ref_no }} · {{ now()->format('d/m/Y H:i') }}</div>
        </div>
    </div>
    <div class="grid">
        <div><span>{{ __('hotel.grp_payer') }}</span><strong>{{ optional($group->contact)->name }}</strong></div>
        <div><span>{{ __('hotel.phone') }}</span>{{ optional($group->contact)->mobile }}</div>
        <div><span>{{ __('hotel.grp_the_rooms') }}</span>{{ count($rooms) }}</div>
        <div><span>{{ __('hotel.grp_settled_by') }}</span>{{ $group->pays === 'each' ? __('hotel.grp_pays_each') : __('hotel.grp_pays_payer') }}</div>
    </div>

    @foreach ($rooms as $x)
        @php $r = $x['res']; $st = $x['st']; @endphp
        <section>
            <h2><span>{{ __('hotel.room') }} {{ optional($r->room)->number ?: '—' }} · {{ optional($r->roomType)->name }} · {{ $r->sleeperName() }}</span>
                <span class="muted" style="font-weight:400;">{{ $r->ref_no }} · {{ $r->arrival->format('d/m') }} → {{ $r->departure->format('d/m/Y') }} · {{ __('hotel.st_' . $r->status) }}</span></h2>
            <table>
                <tbody>
                @foreach ($st['lines'] as $l)
                    @continue($l['voided'])
                    <tr><td style="width:80px;">{{ $l['night'] ? $l['night']->format('d/m/Y') : $l['at']->format('d/m/Y') }}</td><td>{{ $l['what'] }}</td>
                        <td class="num">{{ $l['charge'] ? $num($l['charge']) : '' }}</td><td class="num">{{ $l['pay'] ? '− ' . $num($l['pay']) : '' }}</td></tr>
                @endforeach
                <tr><td></td><td><strong>{{ __('hotel.grp_room_total') }}</strong></td><td class="num"><strong>{{ $num($st['charges']) }}</strong></td><td class="num"><strong>− {{ $num($st['payments']) }}</strong></td></tr>
                </tbody>
            </table>
        </section>
    @endforeach

    <div class="tot">
        <div><span>{{ __('hotel.charges') }}</span><strong>{{ $fmt($sum['charges']) }}</strong></div>
        <div><span>{{ __('hotel.payments') }}</span><strong>− {{ $fmt($sum['payments']) }}</strong></div>
        @if ($sum['to_come'] > 0.004)<div><span>{{ __('hotel.grp_nights_to_come') }}</span><strong>{{ $fmt($sum['to_come']) }}</strong></div>@endif
        <div class="big"><span>{{ $sum['total_left'] < -0.004 ? __('hotel.grp_in_credit') : __('hotel.grp_still_to_pay') }}</span><span>{{ $fmt(abs($sum['total_left']) > 0.004 ? abs($sum['total_left']) : 0) }}</span></div>
    </div>
    <div class="sign"><div>{{ __('hotel.guest_signature') }}</div><div>{{ __('hotel.front_desk_signature') }}</div></div>
</div>
</body>
</html>
