{{-- Shared PDF report header. Variables: $business, $report_title, $filters, $generated_by --}}
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #222; }
    .page-break { page-break-before: always; }

    /* ── Letterhead ───────────────────────────────────── */
    .letterhead { width: 100%; border-bottom: 3px solid #1F4E79; padding-bottom: 8px; margin-bottom: 10px; }
    .letterhead-logo { float: left; margin-right: 12px; }
    .letterhead-logo img { max-height: 60px; max-width: 120px; }
    .letterhead-info { overflow: hidden; }
    .business-name { font-size: 18px; font-weight: bold; color: #1F4E79; line-height: 1.2; }
    .business-meta { font-size: 9px; color: #555; margin-top: 3px; }
    .clearfix::after { content: ''; display: table; clear: both; }

    /* ── Report title bar ─────────────────────────────── */
    .report-title-bar { background: #1F4E79; color: #fff; padding: 6px 10px; margin-bottom: 8px; border-radius: 3px; }
    .report-title-bar .title { font-size: 13px; font-weight: bold; }
    .report-title-bar .meta { font-size: 8px; opacity: 0.85; float: right; margin-top: 2px; }

    /* ── Filter box ───────────────────────────────────── */
    .filter-box { background: #F0F4FA; border-left: 4px solid #1F4E79; padding: 5px 8px; margin-bottom: 10px; font-size: 9px; color: #333; }
    .filter-box strong { color: #1F4E79; }

    /* ── KPI cards ────────────────────────────────────── */
    .kpi-row { width: 100%; margin-bottom: 10px; }
    .kpi-card { display: inline-block; background: #E8F0FE; border: 1px solid #C5D6F5; border-radius: 4px; padding: 6px 10px; margin-right: 6px; margin-bottom: 4px; min-width: 130px; text-align: center; }
    .kpi-card .kpi-label { font-size: 8px; color: #555; }
    .kpi-card .kpi-value { font-size: 13px; font-weight: bold; color: #1F4E79; }

    /* ── Data table ───────────────────────────────────── */
    .data-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 8.5px; }
    .data-table thead tr { background: #1F4E79; color: #fff; }
    .data-table thead th { padding: 5px 4px; text-align: left; font-weight: bold; border: 1px solid #1F4E79; }
    .data-table tbody tr:nth-child(even) { background: #F5F5F5; }
    .data-table tbody tr:nth-child(odd) { background: #FFFFFF; }
    .data-table tbody td { padding: 4px 4px; border: 1px solid #DDD; vertical-align: top; }
    .data-table tfoot tr { background: #DCE6F1; font-weight: bold; border-top: 2px solid #1F4E79; }
    .data-table tfoot td { padding: 5px 4px; border: 1px solid #DDD; }

    /* ── Page footer ──────────────────────────────────── */
    .report-footer { border-top: 1px solid #CCC; padding-top: 4px; margin-top: 12px; font-size: 8px; color: #888; }

    /* Number alignment */
    .num { text-align: right; }
</style>

<div class="letterhead clearfix">
    @if(!empty($logo_path) && file_exists($logo_path))
        <div class="letterhead-logo">
            <img src="{{ $logo_path }}" alt="logo">
        </div>
    @endif
    <div class="letterhead-info">
        <div class="business-name">{{ $business['name'] ?? session('business.name') }}</div>
        @if(!empty($address_line))
            <div class="business-meta">{{ $address_line }}</div>
        @endif
        @if(!empty($contact_line))
            <div class="business-meta">{{ $contact_line }}</div>
        @endif
    </div>
</div>

<div class="report-title-bar clearfix">
    <span class="title">{{ $report_title }}</span>
    <span class="meta">{{ __('report.generated_on') }}: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; {{ __('messages.by') }}: {{ $generated_by }}</span>
</div>

<div class="filter-box">
    <strong>{{ __('report.filters') }}:</strong> {{ $filter_summary }}
</div>
