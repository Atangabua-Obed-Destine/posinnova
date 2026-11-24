{{-- PosInnova hotel: shared look of the hotel pages (prefix ht-) --}}
<style>
.ht { font-size: 14px; color: #101828; }
/* the hidden attribute must win over the display set by the classes below */
.ht [hidden] { display: none !important; }
.ht a.ht-link { color: #1849a9; font-weight: 600; text-decoration: none; }
.ht .ht-muted { color: #667085; }
.ht .ht-small { font-size: 12.5px; }
.ht-head { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; justify-content: space-between; margin-bottom: 18px; }
.ht-head h1 { margin: 0; font-size: 24px; font-weight: 700; }
.ht-head p { margin: 6px 0 0; color: #667085; }
.ht-actions { display: flex; flex-wrap: wrap; gap: 8px; }
.ht-card { background: #fff; border: 1px solid #eaecf0; border-radius: 12px; padding: 18px; margin-bottom: 16px; }
.ht-card h2 { margin: 0 0 12px; font-size: 16px; font-weight: 700; }
.ht-card h3 { margin: 0 0 10px; font-size: 14px; font-weight: 700; }
.ht-grid { display: grid; gap: 14px; }
.ht-g2 { grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); }
.ht-g3 { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); }
.ht-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; margin-bottom: 16px; }
.ht-kpi { background: #fff; border: 1px solid #eaecf0; border-radius: 12px; padding: 16px; }
.ht-kpi .l { color: #667085; font-size: 13px; }
.ht-kpi .v { font-size: 26px; font-weight: 700; margin-top: 4px; line-height: 1.2; }
.ht-kpi .s { color: #667085; font-size: 13px; margin-top: 4px; }
.ht-bar { height: 8px; border-radius: 99px; background: #eaecf0; margin-top: 10px; overflow: hidden; }
.ht-bar > span { display: block; height: 8px; background: #2e90fa; border-radius: 99px; }
.ht-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 15px; border-radius: 8px; border: 1px solid #d0d5dd; background: #fff; color: #344054 !important; font-weight: 600; font-size: 14px; text-decoration: none !important; cursor: pointer; min-height: 40px; line-height: 1.2; white-space: nowrap; }
.ht-btn:hover { background: #f9fafb; }
.ht-btn.pri { background: linear-gradient(90deg, #4f46e5, #3b82f6); border: 0; color: #fff !important; }
.ht-btn.pri:hover { filter: brightness(1.05); }
.ht-btn.ok { background: #067647; border-color: #067647; color: #fff !important; }
.ht-btn.danger { color: #b42318 !important; border-color: #fda29b; }
.ht-btn.sm { min-height: 32px; padding: 5px 11px; font-size: 13px; }
.ht-btn[disabled] { opacity: .55; cursor: not-allowed; }
.ht-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
.ht-p-free { background: #e5f4e4; color: #05603a; }
.ht-p-occ, .ht-p-checked_in { background: #e7f1fb; color: #1849a9; }
.ht-p-arr, .ht-p-confirmed { background: #f4f3ff; color: #5925dc; }
.ht-p-tentative { background: #fff; color: #5925dc; border: 1px dashed #7a5af8; }
.ht-p-dep { background: #fdead7; color: #b93815; }
.ht-p-dirty { background: #fef0c7; color: #93370d; }
.ht-p-ooo, .ht-p-checked_out, .ht-p-cancelled, .ht-p-no_show { background: #f2f4f7; color: #475467; }
.ht-p-bad { background: #fee4e2; color: #b42318; }
.ht table.ht-t { width: 100%; border-collapse: collapse; }
.ht table.ht-t th { text-align: left; font-size: 12px; color: #667085; font-weight: 600; padding: 9px 10px; border-bottom: 1px solid #eaecf0; white-space: nowrap; background: none; }
.ht table.ht-t td { padding: 10px; border-bottom: 1px solid #f2f4f7; vertical-align: middle; }
.ht table.ht-t .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.ht-scroll { overflow-x: auto; }
.ht-f { display: flex; flex-direction: column; gap: 5px; margin-bottom: 12px; }
.ht-f label { font-size: 13px; font-weight: 600; color: #344054; margin: 0; }
.ht-in { padding: 9px 11px; border: 1px solid #d0d5dd; border-radius: 8px; font: inherit; background: #fff; color: #101828; min-height: 40px; width: 100%; box-sizing: border-box; }
.ht-in:focus { outline: 2px solid #84adff; outline-offset: 0; border-color: #84adff; }
.ht-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0 14px; }
.ht-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f2f4f7; }
.ht-row:last-child { border-bottom: 0; }
.ht-note { padding: 10px 12px; border-radius: 8px; font-weight: 500; margin-bottom: 8px; display: block; text-decoration: none !important; }
.ht-note.amber { background: #fef0c7; color: #93370d !important; }
.ht-note.red { background: #fee4e2; color: #b42318 !important; }
.ht-note.purple { background: #f4f3ff; color: #5925dc !important; }
.ht-note.blue { background: #eff4ff; color: #1849a9 !important; }
.ht-note.green { background: #e5f4e4; color: #067647 !important; }
.ht-empty { color: #667085; padding: 14px 0; }
.ht-tabs { display: flex; flex-wrap: wrap; gap: 2px; border-bottom: 1px solid #eaecf0; margin-bottom: 16px; }
.ht-tabs a { padding: 10px 14px; font-weight: 600; color: #667085; border-bottom: 3px solid transparent; text-decoration: none; }
.ht-tabs a.on { color: #1849a9; border-bottom-color: #1849a9; }
.ht-chip { padding: 6px 13px; border-radius: 999px; border: 1px solid #d0d5dd; background: #fff; color: #344054; font-weight: 500; cursor: pointer; min-height: 34px; text-decoration: none !important; display: inline-flex; align-items: center; }
.ht-chip.on { background: #101828; border-color: #101828; color: #fff !important; }
.ht-sw { display: inline-block; width: 11px; height: 11px; border-radius: 3px; vertical-align: -1px; margin-right: 4px; }
details.ht-more > summary { cursor: pointer; list-style: none; }
details.ht-more > summary::-webkit-details-marker { display: none; }
/* room cards to pick from (check-in, new reservation / walk-in) */
.ht-rooms { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
.ht-roomopt { position: relative; display: flex; flex-direction: column; border-radius: 12px; border: 2px solid #1d2939; background: #1d2939; color: #fff; cursor: pointer; margin: 0; font-weight: 400; min-width: 0; overflow: hidden; box-shadow: 0 1px 3px rgba(16,24,40,.25); }
.ht-roomopt:hover { background: #253247; }
.ht-roomopt input { position: absolute; opacity: 0; }
.ht-roomopt .in { display: flex; flex-direction: column; gap: 4px; flex: 1; padding: 12px 14px; }
.ht-roomopt .top { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.ht-roomopt .no { font-size: 22px; font-weight: 700; line-height: 1.1; }
.ht-roomopt .tick { display: none; width: 24px; height: 24px; border-radius: 50%; background: #fff; color: #4f46e5; font-weight: 700; align-items: center; justify-content: center; }
.ht-roomopt .ty { font-weight: 600; }
.ht-roomopt .sub { font-size: 12.5px; color: #d0d5dd; }
.ht-roomopt .pr { font-weight: 700; margin-top: 4px; }
.ht-roomopt .bk { font-size: 12px; font-weight: 700; color: #d9d6fe; }
.ht-roomopt .bar { display: block; padding: 8px 10px; text-align: center; font-size: 13.5px; font-weight: 700; background: #32d583; color: #053321; }
.ht-roomopt .bar.no { background: #fdb022; color: #3d2400; }
.ht-roomopt.on { border-color: #fff; box-shadow: 0 0 0 3px #4f46e5; }
.ht-roomopt.on .tick { display: inline-flex; }
.ht-roomopt:focus-within { outline: 2px solid #84adff; outline-offset: 3px; }
@media print { .ht-noprint, .main-sidebar, .main-header, footer { display: none !important; } }
</style>
