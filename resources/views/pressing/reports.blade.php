@extends('layouts.app')
@section('title', __('pressing.menu_reports'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PressingController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
@endphp
<section class="content ht" id="pr_reports">
    <div class="ht-head"><div><h1>{{ __('pressing.menu_reports') }}</h1><p>{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</p></div>
        <form method="get" class="ht-actions" style="align-items:flex-end">
            <div class="ht-f" style="margin:0"><label for="r_from">{{ __('pressing.from') }}</label><input id="r_from" type="date" name="from" class="ht-in" value="{{ $from->toDateString() }}"></div>
            <div class="ht-f" style="margin:0"><label for="r_to">{{ __('pressing.to') }}</label><input id="r_to" type="date" name="to" class="ht-in" value="{{ $to->toDateString() }}"></div>
            <button class="ht-btn" type="submit">{{ __('pressing.show') }}</button>
        </form>
    </div>
    <div class="ht-kpis">
        <div class="ht-kpi"><div class="l">{{ __('pressing.r_orders') }}</div><div class="v">{{ $r['orders'] }}</div><div class="s">{{ trans_choice('pressing.n_pieces', $r['pieces'], ['n' => $r['pieces']]) }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('pressing.r_sales') }}</div><div class="v">{{ $fmt($r['sales']) }}</div><div class="s">{{ __('pressing.r_split', ['out' => $fmt($r['sales_outside']), 'guests' => $fmt($r['sales_guests'])]) }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('pressing.r_due') }}</div><div class="v">{{ $fmt($r['due']) }}</div><div class="s">{{ __('pressing.r_due_s') }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('pressing.r_on_time') }}</div><div class="v">{{ $r['on_time'] === null ? '—' : $r['on_time'].' %' }}</div><div class="s">{{ __('pressing.r_on_time_s') }}</div></div>
    </div>
    <div class="ht-grid ht-g2">
        <div class="ht-card"><h2>{{ __('pressing.r_by_line') }}</h2><p class="ht-muted ht-small">{{ __('pressing.r_by_line_s') }}</p>
            <div class="ht-scroll"><table class="ht-t"><thead><tr><th>{{ __('pressing.item') }} · {{ __('pressing.service') }}</th><th class="num">{{ __('pressing.pieces') }}</th><th class="num">{{ __('pressing.amount') }}</th></tr></thead><tbody>
                @forelse ($r['by_line'] as $l)<tr><td>{{ $l['item'] }} · {{ $l['service'] }}</td><td class="num">{{ $l['pieces'] }}</td><td class="num">{{ $fmt($l['amount']) }}</td></tr>
                @empty<tr><td colspan="3" class="ht-muted">{{ __('pressing.nothing') }}</td></tr>@endforelse
            </tbody></table></div></div>
        <div class="ht-card"><h2>{{ __('pressing.r_left') }}</h2><p class="ht-muted ht-small">{{ __('pressing.r_left_s') }}</p>
            @forelse ($r['left_over'] as $o)
                <div class="ht-row"><a class="ht-link" href="{{ action([$P, 'show'], [$o->id]) }}">{{ $o->ticket_no }} · {{ optional($o->contact)->name }} · {{ trans_choice('pressing.n_pieces', $o->pieces_count, ['n' => $o->pieces_count]) }}</a>
                    <span>{{ __('pressing.days_waiting', ['date' => optional($o->ready_marked_at)->format('d/m'), 'n' => (int) optional($o->ready_marked_at)->diffInDays(now())]) }}</span></div>
            @empty<p class="ht-muted ht-small">{{ __('pressing.nothing') }}</p>@endforelse
        </div>
    </div>
    <div class="ht-card"><h2>{{ __('pressing.problems') }}</h2><p class="ht-muted ht-small">{{ __('pressing.r_problems_s') }}</p>
        <div class="ht-scroll"><table class="ht-t"><thead><tr><th>{{ __('pressing.date') }}</th><th>{{ __('pressing.ticket') }}</th><th>{{ __('pressing.problem_kind') }}</th><th>{{ __('pressing.what_happened') }}</th><th>{{ __('pressing.agreed') }}</th><th>{{ __('pressing.by') }}</th></tr></thead><tbody>
            @forelse ($r['problems'] as $pb)
                <tr><td>{{ $pb->created_at->format('d/m') }}</td><td>@if ($pb->order)<a class="ht-link" href="{{ action([$P, 'show'], [$pb->order->id]) }}">{{ $pb->order->ticket_no }}</a>@endif @if ($pb->piece) #{{ $pb->piece->tag }} {{ optional($pb->piece->item)->name }}@endif</td>
                    <td>{{ __('pressing.kind_'.$pb->kind) }}</td><td>{{ $pb->description }}</td><td>{{ __('pressing.out_'.$pb->outcome) }}@if ($pb->amount > 0) · {{ $fmt($pb->amount) }}@endif</td><td>{{ optional($pb->user)->first_name }}</td></tr>
            @empty<tr><td colspan="6" class="ht-muted">{{ __('pressing.nothing') }}</td></tr>@endforelse
        </tbody></table></div></div>
</section>
@endsection
