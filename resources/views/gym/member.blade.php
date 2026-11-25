@extends('layouts.app')
@section('title', $m->member_no)

@section('content')
@include('pressing.partials.styles')
@php
    $G = \App\Http\Controllers\GymController::class;
    $fmt = fn ($v) => \App\Utils\Gym::format($m->business_id, $v);
    $pill = ['valid' => 'free', 'expiring' => 'dirty', 'expired' => 'bad', 'future' => 'occ', 'none' => 'checked_out'];
@endphp
<section class="content ht" id="gy_member" data-state="{{ $state }}">
    <div class="ht-head">
        <div><h1>{{ optional($m->contact)->name }} <span class="ht-pill ht-p-{{ $pill[$state] }}">{{ __('gym.state_'.$state) }}</span></h1>
            <p>{{ $m->member_no }} · {{ $m->phone }} · {{ __('gym.member_since', ['date' => $m->created_at->format('d/m/Y')]) }}</p></div>
        <div class="ht-actions">
            <a class="ht-btn" href="{{ action([$G, 'card'], [$m->id]) }}" target="_blank" id="gy_card">{{ __('gym.print_card') }}</a>
            <a class="ht-btn" href="{{ action([$G, 'door'], ['q' => $m->member_no]) }}">{{ __('gym.menu_door') }}</a>
        </div>
    </div>
    <div class="ht-grid" style="grid-template-columns: minmax(0, 2fr) minmax(300px, 1fr); align-items: start">
        <div>
            <div class="ht-card">
                <h2>{{ __('gym.memberships') }}</h2>
                <div class="ht-scroll"><table class="ht-t">
                    <thead><tr><th>{{ __('gym.plan') }}</th><th>{{ __('gym.from') }}</th><th>{{ __('gym.to') }}</th><th class="num">{{ __('gym.amount') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($memberships as $ms)
                            <tr><td>{{ optional($ms->plan)->name }}</td><td>{{ $ms->start_date->format('d/m/Y') }}</td><td>{{ $ms->end_date->format('d/m/Y') }}</td><td class="num">{{ $fmt($ms->amount) }}</td>
                                <td>@if ($ms->visits_left !== null){{ trans_choice('gym.visits_left', $ms->visits_left, ['n' => $ms->visits_left]) }}@elseif ($current && $current->id === $ms->id)<span class="ht-pill ht-p-free">{{ __('gym.current') }}</span>@endif</td></tr>
                        @empty
                            <tr><td colspan="5" class="ht-muted">{{ __('gym.no_membership') }}</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
            <div class="ht-card"><h2>{{ __('gym.visits') }}</h2>
                <div class="gy-in-today" style="display:flex;flex-wrap:wrap;gap:8px">
                    @forelse ($visits as $v)<span style="border:1px solid #eaecf0;border-radius:999px;padding:5px 11px;font-size:13px">{{ $v->visited_at->format('d/m H:i') }}</span>@empty<span class="ht-muted">{{ __('gym.no_visits') }}</span>@endforelse
                </div>
            </div>
        </div>
        <div>
            <div class="ht-card" id="gy_renew">
                <h2>{{ __('gym.renew') }}</h2>
                @if ($plans->isEmpty())
                    <p class="ht-muted">{{ __('gym.needs_setup') }}</p>
                @else
                <form method="post" action="{{ action([$G, 'renew'], [$m->id]) }}">@csrf
                    <div class="ht-f"><label for="gr_plan">{{ __('gym.plan') }}</label><select id="gr_plan" name="plan_id" class="ht-in">@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} · {{ $fmt($p->price) }}</option>@endforeach</select></div>
                    <div class="ht-f"><label for="gr_method">{{ __('gym.method') }}</label><select id="gr_method" name="method" class="ht-in">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <button class="ht-btn pri" type="submit" style="width:100%">{{ __('gym.renew_pay') }}</button>
                </form>
                <p class="ht-muted ht-small">{{ __('gym.renew_rule') }}</p>
                @endif
            </div>
            <div class="ht-card"><h2>{{ __('gym.messages') }}</h2>
                @forelse ($messages as $msg)
                    <div class="ht-row"><span>{{ __('gym.msg_'.str_replace('gym_', '', $msg->kind)) }}</span><span class="ht-small">{{ __('pressing.wa_'.$msg->status) }} · {{ $msg->created_at->format('d/m H:i') }}</span></div>
                @empty
                    <p class="ht-muted ht-small">{{ $m->whatsapp ? __('gym.no_messages') : __('gym.wa_off') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
