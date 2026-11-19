{{-- PosInnova business review (financial summary, quarterly stock control): one look on screen,
     on paper and in the PDF (dompdf: tables only, no flex / grid). --}}
<style>
    .br { color: #1d2939; font-size: 13px; line-height: 1.45; }
    .br * { box-sizing: border-box; }
    .br table { border-collapse: collapse; width: 100%; }
    .br-sheet { background: #fff; border: 1px solid #eaecf0; border-radius: 14px; padding: 26px 28px; box-shadow: 0 1px 2px rgba(16,24,40,.05); }
    .br-head td { vertical-align: top; }
    .br-logo { max-height: 56px; max-width: 160px; }
    .br-biz { font-size: 18px; font-weight: 700; color: #101828; }
    .br-title { font-size: 22px; font-weight: 700; color: #101828; margin: 2px 0 0; letter-spacing: -.01em; }
    .br-sub { color: #475467; font-size: 13px; }
    .br-meta { text-align: right; color: #475467; font-size: 12px; }
    .br-meta strong { color: #101828; }
    .br-rule { border-top: 3px solid #15803d; margin: 14px 0 18px; height: 0; }
    .br-h { font-size: 15px; font-weight: 700; color: #101828; margin: 22px 0 8px; padding-bottom: 4px; border-bottom: 1px solid #eaecf0; }
    .br-h { page-break-after: avoid; }
    .br-h small { font-weight: 500; color: #667085; font-size: 12px; }
    .br-kpis td { width: 25%; padding: 0 6px; vertical-align: top; }
    .br-kpis td:first-child { padding-left: 0; } .br-kpis td:last-child { padding-right: 0; }
    .br-kpi { border: 1px solid #eaecf0; border-radius: 12px; padding: 12px 14px; background: #fcfcfd; }
    .br-kpi-label { font-size: 12px; color: #475467; font-weight: 600; }
    .br-kpi-val { font-size: 19px; font-weight: 700; color: #101828; margin-top: 2px; white-space: nowrap; }
    .br-kpi-sub { font-size: 11.5px; color: #667085; margin-top: 2px; }
    .br-up { color: #067647; font-weight: 700; } .br-down { color: #b42318; font-weight: 700; } .br-flat { color: #667085; }
    .br-t th { text-align: left; font-size: 11.5px; text-transform: uppercase; letter-spacing: .03em; color: #667085; font-weight: 700; padding: 6px 8px; border-bottom: 1px solid #d0d5dd; background: #f9fafb; }
    .br-t td { padding: 6px 8px; border-bottom: 1px solid #f2f4f7; vertical-align: top; }
    .br-t .num, .br-t th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .br-t tr.total td { font-weight: 700; border-top: 1px solid #98a2b3; border-bottom: 0; background: #f9fafb; }
    .br-t tr.sub td { font-weight: 700; background: #fcfcfd; }
    .br-t tr.grand td { font-weight: 700; font-size: 14px; border-top: 2px solid #101828; border-bottom: 0; }
    .br-t td.indent { padding-left: 22px; color: #475467; }
    .br-muted { color: #667085; } .br-small { font-size: 11.5px; }
    .br-neg { color: #b42318; } .br-pos { color: #067647; }
    .br-two td.col { width: 50%; vertical-align: top; }
    .br-two td.col.l { padding-right: 12px; } .br-two td.col.r { padding-left: 12px; }
    .br-bar { width: 100%; height: 9px; border-collapse: collapse; }
    .br-bar td { padding: 0 !important; border: 0 !important; height: 9px; }
    .br-bar td.fill { background: #16a34a; border-radius: 3px; }
    .br-bar td.fill.amber { background: #f79009; } .br-bar td.fill.red { background: #d92d20; } .br-bar td.fill.blue { background: #2e90fa; }
    .br-bar td.rest { background: #f2f4f7; }
    .br-obs td { padding: 5px 0; vertical-align: top; border-bottom: 1px solid #f2f4f7; }
    .br-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; margin-top: 4px; }
    .br-dot.green { background: #12b76a; } .br-dot.red { background: #f04438; } .br-dot.amber { background: #f79009; } .br-dot.slate { background: #98a2b3; } .br-dot.blue { background: #2e90fa; }
    .br-pill { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 11px; font-weight: 700; }
    .br-pill.ok { background: #ecfdf3; color: #067647; } .br-pill.warn { background: #fffaeb; color: #b54708; } .br-pill.bad { background: #fef3f2; color: #b42318; } .br-pill.info { background: #f2f4f7; color: #344054; }
    .br-note { border: 1px dashed #d0d5dd; border-radius: 10px; padding: 10px 12px; min-height: 60px; white-space: pre-wrap; }
    .br-sign td { width: 33%; padding: 34px 10px 4px; text-align: center; font-size: 11.5px; color: #475467; vertical-align: bottom; }
    .br-sign td span { display: block; border-top: 1px solid #344054; padding-top: 4px; }
    .br-foot { margin-top: 18px; font-size: 10.5px; color: #98a2b3; text-align: center; }
    .br-keep { page-break-inside: avoid; }
    @if (empty($pdf))
    @media (max-width: 767px) {
        .br-sheet { padding: 16px 14px; border-radius: 12px; }
        .br-kpis td, .br-two td.col { display: block; width: 100% !important; padding: 0 0 10px !important; }
        .br-head td { display: block; width: 100% !important; } .br-meta { text-align: left; margin-top: 8px; }
        .br-scroll { overflow-x: auto; }
    }
    @else
    /* the PDF: DejaVu Sans only, in normal or bold (dompdf has no semi-bold) */
    .br, .br * { font-family: "DejaVu Sans", sans-serif !important; }
    .br-kpi-label, .br-t th, .br-pill, .br-h, .br-title, .br-biz, .br-kpi-val { font-weight: bold; }
    .br-h small, .br-sub, .br-meta { font-weight: normal; }
    @endif
</style>
