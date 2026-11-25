@extends('layouts.app')
@section('title', __('pressing.menu_new'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PressingController::class;
    $catalog = $list->mapWithKeys(fn ($r) => [$r['item']->id => [
        'name' => $r['item']->name, 'days' => (int) $r['item']->ready_days,
        'prices' => $r['prices']->mapWithKeys(fn ($p) => [$p->service_id => ['p' => (float) $p->price, 'g' => $p->guest_price !== null ? (float) $p->guest_price : null]])->all(),
    ]])->all();
@endphp
<style>
.pr-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
.pr-tile { min-height: 78px; border-radius: 10px; border: 1px solid #d0d5dd; background: #fff; text-align: left; padding: 10px 12px; font: inherit; color: #101828; display: flex; flex-direction: column; gap: 2px; cursor: pointer; }
.pr-tile:hover { border-color: #1d4ed8; }
.pr-tile.has { border: 2px solid #1d4ed8; background: #eff4ff; }
.pr-tile[disabled] { opacity: .45; cursor: not-allowed; }
.pr-tile .pp { color: #475467; font-size: 13px; }
.pr-tile .nn { font-size: 12px; font-weight: 700; color: #1d4ed8; }
.pr-svc { display: flex; flex-wrap: wrap; gap: 6px; }
.pr-svc label { margin: 0; }
.pr-svc input { position: absolute; opacity: 0; }
.pr-svc span { display: inline-flex; align-items: center; min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid #d0d5dd; background: #fff; font-weight: 600; font-size: 13px; cursor: pointer; }
.pr-svc input:checked + span { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
.pr-svc input:focus-visible + span { outline: 2px solid #84adff; }
.pr-basket .ln { display: grid; grid-template-columns: 46px 1fr auto 36px; gap: 8px; align-items: center; padding: 8px 0; border-bottom: 1px solid #f2f4f7; }
.pr-basket .ln input { min-height: 32px; padding: 4px 8px; font-size: 12.5px; }
.pr-basket .tg { font-weight: 700; color: #1d4ed8; }
.pr-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; border-top: 2px solid #101828; padding-top: 10px; margin-top: 8px; }
.pr-pay { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.pr-pay label { margin: 0; }
.pr-pay input { position: absolute; opacity: 0; }
.pr-pay span { display: flex; align-items: center; justify-content: center; min-height: 46px; border-radius: 8px; border: 1px solid #d0d5dd; background: #fff; font-weight: 600; cursor: pointer; text-align: center; padding: 4px 8px; }
.pr-pay input:checked + span { border: 2px solid #1d4ed8; background: #eff4ff; color: #1d4ed8; }
.pr-pay input:disabled + span { opacity: .45; cursor: not-allowed; border-style: dashed; }
.pr-who { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
</style>
<section class="content ht" id="pr_create">
    <div class="ht-head">
        <div><h1>{{ __('pressing.menu_new') }}</h1><p>{{ __('pressing.new_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'index']) }}">{{ __('pressing.back_board') }}</a></div>
    </div>

    @if ($list->isEmpty())
        <a class="ht-note amber" href="{{ action([\App\Http\Controllers\PressingSetupController::class, 'index']) }}">{{ __('pressing.needs_setup') }}</a>
    @endif

    <form method="post" action="{{ action([$P, 'store']) }}" id="pr_form" autocomplete="off">
        @csrf
        <input type="hidden" name="pieces_json" id="pr_pieces_json" value="{{ old('pieces_json') }}">
        <div class="ht-grid" style="grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr); align-items: start">
            <div>
                <div class="ht-card">
                    <h2 class="pr-h"><span class="pr-step">1</span>{{ __('pressing.step_who') }}</h2>
                    <div class="pr-who pr-pay" style="grid-template-columns: repeat(2, minmax(0, 220px))">
                        <label><input type="radio" name="for" value="outside" {{ old('for', 'outside') === 'outside' ? 'checked' : '' }}><span>{{ __('pressing.for_outside') }}</span></label>
                        <label><input type="radio" name="for" value="guest" {{ old('for') === 'guest' ? 'checked' : '' }} {{ empty($guests) ? 'disabled' : '' }}><span>{{ __('pressing.for_guest') }}</span></label>
                    </div>
                    <div id="pr_outside" class="ht-fields">
                        <div class="ht-f"><label for="pr_mobile">{{ __('pressing.mobile') }} *</label><input id="pr_mobile" name="mobile" class="ht-in" type="tel" value="{{ old('mobile') }}"><span class="ht-muted ht-small">{{ __('pressing.mobile_hint') }}</span></div>
                        <div class="ht-f"><label for="pr_name">{{ __('pressing.name') }} *</label><input id="pr_name" name="name" class="ht-in" value="{{ old('name') }}"><span class="ht-muted ht-small">{{ __('pressing.name_hint') }}</span></div>
                    </div>
                    <div id="pr_guest" hidden>
                        <div class="ht-f"><label for="pr_res">{{ __('pressing.room_guest') }} *</label>
                            <select id="pr_res" name="reservation_id" class="ht-in">
                                <option value="">{{ __('pressing.pick_room') }}</option>
                                @foreach ($guests as $g)
                                    <option value="{{ $g['id'] }}" {{ (string) old('reservation_id') === (string) $g['id'] ? 'selected' : '' }}>{{ __('pressing.room', ['n' => $g['room']]) }} · {{ $g['guest'] }} · {{ __('pressing.until', ['date' => \Carbon\Carbon::parse($g['departure'])->format('d/m')]) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @if (empty($guests) && ! empty(\App\Utils\Hotel::enabled(session('business.id'))))
                        <p class="ht-muted ht-small">{{ __('pressing.no_guests') }}</p>
                    @endif
                </div>

                <div class="ht-card">
                    <h2 class="pr-h" style="justify-content: space-between; flex-wrap: wrap"><span style="display:flex;gap:10px;align-items:center"><span class="pr-step">2</span>{{ __('pressing.step_items') }}</span>
                        <span class="pr-svc" role="radiogroup" aria-label="{{ __('pressing.service') }}">
                            @foreach ($services as $i => $svc)
                                <label><input type="radio" name="pr_svc" value="{{ $svc->id }}" {{ $i === 0 ? 'checked' : '' }}><span>{{ $svc->name }}</span></label>
                            @endforeach
                        </span>
                    </h2>
                    <p class="ht-muted ht-small" style="margin-top:-4px">{{ __('pressing.items_hint') }}</p>
                    <div class="pr-tiles" id="pr_tiles">
                        @foreach ($list as $r)
                            <button type="button" class="pr-tile" data-item="{{ $r['item']->id }}"><strong>{{ $r['item']->name }}</strong><span class="pp"></span><span class="nn"></span></button>
                        @endforeach
                    </div>
                </div>

                <div class="ht-card">
                    <h2 class="pr-h"><span class="pr-step">3</span>{{ __('pressing.step_ready') }}</h2>
                    <div class="ht-fields">
                        <div class="ht-f"><label for="pr_ready">{{ __('pressing.ready_on_label') }}</label><input id="pr_ready" name="ready_at" class="ht-in" type="datetime-local" value="{{ old('ready_at') }}"><span class="ht-muted ht-small">{{ __('pressing.ready_hint') }}</span></div>
                        <div class="ht-f"><label>&nbsp;</label><label style="display:flex;gap:10px;align-items:center;min-height:40px;font-weight:600"><input type="checkbox" name="express" value="1" id="pr_express" {{ old('express') ? 'checked' : '' }} style="width:20px;height:20px"> {{ __('pressing.express_label', ['pct' => $s['express_percent'], 'time' => $s['express_time']]) }}</label></div>
                    </div>
                    <div class="ht-f"><label for="pr_note">{{ __('pressing.order_note') }}</label><textarea id="pr_note" name="note" class="ht-in" rows="2">{{ old('note') }}</textarea></div>
                </div>
            </div>

            <div class="ht-card pr-basket" style="position: sticky; top: 12px">
                <h2>{{ __('pressing.basket') }}</h2>
                <div id="pr_lines"><p class="ht-muted ht-small" id="pr_none">{{ __('pressing.basket_empty') }}</p></div>
                <p class="ht-muted ht-small">{{ __('pressing.basket_hint') }}</p>
                <div class="ht-row" id="pr_express_row" hidden><span>{{ __('pressing.express') }} {{ $s['express_percent'] }}%</span><strong id="pr_express_amt"></strong></div>
                <div class="pr-total"><span id="pr_count"></span><span id="pr_total">0</span></div>

                <h2 class="pr-h" style="margin-top:16px"><span class="pr-step">4</span>{{ __('pressing.step_pay') }}</h2>
                <div class="pr-pay" id="pr_pay">
                    <label><input type="radio" name="pay" value="all" {{ old('pay') === 'all' ? 'checked' : '' }}><span>{{ __('pressing.pay_all') }}</span></label>
                    <label><input type="radio" name="pay" value="part" {{ old('pay') === 'part' ? 'checked' : '' }}><span>{{ __('pressing.pay_part') }}</span></label>
                    <label><input type="radio" name="pay" value="later" {{ old('pay', $s['pay_rule'] === 'any' ? 'later' : 'all') === 'later' ? 'checked' : '' }} {{ $s['pay_rule'] !== 'any' ? 'disabled' : '' }}><span>{{ __('pressing.pay_later') }}</span></label>
                    <label><input type="radio" name="pay" value="room" {{ old('pay') === 'room' ? 'checked' : '' }} disabled data-room="{{ $roomBill ? 1 : 0 }}"><span>{{ __('pressing.pay_room') }}</span></label>
                </div>
                <div class="ht-fields" id="pr_money" style="margin-top:10px">
                    <div class="ht-f" id="pr_amount_f"><label for="pr_amount">{{ __('pressing.amount_now') }}</label><input id="pr_amount" name="amount" class="ht-in" inputmode="decimal" value="{{ old('amount') }}"></div>
                    <div class="ht-f"><label for="pr_method">{{ __('pressing.method') }}</label>
                        <select id="pr_method" name="method" class="ht-in">@foreach ($methods as $k => $label)<option value="{{ $k }}" {{ old('method', 'cash') === $k ? 'selected' : '' }}>{{ $label }}</option>@endforeach</select></div>
                </div>
                @if ($s['pay_rule'] !== 'any')<p class="ht-muted ht-small">{{ __('pressing.pay_rule_'.$s['pay_rule']) }}</p>@endif

                <div class="ht-fields" style="margin-top:6px">
                    <div class="ht-f"><label style="display:flex;gap:10px;align-items:center;font-weight:600"><input type="checkbox" name="whatsapp" value="1" checked style="width:20px;height:20px"> {{ __('pressing.wa_opt') }}</label></div>
                    <div class="ht-f"><label for="pr_lang">{{ __('pressing.lang') }}</label><select id="pr_lang" name="lang" class="ht-in"><option value="fr" {{ old('lang', app()->getLocale() === 'en' ? 'en' : 'fr') === 'fr' ? 'selected' : '' }}>Français</option><option value="en" {{ old('lang', app()->getLocale()) === 'en' ? 'selected' : '' }}>English</option></select></div>
                </div>
                <button type="submit" class="ht-btn pri" id="pr_save" style="width:100%;min-height:50px;font-size:15px">{{ __('pressing.save_print') }}</button>
            </div>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
(function () {
    var CAT = {!! json_encode($catalog) !!};
    var DAYS = {!! json_encode($readyDays) !!};
    var S = {!! json_encode(['pct' => (float) $s['express_percent'], 'ready' => $s['ready_time'], 'xtime' => $s['express_time'], 'guestPrices' => (bool) $guestPrices, 'rule' => $s['pay_rule']]) !!};
    var T = {!! json_encode(['n_pieces' => __('pressing.js_pieces'), 'remove' => __('pressing.remove'), 'note' => __('pressing.piece_note_ph'), 'none' => __('pressing.no_price'),
        'check' => __('pressing.cf_check'), 'intro' => __('pressing.cf_intro'), 'total' => __('pressing.cf_total'), 'customer' => __('pressing.cf_customer'),
        'ready' => __('pressing.cf_ready'), 'paid' => __('pressing.cf_paid'), 'room' => __('pressing.pay_room'), 'later' => __('pressing.pay_later'),
        'pieces_lbl' => __('pressing.cf_pieces'), 'yes' => __('pressing.save_print'), 'empty' => __('pressing.err_no_pieces')]) !!};
    var form = document.getElementById('pr_form');
    var money = function (n) { return window.__currency_trans_from_en && typeof __currency_precision !== 'undefined' ? __currency_trans_from_en(n, true) : Math.round(n).toLocaleString(); };
    var num = function (s) { var v = window.__number_uf ? __number_uf(String(s || '')) : parseFloat(String(s || '').replace(/[^\d.]/g, '')); return isFinite(v) ? Number(v) : 0; };
    var pieces = [];
    try { pieces = JSON.parse(document.getElementById('pr_pieces_json').value || '[]') || []; } catch (e) { pieces = []; }
    var readyTouched = !!document.getElementById('pr_ready').value;

    var svc = function () { var r = form.querySelector('input[name=pr_svc]:checked'); return r ? r.value : null; };
    var guest = function () { var r = form.querySelector('input[name=for]:checked'); return r && r.value === 'guest'; };
    var priceOf = function (item, s) {
        var p = CAT[item] && CAT[item].prices[s];
        if (!p) return null;
        return guest() && S.guestPrices && p.g != null ? p.g : p.p;
    };
    var svcName = function (id) { var r = form.querySelector('input[name=pr_svc][value="' + id + '"]'); return r ? r.nextElementSibling.textContent : ''; };

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function readyGuess() {
        var d = new Date(), t;
        if (document.getElementById('pr_express').checked) { d.setDate(d.getDate() + 1); t = S.xtime; }
        else {
            var max = 0; pieces.forEach(function (p) { max = Math.max(max, DAYS[p.item_id] || 2); });
            d.setDate(d.getDate() + Math.max(1, max || 2)); t = S.ready;
        }
        var hm = String(t || '17:00').split(':');
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(+hm[0]) + ':' + pad(+(hm[1] || 0));
    }

    function totals() {
        var sub = 0; pieces.forEach(function (p) { sub += priceOf(p.item_id, p.service_id) || 0; });
        var ex = document.getElementById('pr_express').checked ? Math.round(sub * S.pct / 100) : 0;
        return { sub: sub, ex: ex, total: sub + ex };
    }

    function render() {
        var s = svc();
        document.querySelectorAll('.pr-tile').forEach(function (b) {
            var item = b.getAttribute('data-item'), p = priceOf(item, s);
            b.disabled = p == null;
            b.querySelector('.pp').textContent = p == null ? T.none : money(p);
            var n = pieces.filter(function (x) { return String(x.item_id) === item && String(x.service_id) === String(s); }).length;
            b.querySelector('.nn').textContent = n ? '× ' + n : '';
            b.classList.toggle('has', n > 0);
        });
        var box = document.getElementById('pr_lines');
        box.innerHTML = '';
        if (!pieces.length) { var e = document.createElement('p'); e.className = 'ht-muted ht-small'; e.id = 'pr_none'; e.textContent = T.empty; box.appendChild(e); }
        pieces.forEach(function (p, i) {
            var row = document.createElement('div'); row.className = 'ln'; row.setAttribute('data-tag', i + 1);
            var tg = document.createElement('span'); tg.className = 'tg'; tg.textContent = '#' + (i + 1);
            var mid = document.createElement('div');
            var nm = document.createElement('div'); nm.textContent = (CAT[p.item_id] ? CAT[p.item_id].name : '?') + ' · ' + svcName(p.service_id);
            var note = document.createElement('input'); note.className = 'ht-in'; note.placeholder = T.note; note.value = p.note || ''; note.setAttribute('aria-label', T.note + ' #' + (i + 1));
            note.addEventListener('input', function () { p.note = note.value; sync(); });
            mid.appendChild(nm); mid.appendChild(note);
            var pr = document.createElement('span'); pr.textContent = money(priceOf(p.item_id, p.service_id) || 0);
            var rm = document.createElement('button'); rm.type = 'button'; rm.className = 'ht-btn sm danger'; rm.textContent = '×'; rm.setAttribute('aria-label', T.remove + ' #' + (i + 1));
            rm.addEventListener('click', function () { pieces.splice(i, 1); render(); });
            row.appendChild(tg); row.appendChild(mid); row.appendChild(pr); row.appendChild(rm);
            box.appendChild(row);
        });
        var t = totals();
        document.getElementById('pr_express_row').hidden = !t.ex;
        document.getElementById('pr_express_amt').textContent = money(t.ex);
        document.getElementById('pr_total').textContent = money(t.total);
        document.getElementById('pr_count').textContent = T.n_pieces.replace(':n', pieces.length);
        if (!readyTouched) document.getElementById('pr_ready').value = readyGuess();
        payState();
        sync();
    }
    function sync() { document.getElementById('pr_pieces_json').value = JSON.stringify(pieces.map(function (p) { return { item_id: p.item_id, service_id: p.service_id, note: p.note || '' }; })); }

    function payState() {
        var g = guest();
        document.getElementById('pr_outside').hidden = g;
        document.getElementById('pr_guest').hidden = !g;
        var room = form.querySelector('input[name=pay][value=room]');
        room.disabled = !(g && room.getAttribute('data-room') === '1');
        if (room.disabled && room.checked) { form.querySelector('input[name=pay][value=' + (S.rule === 'any' ? 'later' : 'all') + ']').checked = true; }
        var pay = (form.querySelector('input[name=pay]:checked') || {}).value;
        var t = totals();
        document.getElementById('pr_money').hidden = pay === 'later' || pay === 'room';
        document.getElementById('pr_amount_f').hidden = pay !== 'part';
        var amt = document.getElementById('pr_amount');
        if (pay === 'part' && !amt.value) amt.value = Math.round(t.total / 2);
    }

    document.getElementById('pr_tiles').addEventListener('click', function (e) {
        var b = e.target.closest('.pr-tile'); if (!b || b.disabled) return;
        pieces.push({ item_id: b.getAttribute('data-item'), service_id: svc(), note: '' });
        render();
    });
    form.querySelectorAll('input[name=pr_svc], input[name=for], input[name=pay], #pr_express').forEach(function (el) { el.addEventListener('change', render); });
    document.getElementById('pr_ready').addEventListener('input', function () { readyTouched = true; });

    form.addEventListener('submit', function (e) {
        if (form.__go) { form.__go = false; return; }
        e.preventDefault(); e.stopImmediatePropagation(); e.stopPropagation();
        setTimeout(function () { document.getElementById('pr_save').disabled = false; }, 0);
        if (!pieces.length) { if (window.toastr) toastr.error(T.empty); return; }
        var t = totals(), pay = (form.querySelector('input[name=pay]:checked') || {}).value;
        var who = guest() ? (document.getElementById('pr_res').selectedOptions[0] || {}).textContent : (document.getElementById('pr_name').value + ' · ' + document.getElementById('pr_mobile').value);
        var paid = pay === 'room' ? T.room : (pay === 'later' ? T.later : money(pay === 'all' ? t.total : num(document.getElementById('pr_amount').value)));
        var go = function () { form.__go = true; if (form.requestSubmit) form.requestSubmit(); else HTMLFormElement.prototype.submit.call(form); };
        if (!window.PosConfirm || !PosConfirm.enabled('pressing')) { go(); return; }
        PosConfirm.ask({ kind: 'pressing', title: T.check, intro: T.intro, amount: t.total, amountLabel: T.total, yes: T.yes,
            rows: [[T.customer, who, 'strong'], [T.pieces_lbl, String(pieces.length)], [T.ready, (document.getElementById('pr_ready').value || '').replace('T', ' ')], [T.paid, paid, 'strong']],
            check: T.check }, go);
    }, true);

    // after the layout's own scripts (the currency settings) have run
    if (window.jQuery) { jQuery(render); } else { document.addEventListener('DOMContentLoaded', render); }
})();
</script>
@endsection
