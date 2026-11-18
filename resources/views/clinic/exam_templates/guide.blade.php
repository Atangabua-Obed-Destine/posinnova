@extends('layouts.app')
@section('title', __('clinic.ex_guide_title'))

@php
    $img = fn ($f) => asset('img/help/exam_forms/' . $f . '.jpg');
    $fr = str_starts_with((string) app()->getLocale(), 'fr');
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.ex_guide_title')
        <small class="tw-text-gray-600">@lang('clinic.ex_guide_lead')</small>
    </h1>
</section>

<section class="content">
    <style>
        .gd { max-width:1100px; }
        .gd .box { border-radius:12px; }
        .gd h2 { font-size:20px; font-weight:700; margin:4px 0 12px; color:#312e81; }
        .gd h3 { font-size:16px; font-weight:700; margin:22px 0 8px; color:#1f2937; }
        .gd p, .gd li { font-size:14px; line-height:1.6; color:#374151; }
        .gd ol.gd-steps { counter-reset:s; list-style:none; padding-left:0; }
        .gd ol.gd-steps > li { counter-increment:s; position:relative; padding:2px 0 10px 40px; }
        .gd ol.gd-steps > li::before { content:counter(s); position:absolute; left:0; top:0; width:28px; height:28px; border-radius:50%; background:#4f46e5; color:#fff; font-weight:700; display:flex; align-items:center; justify-content:center; font-size:13px; }
        .gd .gd-b { display:inline-block; border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3; border-radius:6px; padding:0 7px; font-weight:600; font-size:13px; white-space:nowrap; }
        .gd .gd-shot { margin:10px 0 16px; }
        .gd .gd-shot img { max-width:100%; border:1px solid #e5e7eb; border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.06); }
        .gd .gd-shot figcaption { font-size:12.5px; color:#6b7280; margin-top:4px; }
        .gd .gd-tip { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:10px 14px; margin:12px 0; }
        .gd .gd-warn { background:#fffbeb; border:1px solid #fde68a; border-radius:10px; padding:10px 14px; margin:12px 0; }
        .gd table.gd-t { width:100%; border-collapse:collapse; margin:8px 0 14px; }
        .gd table.gd-t th, .gd table.gd-t td { border:1px solid #e5e7eb; padding:7px 10px; font-size:13.5px; vertical-align:top; }
        .gd table.gd-t th { background:#f8fafc; text-align:left; }
        .gd .gd-toc { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px; }
        .gd .gd-toc a { border:1px solid #e5e7eb; background:#fff; border-radius:999px; padding:6px 14px; font-weight:600; }
        .gd .gd-toc a:hover { border-color:#6366f1; text-decoration:none; }
        .gd .gd-two { display:flex; flex-wrap:wrap; gap:16px; align-items:flex-start; }
        .gd .gd-two > * { flex:1 1 320px; }
        .gd summary { cursor:pointer; font-weight:600; font-size:14px; padding:6px 0; }
        .gd details { border-bottom:1px solid #f1f5f9; padding:4px 0; }
        .gd details p { margin:4px 0 8px 18px; }
    </style>

    <div class="gd">
        <div class="gd-toc">
            <a href="#gd_setup"><i class="fas fa-cog"></i> @lang('clinic.ex_guide_toc_setup')</a>
            <a href="#gd_doctor"><i class="fas fa-user-md"></i> @lang('clinic.ex_guide_toc_doctor')</a>
            <a href="#gd_print"><i class="fas fa-print"></i> @lang('clinic.ex_guide_toc_print')</a>
            <a href="#gd_faq"><i class="fas fa-question-circle"></i> @lang('clinic.ex_guide_toc_faq')</a>
            @can('clinic_settings.manage')
                <a href="{{ action([\App\Http\Controllers\ClinicExamTemplateController::class, 'index']) }}" style="margin-left:auto;background:#4f46e5;color:#fff;border-color:#4f46e5;"><i class="fas fa-clipboard-list"></i> @lang('clinic.ex_guide_open_forms')</a>
            @endcan
        </div>

        @include($fr ? 'clinic.exam_templates.guide_fr' : 'clinic.exam_templates.guide_en', ['img' => $img])
    </div>
</section>
@endsection
