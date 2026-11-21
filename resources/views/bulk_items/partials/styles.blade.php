{{-- PosInnova bulk items: the look of the cards and tables (page and POS window) --}}
<style>
    .bk-tabs { margin-bottom: 0; }
    .bk-panel { background: #fff; border: 1px solid #ddd; border-top: 0; padding: 16px; border-radius: 0 0 10px 10px; }
    .bk-intro { color: #475467; font-size: 13px; margin-bottom: 12px; max-width: 900px; }
    .bk-h { font-size: 15px; font-weight: 700; color: #101828; margin: 16px 0 8px; }
    .bk-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end; margin-bottom: 12px; }
    .bk-filters label { display: block; font-size: 12px; color: #475467; font-weight: 600; margin-bottom: 3px; }
    .bk-filters .form-control { height: 36px; border-radius: 8px; }
    .bk-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 12px; }
    .bk-cards.bk-compact { grid-template-columns: 1fr; }
    .bk-card { border: 1px solid #eaecf0; border-radius: 12px; padding: 14px; background: #fff; box-shadow: 0 1px 2px rgba(16,24,40,.05); }
    .bk-card-head { display: flex; justify-content: space-between; gap: 8px; align-items: flex-start; }
    .bk-name { font-size: 15px; font-weight: 700; color: #101828; }
    .bk-sub, .bk-muted { color: #667085; font-size: 12.5px; }
    .bk-open { font-size: 13px; color: #344054; margin-top: 8px; }
    .bk-bar { height: 10px; background: #f2f4f7; border-radius: 999px; overflow: hidden; margin: 8px 0 4px; }
    .bk-bar span { display: block; height: 100%; background: #16a34a; border-radius: 999px; }
    .bk-bar span.over { background: #f79009; }
    .bk-used { font-size: 12.5px; color: #344054; }
    .bk-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
    .bk-pill { display: inline-block; padding: 2px 9px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #f2f4f7; color: #344054; white-space: nowrap; }
    .bk-pill.ok { background: #ecfdf3; color: #067647; } .bk-pill.warn { background: #fffaeb; color: #b54708; } .bk-pill.bad { background: #fef3f2; color: #b42318; }
    .bk-empty { color: #667085; padding: 18px; text-align: center; border: 1px dashed #d0d5dd; border-radius: 12px; }
    .bk-table th { font-size: 12px; color: #475467; }
    .bk-row-low td { background: #fff6f5 !important; }
    .bk-add { border: 1px solid #eaecf0; border-radius: 12px; padding: 12px 14px; background: #fcfcfd; margin-bottom: 8px; }
    .bk-dialog label { display: block; text-align: left; font-size: 13px; color: #344054; font-weight: 600; margin: 8px 0 4px; }
    .bk-dialog input[type=number], .bk-dialog textarea { width: 100%; border: 1px solid #d0d5dd; border-radius: 8px; padding: 8px 10px; font-size: 14px; }
    .bk-dialog .bk-check { display: flex; gap: 8px; align-items: center; font-weight: 500; margin-top: 10px; }
</style>
