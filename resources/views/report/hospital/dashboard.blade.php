@extends('layouts.app')
@section('title', __('lang_v1.hospital_dashboard'))

@php
    $k = $kpis;
    // KPI card definitions: [label, value, icon, accent classes, optional suffix]
    $cards = [
        ['label' => __('lang_v1.opd_today'), 'value' => $k['opd_today'], 'icon' => 'fa-stethoscope', 'bg' => 'tw-bg-sky-100', 'fg' => 'tw-text-sky-600'],
        ['label' => __('lang_v1.current_inpatients'), 'value' => $k['current_inpatients'], 'icon' => 'fa-procedures', 'bg' => 'tw-bg-indigo-100', 'fg' => 'tw-text-indigo-600'],
        ['label' => __('lang_v1.available_beds'), 'value' => $k['available_beds'] . ' / ' . $k['total_beds'], 'icon' => 'fa-bed', 'bg' => 'tw-bg-green-100', 'fg' => 'tw-text-green-600'],
        ['label' => __('lang_v1.pending_lab_results'), 'value' => $k['pending_labs'], 'icon' => 'fa-vial', 'bg' => 'tw-bg-amber-100', 'fg' => 'tw-text-amber-600'],
        ['label' => __('lang_v1.bodies_in_morgue'), 'value' => $k['morgue_in_storage'], 'icon' => 'fa-snowflake', 'bg' => 'tw-bg-slate-100', 'fg' => 'tw-text-slate-600'],
        ['label' => __('lang_v1.theatre_scheduled'), 'value' => $k['theatre_scheduled'], 'icon' => 'fa-user-md', 'bg' => 'tw-bg-purple-100', 'fg' => 'tw-text-purple-600'],
        ['label' => __('lang_v1.deliveries_this_month'), 'value' => $k['deliveries_month'], 'icon' => 'fa-baby', 'bg' => 'tw-bg-pink-100', 'fg' => 'tw-text-pink-600'],
        ['label' => __('lang_v1.new_patients_month'), 'value' => $k['patients_month'], 'icon' => 'fa-user-plus', 'bg' => 'tw-bg-teal-100', 'fg' => 'tw-text-teal-600'],
    ];
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.hospital_dashboard')
        <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">{{ session('business.name') }}</small>
    </h1>
</section>

<section class="content">

    {{-- Critical lab value alerts (last 48h) --}}
    @if(isset($critical_labs) && $critical_labs->count())
        <div class="tw-rounded-2xl tw-bg-red-50 tw-ring-1 tw-ring-red-200 tw-p-4 tw-mb-4">
            <div class="tw-flex tw-items-center tw-gap-2 tw-mb-2">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-8 tw-h-8 tw-rounded-lg tw-bg-red-600 tw-text-white"><i class="fas fa-triangle-exclamation"></i></span>
                <span class="tw-font-bold tw-text-red-800">@lang('lang_v1.critical_lab_alerts') ({{ $critical_labs->count() }})</span>
            </div>
            <div class="tw-flex tw-flex-wrap tw-gap-2">
                @foreach($critical_labs as $cl)
                    @php $flag = ['critical'=>'tw-bg-red-600','high'=>'tw-bg-orange-500','low'=>'tw-bg-amber-500'][$cl->result_flag] ?? 'tw-bg-gray-500'; @endphp
                    <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'show'], [$cl->lab_request_id]) }}"
                       class="tw-inline-flex tw-items-center tw-gap-2 tw-bg-white tw-ring-1 tw-ring-red-200 tw-rounded-full tw-pl-1 tw-pr-3 tw-py-1 tw-text-sm hover:tw-ring-red-400">
                        <span class="{{ $flag }} tw-text-white tw-text-[10px] tw-font-bold tw-px-2 tw-py-0.5 tw-rounded-full">{{ strtoupper(__('lang_v1.lab_flag_'.$cl->result_flag)) }}</span>
                        <span class="tw-font-semibold tw-text-gray-800">{{ $cl->patient_name }}</span>
                        <span class="tw-text-gray-500">{{ $cl->test_name }}: {{ $cl->result_value }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Revenue hero --}}
    <div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-4 tw-mb-4">
        <div class="lg:tw-col-span-2 tw-rounded-2xl tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-p-5 tw-shadow-sm">
            <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
                <div>
                    <p class="tw-text-sm tw-opacity-80 tw-mb-1">@lang('lang_v1.revenue_today')</p>
                    <p class="tw-text-3xl tw-font-bold"><span class="display_currency" data-currency_symbol="true">{{ $k['revenue_today'] }}</span></p>
                </div>
                <div class="tw-text-right">
                    <p class="tw-text-sm tw-opacity-80 tw-mb-1">@lang('lang_v1.revenue_this_month')</p>
                    <p class="tw-text-2xl tw-font-semibold"><span class="display_currency" data-currency_symbol="true">{{ $k['revenue_month'] }}</span></p>
                </div>
            </div>
        </div>
        {{-- Bed occupancy gauge --}}
        <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-5 tw-shadow-sm">
            <p class="tw-text-sm tw-text-gray-500 tw-mb-2">@lang('lang_v1.bed_occupancy')</p>
            <div class="tw-flex tw-items-center tw-gap-3">
                <span class="tw-text-3xl tw-font-bold {{ $k['occupancy_pct'] >= 90 ? 'tw-text-red-600' : ($k['occupancy_pct'] >= 70 ? 'tw-text-amber-600' : 'tw-text-green-600') }}">{{ $k['occupancy_pct'] }}%</span>
                <span class="tw-text-sm tw-text-gray-400">{{ $k['occupied_beds'] }} / {{ $k['total_beds'] }} @lang('lang_v1.beds')</span>
            </div>
            <div class="tw-mt-2 tw-h-2 tw-w-full tw-rounded-full tw-bg-gray-100 tw-overflow-hidden">
                <div class="tw-h-full {{ $k['occupancy_pct'] >= 90 ? 'tw-bg-red-500' : ($k['occupancy_pct'] >= 70 ? 'tw-bg-amber-500' : 'tw-bg-green-500') }}" style="width: {{ min(100, $k['occupancy_pct']) }}%;"></div>
            </div>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="tw-grid tw-grid-cols-2 sm:tw-grid-cols-3 xl:tw-grid-cols-4 tw-gap-4 tw-mb-4">
        @foreach($cards as $c)
            <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4">
                <div class="tw-flex tw-items-center tw-gap-3">
                    <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-11 tw-h-11 tw-rounded-lg {{ $c['bg'] }} {{ $c['fg'] }}">
                        <i class="fas {{ $c['icon'] }}"></i>
                    </span>
                    <div class="tw-min-w-0">
                        <p class="tw-text-xs tw-font-medium tw-text-gray-500 tw-truncate">{{ $c['label'] }}</p>
                        <p class="tw-text-xl tw-font-bold tw-text-gray-900">{{ $c['value'] }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        {{-- OPD 14-day trend --}}
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.opd_trend_14d')])
                <div class="tw-p-2">
                    @include('nursing.partials.sparkline', ['series' => collect($opd_trend), 'label' => __('lang_v1.daily_consultations'), 'unit' => '', 'color' => '#4f46e5'])
                </div>
                <div class="tw-grid tw-grid-cols-7 tw-gap-1 tw-mt-3">
                    @foreach(collect($opd_trend)->take(-7) as $d)
                        <div class="tw-text-center">
                            <div class="tw-text-sm tw-font-semibold tw-text-gray-900">{{ $d['y'] }}</div>
                            <div class="tw-text-[10px] tw-text-gray-400">{{ $d['t'] }}</div>
                        </div>
                    @endforeach
                </div>
            @endcomponent
        </div>
        {{-- Ward occupancy bars --}}
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.ward_occupancy')])
                @forelse($wards as $w)
                    @php
                        $occ = $w->beds_count > 0 ? round(($w->active_admissions_count / $w->beds_count) * 100) : 0;
                    @endphp
                    <div class="tw-mb-3">
                        <div class="tw-flex tw-items-center tw-justify-between tw-mb-1">
                            <span class="tw-text-sm tw-font-medium tw-text-gray-700">{{ $w->name }}</span>
                            <span class="tw-text-xs tw-text-gray-400">{{ $w->active_admissions_count }} / {{ $w->beds_count }}</span>
                        </div>
                        <div class="tw-h-2 tw-w-full tw-rounded-full tw-bg-gray-100 tw-overflow-hidden">
                            <div class="tw-h-full {{ $occ >= 90 ? 'tw-bg-red-500' : ($occ >= 70 ? 'tw-bg-amber-500' : 'tw-bg-indigo-500') }}" style="width: {{ min(100, $occ) }}%;"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted text-center" style="padding:20px 0;">@lang('lang_v1.no_wards_create_first')</p>
                @endforelse
            @endcomponent
        </div>
    </div>

    {{-- Quick links to detailed reports --}}
    <div class="tw-flex tw-flex-wrap tw-gap-2 tw-mt-2">
        @can('hospital_report.opd')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'opd']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-stethoscope"></i> @lang('lang_v1.opd_report')</a>@endcan
        @can('hospital_report.admissions')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'admissions']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-procedures"></i> @lang('lang_v1.admissions_report')</a>@endcan
        @can('hospital_report.lab')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'lab']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-vial"></i> @lang('lang_v1.lab_report')</a>@endcan
        @can('hospital_report.ward')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'ward']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-pills"></i> @lang('lang_v1.ward_consumption_report')</a>@endcan
        @can('hospital_report.revenue')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'revenue']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-coins"></i> @lang('lang_v1.revenue_report')</a>@endcan
        @can('hospital_report.revenue')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'mutualClaims']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-shield-heart"></i> @lang('lang_v1.insurance_claims')</a>@endcan
        @can('hospital_report.mortality')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'mortality']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-book-dead"></i> @lang('lang_v1.mortality_register')</a>@endcan
        @can('hospital_report.antenatal')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'antenatal']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-baby"></i> @lang('lang_v1.antenatal_report')</a>@endcan
        @can('hospital_report.morgue')<a href="{{ action([\App\Http\Controllers\HospitalReportController::class, 'morgue']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline"><i class="fas fa-snowflake"></i> @lang('lang_v1.morgue_register')</a>@endcan
    </div>

</section>
@endsection
