@extends('layouts.app')
@section('title', __('pressing.menu_collect').' · '.$o->ticket_no)

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PressingController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format($o->business_id, $v);
@endphp
<section class="content ht" id="pr_collect">
    <div class="ht-head"><div><h1>{{ __('pressing.menu_collect') }} · {{ $o->ticket_no }} · {{ optional($o->contact)->name }}</h1>
        <p>{{ $o->status === 'ready' ? __('pressing.ready_since', ['when' => optional($o->ready_marked_at)->format('d/m H:i')]) : __('pressing.st_'.$o->status) }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'show'], [$o->id]) }}">{{ __('pressing.open_order') }}</a></div></div>

    @if ($o->status !== 'ready')
        <div class="ht-note amber">{{ $o->status === 'collected' ? __('pressing.err_collected') : __('pressing.err_not_ready') }}</div>
    @else
    <form method="post" action="{{ action([$P, 'collect'], [$o->id]) }}" id="pr_collect_form">@csrf
        <div class="ht-grid" style="grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr); align-items: start">
            <div class="ht-card">
                <h2>{{ __('pressing.tick_pieces') }}</h2>
                <p class="ht-muted ht-small">{{ __('pressing.tick_hint') }}</p>
                @foreach ($o->pieces as $p)
                    <label class="ht-row" style="justify-content:flex-start;gap:12px;align-items:center;min-height:44px;border:1px solid #eaecf0;border-radius:8px;padding:6px 12px;margin-bottom:6px;cursor:pointer">
                        <input type="checkbox" name="handed[]" value="{{ $p->id }}" class="pr-hand" style="width:20px;height:20px">
                        <strong>#{{ $p->tag }}</strong> {{ optional($p->item)->name }} · {{ optional($p->service)->name }}
                        @if ($p->note)<span style="color:#b54708" class="ht-small">{{ $p->note }}</span>@endif
                    </label>
                @endforeach
                <p class="ht-small" style="margin-top:10px"><a class="ht-link" href="{{ action([$P, 'show'], [$o->id]) }}#pr_problems">{{ __('pressing.missing_damaged') }}</a></p>
            </div>
            <div class="ht-card">
                <h2>{{ __('pressing.to_pay') }}</h2>
                <div class="ht-row"><span>{{ __('pressing.total') }}</span><span>{{ $fmt($m['total']) }}</span></div>
                <div class="ht-row"><span>{{ __('pressing.paid_lbl') }}</span><span>{{ $fmt($m['paid']) }}</span></div>
                @if ($o->on_room_bill)
                    <div class="ht-row"><span>{{ __('pressing.on_room_bill') }}</span><strong>{{ $fmt($m['due']) }}</strong></div>
                    <input type="hidden" name="amount" value="0">
                @else
                    <div class="ht-row" style="font-size:20px"><strong>{{ __('pressing.balance') }}</strong><strong id="pr_bal">{{ $fmt($m['counter_due']) }}</strong></div>
                    <input type="hidden" name="amount" value="{{ $m['counter_due'] }}">
                    @if ($m['counter_due'] > 0)
                        <div class="ht-f"><label for="pr_cm">{{ __('pressing.method') }}</label><select id="pr_cm" name="method" class="ht-in">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    @endif
                @endif
                <div class="ht-f"><label for="pr_by">{{ __('pressing.collected_by') }}</label><input id="pr_by" name="collected_by" class="ht-in" value="{{ optional($o->contact)->name }}"><span class="ht-muted ht-small">{{ __('pressing.collected_by_hint') }}</span></div>
                <button type="submit" class="ht-btn ok" id="pr_hand_over" style="width:100%;min-height:50px" disabled>{{ $m['counter_due'] > 0 ? __('pressing.take_and_hand', ['amount' => $fmt($m['counter_due'])]) : __('pressing.hand_over') }}</button>
                <p class="ht-muted ht-small">{{ __('pressing.hand_hint') }}</p>
            </div>
        </div>
    </form>
    @endif
</section>
@endsection

@section('javascript')
<script>
(function () {
    var boxes = document.querySelectorAll('.pr-hand'), btn = document.getElementById('pr_hand_over');
    if (!btn) return;
    var upd = function () { btn.disabled = Array.prototype.some.call(boxes, function (b) { return !b.checked; }); };
    boxes.forEach(function (b) { b.addEventListener('change', upd); });
    upd();
})();
</script>
@endsection
