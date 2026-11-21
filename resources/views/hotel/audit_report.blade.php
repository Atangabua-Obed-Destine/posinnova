@extends('layouts.app')
@section('title', __('hotel.daily_report') . ' ' . $audit->audit_date->format('d/m/Y'))

@section('content')
@include('hotel.partials.styles')
@php $A = \App\Http\Controllers\HotelAuditController::class; @endphp
<section class="content ht" id="ht_audit_report">
    <div class="ht-head">
        <div>
            <a class="ht-link ht-noprint" href="{{ action([$A, 'index']) }}">‹ {{ __('hotel.menu_audit') }}</a>
            <h1 style="margin-top:6px;">{{ __('hotel.daily_report') }} · {{ $audit->audit_date->translatedFormat('l j F Y') }}</h1>
            <p>{{ __('hotel.closed') }} {{ $audit->ran_at->format('d/m/Y H:i') }} · {{ $audit->ran_by ? trim($by->first_name . ' ' . $by->last_name) : __('hotel.automatic') }}</p>
        </div>
        <div class="ht-actions ht-noprint"><button type="button" class="ht-btn" onclick="window.print()">{{ __('hotel.print') }}</button></div>
    </div>
    <div class="ht-kpis">
        <div class="ht-kpi"><div class="l">{{ __('hotel.occupancy') }}</div><div class="v">{{ $s['occupancy'] ?? 0 }}%</div><div class="s">{{ __('hotel.n_of_sellable', ['n' => $s['sold'] ?? 0, 'm' => $s['sellable'] ?? 0]) }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('hotel.adr') }}</div><div class="v">{{ $fmt($s['adr'] ?? 0) }}</div><div class="s">{{ __('hotel.adr_help') }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('hotel.revpar') }}</div><div class="v">{{ $fmt($s['revpar'] ?? 0) }}</div><div class="s">{{ __('hotel.revpar_help') }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('hotel.total_revenue') }}</div><div class="v">{{ $fmt($s['revenue_total'] ?? 0) }}</div><div class="s">{{ __('hotel.all_departments') }}</div></div>
    </div>
    <div class="ht-grid ht-g3">
        <div class="ht-card"><h2>{{ __('hotel.revenue_by_dept') }}</h2>
            @foreach (['rooms', 'hotel_extras', 'tourist_tax', 'other_sales'] as $k)
                <div class="ht-row"><span>{{ __('hotel.dept_' . $k) }}</span><strong>{{ $fmt($s['departments'][$k] ?? 0) }}</strong></div>
            @endforeach
            <div class="ht-row"><strong>{{ __('hotel.total') }}</strong><strong>{{ $fmt($s['revenue_total'] ?? 0) }}</strong></div></div>
        <div class="ht-card"><h2>{{ __('hotel.money_received') }}</h2>
            @forelse ($s['payments'] ?? [] as $m => $v)
                <div class="ht-row"><span>{{ $methods[$m] ?? $m }}</span><strong>{{ $fmt($v) }}</strong></div>
            @empty
                <div class="ht-empty">{{ __('hotel.nothing') }}</div>
            @endforelse
            <div class="ht-row"><span class="ht-muted">{{ __('hotel.owed_in_house') }}</span><strong>{{ $fmt($s['owed_in_house'] ?? 0) }}</strong></div></div>
        <div class="ht-card"><h2>{{ __('hotel.tomorrow') }}</h2>
            <div class="ht-row"><span>{{ __('hotel.arrivals') }}</span><strong>{{ $s['arrivals_tomorrow'] ?? 0 }}</strong></div>
            <div class="ht-row"><span>{{ __('hotel.departures') }}</span><strong>{{ $s['departures_tomorrow'] ?? 0 }}</strong></div>
            <div class="ht-row"><span>{{ __('hotel.in_house') }}</span><strong>{{ $s['in_house'] ?? 0 }}</strong></div>
            <div class="ht-row"><span>{{ __('hotel.no_shows') }}</span><strong>{{ $s['no_shows'] ?? $audit->no_shows }}</strong></div>
            <div class="ht-row"><span>{{ __('hotel.state_ooo') }}</span><strong>{{ $s['out_of_order'] ?? 0 }}</strong></div></div>
    </div>
    <p class="ht-muted ht-small">{{ __('hotel.report_help') }}</p>
</section>
@endsection
