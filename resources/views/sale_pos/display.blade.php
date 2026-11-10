@php
    // PosInnova customer display. A page of its own (no app header, no sidebar):
    // it runs full screen on a monitor or TV facing the customer, in the same
    // browser as the POS screen, and reads the cart that POS screen keeps in
    // localStorage ("pos_form_data_array", plus "pos_display_last_sale" when a
    // sale is finished). Every label shows English and French together.
    $bi = function ($key) use ($strings) {
        $p = $strings[$key] ?? [$key, null];
        return e($p[0]) . (empty($p[1]) ? '' : '<em class="fr">' . e($p[1]) . '</em>');
    };
    $initials = collect(preg_split('/\s+/', trim($business_name)))->filter()->take(2)
        ->map(function ($w) { return mb_strtoupper(mb_substr($w, 0, 1)); })->implode('');
@endphp
<!DOCTYPE html>
<html lang="{{ $config['locale'][0] }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $business_name }} - {{ __('lang_v1.customer_display_screen') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@500;600;700&family=Source+Sans+3:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&display=swap" rel="stylesheet">
<style>
/* One visual world (brand navy), drawn at 1600x900 and scaled to the screen.
   Layout: top bar / receipt tape 1fr + totals 600px / info strip. */
:root{
  --navy:#001f3e; --navy-2:#062a4f; --slot:#00132a;
  --on:#eef3f8; --on-2:#aec0d0; --on-3:#7d97ad;
  --indigo:#4f46e5; --indigo-l:#a9adff;
  --cash:#28b77b; --cash-l:#62e2a9;
  --amber:#f2ac33;
  --paper:#fbfaf5; --paper-ink:#17232c; --paper-2:#5b6c78; --paper-line:#dedbd0;
  --f-body:"Source Sans 3","Segoe UI",-apple-system,Roboto,Arial,sans-serif;
  --f-num:"IBM Plex Mono",Consolas,"Cascadia Mono",ui-monospace,monospace;
  color-scheme: dark;
}
*{box-sizing:border-box;}
html,body{height:100%; margin:0; overflow:hidden; background:#000;}
body{color:var(--on); font-family:var(--f-body); line-height:1.4; -webkit-font-smoothing:antialiased;}
body.no-cursor{cursor:none;}
.mono{font-family:var(--f-num); font-variant-numeric:tabular-nums;}
svg{display:block; flex:none;}
em.fr{font-style:italic; font-weight:400; color:var(--on-3);}

.stage{position:absolute; left:0; top:0; width:1600px; height:900px; transform-origin:0 0; overflow:hidden;
  display:grid; grid-template-rows:92px 1fr 64px;
  background:radial-gradient(1200px 600px at 85% -10%, rgba(79,70,229,.22), transparent 60%), var(--navy);}

/* top bar */
.s-top{display:flex; align-items:center; gap:28px; padding:0 40px; border-bottom:1px solid rgba(255,255,255,.08); min-width:0;}
.brand{display:flex; align-items:center; gap:14px; min-width:0; max-width:520px;}
.mark{width:52px; height:52px; border-radius:10px; background:var(--indigo); display:grid; place-items:center; flex:none; overflow:hidden;
  font-weight:800; font-size:22px; letter-spacing:.02em; color:#fff;}
.mark img{width:100%; height:100%; object-fit:contain; background:#fff;}
.brand div{min-width:0;}
.brand b{display:block; font-size:24px; font-weight:800; letter-spacing:.03em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.brand small{display:block; font-size:16px; color:var(--on-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.s-msg{flex:1; min-width:0; text-align:center; font-size:22px; font-weight:600; color:var(--on-2); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.s-msg em{margin-left:10px;}
.s-who{flex:none; padding:8px 16px; border-radius:8px; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.12); text-align:left; font-size:15px; line-height:1.2; color:var(--on-2); max-width:260px;}
.s-who b{display:block; font-size:18px; color:var(--on); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.s-who em{margin-left:6px;}
.s-clock{flex:none; text-align:right;}
.s-clock .mono{font-size:34px; font-weight:600; line-height:1;}
.s-clock small{display:block; font-size:15px; color:var(--on-3); margin-top:4px; white-space:nowrap;}

/* main */
.s-main{display:grid; grid-template-columns:1fr 600px; gap:32px; padding:26px 40px 22px; min-height:0;}
.s-left,.s-right{min-height:0; min-width:0; position:relative;}

/* receipt tape */
.tape{height:100%; display:flex; flex-direction:column;}
.slot{position:relative; z-index:2; height:58px; flex:none; border-radius:12px; background:var(--slot);
  box-shadow:inset 0 2px 8px rgba(0,0,0,.6), 0 1px 0 rgba(255,255,255,.08);
  display:flex; align-items:center; gap:14px; padding:0 22px;}
.led{width:10px; height:10px; border-radius:50%; background:var(--cash-l); box-shadow:0 0 10px var(--cash-l); flex:none;}
.slot .ttl{font-size:21px; font-weight:700;}
.slot .ttl em{margin-left:8px; font-size:19px;}
.slot .cnt{margin-left:auto; font-size:17px; color:var(--on-2);}
.paper{position:relative; flex:1; min-height:0; margin:-6px 18px 0; padding:22px 30px 34px; background:var(--paper); color:var(--paper-ink); overflow:hidden;
  box-shadow:inset 0 16px 14px -12px rgba(0,0,0,.35);
  -webkit-mask:conic-gradient(from -45deg at bottom,#0000,#000 1deg 89deg,#0000 90deg) 50%/26px 100%;
          mask:conic-gradient(from -45deg at bottom,#0000,#000 1deg 89deg,#0000 90deg) 50%/26px 100%;}
.lines{list-style:none; margin:0; padding:0;}
.ln{display:grid; grid-template-columns:92px 1fr auto; gap:20px; align-items:center; padding:12px 0; border-bottom:1.5px dashed var(--paper-line); position:relative;}
.ln::before{content:""; position:absolute; left:-30px; top:8px; bottom:8px; width:6px; border-radius:0 4px 4px 0; background:var(--indigo); opacity:0; transition:opacity .6s;}
.ln.fresh::before{opacity:1;}
.qty{display:flex; flex-direction:column; align-items:center; justify-content:center; height:62px; border-radius:8px; background:#efece2; line-height:1; padding:0 4px;}
.qty b{font-family:var(--f-num); font-size:26px; font-weight:700; white-space:nowrap;}
.qty span{font-size:13px; font-weight:700; color:var(--paper-2); margin-top:4px; text-transform:uppercase; letter-spacing:.04em; white-space:nowrap; max-width:84px; overflow:hidden; text-overflow:ellipsis;}
.ln.bump .qty{animation:bump .9s ease;}
@keyframes bump{0%{background:#efece2} 20%{background:var(--indigo); color:#fff} 100%{background:#efece2}}
.ln.bump .qty span{color:inherit;}
.nm{font-size:23px; font-weight:700; line-height:1.15; overflow-wrap:anywhere;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;}
.meta{display:flex; align-items:center; flex-wrap:wrap; gap:8px 16px; margin-top:6px; font-size:17px; color:var(--paper-2);}
.exp{display:inline-flex; align-items:center; gap:6px; height:26px; padding:0 10px; border-radius:13px; font-size:15px; font-weight:700; border:1.5px solid #9fd2b7; color:#17643f; background:#eef8f2;}
.exp.soon{border-color:#e9b766; color:#87510a; background:#fdf3e2;}
.amt{font-family:var(--f-num); font-size:28px; font-weight:600; text-align:right; white-space:nowrap;}
.ln.enter{animation:feed .55s cubic-bezier(.2,.8,.2,1);}
@keyframes feed{from{opacity:0; transform:translateY(-26px)} to{opacity:1; transform:none}}
.more{margin-top:14px; font-size:17px; color:var(--paper-2); text-align:center;}
.more em.fr{color:var(--paper-2); display:block;}
.empty-tape{padding:120px 0; text-align:center; color:var(--paper-2); font-size:22px; border:0;}
.empty-tape em.fr{display:block; color:var(--paper-2);}
.stamp{position:absolute; right:56px; bottom:58px; transform:rotate(-9deg); padding:10px 26px; border:5px double var(--cash); color:var(--cash); border-radius:10px;
  font-size:44px; font-weight:800; letter-spacing:.08em; opacity:0; pointer-events:none; background:rgba(251,250,245,.85); text-align:center; line-height:1.1;}
.stamp small{display:block; font-size:16px; letter-spacing:.06em; font-weight:700;}
[data-mode="done"] .stamp{opacity:.92; animation:stamp .4s ease-out;}
@keyframes stamp{from{transform:rotate(-9deg) scale(1.6); opacity:0} to{transform:rotate(-9deg) scale(1); opacity:.92}}

/* right column: sale */
.r-sale{height:100%; display:flex; flex-direction:column; gap:18px;}
.cust{display:flex; align-items:baseline; gap:12px; font-size:18px; color:var(--on-2); min-width:0;}
.cust b{color:var(--on); font-size:21px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.cust b em{margin-left:8px; font-size:18px;}
.total{padding:24px 28px 22px; border-radius:14px; background:var(--navy-2); border:1px solid rgba(255,255,255,.1);}
.lbl{font-size:20px; font-weight:700;}
.lbl em{margin-left:8px; font-size:18px;}
.big{display:flex; align-items:baseline; gap:14px; margin-top:6px; line-height:1; white-space:nowrap;}
.big .mono{font-size:104px; font-weight:700; letter-spacing:-.03em;}
.big small{font-size:30px; font-weight:700; color:var(--on-2);}
.words{margin:14px 0 0; font-size:18px; line-height:1.35; color:var(--on-2);}
.words span{display:block; color:var(--on); font-weight:600;}
.words em{display:block;}
.sum{display:grid; grid-template-columns:1fr auto; gap:8px 16px; margin:0; padding:0 4px; font-size:19px;}
.sum dt{color:var(--on-2);}
.sum dt em{margin-left:6px;}
.sum dd{margin:0; text-align:right; font-family:var(--f-num); font-weight:600;}
.pay{flex:1; min-height:0; border-radius:14px; padding:22px 26px; display:flex; flex-direction:column; gap:14px; overflow:hidden;}
.pay.wait{border:2px dashed rgba(255,255,255,.18); justify-content:center; align-items:center; text-align:center; font-size:21px; color:var(--on-2);}
.pay.wait em{display:block; font-size:19px;}
.dots{display:flex; gap:8px; margin-bottom:8px;}
.dots i{width:10px; height:10px; border-radius:50%; background:var(--indigo-l); animation:dot 1.2s infinite ease-in-out;}
.dots i:nth-child(2){animation-delay:.15s} .dots i:nth-child(3){animation-delay:.3s}
@keyframes dot{0%,80%,100%{opacity:.25; transform:scale(.8)} 40%{opacity:1; transform:none}}
.pay.paid{background:rgba(40,183,123,.1); border:1.5px solid rgba(98,226,169,.4);}
.methods{display:flex; flex-wrap:wrap; gap:8px;}
.chip{display:inline-flex; align-items:center; height:32px; padding:0 14px; border-radius:16px; background:rgba(255,255,255,.1); font-weight:700; font-size:17px;}
.chip em{margin-left:6px; font-size:15px;}
.prow{display:flex; align-items:baseline; justify-content:space-between; gap:12px; font-size:20px;}
.prow .mono{font-weight:600; font-size:26px; white-space:nowrap;}
.prow.big2{padding-top:12px; border-top:1px solid rgba(98,226,169,.3);}
.prow.big2 .lbl{color:var(--cash-l);}
.prow.big2 .mono{font-size:52px; color:var(--cash-l); font-weight:700;}
.prow.due .lbl, .prow.due .mono{color:var(--amber);}
.cash-lbl{font-size:16px; color:var(--on-2);}
.cash-lbl em{margin-left:6px;}
.denoms{display:flex; flex-wrap:wrap; align-items:center; gap:12px;}
.note{display:flex; align-items:center; justify-content:center; height:48px; min-width:104px; padding:0 12px; border-radius:6px; background:#d9efe3; color:#0d4a30; font-family:var(--f-num); font-weight:700; font-size:20px; border:2px solid #9ed6b9;}
.coin{display:grid; place-items:center; width:54px; height:54px; border-radius:50%; background:radial-gradient(circle at 35% 30%, #fff6d6, #d9b75c 70%); color:#4a3608; font-family:var(--f-num); font-weight:700; font-size:16px; border:2px solid #b8923a;}
.times{font-family:var(--f-num); font-size:18px; color:var(--on-2); margin-left:-4px;}

/* right column: thank you */
.r-done{height:100%; display:none; flex-direction:column; justify-content:center; align-items:flex-start; gap:18px; padding:0 8px;}
.tick{width:92px; height:92px; border-radius:50%; background:var(--cash); display:grid; place-items:center; animation:pop .45s ease-out;}
@keyframes pop{from{transform:scale(.4); opacity:0} to{transform:none; opacity:1}}
.r-done h2{margin:0; font-size:72px; font-weight:800; line-height:1;}
.r-done h2 em{display:block; font-size:46px; font-weight:600; margin-top:6px; color:var(--on-2);}
.r-done .well{font-size:24px; color:var(--on-2);}
.r-done .well em{margin-left:8px;}
.rows{width:100%; display:grid; grid-template-columns:1fr auto; gap:10px 20px; padding:18px 22px; border-radius:12px; background:var(--navy-2); font-size:20px;}
.rows em{margin-left:6px;}
.rows .mono{text-align:right; font-weight:600; white-space:nowrap;}

/* idle */
.idle{display:none; height:100%; grid-template-rows:auto 1fr; gap:26px;}
.hello h1{margin:0; font-size:84px; font-weight:800; line-height:1; letter-spacing:-.02em;}
.hello h1 em{font-weight:600; color:var(--indigo-l); margin-left:22px; font-style:italic;}
.hello p{margin:14px 0 0; font-size:22px; color:var(--on-2); max-width:46ch;}
.hello p em{display:block;}
.slides{position:relative; border-radius:16px; overflow:hidden; background:var(--paper); color:var(--paper-ink); min-height:0;}
.slide{position:absolute; inset:0; display:grid; place-items:center; opacity:0; transition:opacity .8s;}
.slide.on{opacity:1;}
.slide img{position:absolute; inset:0; width:100%; height:100%; object-fit:contain; padding:18px;}
.slide.card{padding:40px 56px; text-align:center;}
.slide.card b{display:block; font-size:56px; font-weight:800; line-height:1.05;}
.slide.card span{display:block; margin-top:14px; font-size:26px; color:var(--paper-2);}
.sdots{position:absolute; left:50%; transform:translateX(-50%); bottom:18px; display:flex; gap:8px;}
.sdots i{width:30px; height:5px; border-radius:3px; background:rgba(0,0,0,.18);}
.sdots i.on{background:var(--indigo);}
.r-idle{display:none; height:100%; flex-direction:column; gap:20px;}
.open{display:inline-flex; align-self:flex-start; align-items:center; gap:10px; height:44px; padding:0 18px; border-radius:22px; background:rgba(40,183,123,.15); color:var(--cash-l); font-weight:700; font-size:20px; border:1.5px solid rgba(98,226,169,.4);}
.open em.fr{color:var(--cash-l); opacity:.8;}
.next{padding:30px 30px 28px; border-radius:14px; background:var(--navy-2); border:1px solid rgba(255,255,255,.1);}
.next b{display:block; font-size:44px; line-height:1.05; font-weight:800;}
.next em{display:block; font-size:30px; margin-top:8px;}
.accept .lbl{display:block; margin-bottom:10px;}
.accept .methods .chip{height:40px; font-size:19px;}

/* info strip */
.s-foot{display:flex; align-items:center; gap:20px; padding:0 40px; background:var(--slot); border-top:1px solid rgba(255,255,255,.08); min-width:0;}
.tag{flex:none; display:flex; align-items:center; gap:8px; height:34px; padding:0 14px; border-radius:17px; background:var(--indigo); color:#fff; font-weight:800; font-size:15px; letter-spacing:.05em; text-transform:uppercase;}
.info{flex:1; min-width:0; font-size:19px; line-height:1.22; transition:opacity .5s;}
.info span,.info em{display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;}
.info em{font-size:17px;}
.pw{flex:none; font-size:14px; color:var(--on-3); letter-spacing:.05em;}
.pw b{color:var(--on-2); letter-spacing:.08em;}

/* modes */
[data-mode="idle"] .tape,[data-mode="idle"] .r-sale{display:none;}
[data-mode="idle"] .idle{display:grid;}
[data-mode="idle"] .r-idle{display:flex;}
[data-mode="done"] .r-sale{display:none;}
[data-mode="done"] .r-done{display:flex;}

.fs{position:fixed; left:14px; bottom:14px; z-index:5; height:36px; padding:0 14px; border-radius:8px; border:1px solid rgba(255,255,255,.25);
  background:rgba(0,19,42,.85); color:#fff; font:600 14px var(--f-body); cursor:pointer; opacity:0; transition:opacity .3s; pointer-events:none;}
body.show-ui .fs{opacity:1; pointer-events:auto;}
.fs:focus-visible{outline:2px solid var(--indigo-l); opacity:1; pointer-events:auto;}
@media (prefers-reduced-motion:reduce){ *,*::before{animation:none !important; transition:none !important;} }
</style>
</head>
<body>
<button type="button" class="fs" id="fs">{{ $strings['full_screen'][0] }}</button>

<div class="stage" id="stage" data-mode="idle">
  <header class="s-top">
    <div class="brand">
      <span class="mark">@if($logo)<img src="{{ $logo }}" alt="">@else{{ $initials }}@endif</span>
      <div><b>{{ $business_name }}</b><small id="where"></small></div>
    </div>
    <div class="s-msg" id="msg"></div>
    @if($cashier !== '')
      <div class="s-who">{!! $bi('served_by') !!}<b>{{ $cashier }}</b></div>
    @endif
    <div class="s-clock"><div class="mono" id="clk">--:--</div><small id="dte"></small></div>
  </header>

  <main class="s-main">
    <section class="s-left">
      <div class="tape">
        <div class="slot"><span class="led"></span><span class="ttl">{!! $bi('your_items') !!}</span><span class="cnt" id="cnt"></span></div>
        <div class="paper" id="paper">
          <ol class="lines" id="lines"></ol>
          <div class="more" id="more"></div>
          <div class="stamp" aria-hidden="true">{{ $strings['paid_stamp'][0] }}@if($strings['paid_stamp'][1])<small>{{ $strings['paid_stamp'][1] }}</small>@endif</div>
        </div>
      </div>

      <div class="idle">
        <div class="hello">
          <h1>{{ $strings['welcome'][0] }}@if($strings['welcome'][1])<em>{{ $strings['welcome'][1] }}</em>@endif</h1>
          <p>{!! $bi('welcome_sub') !!}</p>
        </div>
        <div class="slides" id="slides">
          @forelse($carousel_images as $i => $src)
            <div class="slide{{ $i === 0 ? ' on' : '' }}"><img src="{{ $src }}" alt=""></div>
          @empty
            <div class="slide card on"><div><b>{{ $business_name }}</b><span id="slideWhere"></span></div></div>
          @endforelse
          @if(count($carousel_images) > 1)
            <div class="sdots">@foreach($carousel_images as $i => $src)<i class="{{ $i === 0 ? 'on' : '' }}"></i>@endforeach</div>
          @endif
        </div>
      </div>
    </section>

    <aside class="s-right">
      <div class="r-sale">
        <div class="cust">{!! $bi('customer') !!} <b id="cust"></b></div>
        <div class="total">
          <div class="lbl">{!! $bi('total_to_pay') !!}</div>
          <div class="big"><span class="mono" id="tot">0</span><small>{{ $config['currency']['symbol'] }}</small></div>
          <p class="words"><span id="w1"></span><em class="fr" id="w2"></em></p>
        </div>
        <dl class="sum" id="sum"></dl>
        <div class="pay wait" id="pay"></div>
      </div>

      <div class="r-done">
        <span class="tick"><svg width="48" height="48" viewBox="0 0 48 48"><path d="M10 25l9 9 19-20" fill="none" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
        <h2>{{ $strings['thank_you'][0] }}@if($strings['thank_you'][1])<em>{{ $strings['thank_you'][1] }}</em>@endif</h2>
        <div class="well">{!! $bi('see_you_soon') !!}</div>
        <div class="rows" id="doneRows"></div>
      </div>

      <div class="r-idle">
        <span class="open"><span class="led"></span>{!! $bi('open') !!}</span>
        <div class="next"><b>{{ $strings['next_customer'][0] }}</b>@if($strings['next_customer'][1])<em class="fr">{{ $strings['next_customer'][1] }}</em>@endif</div>
        <div class="accept"><span class="lbl">{!! $bi('we_accept') !!}</span><div class="methods" id="accept"></div></div>
      </div>
    </aside>
  </main>

  <footer class="s-foot">
    <span class="tag">{{ $strings['info'][0] }}</span>
    <div class="info" id="info"></div>
    <span class="pw">{{ $strings['powered_by'][0] }} <b>I-NNOVA POS</b></span>
  </footer>
</div>

<script>
(function () {
  'use strict';
  var C = @json($config);
  var S = C.strings, CUR = C.currency;
  var HEADING = @json($heading);
  var DONE_MS = 12000, MAX_LINES = 6;

  var $ = function (id) { return document.getElementById(id); };
  var stage = $('stage');

  /* ---------- text ---------- */
  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
    });
  }
  function rep(t, r) {
    Object.keys(r || {}).forEach(function (k) { t = t.split(':' + k).join(r[k]); });
    return t;
  }
  // a label in both languages: "Total to pay <em>Total à payer</em>"
  function bi(key, r) {
    var p = S[key] || [key, null];
    return esc(rep(p[0], r)) + (p[1] ? '<em class="fr">' + esc(rep(p[1], r)) + '</em>' : '');
  }
  function biPair(pair) {
    return esc(pair[0]) + (pair[1] ? '<em class="fr">' + esc(pair[1]) + '</em>' : '');
  }

  /* ---------- numbers ---------- */
  function escRe(s) { return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); }
  // a number as the POS form wrote it ("1,450.00", "FCFA 1 450,00") back to 1450
  function uf(v) {
    if (typeof v === 'number') return v;
    if (v == null) return 0;
    var s = String(v);
    if (CUR.symbol) s = s.split(CUR.symbol).join('');
    if (CUR.thousand) s = s.replace(new RegExp(escRe(CUR.thousand), 'g'), '');
    if (CUR.decimal && CUR.decimal !== '.') s = s.split(CUR.decimal).join('.');
    s = s.replace(/[^0-9.\-]/g, '');
    var n = parseFloat(s);
    return isNaN(n) ? 0 : n;
  }
  function group(intStr) {
    return intStr.replace(/\B(?=(\d{3})+(?!\d))/g, CUR.thousand || '');
  }
  function money(n) {
    var neg = n < 0, f = Math.abs(n).toFixed(CUR.precision);
    var parts = f.split('.');
    return (neg ? '-' : '') + group(parts[0]) + (parts[1] ? (CUR.decimal || '.') + parts[1] : '');
  }
  function qtyFmt(n) {
    var f = (+n).toFixed(CUR.qtyPrecision).replace(/\.?0+$/, '');
    var parts = f.split('.');
    return group(parts[0]) + (parts[1] ? (CUR.decimal || '.') + parts[1] : '');
  }

  var EN1 = ['zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];
  var EN10 = ['','','twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
  function en99(n) { return n < 20 ? EN1[n] : EN10[Math.floor(n / 10)] + (n % 10 ? '-' + EN1[n % 10] : ''); }
  function en999(n) {
    var h = Math.floor(n / 100), r = n % 100, s = [];
    if (h) s.push(EN1[h] + ' hundred');
    if (r) { if (h) s.push('and'); s.push(en99(r)); }
    return s.join(' ');
  }
  function enWords(n) {
    if (n === 0) return 'zero';
    var parts = [];
    [[1e9, 'billion'], [1e6, 'million'], [1e3, 'thousand']].forEach(function (sc) {
      var q = Math.floor(n / sc[0]);
      if (q) { parts.push(en999(q) + ' ' + sc[1]); n %= sc[0]; }
    });
    if (n) { if (parts.length && n < 100) parts.push('and'); parts.push(en999(n)); }
    return parts.join(' ');
  }
  var FR1 = ['zéro','un','deux','trois','quatre','cinq','six','sept','huit','neuf','dix','onze','douze','treize','quatorze','quinze','seize'];
  var FR10 = {2: 'vingt', 3: 'trente', 4: 'quarante', 5: 'cinquante', 6: 'soixante'};
  function fr99(n, last) {
    if (n < 17) return FR1[n];
    if (n < 20) return 'dix-' + FR1[n - 10];
    var t = Math.floor(n / 10), r = n % 10;
    if (t === 7) return n === 71 ? 'soixante et onze' : 'soixante-' + fr99(n - 60);
    if (t === 8) return r ? 'quatre-vingt-' + FR1[r] : (last ? 'quatre-vingts' : 'quatre-vingt');
    if (t === 9) return 'quatre-vingt-' + fr99(n - 80);
    if (r === 0) return FR10[t];
    return FR10[t] + (r === 1 ? ' et un' : '-' + FR1[r]);
  }
  function fr999(n, last) {
    var h = Math.floor(n / 100), r = n % 100, s = '';
    if (h) s = (h === 1 ? 'cent' : FR1[h] + ' cent' + (r === 0 && last ? 's' : ''));
    if (r) s += (s ? ' ' : '') + fr99(r, last);
    return s;
  }
  function frWords(n) {
    if (n === 0) return 'zéro';
    var parts = [], q;
    q = Math.floor(n / 1e9); if (q) { parts.push(q === 1 ? 'un milliard' : fr999(q, true) + ' milliards'); n %= 1e9; }
    q = Math.floor(n / 1e6); if (q) { parts.push(q === 1 ? 'un million' : fr999(q, true) + ' millions'); n %= 1e6; }
    q = Math.floor(n / 1e3); if (q) { parts.push(q === 1 ? 'mille' : fr999(q, false) + ' mille'); n %= 1e3; }
    if (n) parts.push(fr999(n, true));
    return parts.join(' ');
  }
  function words(n, lang) {
    if (!(n > 0) || n >= 1e12) return '';
    var whole = Math.floor(n + 1e-9), cents = Math.round((n - whole) * 100);
    var w = lang === 'fr' ? frWords(whole) : enWords(whole);
    if (CUR.precision > 0 && cents > 0) w += (lang === 'fr' ? ' et ' : ' and ') + (cents < 10 ? '0' : '') + cents + '/100';
    return w + ' ' + (CUR.symbol || CUR.code || '');
  }
  function breakdown(n) {
    var out = [];
    (CUR.denominations || []).forEach(function (d) {
      var c = Math.floor((n + 1e-9) / d);
      if (c > 0) { out.push({d: d, c: c}); n = Math.round((n - c * d) * 100) / 100; }
    });
    return n > 0.009 ? out.concat([{d: n, c: 1, rest: true}]) : out;
  }

  /* ---------- reading the POS cart ---------- */
  function readJSON(key) {
    try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch (e) { return null; }
  }
  function parseCart(arr) {
    var f = {}, rows = {}, pays = {};
    (arr || []).forEach(function (it) {
      var m = it.name.match(/^products\[(\d+)\]\[(.+?)\]$/);
      if (m) { (rows[m[1]] = rows[m[1]] || {})[m[2]] = it.value; return; }
      m = it.name.match(/^payment\[(\d+)\]\[(method|amount)\]$/);
      if (m) { (pays[m[1]] = pays[m[1]] || {})[m[2]] = it.value; return; }
      f[it.name] = it.value;
    });
    var lines = Object.keys(rows).map(function (k) {
      var r = rows[k];
      return {
        key: k, variation_id: r.variation_id, lot: r.lot_no_line_id,
        unit_id: r.sub_unit_id || r.product_unit_id,
        qty: uf(r.quantity), price: uf(r.unit_price_inc_tax)
      };
    }).filter(function (l) { return l.variation_id; })
      .sort(function (a, b) { return (+b.key) - (+a.key); });   // newest first

    var priceTotal = lines.reduce(function (t, l) { return t + l.qty * l.price; }, 0);
    if (f.price_total != null) priceTotal = uf(f.price_total);
    var disc = uf(f.discount_amount);
    if (f.discount_type === 'percentage') disc = priceTotal * disc / 100;

    var methods = [];
    Object.keys(pays).forEach(function (k) {
      if (pays[k].method && uf(pays[k].amount) > 0) methods.push(pays[k].method);
    });
    return {
      lines: lines,
      contactId: f.contact_id, walkInName: (f.walk_in_customer_name || '').trim(),
      locationId: f.location_id || C.defaultLocation,
      subtotal: priceTotal, discount: disc,
      tax: uf(f.order_tax), shipping: uf(f.shipping_charges_amount),
      total: f.final_total != null ? uf(f.final_total) : priceTotal,
      paying: String(f.__display_paying) === '1',
      paid: uf(f.total_paying_input), change: uf(f.change_return), balance: uf(f.in_balance_due),
      methods: methods
    };
  }

  /* ---------- names, expiry, customer: one request, cached ---------- */
  var cache = {vars: {}, lots: {}, earliest: {}, contacts: {}}, inflight = null;
  function missingFor(cart) {
    var v = [], l = [], loc = cart.locationId, ear = cache.earliest[loc] || {};
    cart.lines.forEach(function (x) {
      if (!cache.vars[x.variation_id] || (C.showExpiry && !x.lot && !(x.variation_id in ear))) v.push(x.variation_id);
      if (C.showExpiry && x.lot && !(x.lot in cache.lots)) l.push(x.lot);
    });
    var c = cart.contactId && !(cart.contactId in cache.contacts) ? cart.contactId : '';
    return {v: v, l: l, c: c};
  }
  function fetchDetails(cart) {
    var need = missingFor(cart);
    if (!need.v.length && !need.l.length && !need.c) return;
    var sig = JSON.stringify(need) + '|' + cart.locationId;
    if (inflight === sig) return;
    inflight = sig;
    var q = 'variations=' + encodeURIComponent(need.v.join(',')) + '&lots=' + encodeURIComponent(need.l.join(',')) +
            '&location_id=' + encodeURIComponent(cart.locationId || '') + '&contact_id=' + encodeURIComponent(need.c);
    var xhr = new XMLHttpRequest();
    xhr.open('GET', C.detailsUrl + '?' + q);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onload = function () {
      inflight = null;
      if (xhr.status !== 200) return;
      var d; try { d = JSON.parse(xhr.responseText); } catch (e) { return; }
      var ear = cache.earliest[cart.locationId] = cache.earliest[cart.locationId] || {};
      need.v.forEach(function (id) {
        cache.vars[id] = d.variations[id] || {name: '-', unit_id: null};   // deleted product: don't ask again
        ear[id] = d.earliest[id] || null;           // null = asked, no dated stock
      });
      need.l.forEach(function (id) { cache.lots[id] = d.lots[id] || null; });
      if (need.c) cache.contacts[need.c] = d.contact;
      render();
    };
    xhr.onerror = function () { inflight = null; };
    xhr.send();
  }

  /* ---------- render ---------- */
  var prevQty = null, flash = {}, lastLines = [], shownSale = null;

  function monthsLeft(iso) {
    var e = new Date(iso + 'T00:00:00'), t = new Date();
    return (e.getFullYear() - t.getFullYear()) * 12 + (e.getMonth() - t.getMonth()) + (e.getDate() - t.getDate()) / 30;
  }
  function expiryOf(line, loc) {
    if (!C.showExpiry) return null;
    if (line.lot) return cache.lots[line.lot] || null;
    return (cache.earliest[loc] || {})[line.variation_id] || null;
  }
  function unitOf(line) {
    var v = cache.vars[line.variation_id];
    return C.units[line.unit_id] || (v && C.units[v.unit_id]) || '';
  }
  function methodLabel(loc, m) {
    var pm = (C.paymentMethods[loc] || {})[m] || (C.paymentMethods[C.defaultLocation] || {})[m];
    return pm ? '<span class="chip">' + biPair(pm) + '</span>' : '<span class="chip">' + esc(m) + '</span>';
  }

  function renderLines(lines, loc) {
    var shown = lines, extra = 0;
    if (shown.length > MAX_LINES) { extra = shown.length - MAX_LINES; shown = shown.slice(0, MAX_LINES); }
    if (!lines.length) {
      $('lines').innerHTML = '<li class="empty-tape">' + bi('waiting_first') + '</li>';
      $('more').innerHTML = '';
      return;
    }
    $('lines').innerHTML = shown.map(function (l) {
      var v = cache.vars[l.variation_id], exp = expiryOf(l, loc), cls = 'ln';
      if (flash[l.key]) cls += flash[l.key] === 'add' ? ' enter fresh' : ' bump fresh';
      var expHtml = '';
      if (exp) {
        var soon = monthsLeft(exp) < 3, p = exp.split('-');
        expHtml = '<span class="exp' + (soon ? ' soon' : '') + '">' +
          (soon ? '<svg width="14" height="14" viewBox="0 0 14 14"><path d="M7 1l6.5 12h-13z" fill="currentColor"/></svg>' : '') +
          esc(S.expiry_short[0]) + ' ' + p[1] + '/' + p[0] + '</span>';
      }
      return '<li class="' + cls + '">' +
        '<div class="qty"><b>' + qtyFmt(l.qty) + '</b><span>' + esc(unitOf(l)) + '</span></div>' +
        '<div><div class="nm">' + (v ? esc(v.name) : '&hellip;') + '</div>' +
        '<div class="meta"><span class="mono">' + qtyFmt(l.qty) + ' × ' + money(l.price) + '</span>' + expHtml + '</div></div>' +
        '<div class="amt">' + money(l.qty * l.price) + '</div></li>';
    }).join('');
    $('more').innerHTML = extra ? bi('earlier_items', {count: extra}) : '';
  }

  function fitTotal(el) {
    var len = el.textContent.length;
    var room = 540 - 26 * Math.max(1, String(CUR.symbol || '').length) * 0.62 - 14;   // minus the symbol beside it
    el.style.fontSize = Math.min(104, Math.floor(room / (Math.max(len, 1) * 0.62))) + 'px';
  }

  function render() {
    var cart = parseCart(readJSON('pos_form_data_array'));
    var sale = readJSON('pos_display_last_sale');
    var recent = sale && (Date.now() - sale.at) < DONE_MS;
    var loc = cart.locationId;
    var mode = cart.lines.length ? (cart.paying ? 'paying' : 'serving') : (recent ? 'done' : 'idle');

    // what changed since last time, for the feed-in and quantity flash
    var q = {};
    cart.lines.forEach(function (l) { q[l.key] = l.qty; });
    if (prevQty) {
      cart.lines.forEach(function (l) {
        var kind = !(l.key in prevQty) ? 'add' : (l.qty > prevQty[l.key] ? 'bump' : null);
        if (!kind) return;
        flash[l.key] = kind;
        setTimeout(function () { if (flash[l.key] === kind) { delete flash[l.key]; schedule(); } }, 2400);
      });
    }
    prevQty = q;

    stage.setAttribute('data-mode', mode);
    fetchDetails(cart);

    var locInfo = C.locations[loc] || {};
    $('where').textContent = [locInfo.name, locInfo.city, locInfo.mobile].filter(Boolean).join(' · ');
    if ($('slideWhere')) $('slideWhere').textContent = [locInfo.city, locInfo.mobile].filter(Boolean).join(' · ');

    $('msg').innerHTML = mode === 'idle'
      ? (HEADING ? esc(HEADING) : bi('welcome'))
      : mode === 'done' ? bi('keep_receipt') : bi('check_items');

    if (mode === 'done') {
      renderLines(lastLines, loc);
      renderDone(sale, loc);
      setTimeout(render, Math.max(500, DONE_MS - (Date.now() - sale.at) + 50));
    } else if (mode !== 'idle') {
      lastLines = cart.lines;
      renderLines(cart.lines, loc);
      renderSale(cart, loc, mode);
    } else {
      lastLines = [];
    }
    $('accept').innerHTML = (C.acceptMethods[loc] || []).slice(0, 6).map(function (m) {
      return methodLabel(loc, m);
    }).join('');
    infoItems = buildInfo(loc);
  }

  function renderSale(cart, loc, mode) {
    var units = cart.lines.reduce(function (t, l) { return t + l.qty; }, 0);
    var cnt = function (i) {
      return esc(rep(S.items_count[i], {count: cart.lines.length})) + ' · ' + esc(rep(S.units_count[i], {count: qtyFmt(units)}));
    };
    $('cnt').innerHTML = cnt(0) + (S.items_count[1] ? '<em class="fr" style="display:block;font-size:15px">' + cnt(1) + '</em>' : '');

    var contact = cart.contactId ? cache.contacts[cart.contactId] : null;
    var who = cart.walkInName || (contact && !contact.is_default ? contact.name : '');
    $('cust').innerHTML = who ? esc(who) : bi('walk_in');

    var tot = $('tot');
    tot.textContent = money(cart.total);
    fitTotal(tot);
    var L = C.locale;
    $('w1').textContent = words(cart.total, L[0]);
    $('w2').textContent = words(cart.total, L[1]);

    var sum = '<dt>' + bi('subtotal') + '</dt><dd>' + money(cart.subtotal) + '</dd>';
    if (cart.discount > 0) sum += '<dt>' + bi('discount') + '</dt><dd>- ' + money(cart.discount) + '</dd>';
    if (cart.tax > 0) sum += '<dt>' + bi('order_tax') + '</dt><dd>+ ' + money(cart.tax) + '</dd>';
    if (cart.shipping > 0) sum += '<dt>' + bi('shipping') + '</dt><dd>+ ' + money(cart.shipping) + '</dd>';
    $('sum').innerHTML = sum;

    var pay = $('pay');
    if (mode !== 'paying') {
      pay.className = 'pay wait';
      pay.innerHTML = '<div class="dots"><i></i><i></i><i></i></div>' + bi('adding_items');
      return;
    }
    pay.className = 'pay paid';
    var h = '';
    if (cart.methods.length) h += '<div class="methods">' + cart.methods.map(function (m) { return methodLabel(loc, m); }).join('') + '</div>';
    h += '<div class="prow"><span class="lbl">' + bi('amount_received') + '</span><span class="mono">' + money(cart.paid) + '</span></div>';
    if (cart.change > 0) {
      h += '<div class="prow big2"><span class="lbl">' + bi('your_change') + '</span><span class="mono">' + money(cart.change) + '</span></div>';
      var parts = breakdown(cart.change);
      if ((CUR.denominations || []).length && parts.length) {
        h += '<div class="cash-lbl">' + bi('counted_out_as') + '</div><div class="denoms">' + parts.map(function (b) {
          var coin = b.rest || b.d <= CUR.coinMax;
          var label = Math.abs(b.d - Math.round(b.d)) < 1e-9 ? group(String(Math.round(b.d))) : money(b.d);
          var el = '<span class="' + (coin ? 'coin' : 'note') + '">' + label + '</span>';
          return el + (b.c > 1 ? '<span class="times">× ' + b.c + '</span>' : '');
        }).join('') + '</div>';
      }
    } else if (cart.balance > 0.009) {
      h += '<div class="prow big2 due"><span class="lbl">' + bi('still_to_pay') + '</span><span class="mono">' + money(cart.balance) + '</span></div>';
    } else {
      h += '<div class="prow big2"><span class="lbl">' + bi('no_change_due') + '</span><span class="mono" style="font-size:40px">' + money(0) + '</span></div>';
    }
    pay.innerHTML = h;
  }

  function renderDone(sale, loc) {
    var sym = ' ' + esc(CUR.symbol || '');
    var h = '<span>' + bi('total_to_pay') + '</span><span class="mono">' + money(sale.total) + sym + '</span>';
    h += '<span>' + bi('amount_received') + '</span><span class="mono">' + money(sale.paid || sale.total) + sym + '</span>';
    if (sale.change > 0) {
      h += '<span>' + bi('change') + '</span><span class="mono">' + money(sale.change) + sym + '</span>';
    } else if ((sale.methods || []).length) {
      h += '<span>' + sale.methods.map(function (m) { return methodLabel(sale.location_id || loc, m); }).join(' ') + '</span><span></span>';
    }
    if (sale.invoice_no) h += '<span>' + bi('receipt') + '</span><span class="mono">N° ' + esc(sale.invoice_no) + '</span>';
    $('doneRows').innerHTML = h;
  }

  /* ---------- info strip: the business's own messages, then the built-in ones ---------- */
  var infoItems = [], infoI = 0;
  function buildInfo(loc) {
    var items = (C.messages || []).map(function (m) { return '<span>' + esc(m) + '</span>'; });
    var s = S.check_change;
    items.push('<span>' + esc(s[0]) + '</span>' + (s[1] ? '<em class="fr">' + esc(s[1]) + '</em>' : ''));
    var phone = (C.locations[loc] || {}).mobile;
    if (phone) {
      var c = S.call_us;
      items.push('<span>' + esc(rep(c[0], {phone: phone})) + '</span>' + (c[1] ? '<em class="fr">' + esc(rep(c[1], {phone: phone})) + '</em>' : ''));
    }
    return items;
  }
  function nextInfo() {
    var el = $('info');
    if (!infoItems.length) return;
    el.style.opacity = 0;
    setTimeout(function () { el.innerHTML = infoItems[infoI++ % infoItems.length]; el.style.opacity = 1; }, 350);
  }

  /* ---------- clock, slides ---------- */
  function clock() {
    var d = new Date(), o = {weekday: 'short', day: 'numeric', month: 'short'};
    $('clk').textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    try {
      $('dte').textContent = C.locale.map(function (l) { return d.toLocaleDateString(l === 'fr' ? 'fr-FR' : 'en-GB', o); }).join(' · ');
    } catch (e) {}
  }
  var slideI = 0;
  function nextSlide() {
    var slides = document.querySelectorAll('.slide');
    if (slides.length < 2) return;
    slideI = (slideI + 1) % slides.length;
    slides.forEach(function (s, i) { s.classList.toggle('on', i === slideI); });
    document.querySelectorAll('.sdots i').forEach(function (s, i) { s.classList.toggle('on', i === slideI); });
  }

  /* ---------- fit 1600x900 to the screen ---------- */
  function fit() {
    var W = window.innerWidth, H = window.innerHeight, k = Math.min(W / 1600, H / 900);
    stage.style.transform = 'translate(' + ((W - 1600 * k) / 2) + 'px,' + ((H - 900 * k) / 2) + 'px) scale(' + k + ')';
  }

  /* ---------- full screen button and cursor: only while the mouse moves ---------- */
  var uiTimer = null;
  function showUi() {
    document.body.classList.add('show-ui');
    document.body.classList.remove('no-cursor');
    clearTimeout(uiTimer);
    uiTimer = setTimeout(function () {
      document.body.classList.remove('show-ui');
      document.body.classList.add('no-cursor');
    }, 3000);
  }
  document.addEventListener('mousemove', showUi);
  $('fs').addEventListener('click', function () {
    var el = document.documentElement;
    try {
      if (document.fullscreenElement) document.exitFullscreen();
      else if (el.requestFullscreen) el.requestFullscreen();
    } catch (e) {}
  });

  /* ---------- updates from the POS tab ---------- */
  var timer = null, lastRaw = null;
  function schedule() { clearTimeout(timer); timer = setTimeout(render, 120); }
  window.addEventListener('storage', function (e) {
    if (e.key === 'pos_form_data_array' || e.key === 'pos_display_last_sale' || e.key === null) schedule();
  });
  // safety net if a storage event is ever missed
  setInterval(function () {
    var raw = (localStorage.getItem('pos_form_data_array') || '') + (localStorage.getItem('pos_display_last_sale') || '');
    if (raw !== lastRaw) { lastRaw = raw; schedule(); }
  }, 2000);

  window.addEventListener('resize', fit);
  fit(); clock(); render(); nextInfo(); showUi();
  setInterval(clock, 15000);
  setInterval(nextInfo, 7000);
  setInterval(nextSlide, 6000);
})();
</script>
</body>
</html>
