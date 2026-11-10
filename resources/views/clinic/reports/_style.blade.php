{{-- PosInnova clinic reports: shared look (inline: the compiled Tailwind bundle lacks most of these) --}}
<style>
    .cr-wrap { padding: 18px 20px 28px; }
    .cr-crumb { font-size: 13px; color: #475467; margin-bottom: 4px; }
    .cr-crumb a { color: #475467; text-decoration: underline; display: inline-block; padding: 8px 0; margin: -8px 0; }
    .cr-title { margin: 0; font-size: 26px; font-weight: 700; color: #101828; letter-spacing: -.01em; display: flex; align-items: center; gap: 10px; }
    .cr-title svg { width: 28px; height: 28px; color: #475467; }
    .cr-lead { margin: 4px 0 0; color: #475467; font-size: 14px; max-width: 900px; }
    .cr-bar { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: flex-end; background: #fff; border: 1px solid #eaecf0; border-radius: 14px; padding: 12px 14px; margin: 16px 0; }
    .cr-presets { display: flex; flex-wrap: wrap; gap: 6px; }
    .cr-chip { display: inline-flex; align-items: center; min-height: 34px; padding: 6px 12px; border-radius: 999px; border: 1px solid #d0d5dd; background: #fff; color: #344054; font-size: 13px; font-weight: 600; text-decoration: none !important; }
    .cr-chip:hover { background: #f9fafb; color: #101828; }
    .cr-chip.is-on { background: #101828; border-color: #101828; color: #fff; }
    .cr-field { display: flex; flex-direction: column; gap: 3px; }
    .cr-field label { font-size: 12px; font-weight: 600; color: #475467; margin: 0; }
    .cr-field input, .cr-field select { height: 36px; border: 1px solid #d0d5dd; border-radius: 8px; padding: 4px 10px; font-size: 14px; background: #fff; color: #101828; }
    .cr-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: 8px; border: 1px solid #d0d5dd; background: #fff; color: #344054; font-size: 14px; font-weight: 600; text-decoration: none !important; cursor: pointer; }
    .cr-btn:hover { background: #f9fafb; color: #101828; }
    .cr-btn.is-primary { background: #1570ef; border-color: #1570ef; color: #fff; }
    .cr-btn.is-primary:hover { background: #175cd3; color: #fff; }
    .cr-btn svg { width: 18px; height: 18px; }
    .cr-spacer { flex: 1; }
    .cr-scope { font-size: 13px; color: #475467; display: flex; align-items: center; gap: 6px; min-height: 36px; }
    .cr-note { background: #f9fafb; border: 1px solid #eaecf0; border-radius: 10px; padding: 10px 12px; color: #475467; font-size: 13px; margin-bottom: 14px; }
    .cr-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 14px; margin-bottom: 16px; }
    .cr-tile { display: flex; gap: 12px; background: #fff; border: 1px solid #eaecf0; border-radius: 14px; padding: 16px; box-shadow: 0 1px 2px rgba(16,24,40,.05); color: #101828; text-decoration: none !important; }
    a.cr-tile:hover { box-shadow: 0 6px 16px rgba(16,24,40,.10); color: #101828; }
    .cr-ico { flex: none; width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .cr-ico svg { width: 21px; height: 21px; }
    .cr-tile-label { display: block; font-size: 13px; font-weight: 500; color: #475467; }
    .cr-tile-value { display: block; font-size: 24px; line-height: 1.2; font-weight: 700; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .cr-tile-value.is-money { font-size: 19px; }
    .cr-tile-sub { display: block; font-size: 12px; color: #475467; margin-top: 2px; }
    .cr-card { background: #fff; border: 1px solid #eaecf0; border-radius: 14px; box-shadow: 0 1px 2px rgba(16,24,40,.05); margin-bottom: 16px; overflow: hidden; }
    .cr-card-head { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid #f2f4f7; }
    .cr-card-title { margin: 0; font-size: 16px; font-weight: 700; color: #101828; }
    .cr-card-note { padding: 8px 16px 0; font-size: 13px; color: #475467; }
    .cr-table-wrap { overflow-x: auto; }
    .cr-table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .cr-table th { text-align: left; font-size: 12px; font-weight: 600; color: #475467; background: #f9fafb; padding: 9px 12px; border-bottom: 1px solid #eaecf0; white-space: nowrap; }
    .cr-table td { padding: 9px 12px; border-bottom: 1px solid #f2f4f7; color: #101828; vertical-align: top; }
    .cr-table tr:last-child td { border-bottom: 0; }
    .cr-table .is-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .cr-table .is-strong { font-weight: 700; }
    .cr-table tfoot td { font-weight: 700; background: #f9fafb; border-top: 1px solid #eaecf0; }
    .cr-table a { font-weight: 600; }
    .cr-empty { padding: 22px 16px; text-align: center; color: #475467; }
    .cr-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .cr-muted { color: #98a2b3; }
    /* single-series bar chart: one hue, thin bars, rounded data end on the baseline, value on hover */
    .cr-chart { padding: 14px 16px 10px; }
    .cr-plot { position: relative; display: flex; align-items: flex-end; gap: 2px; height: 180px; border-bottom: 1px solid #d0d5dd; padding-top: 18px; }
    .cr-plot::before { content: attr(data-max); position: absolute; top: 0; left: 0; font-size: 11px; color: #98a2b3; }
    .cr-plot::after { content: ''; position: absolute; left: 0; right: 0; top: 18px; border-top: 1px dashed #eaecf0; pointer-events: none; }
    .cr-col { position: relative; flex: 1 1 0; min-width: 4px; height: 100%; display: flex; align-items: flex-end; justify-content: center; }
    .cr-col > i { display: block; width: 100%; max-width: 28px; background: #2e90fa; border-radius: 4px 4px 0 0; min-height: 0; }
    .cr-col:hover > i { background: #175cd3; }
    .cr-col .cr-tip { display: none; position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%); background: #101828; color: #fff; font-size: 12px; padding: 5px 8px; border-radius: 6px; white-space: nowrap; z-index: 5; pointer-events: none; }
    .cr-col:hover .cr-tip, .cr-col:focus .cr-tip { display: block; }
    .cr-col .cr-tip { bottom: auto; top: 0; }
    .cr-xlabels { display: flex; gap: 2px; margin-top: 4px; }
    .cr-xlabels span { flex: 1 1 0; min-width: 4px; font-size: 11px; color: #475467; text-align: center; overflow: visible; white-space: nowrap; }
    .cr-hbars { padding: 14px 16px; display: grid; grid-template-columns: minmax(110px, 200px) 1fr auto; gap: 8px 12px; align-items: center; }
    .cr-hbars .cr-hl { font-size: 13px; color: #344054; }
    .cr-hbars .cr-ht { height: 14px; background: #f2f4f7; border-radius: 0 4px 4px 0; overflow: hidden; }
    .cr-hbars .cr-ht > i { display: block; height: 100%; background: #2e90fa; border-radius: 0 4px 4px 0; }
    .cr-hbars .cr-hv { font-size: 13px; font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; }
    .cr-chart details { margin-top: 8px; font-size: 13px; }
    .cr-chart summary { cursor: pointer; color: #1570ef; font-weight: 600; }
    .cr-groups { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 14px; }
    .cr-report { display: flex; gap: 12px; background: #fff; border: 1px solid #eaecf0; border-radius: 14px; padding: 16px; text-decoration: none !important; color: #101828; box-shadow: 0 1px 2px rgba(16,24,40,.05); }
    .cr-report:hover { box-shadow: 0 6px 16px rgba(16,24,40,.10); color: #101828; }
    .cr-report b { display: block; font-size: 15px; }
    .cr-report span.d { display: block; font-size: 13px; color: #475467; margin-top: 3px; }
    .cr-group-title { font-size: 13px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #475467; margin: 22px 0 10px; }
    @media (max-width: 600px) {
        .cr-wrap { padding: 14px; }
        .cr-title { font-size: 21px; }
        .cr-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .cr-tile { flex-direction: column; gap: 8px; padding: 12px; }
        .cr-tile-value.is-money { font-size: 16px; }
        .cr-hbars { grid-template-columns: 1fr auto; }
        .cr-hbars .cr-ht { grid-column: 1 / -1; order: 3; }
    }
    @media print {
        .side-bar, .main-header, header, .no-print, .cr-bar form, .cr-noprint, footer, .main-footer { display: none !important; }
        .cr-wrap { padding: 0; }
        .cr-card, .cr-tile { box-shadow: none; break-inside: avoid; }
        .cr-table-wrap { overflow: visible; }
        body { background: #fff !important; }
    }
</style>
