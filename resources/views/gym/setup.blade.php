@extends('layouts.app')
@section('title', __('gym.menu_setup'))

@section('content')
@include('pressing.partials.styles')
@php $S = \App\Http\Controllers\GymSetupController::class; @endphp
<section class="content ht" id="gy_setup">
    <div class="ht-head"><div><h1>{{ __('gym.menu_setup') }}</h1><p>{{ __('gym.setup_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([\App\Http\Controllers\GymController::class, 'door']) }}">{{ __('gym.menu_door') }}</a></div></div>

    <div class="ht-card">
        <h2 class="pr-h"><span class="pr-step {{ $plans->where('is_active', true)->isNotEmpty() ? 'done' : '' }}">{{ $plans->where('is_active', true)->isNotEmpty() ? '✓' : '1' }}</span>{{ __('gym.setup_plans') }}</h2>
        <p class="ht-muted">{{ __('gym.setup_plans_hint') }}</p>
        @if ($plans->isEmpty())
            <form method="post" action="{{ action([$S, 'usualPlans']) }}" style="margin-bottom:12px">@csrf<button class="ht-btn" type="submit" id="gy_usual">{{ __('gym.add_usual') }}</button></form>
        @endif
        @if ($plans->isNotEmpty())
        <form method="post" action="{{ action([$S, 'plans']) }}" id="gy_plans">@csrf
            <div class="ht-scroll"><table class="ht-t" style="min-width:720px">
                <thead><tr><th>{{ __('gym.plan') }}</th><th>{{ __('gym.kind') }}</th><th>{{ __('gym.length') }}</th><th>{{ __('gym.price') }}</th><th>{{ __('gym.on_sale') }}</th></tr></thead>
                <tbody>
                    @foreach ($plans as $p)
                        <tr data-plan="{{ $p->id }}">
                            <td><input name="plans[{{ $p->id }}][name]" class="ht-in" value="{{ $p->name }}" aria-label="{{ __('gym.plan') }}"></td>
                            <td>{{ __('gym.kind_plan_'.$p->kind) }}</td>
                            <td>
                                @if ($p->kind === 'months')<input name="plans[{{ $p->id }}][months]" type="number" min="1" class="ht-in" style="width:80px" value="{{ $p->months }}" aria-label="{{ __('gym.months') }}"> {{ __('gym.months') }}
                                @elseif ($p->kind === 'visits')<input name="plans[{{ $p->id }}][visits]" type="number" min="1" class="ht-in" style="width:80px" value="{{ $p->visits }}" aria-label="{{ __('gym.visits') }}"> {{ __('gym.visits') }}, {{ __('gym.within') }} <input name="plans[{{ $p->id }}][months]" type="number" min="1" class="ht-in" style="width:70px" value="{{ $p->months ?: 6 }}" aria-label="{{ __('gym.months') }}"> {{ __('gym.months') }}
                                @else {{ __('gym.one_day') }}@endif
                            </td>
                            <td><input name="plans[{{ $p->id }}][price]" class="ht-in" inputmode="decimal" style="width:120px" value="{{ (float) $p->price ?: '' }}" placeholder="—" aria-label="{{ __('gym.price') }} · {{ $p->name }}"></td>
                            <td><input type="checkbox" name="plans[{{ $p->id }}][active]" value="1" {{ $p->is_active ? 'checked' : '' }} aria-label="{{ __('gym.on_sale') }} · {{ $p->name }}" style="width:20px;height:20px"></td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
            <p class="ht-muted ht-small">{{ __('gym.plans_note') }}</p>
            <button class="ht-btn pri" type="submit" id="gy_save_plans">{{ __('gym.save_plans') }}</button>
        </form>
        @endif
        <details style="margin-top:12px"><summary class="ht-link" style="cursor:pointer">{{ __('gym.add_plan') }}</summary>
            <form method="post" action="{{ action([$S, 'addPlan']) }}" style="margin-top:10px">@csrf
                <div class="ht-fields">
                    <div class="ht-f"><label for="np_name">{{ __('gym.plan') }}</label><input id="np_name" name="name" class="ht-in" placeholder="{{ __('gym.new_plan_ph') }}"></div>
                    <div class="ht-f"><label for="np_kind">{{ __('gym.kind') }}</label><select id="np_kind" name="kind" class="ht-in">@foreach (\App\GymPlan::KINDS as $k)<option value="{{ $k }}">{{ __('gym.kind_plan_'.$k) }}</option>@endforeach</select></div>
                    <div class="ht-f"><label for="np_months">{{ __('gym.months') }}</label><input id="np_months" name="months" type="number" min="1" value="1" class="ht-in"></div>
                    <div class="ht-f"><label for="np_visits">{{ __('gym.visits') }}</label><input id="np_visits" name="visits" type="number" min="1" value="10" class="ht-in"></div>
                    <div class="ht-f"><label for="np_price">{{ __('gym.price') }}</label><input id="np_price" name="price" class="ht-in" inputmode="decimal"></div>
                </div>
                <button class="ht-btn" type="submit">{{ __('gym.add') }}</button>
            </form>
        </details>
    </div>

    <form method="post" action="{{ action([$S, 'settings']) }}" id="gy_settings">@csrf
        <div class="ht-card">
            <h2 class="pr-h"><span class="pr-step">2</span>{{ __('gym.setup_guests') }}</h2>
            @if (! $hotel)<p class="ht-muted ht-small">{{ __('gym.no_hotel') }}</p>@endif
            <div class="ht-grid ht-g3">
                @foreach (['free', 'room', 'same'] as $k)
                    <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid #d0d5dd;border-radius:10px;padding:12px;margin:0">
                        <input type="radio" name="guest_rule" value="{{ $k }}" {{ $s['guest_rule'] === $k ? 'checked' : '' }} style="width:20px;height:20px;margin-top:2px">
                        <span><strong>{{ __('gym.rule_'.$k) }}</strong><br><span class="ht-muted ht-small">{{ __('gym.rule_'.$k.'_hint') }}</span></span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="ht-card">
            <h2 class="pr-h"><span class="pr-step">3</span>{{ __('gym.setup_reminders') }}</h2>
            @if ($waReady)<div class="ht-note green">{{ __('gym.wa_connected') }}</div>@else<div class="ht-note amber">{{ __('gym.wa_not_connected') }}</div>@endif
            <p class="ht-muted ht-small">{{ __('gym.wa_templates_hint') }}</p>
            <div class="ht-grid ht-g2">
                @foreach (['expiring' => 'remind_before', 'expired' => 'remind_after'] as $k => $days)
                    <div style="border:1px solid #eaecf0;border-radius:10px;padding:12px">
                        <label style="display:flex;gap:8px;align-items:center;font-weight:700;margin:0 0 8px"><input type="checkbox" name="send_{{ $k }}" value="1" {{ ! empty($s['send_'.$k]) ? 'checked' : '' }}> {{ __('gym.msg_'.$k) }}</label>
                        <div class="ht-fields">
                            <div class="ht-f"><label for="d_{{ $k }}">{{ __('gym.'.$days) }}</label><input id="d_{{ $k }}" name="{{ $days }}" type="number" min="0" class="ht-in" value="{{ $s[$days] }}"></div>
                            <div class="ht-f"><label for="t_{{ $k }}">{{ __('gym.tpl_name') }}</label><input id="t_{{ $k }}" name="tpl_{{ $k }}" class="ht-in" value="{{ $s['tpl_'.$k] }}"></div>
                        </div>
                        <div class="ht-small" style="background:#f9fafb;border-radius:8px;padding:8px;margin-bottom:6px"><strong>FR</strong> {{ __('gym.wa_tpl_'.$k, [], 'fr') }}</div>
                        <div class="ht-small" style="background:#f9fafb;border-radius:8px;padding:8px"><strong>EN</strong> {{ __('gym.wa_tpl_'.$k, [], 'en') }}</div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="ht-card">
            <h2 class="pr-h"><span class="pr-step">4</span>{{ __('gym.setup_rules') }}</h2>
            <div class="ht-fields">
                <div class="ht-f"><label for="gs_loc">{{ __('gym.location') }}</label><select id="gs_loc" name="location_id" class="ht-in">@foreach ($locations as $id => $n)<option value="{{ $id }}" {{ (int) $s['location_id'] === (int) $id ? 'selected' : '' }}>{{ $n }}</option>@endforeach</select></div>
                <div class="ht-f"><label for="gs_prefix">{{ __('gym.prefix') }}</label><input id="gs_prefix" name="member_prefix" class="ht-in" value="{{ $s['member_prefix'] }}"><span class="ht-muted ht-small">{{ __('gym.prefix_hint') }}</span></div>
            </div>
        </div>
        <button class="ht-btn pri" type="submit" id="gy_save_settings">{{ __('gym.save') }}</button>
    </form>
</section>
@endsection
