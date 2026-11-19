<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('review.sc_title') }} — {{ $biz->name }}</title>
    @include('business_review.partials.styles')
    <style>
        @page { margin: 12mm 11mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; }
        .br { font-size: 8.4pt; }
        .br-title { font-size: 15pt; } .br-biz { font-size: 12pt; } .br-kpi-val { font-size: 12pt; }
        .br-h { font-size: 10.5pt; margin-top: 14px; }
        .br-t th { font-size: 6.8pt; padding: 4px 4px; } .br-t td { padding: 3px 4px; }
        .br-kpi { border-radius: 6px; padding: 7px 7px; }
        #sc_counts td, #sc_counts th { font-size: 7pt; } #sc_counts .br-small { font-size: 6.4pt; }
        .br-kpis { table-layout: fixed; } .br-kpis td { padding: 0 3px; }
        .br-kpi-val { font-size: 10pt; white-space: normal; word-wrap: break-word; }
    </style>
</head>
<body>
    @include('business_review.partials.stock_body', ['pdf' => true])
</body>
</html>
