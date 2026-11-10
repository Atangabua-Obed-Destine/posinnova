@extends('layouts.app')
@section('title', __('clinic.rep_all_reports'))

{{-- PosInnova: list of the clinic reports the user may open --}}
@section('content')
@include('clinic.reports._style')
@php
    $tones = ['practice' => ['#eff8ff', '#175cd3'], 'money' => ['#ecfdf3', '#067647'], 'activity' => ['#fffaeb', '#b54708']];
@endphp
<div class="cr-wrap">
    <h1 class="cr-title">{!! \App\Utils\ClinicIcons::svg('report-medical', '') !!} {{ __('clinic.rep_all_reports') }}</h1>
    <p class="cr-lead">{{ __('clinic.rep_hub_lead') }}</p>

    @foreach ($available as $group => $keys)
        <h2 class="cr-group-title">{{ __('clinic.rep_group_' . $group) }}</h2>
        <div class="cr-groups">
            @foreach ($keys as $key)
                <a class="cr-report" id="cr_report_{{ $key }}" href="{{ action([\App\Http\Controllers\ClinicReportController::class, 'show'], [$key]) }}">
                    <span class="cr-ico" style="background:{{ $tones[$group][0] }};color:{{ $tones[$group][1] }};">{!! \App\Utils\ClinicIcons::svg(\App\Utils\ClinicReports::ICONS[$key], '') !!}</span>
                    <span><b>{{ __('clinic.rep_title_' . $key) }}</b><span class="d">{{ __('clinic.rep_desc_' . $key) }}</span></span>
                </a>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
