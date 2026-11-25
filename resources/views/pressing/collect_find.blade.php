@extends('layouts.app')
@section('title', __('pressing.menu_collect'))

@section('content')
@include('pressing.partials.styles')
@php
    $P = \App\Http\Controllers\PressingController::class;
    $fmt = fn ($v) => \App\Utils\Pressing::format(session('business.id'), $v);
@endphp
<section class="content ht" id="pr_collect_find">
    <div class="ht-head"><div><h1>{{ __('pressing.menu_collect') }}</h1><p>{{ __('pressing.collect_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$P, 'index']) }}">{{ __('pressing.back_board') }}</a></div></div>
    <form method="get" action="{{ action([$P, 'collectFind']) }}" class="ht-card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div class="ht-f" style="flex:1 1 320px;margin:0"><label for="pr_cq">{{ __('pressing.find_ticket') }}</label><input id="pr_cq" name="q" class="ht-in" value="{{ $q }}" autofocus placeholder="{{ __('pressing.find_ph') }}" style="font-size:18px;min-height:52px"></div>
        <button class="ht-btn pri" type="submit" style="min-height:52px">{{ __('pressing.find') }}</button>
    </form>
    @if ($found !== null)
        <div class="ht-card"><h2>{{ __('pressing.found', ['n' => $found->count(), 'q' => $q]) }}</h2>
            @forelse ($found as $o)
                <div class="ht-row"><a class="ht-link" href="{{ action([$P, $o->status === 'ready' ? 'collectForm' : 'show'], [$o->id]) }}">{{ $o->ticket_no }} · {{ optional($o->contact)->name }}</a><span>{{ __('pressing.st_'.$o->status) }}</span></div>
            @empty
                <p class="ht-muted">{{ __('pressing.found_none') }}</p>
            @endforelse
        </div>
    @endif
    <div class="ht-card"><h2>{{ __('pressing.ready_waiting', ['n' => $ready->count()]) }}</h2>
        <div class="ht-scroll"><table class="ht-t"><thead><tr><th>{{ __('pressing.ticket') }}</th><th>{{ __('pressing.customer') }}</th><th>{{ __('pressing.pieces') }}</th><th>{{ __('pressing.ready_since_lbl') }}</th><th class="num">{{ __('pressing.to_pay') }}</th></tr></thead><tbody>
            @forelse ($ready as $o)
                <tr><td><a class="ht-link" href="{{ action([$P, 'collectForm'], [$o->id]) }}">{{ $o->ticket_no }}</a></td><td>{{ optional($o->contact)->name }}</td><td>{{ $o->pieces_count }}</td><td>{{ optional($o->ready_marked_at)->format('d/m H:i') }}</td>
                    <td class="num">{{ $o->on_room_bill ? __('pressing.on_room_bill') : $fmt(\App\Utils\Pressing::money($o)['due']) }}</td></tr>
            @empty
                <tr><td colspan="5" class="ht-muted">{{ __('pressing.none_ready') }}</td></tr>
            @endforelse
        </tbody></table></div>
    </div>
</section>
@endsection
