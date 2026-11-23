<style>
.ch { color: #101828; }
.ch [hidden] { display: none !important; }
.ch-head { display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end; justify-content: space-between; margin-bottom: 14px; }
.ch-head h1 { font-size: 24px; font-weight: 700; margin: 0 0 4px; }
.ch-head p { margin: 0; color: #475467; max-width: 760px; line-height: 1.45; }
.ch-card { background: #fff; border: 1px solid #eaecf0; border-radius: 12px; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
.ch-card h2 { font-size: 17px; font-weight: 700; margin: 0 0 4px; }
.ch-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 14px; }
.ch-step { display: flex; gap: 10px; padding: 12px 14px; border: 1px solid #eaecf0; border-radius: 12px; background: #fff; }
.ch-step .n { width: 28px; height: 28px; border-radius: 50%; background: #1849a9; color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; flex: none; }
.ch-step b { display: block; line-height: 1.25; }
.ch-step span.d { font-size: 12.5px; color: #667085; display: block; line-height: 1.35; margin-top: 2px; }
.ch-muted { color: #667085; } .ch-small { font-size: 12.5px; line-height: 1.4; }
.ch-hint { font-size: 12.5px; color: #667085; line-height: 1.35; margin-top: 4px; }
.ch-pill { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 3px 10px; font-size: 12.5px; font-weight: 600; background: #f2f4f7; color: #344054; white-space: nowrap; }
.ch-pill.ok { background: #dcfae6; color: #067647; } .ch-pill.warn { background: #fef0c7; color: #93370d; }
.ch-pill.bad { background: #fee4e2; color: #b42318; } .ch-pill.off { background: #f2f4f7; color: #667085; }
.ch-loc-h { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.ch-loc-h .nm { font-size: 17px; font-weight: 700; }
.ch-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; }
.ch label.l { font-weight: 600; margin-bottom: 4px; display: block; }
.ch .ch-in { width: 100%; min-height: 40px; border: 1px solid #d0d5dd; border-radius: 8px; padding: 8px 10px; background: #fff; }
.ch .ch-in:focus { border-color: #1849a9; outline: none; box-shadow: 0 0 0 3px rgba(24,73,169,.15); }
.ch-radio { display: flex; gap: 8px; align-items: flex-start; border: 1px solid #eaecf0; border-radius: 8px; padding: 8px 10px; margin: 0 0 6px; font-weight: 400; cursor: pointer; }
.ch-radio input { margin-top: 3px; }
.ch-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; min-height: 40px; padding: 8px 14px; border-radius: 8px; border: 1px solid #d0d5dd; background: #fff; color: #344054 !important; font-weight: 600; text-decoration: none !important; cursor: pointer; }
.ch-btn.pri { background: #1849a9; border-color: #1849a9; color: #fff !important; }
.ch-btn.ok { background: #067647; border-color: #067647; color: #fff !important; }
.ch-btn.danger { color: #b42318 !important; border-color: #fda29b; }
.ch-btn.sm { min-height: 32px; padding: 4px 10px; font-size: 13px; }
.ch-btn[disabled] { opacity: .55; cursor: not-allowed; }
.ch-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.ch-facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 8px; margin: 10px 0 2px; }
.ch-facts > div { background: #f9fafb; border-radius: 8px; padding: 8px 10px; }
.ch-facts .k { font-size: 12px; color: #667085; display: block; }
.ch-facts .v { font-weight: 700; }
.ch-msg { border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; font-weight: 500; }
.ch-msg.ok { background: #dcfae6; color: #05603a; } .ch-msg.bad { background: #fee4e2; color: #912018; } .ch-msg.warn { background: #fef0c7; color: #93370d; }
.ch-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.ch-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #667085; font-weight: 600; padding: 8px; border-bottom: 1px solid #eaecf0; text-align: left; white-space: nowrap; }
.ch-table td { padding: 8px; border-bottom: 1px solid #f2f4f7; vertical-align: middle; }
.ch-table tr.is-on td { background: #f6fef9; }
.ch-table tr.is-never td { background: #fffbfa; }
.ch-table .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.ch-table input.ch-in, .ch-table select.ch-in { min-height: 34px; padding: 4px 8px; }
.ch-table .w-s { width: 90px; } .ch-table .w-m { width: 120px; }
.ch-sw { position: relative; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 500; margin: 0; }
.ch-sw input { width: 18px; height: 18px; }
.ch-filter { display: flex; flex-wrap: wrap; gap: 8px; align-items: flex-end; }
.ch-filter > div { min-width: 160px; }
.ch-bulk { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 0; }
.ch-cats { columns: 3 220px; column-gap: 16px; }
.ch-cats label { display: flex; gap: 8px; align-items: center; font-weight: 400; break-inside: avoid; margin: 0 0 6px; }
.ch-saving { color: #667085; font-size: 12px; }
@media (max-width: 760px) {
  .ch-steps { grid-template-columns: 1fr; }
  .ch-table thead { display: none; }
  .ch-table, .ch-table tbody, .ch-table tr, .ch-table td { display: block; width: 100%; }
  .ch-table tr { border: 1px solid #eaecf0; border-radius: 10px; margin-bottom: 10px; padding: 6px; }
  .ch-table td { border: 0; padding: 4px 6px; }
  .ch-table td[data-l]::before { content: attr(data-l); display: block; font-size: 12px; color: #667085; }
  .ch-table .num { text-align: left; }
}
</style>
