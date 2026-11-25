{{-- PosInnova pressing: the hotel's shared look (ht-) plus the pressing's own cards (pr-) --}}
@include('hotel.partials.styles')
<style>
.pr-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; align-items: start; }
.pr-col h3 { display: flex; justify-content: space-between; margin: 0 0 10px; font-size: 14px; font-weight: 700; }
.pr-col h3 span { color: #667085; font-weight: 600; }
.pr-cards { display: flex; flex-direction: column; gap: 10px; }
/* dark ticket cards, the stage as a bright bar at the foot (read from across the counter) */
.pr-card { display: flex; flex-direction: column; border-radius: 12px; background: #1d2939; color: #f2f4f7 !important; text-decoration: none !important; overflow: hidden; box-shadow: 0 1px 3px rgba(16,24,40,.25); border: 2px solid #1d2939; }
.pr-card:hover { background: #253247; }
.pr-card.late { border-color: #f04438; }
.pr-card .bd { padding: 11px 13px 10px; display: flex; flex-direction: column; gap: 3px; }
.pr-card .tk { display: flex; justify-content: space-between; align-items: center; gap: 6px; }
.pr-card .tk strong { font-size: 16px; }
.pr-card .who { font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pr-card .ln { font-size: 12.5px; color: #d0d5dd; display: flex; justify-content: space-between; gap: 8px; }
.pr-card .bar { height: 7px; }
.pr-tag { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.pr-tag.ex { background: #f79009; color: #1d2939; }
.pr-tag.late { background: #f04438; color: #fff; }
.pr-tag.wa { background: transparent; color: #6ce9a6; padding: 0; }
.pr-tag.wa.no { color: #fec84b; }
.pr-st-received { background: #53b1fd; } .pr-st-washing { background: #22d3ee; } .pr-st-ironing { background: #a78bfa; }
.pr-st-ready { background: #32d583; } .pr-st-collected { background: #98a2b3; } .pr-st-cancelled { background: #667085; }
.pr-empty { border: 1px dashed #d0d5dd; border-radius: 12px; padding: 16px; color: #667085; font-size: 13px; text-align: center; }
.pr-chips { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.pr-chip { min-height: 36px; padding: 0 14px; border-radius: 999px; border: 1px solid #d0d5dd; background: #fff; color: #344054 !important; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; text-decoration: none !important; }
.pr-chip.on { border-color: #1d4ed8; background: #eff4ff; color: #1d4ed8 !important; }
.pr-step { display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #0f172a; color: #fff; font-size: 13px; font-weight: 700; flex: none; }
.pr-step.done { background: #067647; }
.pr-h { display: flex; align-items: center; gap: 10px; margin: 0 0 12px; font-size: 16px; font-weight: 700; }
.pr-flow { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; }
.pr-flow .s { display: flex; flex-direction: column; gap: 5px; font-size: 13px; }
.pr-flow .s i { display: block; height: 8px; border-radius: 4px; background: #eaecf0; }
.pr-flow .s.done i { background: #32d583; }
.pr-flow .s.now i { outline: 2px solid #1d4ed8; outline-offset: 1px; }
@media (max-width: 700px) { .pr-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
