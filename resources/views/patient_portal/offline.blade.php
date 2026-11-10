<!DOCTYPE html>
<html data-theme="light" lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, viewport-fit=cover" name="viewport">
    <title>@lang('lang_v1.patient_portal')</title>
    <link href="{{ asset('css/tailwind/app.css?v='.$asset_v) }}" rel="stylesheet">
    <link href="{{ asset('css/tailwind/portal.css?v='.$asset_v) }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/vendor.css?v='.$asset_v) }}">
</head>
<body class="tw-min-h-screen tw-bg-gradient-to-br tw-from-indigo-600 tw-to-blue-500 tw-flex tw-items-center tw-justify-center tw-p-6">
    <div class="tw-text-center tw-text-white">
        <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-16 tw-h-16 tw-rounded-3xl tw-bg-white/15 tw-mb-3"><i class="fas fa-wifi tw-text-3xl"></i></span>
        <p class="tw-text-lg tw-font-bold">@lang('lang_v1.portal_offline_title')</p>
        <p class="tw-text-sm tw-opacity-80 tw-mt-1">@lang('lang_v1.portal_offline_sub')</p>
        <button onclick="location.reload()" class="tw-mt-4 tw-rounded-xl tw-bg-white tw-text-indigo-600 tw-px-5 tw-py-2.5 tw-text-sm tw-font-bold">@lang('lang_v1.retry')</button>
    </div>
</body>
</html>
