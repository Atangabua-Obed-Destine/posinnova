@extends('layouts.app')
@section('title', __('pressing.menu_setup'))

@section('content')
@include('pressing.partials.styles')
@php
    $S = \App\Http\Controllers\PressingSetupController::class;
    $hasPrices = $prices->isNotEmpty();
    $activeServices = $services->where('is_active', true);
@endphp
<section class="content ht" id="pr_setup">
    <div class="ht-head"><div><h1>{{ __('pressing.menu_setup') }}</h1><p>{{ __('pressing.setup_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([\App\Http\Controllers\PressingController::class, 'index']) }}">{{ __('pressing.back_board') }}</a></div></div>

    <div class="ht-card" id="pr_step_prices">
        <h2 class="pr-h"><span class="pr-step {{ $hasPrices ? 'done' : '' }}">{{ $hasPrices ? '✓' : '1' }}</span>{{ __('pressing.setup_prices') }}</h2>
        <p class="ht-muted">{{ __('pressing.setup_prices_hint') }}</p>
        @if ($items->isEmpty())
            <div class="ht-note blue">{{ __('pressing.no_items') }}</div>
        @endif
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">
            <form method="post" action="{{ action([$S, 'usualItems']) }}">@csrf<button class="ht-btn" type="submit" id="pr_usual">{{ __('pressing.add_usual') }}</button></form>
            <form method="post" action="{{ action([$S, 'addItem']) }}" style="display:flex;gap:8px;flex-wrap:wrap">@csrf
                <label for="pr_new_item" class="sr-only">{{ __('pressing.new_item') }}</label>
                <input id="pr_new_item" name="name" class="ht-in" placeholder="{{ __('pressing.new_item_ph') }}" style="width:220px">
                <label for="pr_new_days" class="sr-only">{{ __('pressing.ready_days') }}</label>
                <input id="pr_new_days" name="ready_days" class="ht-in" type="number" min="1" value="2" style="width:80px" title="{{ __('pressing.ready_days') }}">
                <button class="ht-btn" type="submit">+ {{ __('pressing.add_item') }}</button>
            </form>
        </div>
        @if ($items->isNotEmpty())
        <form method="post" action="{{ action([$S, 'prices']) }}" id="pr_prices">@csrf
            <div class="ht-scroll">
                <table class="ht-t" style="min-width:720px">
                    <thead><tr><th>{{ __('pressing.item') }}</th>@foreach ($activeServices as $svc)<th>{{ $svc->name }}</th>@endforeach<th>{{ __('pressing.ready_days') }}</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($items as $it)
                            <tr style="{{ $it->is_active ? '' : 'opacity:.5' }}">
                                <td><label for="it_{{ $it->id }}" class="sr-only">{{ __('pressing.item') }}</label><input id="it_{{ $it->id }}" name="items[{{ $it->id }}][name]" class="ht-in" value="{{ $it->name }}" style="min-width:160px"></td>
                                @foreach ($activeServices as $svc)
                                    @php $p = $prices[$it->id.'|'.$svc->id] ?? null; @endphp
                                    <td><input name="items[{{ $it->id }}][price][{{ $svc->id }}]" class="ht-in" inputmode="decimal" style="width:110px" value="{{ $p ? (float) $p->price : '' }}" placeholder="—" aria-label="{{ $it->name }} · {{ $svc->name }}">
                                        @if (! empty($s['guest_prices']))<input name="items[{{ $it->id }}][guest][{{ $svc->id }}]" class="ht-in" inputmode="decimal" style="width:110px;margin-top:4px" value="{{ $p && $p->guest_price !== null ? (float) $p->guest_price : '' }}" placeholder="{{ __('pressing.guest_ph') }}" aria-label="{{ $it->name }} · {{ $svc->name }} · {{ __('pressing.guest_ph') }}">@endif</td>
                                @endforeach
                                <td><input name="items[{{ $it->id }}][ready_days]" class="ht-in" type="number" min="1" max="60" style="width:80px" value="{{ $it->ready_days }}" aria-label="{{ __('pressing.ready_days') }} · {{ $it->name }}"></td>
                                <td><button class="ht-btn sm" type="submit" form="pr_toggle_{{ $it->id }}">{{ $it->is_active ? __('pressing.hide') : __('pressing.show_again') }}</button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="ht-muted ht-small">{{ __('pressing.prices_note') }}</p>
            <button class="ht-btn pri" type="submit" id="pr_save_prices">{{ __('pressing.save_prices') }}</button>
        </form>
        @foreach ($items as $it)<form method="post" action="{{ action([$S, 'toggleItem'], [$it->id]) }}" id="pr_toggle_{{ $it->id }}">@csrf</form>@endforeach
        @endif

        <details style="margin-top:14px"><summary class="ht-link" style="cursor:pointer">{{ __('pressing.services_title') }}</summary>
            <form method="post" action="{{ action([$S, 'service']) }}" style="margin-top:10px">@csrf
                <p class="ht-muted ht-small">{{ __('pressing.services_hint') }}</p>
                @foreach ($services as $svc)
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px">
                        <input name="services[{{ $svc->id }}][name]" class="ht-in" value="{{ $svc->name }}" style="max-width:260px" aria-label="{{ __('pressing.service') }}">
                        <label style="display:flex;gap:6px;align-items:center;margin:0"><input type="checkbox" name="services[{{ $svc->id }}][active]" value="1" {{ $svc->is_active ? 'checked' : '' }}> {{ __('pressing.offered') }}</label>
                    </div>
                @endforeach
                <div class="ht-f" style="max-width:300px"><label for="pr_new_svc">{{ __('pressing.new_service') }}</label><input id="pr_new_svc" name="new_service" class="ht-in" placeholder="{{ __('pressing.new_service_ph') }}"></div>
                <button class="ht-btn" type="submit">{{ __('pressing.save_services') }}</button>
            </form>
        </details>
    </div>

    <form method="post" action="{{ action([$S, 'settings']) }}" id="pr_settings">@csrf
        <div class="ht-card">
            <h2 class="pr-h"><span class="pr-step">2</span>{{ __('pressing.setup_wa') }}</h2>
            @if ($waReady)
                <div class="ht-note green">{{ __('pressing.wa_connected', ['phone' => optional($wa)->display_phone]) }}</div>
            @else
                <div class="ht-note amber">{{ __('pressing.wa_not_connected') }}</div>
            @endif
            <p class="ht-muted ht-small">{{ __('pressing.wa_templates_hint') }}</p>
            <div class="ht-grid ht-g3">
                @foreach (['received', 'ready', 'reminder'] as $k)
                    <div style="border:1px solid #eaecf0;border-radius:10px;padding:12px">
                        <label style="display:flex;gap:8px;align-items:center;font-weight:700;margin:0 0 8px"><input type="checkbox" name="send_{{ $k }}" value="1" {{ ! empty($s['send_'.$k]) ? 'checked' : '' }}> {{ __('pressing.msg_'.$k) }}</label>
                        <div class="ht-f"><label for="tpl_{{ $k }}">{{ __('pressing.tpl_name') }}</label><input id="tpl_{{ $k }}" name="tpl_{{ $k }}" class="ht-in" value="{{ $s['tpl_'.$k] }}">
                            @if ($wa)<span class="ht-small">{{ __('pressing.tpl_status') }}: FR {{ $wa->templateStatus($s['tpl_'.$k], 'fr') ?: '—' }} · EN {{ $wa->templateStatus($s['tpl_'.$k], 'en') ?: '—' }}</span>@endif</div>
                        <div class="ht-small" style="background:#f9fafb;border-radius:8px;padding:8px;margin-bottom:6px"><strong>FR</strong> {{ __('pressing.wa_tpl_'.$k, [], 'fr') }}</div>
                        <div class="ht-small" style="background:#f9fafb;border-radius:8px;padding:8px"><strong>EN</strong> {{ __('pressing.wa_tpl_'.$k, [], 'en') }}</div>
                    </div>
                @endforeach
            </div>
            <div class="ht-fields" style="margin-top:12px">
                <div class="ht-f"><label for="pr_rem">{{ __('pressing.reminder_days') }}</label><input id="pr_rem" name="reminder_days" type="number" min="1" class="ht-in" value="{{ $s['reminder_days'] }}"></div>
            </div>
        </div>

        <div class="ht-card">
            <h2 class="pr-h"><span class="pr-step">3</span>{{ __('pressing.setup_rules') }}</h2>
            <div class="ht-fields">
                <div class="ht-f"><label for="pr_loc">{{ __('pressing.location') }}</label><select id="pr_loc" name="location_id" class="ht-in">@foreach ($locations as $id => $n)<option value="{{ $id }}" {{ (int) $s['location_id'] === (int) $id ? 'selected' : '' }}>{{ $n }}</option>@endforeach</select><span class="ht-muted ht-small">{{ __('pressing.location_hint') }}</span></div>
                <div class="ht-f"><label for="pr_prefix">{{ __('pressing.prefix') }}</label><input id="pr_prefix" name="ticket_prefix" class="ht-in" value="{{ $s['ticket_prefix'] }}"><span class="ht-muted ht-small">{{ __('pressing.prefix_hint') }}</span></div>
                <div class="ht-f"><label for="pr_rt">{{ __('pressing.ready_time') }}</label><input id="pr_rt" name="ready_time" type="time" class="ht-in" value="{{ $s['ready_time'] }}"></div>
                <div class="ht-f"><label for="pr_xp">{{ __('pressing.express_pct') }}</label><input id="pr_xp" name="express_percent" type="number" min="0" class="ht-in" value="{{ $s['express_percent'] }}"><span class="ht-muted ht-small">{{ __('pressing.express_pct_hint') }}</span></div>
                <div class="ht-f"><label for="pr_xt">{{ __('pressing.express_time') }}</label><input id="pr_xt" name="express_time" type="time" class="ht-in" value="{{ $s['express_time'] }}"></div>
                <div class="ht-f"><label for="pr_rule">{{ __('pressing.pay_rule') }}</label><select id="pr_rule" name="pay_rule" class="ht-in">@foreach (['any', 'half', 'full'] as $k)<option value="{{ $k }}" {{ $s['pay_rule'] === $k ? 'selected' : '' }}>{{ __('pressing.rule_'.$k) }}</option>@endforeach</select></div>
                <div class="ht-f"><label for="pr_left">{{ __('pressing.left_days') }}</label><input id="pr_left" name="left_over_days" type="number" min="1" class="ht-in" value="{{ $s['left_over_days'] }}"><span class="ht-muted ht-small">{{ __('pressing.left_days_hint') }}</span></div>
            </div>
            @if (\App\Utils\Hotel::enabled(session('business.id')))
                <input type="hidden" name="hotel_fields" value="1">
                <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="charge_to_room" value="1" {{ ! empty($s['charge_to_room']) ? 'checked' : '' }}> {{ __('pressing.charge_to_room_opt') }}</label>
                <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="guest_prices" value="1" {{ ! empty($s['guest_prices']) ? 'checked' : '' }}> {{ __('pressing.guest_prices_opt') }}</label>
            @endif
        </div>
        <button class="ht-btn pri" type="submit" id="pr_save_settings">{{ __('pressing.save_settings') }}</button>
    </form>
</section>
@endsection
