@extends('layouts.app')
@section('title', $o->ticket_no)

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PressingController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format($o->business_id, $v);
    $can = fn ($p) => \App\Http\Controllers\PressingController::can($p);
    $flow = ['received', 'washing', 'ironing', 'ready', 'collected'];
    $at = $o->events->groupBy('status')->map->last();
    $pos = array_search($o->status, $flow);
    $open = $o->isOpen();
@endphp
<section class="content ht" id="pr_order" data-status="{{ $o->status }}">
    <div class="ht-head">
        <div>
            <h1>{{ $o->ticket_no }} · {{ optional($o->contact)->name }} @if ($o->express)<span class="ht-pill" style="background:#fef0c7;color:#93370d">{{ __('pressing.express_up') }}</span>@endif</h1>
            <p>{{ trans_choice('pressing.n_pieces', $o->pieces->count(), ['n' => $o->pieces->count()]) }} · {{ __('pressing.dropped', ['when' => $o->received_at->format('d/m/Y H:i')]) }} · {{ __('pressing.ready_on', ['when' => $o->ready_at->format('d/m/Y H:i')]) }}
                @if ($o->reservation) · {{ __('pressing.room', ['n' => optional($o->reservation->room)->number]) }} ({{ $o->reservation->ref_no }})@endif
                @if ($o->phone) · {{ $o->phone }}@endif</p>
        </div>
        <div class="ht-actions">
            <a class="ht-btn" href="{{ action([$P, 'ticket'], [$o->id]) }}" target="_blank" id="pr_print">{{ __('pressing.print_ticket') }}</a>
            @if ($o->status === 'ready' && $can('pressing.counter'))
                <a class="ht-btn pri" href="{{ action([$P, 'collectForm'], [$o->id]) }}" id="pr_to_collect">{{ __('pressing.menu_collect') }}</a>
            @endif
            <a class="ht-btn" href="{{ action([$P, 'index']) }}">{{ __('pressing.back_board') }}</a>
        </div>
    </div>

    @if ($o->status === 'cancelled')
        <div class="ht-note red">{{ __('pressing.is_cancelled', ['why' => optional($at->get('cancelled'))->note]) }}</div>
    @endif

    <div class="ht-card">
        <h2>{{ __('pressing.where') }}</h2>
        <div class="pr-flow">
            @foreach ($flow as $i => $st)
                @php $e = $at->get($st); $done = $pos !== false && $i <= $pos && $o->status !== 'cancelled'; @endphp
                <div class="s {{ $done ? 'done' : '' }} {{ $o->status === $st ? 'now' : '' }}"><i></i><strong>{{ __('pressing.st_'.$st) }}</strong>
                    <span class="ht-muted ht-small">{{ $e ? $e->created_at->format('d/m H:i').' · '.optional($e->user)->first_name : '—' }}</span></div>
            @endforeach
        </div>
        @if (in_array($o->status, ['received', 'washing', 'ironing', 'ready']) && ($can('pressing.workshop') || $can('pressing.counter')))
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;align-items:center">
                @foreach (['received', 'washing', 'ironing'] as $st)
                    @if ($o->status !== $st)
                        <form method="post" action="{{ action([$P, 'stage'], [$o->id]) }}">@csrf<input type="hidden" name="status" value="{{ $st }}"><button class="ht-btn sm" type="submit">{{ __('pressing.move_to', ['stage' => __('pressing.st_'.$st)]) }}</button></form>
                    @endif
                @endforeach
            </div>
            @if ($o->status !== 'ready')
                <div class="ht-note green" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-top:12px">
                    <form method="post" action="{{ action([$P, 'ready'], [$o->id]) }}">@csrf<button class="ht-btn ok" type="submit" id="pr_ready" style="min-height:46px">{{ __('pressing.mark_ready') }}</button></form>
                    <span style="flex:1 1 320px">{{ $o->whatsapp && $o->phone ? ($auto['ready'] ? __('pressing.mark_ready_auto', ['phone' => $o->phone]) : __('pressing.mark_ready_link')) : __('pressing.mark_ready_none') }}</span>
                </div>
            @endif
        @endif
        @if ($o->status === 'ready' && $o->whatsapp && $links['ready'])
            <div class="ht-note blue" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px">
                <span style="flex:1 1 300px">{{ __('pressing.ready_since', ['when' => optional($o->ready_marked_at)->format('d/m H:i')]) }}. {{ $messages->where('kind', 'pressing_ready')->last() ? __('pressing.wa_state', ['state' => __('pressing.wa_'.$messages->where('kind', 'pressing_ready')->last()->status)]) : __('pressing.wa_not_auto') }}</span>
                <a class="ht-btn sm" href="{{ $links['ready'] }}" target="_blank" rel="noopener" id="pr_wa_link">{{ __('pressing.send_wa') }}</a>
                @if ($auto['ready'])
                    <form method="post" action="{{ action([$P, 'notify'], [$o->id]) }}">@csrf<input type="hidden" name="kind" value="ready"><button class="ht-btn sm" type="submit">{{ __('pressing.resend') }}</button></form>
                @endif
            </div>
        @endif
    </div>

    <div class="ht-grid" style="grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr); align-items: start">
        <div class="ht-card">
            <h2>{{ __('pressing.pieces') }}</h2>
            <div class="ht-scroll">
                <table class="ht-t">
                    <thead><tr><th>{{ __('pressing.tag') }}</th><th>{{ __('pressing.item') }}</th><th>{{ __('pressing.service') }}</th><th>{{ __('pressing.note_drop') }}</th><th class="num">{{ __('pressing.price') }}</th>@if ($o->status === 'collected')<th>{{ __('pressing.handed') }}</th>@endif</tr></thead>
                    <tbody>
                        @foreach ($o->pieces as $p)
                            <tr data-tag="{{ $p->tag }}"><td><strong>#{{ $p->tag }}</strong></td><td>{{ optional($p->item)->name }}</td><td>{{ optional($p->service)->name }}</td>
                                <td>@if ($p->note)<span style="color:#b54708">{{ $p->note }}</span>@else<span class="ht-muted">—</span>@endif</td><td class="num">{{ $fmt($p->price) }}</td>
                                @if ($o->status === 'collected')<td>{{ $p->handed_over ? '✓' : '' }}</td>@endif</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($o->note)<p class="ht-muted" style="margin-top:10px">{{ __('pressing.order_note') }}: {{ $o->note }}</p>@endif
        </div>

        <div>
            <div class="ht-card" id="pr_money">
                <h2>{{ __('pressing.money') }}</h2>
                <div class="ht-row"><span>{{ __('pressing.total') }}</span><strong>{{ $fmt($m['total']) }}</strong></div>
                <div class="ht-row"><span>{{ __('pressing.paid_lbl') }}</span><span>{{ $fmt($m['paid']) }}</span></div>
                @if ($o->on_room_bill)
                    <div class="ht-row"><span>{{ __('pressing.on_room_bill') }}</span><strong>{{ $fmt($m['due']) }}</strong></div>
                    <p class="ht-muted ht-small">{{ __('pressing.room_bill_note') }}</p>
                @else
                    <div class="ht-row"><span>{{ __('pressing.to_pay') }}</span><strong id="pr_due">{{ $fmt($m['due']) }}</strong></div>
                @endif
                @if ($o->transaction_id && auth()->user()->can('sell.view'))<a class="ht-link ht-small" href="{{ url('/sells/'.$o->transaction_id) }}" target="_blank">{{ __('pressing.see_sale') }}</a>@endif
                @if ($m['counter_due'] > 0 && $open && $can('pressing.counter'))
                    <form method="post" action="{{ action([$P, 'pay'], [$o->id]) }}" style="margin-top:12px">@csrf
                        <div class="ht-fields">
                            <div class="ht-f"><label for="pr_pay_amt">{{ __('pressing.amount') }}</label><input id="pr_pay_amt" name="amount" class="ht-in" value="{{ round($m['counter_due']) }}"></div>
                            <div class="ht-f"><label for="pr_pay_m">{{ __('pressing.method') }}</label><select id="pr_pay_m" name="method" class="ht-in">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                        </div>
                        <button class="ht-btn" type="submit">{{ __('pressing.take_payment') }}</button>
                    </form>
                @endif
                <p class="ht-muted ht-small" style="margin-top:8px">{{ __('pressing.ordinary_sale') }}</p>
            </div>

            <div class="ht-card">
                <h2>{{ __('pressing.messages') }}</h2>
                @forelse ($messages as $msg)
                    <div class="ht-row"><span>{{ __('pressing.msg_'.str_replace('pressing_', '', $msg->kind)) }}</span><span class="ht-small">{{ __('pressing.wa_'.$msg->status) }} · {{ $msg->created_at->format('d/m H:i') }}</span></div>
                @empty
                    <p class="ht-muted ht-small">{{ $o->whatsapp ? __('pressing.no_messages') : __('pressing.wa_off') }}</p>
                @endforelse
                @if ($o->whatsapp && $links['received'])<a class="ht-link ht-small" href="{{ $links['received'] }}" target="_blank" rel="noopener">{{ __('pressing.send_ticket_wa') }}</a>@endif
            </div>
        </div>
    </div>

    <div class="ht-card" id="pr_problems">
        <h2>{{ __('pressing.problems') }}</h2>
        @foreach ($o->problems as $pb)
            <div class="ht-row"><span><strong>{{ __('pressing.kind_'.$pb->kind) }}</strong>@if ($pb->piece) · #{{ $pb->piece->tag }} {{ optional($pb->piece->item)->name }}@endif · {{ $pb->description }}</span>
                <span>{{ __('pressing.out_'.$pb->outcome) }}@if ($pb->amount > 0) · {{ $fmt($pb->amount) }}@endif · <span class="ht-muted ht-small">{{ $pb->created_at->format('d/m H:i') }}</span></span></div>
        @endforeach
        @if ($can('pressing.counter') && $o->status !== 'cancelled')
            <details @if ($o->problems->isEmpty()) @endif>
                <summary class="ht-link" style="cursor:pointer;margin:6px 0">{{ __('pressing.report_problem') }}</summary>
                <form method="post" action="{{ action([$P, 'problem'], [$o->id]) }}" style="margin-top:10px">@csrf
                    <div class="ht-fields">
                        <div class="ht-f"><label for="pb_kind">{{ __('pressing.problem_kind') }}</label><select id="pb_kind" name="kind" class="ht-in">@foreach (\App\PressingProblem::KINDS as $k)<option value="{{ $k }}">{{ __('pressing.kind_'.$k) }}</option>@endforeach</select></div>
                        <div class="ht-f"><label for="pb_piece">{{ __('pressing.piece') }}</label><select id="pb_piece" name="piece_id" class="ht-in"><option value="">{{ __('pressing.whole_order') }}</option>@foreach ($o->pieces as $p)<option value="{{ $p->id }}">#{{ $p->tag }} {{ optional($p->item)->name }}</option>@endforeach</select></div>
                        <div class="ht-f"><label for="pb_out">{{ __('pressing.agreed') }}</label><select id="pb_out" name="outcome" class="ht-in">@foreach (\App\PressingProblem::OUTCOMES as $k)<option value="{{ $k }}">{{ __('pressing.out_'.$k) }}</option>@endforeach</select></div>
                        <div class="ht-f"><label for="pb_amt">{{ __('pressing.refund_amount') }}</label><input id="pb_amt" name="amount" class="ht-in"><span class="ht-muted ht-small">{{ __('pressing.refund_hint') }}</span></div>
                    </div>
                    <div class="ht-f"><label for="pb_desc">{{ __('pressing.what_happened') }} *</label><textarea id="pb_desc" name="description" class="ht-in" rows="2"></textarea></div>
                    <button class="ht-btn danger" type="submit">{{ __('pressing.save_problem') }}</button>
                </form>
            </details>
        @endif
        @if ($o->problems->isEmpty() && ! $can('pressing.counter'))<p class="ht-muted ht-small">{{ __('pressing.no_problems') }}</p>@endif
    </div>

    @if ($open && $m['paid'] < 0.005 && ! $o->on_room_bill && $can('pressing.counter'))
        <details class="ht-card">
            <summary class="ht-link" style="cursor:pointer">{{ __('pressing.cancel_order') }}</summary>
            <form method="post" action="{{ action([$P, 'cancel'], [$o->id]) }}" style="margin-top:10px">@csrf
                <div class="ht-f"><label for="pr_reason">{{ __('pressing.cancel_reason') }} *</label><input id="pr_reason" name="reason" class="ht-in"></div>
                <button class="ht-btn danger" type="submit">{{ __('pressing.cancel_btn') }}</button>
            </form>
        </details>
    @endif
</section>
@endsection

@section('javascript')
@if ($print)
<script>window.open({!! json_encode(action([\App\Http\Controllers\PressingController::class, 'ticket'], [$o->id])) !!}, '_blank');</script>
@endif
@endsection
