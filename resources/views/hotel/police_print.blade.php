<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('hotel.police_register') }} {{ $day->format('d/m/Y') }}</title>
@include('hotel.partials.styles')
<style>
    @page { size: A4 landscape; margin: 10mm; }
    body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; padding: 16px; }
    .ht table.ht-t td, .ht table.ht-t th { font-size: 11.5px; padding: 5px 6px; border-bottom: 1px solid #d0d5dd; }
    .noprint { text-align: center; margin-bottom: 10px; }
    @media print { .noprint { display: none; } body { padding: 0; } }
</style>
</head>
<body class="ht">
<div class="noprint"><button type="button" onclick="window.print()">{{ __('hotel.print') }}</button></div>
<h1 style="font-size:17px;margin:0 0 2px;">{{ __('hotel.police_register') }} · {{ $business->name }}</h1>
<p class="ht-muted" style="margin:0 0 10px;">{{ __('hotel.police_on', ['date' => $day->format('d/m/Y')]) }} · {{ trans_choice('hotel.n_guests', $cards->count(), ['n' => $cards->count()]) }}</p>
@include('hotel.partials.police_table')
</body>
</html>
