@extends('layouts.app')
@section('title', __('pressing.menu_board'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PressingController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
    $cols = ['received' => __('pressing.st_received'), 'washing' => __('pressing.st_washing'), 'ironing' => __('pressing.st_ironing'), 'ready' => __('pressing.st_ready')];
@endphp
<section class="content ht" id="pr_board">
    <div class="ht-head">
        <div><h1>{{ __('pressing.module') }}</h1><p>{{ __('pressing.board_lead') }}</p></div>
        <div class="ht-actions">
            <form method="get" action="{{ action([$P, 'index']) }}" style="display:flex;gap:8px">
                <label for="pr_q" class="sr-only">{{ __('pressing.find') }}</label>
                <input id="pr_q" name="q" class="ht-in" value="{{ $q }}" placeholder="{{ __('pressing.find_ph') }}" style="width:260px">
                <button class="ht-btn" type="submit">{{ __('pressing.find') }}</button>
            </form>
            @if (\App\Http\Controllers\PressingController::can('pressing.counter'))
                <a class="ht-btn" href="{{ action([$P, 'collectFind']) }}">{{ __('pressing.menu_collect') }}</a>
                <a class="ht-btn pri" href="{{ action([$P, 'create']) }}" id="pr_new">+ {{ __('pressing.menu_new') }}</a>
            @endif
        </div>
    </div>

    @if ($needsSetup)
        <a class="ht-note amber" href="{{ action([\App\Http\Controllers\PressingSetupController::class, 'index']) }}">{{ __('pressing.needs_setup') }}</a>
    @endif

    @if ($found !== null)
        <div class="ht-card">
            <h2>{{ __('pressing.found', ['n' => $found->count(), 'q' => $q]) }}</h2>
            @forelse ($found as $o)
                <div class="ht-row"><a class="ht-link" href="{{ action([$P, 'show'], [$o->id]) }}">{{ $o->ticket_no }} · {{ optional($o->contact)->name }}</a>
                    <span><span class="ht-pill ht-p-{{ $o->status === 'ready' ? 'free' : ($o->status === 'collected' ? 'checked_out' : 'occ') }}">{{ __('pressing.st_'.$o->status) }}</span> <span class="ht-muted">{{ $o->received_at->format('d/m/Y') }}</span></span></div>
            @empty
                <p class="ht-muted">{{ __('pressing.found_none') }}</p>
            @endforelse
        </div>
    @endif

    <div class="ht-kpis">
        <div class="ht-kpi"><div class="l">{{ __('pressing.k_today') }}</div><div class="v">{{ $board['today_orders'] }}</div><div class="s">{{ __('pressing.k_today_s', ['pieces' => $board['today_pieces'], 'amount' => $fmt($board['today_total'])]) }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('pressing.k_work') }}</div><div class="v">{{ $board['in_work'] }}</div><div class="s">{{ __('pressing.k_work_s') }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('pressing.k_ready') }}</div><div class="v" style="color:#067647">{{ $board['ready'] }}</div><div class="s">{{ __('pressing.k_ready_s') }}</div></div>
        <div class="ht-kpi"><div class="l" style="color:#b42318">{{ __('pressing.k_late') }}</div><div class="v" style="color:#b42318">{{ $board['late'] }}</div><div class="s">{{ __('pressing.k_late_s') }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('pressing.k_left') }}</div><div class="v" style="color:#b54708">{{ $board['left_over'] }}</div><div class="s">{{ __('pressing.k_left_s') }}</div></div>
    </div>

    <div class="pr-chips">
        @foreach (['all' => __('pressing.f_all'), 'express' => __('pressing.f_express')] + ($hotel ? ['guests' => __('pressing.f_guests'), 'outside' => __('pressing.f_outside')] : []) as $k => $label)
            <a class="pr-chip {{ $filter === $k ? 'on' : '' }}" href="{{ action([$P, 'index'], $k === 'all' ? [] : ['f' => $k]) }}">{{ $label }}</a>
        @endforeach
    </div>

    <div class="pr-cols">
        @foreach ($cols as $st => $label)
            <div class="pr-col" data-col="{{ $st }}">
                <h3>{{ $label }} <span>{{ $board['columns'][$st]->count() }}</span></h3>
                <div class="pr-cards">
                    @forelse ($board['columns'][$st] as $o)
                        @php
                            $msg = $messages[$o->id] ?? null;
                            $mm = \App\Utils\Pressing::money($o);
                            $moneyLine = $o->on_room_bill ? __('pressing.on_room_bill') : ($mm['due'] > 0 ? __('pressing.due_short', ['amount' => $fmt($mm['due'])]) : __('pressing.paid_short'));
                        @endphp
                        <a class="pr-card {{ $o->isLate() ? 'late' : '' }}" href="{{ action([$P, 'show'], [$o->id]) }}" data-ticket="{{ $o->ticket_no }}">
                            <div class="bd">
                                <div class="tk"><strong>{{ $o->ticket_no }}</strong>
                                    @if ($o->isLate())
                                        <span class="pr-tag late">{{ __('pressing.late_by', ['n' => max(1, (int) $o->ready_at->diffInDays(now()))]) }}</span>
                                    @elseif ($o->express)
                                        <span class="pr-tag ex">{{ __('pressing.express_up') }}</span>
                                    @elseif ($st === 'ready')
                                        <span class="pr-tag wa {{ $msg && in_array($msg->status, ['delivered', 'read']) ? '' : 'no' }}">{{ $msg ? __('pressing.wa_'.($msg->status ?: 'queued')) : __('pressing.wa_none') }}</span>
                                    @endif
                                </div>
                                <div class="who">{{ optional($o->contact)->name }}@if ($o->reservation) · {{ __('pressing.room', ['n' => optional($o->reservation->room)->number]) }}@endif</div>
                                <div class="ln"><span>{{ trans_choice('pressing.n_pieces', $o->pieces_count, ['n' => $o->pieces_count]) }}</span><span>{{ $fmt($o->total) }}</span></div>
                                <div class="ln"><span>{{ $st === 'ready' ? __('pressing.ready_since', ['when' => optional($o->ready_marked_at)->format('d/m H:i')]) : __('pressing.ready_on', ['when' => $o->ready_at->format('d/m H:i')]) }}</span>
                                    <span>{{ $moneyLine }}</span></div>
                            </div>
                            <div class="bar pr-st-{{ $st }}"></div>
                        </a>
                    @empty
                        <div class="pr-empty">{{ __('pressing.col_empty') }}</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
    <p class="ht-muted ht-small" style="margin-top:12px">{{ __('pressing.board_key') }}</p>
</section>
@endsection
