<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('review.fin_title') }} — {{ $biz->name }}</title>
    @include('business_review.partials.styles')
    <style>
        @page { margin: 12mm 11mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; }
        .br { font-size: 8.6pt; }
        .br-title { font-size: 15pt; } .br-biz { font-size: 12pt; } .br-kpi-val { font-size: 12pt; }
        .br-h { font-size: 10.5pt; margin-top: 14px; }
        .br-t th { font-size: 7pt; padding: 4px 5px; } .br-t td { padding: 4px 5px; }
        .br-kpi { border-radius: 6px; padding: 7px 7px; }
        .br-kpis { table-layout: fixed; } .br-kpis td { padding: 0 3px; }
        .br-kpi-val { font-size: 10pt; white-space: normal; word-wrap: break-word; }
        .br-bar, .br-bar td { height: 7px; }
    </style>
</head>
<body>
    @include('business_review.partials.financial_body')
</body>
</html>
