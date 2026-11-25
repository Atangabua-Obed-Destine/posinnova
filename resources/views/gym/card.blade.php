<!doctype html>
<html lang="{{ app()->getLocale() }}" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $m->member_no }}</title>
<style>
  @page { size: 86mm 54mm; margin: 0; }
  body { margin: 0; font-family: "Segoe UI", Arial, sans-serif; background: #e4e7ec; }
  .card { width: 86mm; height: 54mm; box-sizing: border-box; padding: 5mm; background: #1d2939; color: #fff; display: flex; gap: 4mm; margin: 10mm auto; border-radius: 3mm; }
  .card .l { flex: 1; display: flex; flex-direction: column; justify-content: space-between; min-width: 0; }
  .card .biz { font-size: 9pt; color: #d0d5dd; text-transform: uppercase; letter-spacing: .08em; }
  .card .nm { font-size: 13pt; font-weight: 700; line-height: 1.2; }
  .card .no { font-size: 15pt; font-weight: 700; letter-spacing: .05em; }
  .card .till { font-size: 8.5pt; color: #6ce9a6; }
  .card .qr { width: 30mm; height: 30mm; background: #fff; padding: 1.5mm; box-sizing: border-box; align-self: center; border-radius: 2mm; }
  .card .qr img { width: 100%; height: 100%; display: block; }
  .noprint { width: 86mm; margin: 0 auto; display: flex; gap: 8px; }
  .noprint button { flex: 1; min-height: 40px; font-size: 14px; }
  @media print { body { background: #fff; } .card { margin: 0; border-radius: 0; } .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint" style="margin-top:10px"><button type="button" onclick="window.print()">{{ __('gym.print') }}</button><button type="button" onclick="window.close()">{{ __('gym.close') }}</button></div>
<div class="card" id="gy_member_card">
    <div class="l">
        <div class="biz">{{ $business->name }} · {{ __('gym.module') }}</div>
        <div class="nm">{{ optional($m->contact)->name }}</div>
        <div><div class="no">{{ $m->member_no }}</div>
            @if ($current)<div class="till">{{ $current->visits_left !== null ? trans_choice('gym.visits_left', $current->visits_left, ['n' => $current->visits_left]) : __('gym.valid_until', ['date' => $current->end_date->format('d/m/Y')]) }}</div>@endif</div>
    </div>
    <div class="qr"><img alt="{{ $m->member_no }}" src="data:image/png;base64,{{ DNS2D::getBarcodePNG($m->member_no, 'QRCODE', 4, 4) }}"></div>
</div>
</body>
</html>
