@extends('layouts.app')
@section('title', __('gym.menu_door'))

@section('content')
@include('pressing.partials.styles')
@php
    $G = \App\Http\Controllers\GymController::class;
    $fmt = fn ($v) => \App\Utils\Gym::format(session('business.id'), $v);
    $can = fn ($p) => \App\Http\Controllers\GymController::can($p);
@endphp
<style>
.gy-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(290px, 1fr)); gap: 14px; margin-bottom: 16px; }
.gy-card { background: #1d2939; color: #f2f4f7; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 1px 3px rgba(16,24,40,.25); }
.gy-card .bd { padding: 16px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
.gy-card .who { display: flex; gap: 12px; align-items: center; }
.gy-card .av { width: 54px; height: 54px; border-radius: 50%; background: #344054; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; flex: none; }
.gy-card .nm { font-size: 17px; font-weight: 700; }
.gy-card .sub { font-size: 13px; color: #d0d5dd; }
.gy-card .st { font-size: 15px; font-weight: 700; }
.gy-card .st.ok { color: #6ce9a6; } .gy-card .st.bad { color: #fda29b; } .gy-card .st.warn { color: #fec84b; } .gy-card .st.guest { color: #84caff; }
.gy-card .bar { height: 8px; }
.gy-card .acts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: auto; }
.gy-card .acts form { flex: 1 1 120px; display: flex; gap: 6px; flex-wrap: wrap; }
.gy-card .btn-in { flex: 1; min-height: 48px; border-radius: 8px; border: 0; font: 700 15px inherit; cursor: pointer; }
.gy-card select { min-height: 40px; border-radius: 8px; border: 1px solid #667085; background: #101828; color: #f2f4f7; padding: 0 8px; flex: 1 1 100%; }
.gy-in-today { display: flex; flex-wrap: wrap; gap: 8px; }
.gy-in-today span { border: 1px solid #eaecf0; border-radius: 999px; padding: 6px 12px; font-size: 13px; background: #fff; }
</style>
<section class="content ht" id="gy_door">
    <div class="ht-head">
        <div><h1>{{ __('gym.door_title') }}</h1><p>{{ __('gym.door_lead', ['visits' => $today->count(), 'active' => $counts['active'], 'expiring' => $counts['expiring']]) }}</p></div>
        <div class="ht-actions">
            <a class="ht-btn" href="{{ action([$G, 'members']) }}">{{ __('gym.menu_members') }}</a>
            @if ($can('gym.members'))<a class="ht-btn pri" href="{{ action([$G, 'create']) }}">+ {{ __('gym.menu_new') }}</a>@endif
        </div>
    </div>

    @if ($needsSetup)
        <a class="ht-note amber" href="{{ action([\App\Http\Controllers\GymSetupController::class, 'index']) }}">{{ __('gym.needs_setup') }}</a>
    @endif

    <form method="get" action="{{ action([$G, 'door']) }}" class="ht-card" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div class="ht-f" style="flex:1 1 340px;margin:0"><label for="gy_q">{{ __('gym.find_label') }}</label>
            <input id="gy_q" name="q" class="ht-in" value="{{ $q }}" autofocus placeholder="{{ __('gym.find_ph') }}" style="font-size:18px;min-height:56px"></div>
        <button class="ht-btn pri" type="submit" style="min-height:56px">{{ __('gym.find') }}</button>
    </form>

    @if ($found !== null)
        <div class="gy-cards" id="gy_found">
            @foreach ($found['members'] as $m)
                @php
                    $state = \App\Utils\Gym::status($m);
                    $cur = $m->current();
                    $last = $m->latest();
                    $bar = ['valid' => '#32d583', 'expiring' => '#fdb022', 'expired' => '#f04438', 'future' => '#53b1fd', 'none' => '#98a2b3'][$state];
                    $initials = collect(preg_split('/\s+/', trim(optional($m->contact)->name ?: '?')))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
                @endphp
                <div class="gy-card" data-member="{{ $m->member_no }}" data-state="{{ $state }}">
                    <div class="bd">
                        <div class="who"><div class="av">{{ $initials }}</div><div><div class="nm">{{ optional($m->contact)->name }}</div><div class="sub">{{ $m->member_no }} · {{ optional(optional($last)->plan)->name }}</div></div></div>
                        @if ($cur)
                            <div class="st {{ $state === 'expiring' ? 'warn' : 'ok' }}">{{ $cur->visits_left !== null ? trans_choice('gym.visits_left', $cur->visits_left, ['n' => $cur->visits_left]) : __('gym.valid_until', ['date' => $cur->end_date->format('d/m/Y')]) }}</div>
                        @elseif ($last && $state === 'future')
                            <div class="st guest">{{ __('gym.starts_on', ['date' => $last->start_date->format('d/m/Y')]) }}</div>
                        @elseif ($last)
                            <div class="st bad">{{ $last->visits_left === 0 ? __('gym.no_visits_left') : __('gym.expired_on', ['date' => $last->end_date->format('d/m/Y')]) }}</div>
                        @else
                            <div class="st bad">{{ __('gym.no_membership') }}</div>
                        @endif
                        <div class="sub">{{ __('gym.last_visit', ['when' => optional(optional($m->visits()->first())->visited_at)->format('d/m H:i') ?: '—']) }}</div>
                        <div class="acts">
                            @if ($cur && $can('gym.door'))
                                <form method="post" action="{{ action([$G, 'checkIn'], [$m->id]) }}">@csrf<button class="btn-in" type="submit" style="background:#32d583;color:#053321">{{ __('gym.let_in') }}</button></form>
                            @endif
                            @if (! $cur || $state === 'expiring')
                                @if ($plans->isNotEmpty())
                                    <form method="post" action="{{ action([$G, 'renew'], [$m->id]) }}" class="gy-renew">@csrf
                                        <select name="plan_id" aria-label="{{ __('gym.plan') }}">@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->name }} · {{ $fmt($p->price) }}</option>@endforeach</select>
                                        <select name="method" aria-label="{{ __('gym.method') }}">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                                        <button class="btn-in" type="submit" style="background:#f79009;color:#1d2939">{{ __('gym.renew') }}</button>
                                    </form>
                                @endif
                                @if (! $cur && $dayPlan && $can('gym.door'))
                                    <form method="post" action="{{ action([$G, 'dayPass']) }}">@csrf<input type="hidden" name="member_id" value="{{ $m->id }}"><input type="hidden" name="method" value="cash">
                                        <button class="btn-in" type="submit" style="background:transparent;color:#f2f4f7;border:1px solid #667085">{{ __('gym.day_pass_cash', ['price' => $fmt($dayPlan->price)]) }}</button></form>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="bar" style="background: {{ $bar }}"></div>
                </div>
            @endforeach
            @foreach ($found['guests'] as $res)
                <div class="gy-card" data-guest="{{ $res->id }}">
                    <div class="bd">
                        <div class="who"><div class="av">{{ optional($res->room)->number }}</div><div><div class="nm">{{ optional($res->contact)->name }}</div><div class="sub">{{ __('gym.hotel_guest', ['room' => optional($res->room)->number, 'date' => $res->departure->format('d/m')]) }}</div></div></div>
                        <div class="st guest">{{ __('gym.guest_rule_'.$s['guest_rule']) }}</div>
                        <div class="acts">
                            @if ($s['guest_rule'] === 'free')
                                <form method="post" action="{{ action([$G, 'guestIn']) }}">@csrf<input type="hidden" name="reservation_id" value="{{ $res->id }}"><button class="btn-in" type="submit" style="background:#53b1fd;color:#0b2545">{{ __('gym.let_in_free') }}</button></form>
                            @elseif ($dayPlan)
                                @if ($roomBill && $s['guest_rule'] === 'room')
                                    <form method="post" action="{{ action([$G, 'dayPass']) }}">@csrf<input type="hidden" name="reservation_id" value="{{ $res->id }}"><input type="hidden" name="pay" value="room">
                                        <button class="btn-in" type="submit" style="background:#53b1fd;color:#0b2545">{{ __('gym.pass_to_room', ['price' => $fmt($dayPlan->price)]) }}</button></form>
                                @endif
                                <form method="post" action="{{ action([$G, 'dayPass']) }}">@csrf<input type="hidden" name="reservation_id" value="{{ $res->id }}"><input type="hidden" name="method" value="cash">
                                    <button class="btn-in" type="submit" style="background:transparent;color:#f2f4f7;border:1px solid #667085">{{ __('gym.day_pass_cash', ['price' => $fmt($dayPlan->price)]) }}</button></form>
                            @endif
                        </div>
                    </div>
                    <div class="bar" style="background:#53b1fd"></div>
                </div>
            @endforeach
            @if ($found['members']->isEmpty() && $found['guests']->isEmpty())
                <div class="ht-card"><p class="ht-muted" style="margin:0">{{ __('gym.found_none', ['q' => $q]) }}</p></div>
            @endif
        </div>
        <p class="ht-muted ht-small">{{ __('gym.cards_key') }}</p>
    @endif

    @if ($dayPlan && $can('gym.door'))
        <details class="ht-card" {{ $found !== null && $found['members']->isEmpty() && $found['guests']->isEmpty() ? 'open' : '' }}>
            <summary class="ht-link" style="cursor:pointer">{{ __('gym.sell_pass', ['price' => $fmt($dayPlan->price)]) }}</summary>
            <form method="post" action="{{ action([$G, 'dayPass']) }}" style="margin-top:10px" id="gy_pass_form">@csrf
                <div class="ht-fields">
                    <div class="ht-f"><label for="gp_name">{{ __('gym.name') }}</label><input id="gp_name" name="name" class="ht-in" value="{{ $q && ! preg_match('/\d{5,}/', $q) ? $q : '' }}"></div>
                    <div class="ht-f"><label for="gp_mobile">{{ __('gym.mobile_opt') }}</label><input id="gp_mobile" name="mobile" class="ht-in" type="tel"></div>
                    <div class="ht-f"><label for="gp_method">{{ __('gym.method') }}</label><select id="gp_method" name="method" class="ht-in">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                </div>
                <button class="ht-btn pri" type="submit">{{ __('gym.sell_and_let_in') }}</button>
            </form>
        </details>
    @endif

    <div class="ht-card"><h2>{{ __('gym.in_today', ['n' => $today->count()]) }}</h2>
        <div class="gy-in-today">
            @forelse ($today as $v)
                <span>{{ $v->visited_at->format('H:i') }} {{ $v->name }} @if ($v->kind !== 'member')<em class="ht-muted">· {{ __('gym.kind_'.$v->kind) }}</em>@endif</span>
            @empty
                <span class="ht-muted">{{ __('gym.nobody_yet') }}</span>
            @endforelse
        </div>
    </div>
</section>
@endsection
