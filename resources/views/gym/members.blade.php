@extends('layouts.app')
@section('title', __('gym.menu_members'))

@section('content')
@include('pressing.partials.styles')
@php
    $G = \App\Http\Controllers\GymController::class;
    $pill = ['valid' => 'free', 'expiring' => 'dirty', 'expired' => 'bad', 'future' => 'occ', 'none' => 'checked_out'];
@endphp
<section class="content ht" id="gy_members">
    <div class="ht-head"><div><h1>{{ __('gym.menu_members') }}</h1><p>{{ __('gym.members_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$G, 'door']) }}">{{ __('gym.menu_door') }}</a>
            @if (\App\Http\Controllers\GymController::can('gym.members'))<a class="ht-btn pri" href="{{ action([$G, 'create']) }}">+ {{ __('gym.menu_new') }}</a>@endif</div></div>
    <div class="ht-kpis">
        <div class="ht-kpi"><div class="l">{{ __('gym.k_active') }}</div><div class="v" style="color:#067647">{{ $active }}</div></div>
        <div class="ht-kpi"><div class="l" style="color:#b54708">{{ __('gym.k_expiring') }}</div><div class="v" style="color:#b54708">{{ $expiring }}</div><div class="s">{{ __('gym.k_expiring_s') }}</div></div>
        <div class="ht-kpi"><div class="l" style="color:#b42318">{{ __('gym.k_expired') }}</div><div class="v" style="color:#b42318">{{ $expired_month }}</div><div class="s">{{ __('gym.k_expired_s') }}</div></div>
        <div class="ht-kpi"><div class="l">{{ __('gym.k_passes') }}</div><div class="v">{{ $passes_month }}</div></div>
    </div>
    <div class="pr-chips">
        @foreach (['all' => __('gym.f_all'), 'expiring' => __('gym.f_expiring'), 'expired' => __('gym.f_expired')] as $k => $l)
            <a class="pr-chip {{ $filter === $k ? 'on' : '' }}" href="{{ action([$G, 'members'], $k === 'all' ? [] : ['f' => $k]) }}">{{ $l }}</a>
        @endforeach
    </div>
    <div class="ht-card">
        <div class="ht-scroll"><table class="ht-t" style="min-width:760px">
            <thead><tr><th>{{ __('gym.member') }}</th><th>{{ __('gym.plan') }}</th><th>{{ __('gym.ends') }}</th><th class="num">{{ __('gym.visits30') }}</th><th>{{ __('gym.state') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($list as $m)
                    <tr data-member="{{ $m->member_no }}">
                        <td><a class="ht-link" href="{{ action([$G, 'show'], [$m->id]) }}">{{ optional($m->contact)->name }}</a><div class="ht-muted ht-small">{{ $m->member_no }} · {{ $m->phone }}</div></td>
                        <td>{{ optional(optional($m->last)->plan)->name ?: '—' }}</td>
                        <td>{{ $m->last ? ($m->last->visits_left !== null ? trans_choice('gym.visits_left', $m->last->visits_left, ['n' => $m->last->visits_left]) : $m->last->end_date->format('d/m/Y')) : '—' }}</td>
                        <td class="num">{{ $m->visits30 }}</td>
                        <td><span class="ht-pill ht-p-{{ $pill[$m->state] }}">{{ __('gym.state_'.$m->state) }}</span></td>
                        <td style="text-align:right">@if (! empty($links[$m->id]))<a class="ht-btn sm" href="{{ $links[$m->id] }}" target="_blank" rel="noopener">{{ __('gym.send_wa') }}</a>@endif
                            <a class="ht-btn sm" href="{{ action([$G, 'show'], [$m->id]) }}">{{ in_array($m->state, ['expiring', 'expired']) ? __('gym.renew') : __('gym.open') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ht-muted">{{ __('gym.no_members') }}</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <p class="ht-muted ht-small">{{ __('gym.renew_rule') }}</p>
    </div>
</section>
@endsection
