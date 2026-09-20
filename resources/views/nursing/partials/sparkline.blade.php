{{--
    Dependency-free SVG sparkline.
    Props: $series (collection of ['t'=>label,'y'=>number|null]), $label, $unit,
           $color (hex), $lo/$hi (optional normal band).
--}}
@php
    $points = collect($series)->filter(fn($p) => !is_null($p['y'] ?? null))->values();
    $w = 260; $h = 64; $pad = 6;
    $count = $points->count();
    $vals = $points->pluck('y')->map(fn($v) => (float) $v);
    $min = $vals->min();
    $max = $vals->max();
    if ($min === $max) { $min -= 1; $max += 1; }
    $range = $max - $min;
    $latest = $points->last()['y'] ?? null;
    $first = $points->first()['y'] ?? null;
    $trendUp = (!is_null($latest) && !is_null($first)) ? $latest > $first : null;

    $coords = [];
    foreach ($points as $i => $p) {
        $x = $count > 1 ? $pad + ($i * (($w - 2 * $pad) / ($count - 1))) : $w / 2;
        $y = $h - $pad - (((float) $p['y'] - $min) / $range) * ($h - 2 * $pad);
        $coords[] = round($x, 1) . ',' . round($y, 1);
    }
    $polyline = implode(' ', $coords);
    $lastX = $count ? explode(',', end($coords))[0] : 0;
    $lastY = $count ? explode(',', end($coords))[1] : 0;
@endphp
<div class="tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-bg-white tw-p-3">
    <div class="tw-flex tw-items-center tw-justify-between tw-mb-1">
        <span class="tw-text-xs tw-font-medium tw-text-gray-500">{{ $label }}</span>
        <span class="tw-text-sm tw-font-bold" style="color: {{ $color }};">
            {{ !is_null($latest) ? $latest : '—' }}<span class="tw-text-[10px] tw-text-gray-400"> {{ $unit }}</span>
            @if(!is_null($trendUp))
                <i class="fas fa-arrow-{{ $trendUp ? 'up' : 'down' }} tw-text-[10px] {{ $trendUp ? 'tw-text-red-400' : 'tw-text-blue-400' }}"></i>
            @endif
        </span>
    </div>
    @if($count >= 1)
        <svg viewBox="0 0 {{ $w }} {{ $h }}" width="100%" height="{{ $h }}" preserveAspectRatio="none">
            @isset($lo)
                @php
                    $yLo = $h - $pad - (($lo - $min) / $range) * ($h - 2 * $pad);
                    $yHi = $h - $pad - (($hi - $min) / $range) * ($h - 2 * $pad);
                @endphp
                <rect x="0" y="{{ round(min($yLo, $yHi), 1) }}" width="{{ $w }}" height="{{ round(abs($yLo - $yHi), 1) }}" fill="{{ $color }}" opacity="0.06"></rect>
            @endisset
            <polyline points="{{ $polyline }}" fill="none" stroke="{{ $color }}" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></polyline>
            <circle cx="{{ $lastX }}" cy="{{ $lastY }}" r="3" fill="{{ $color }}"></circle>
        </svg>
    @else
        <div class="tw-h-[64px] tw-flex tw-items-center tw-justify-center tw-text-xs tw-text-gray-300">@lang('lang_v1.no_data')</div>
    @endif
</div>
