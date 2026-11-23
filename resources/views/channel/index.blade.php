@extends('layouts.app')
@section('title', __('channel.menu_link'))

@section('content')
@include('channel.partials.styles')
@php
    $C = \App\Http\Controllers\ChannelController::class;
    $fmt = function ($d) { return $d ? \Carbon\Carbon::parse($d)->diffForHumans() : __('channel.never_yet'); };
@endphp
<section class="content ch" id="ch_setup">
    <div class="ch-head">
        <div>
            <h1>{{ __('channel.menu_link') }}</h1>
            <p>{{ __('channel.setup_lead') }}</p>
        </div>
        <a class="ch-btn" href="{{ action([$C, 'products']) }}">{{ __('channel.menu_products') }} ({{ $published }}) &rarr;</a>
    </div>

    <div class="ch-steps">
        <div class="ch-step"><span class="n">1</span><span><b>{{ __('channel.step1_t') }}</b><span class="d">{{ __('channel.step1_d') }}</span></span></div>
        <div class="ch-step"><span class="n">2</span><span><b>{{ __('channel.step2_t') }}</b><span class="d">{{ __('channel.step2_d') }}</span></span></div>
        <div class="ch-step"><span class="n">3</span><span><b>{{ __('channel.step3_t') }}</b><span class="d">{{ __('channel.step3_d') }}</span></span></div>
    </div>

    @if ($locations->isEmpty())
        <div class="ch-card">{{ __('channel.no_locations') }}</div>
    @endif

    @foreach ($locations as $loc)
        @php
            $link = $links[$loc->id] ?? null;
            $types = $pay[$loc->id] ?? [];
            $guess = \App\Http\Controllers\ChannelController::guessMobileMoney($types);
            $chosen = old('pay_method', $link->pay_method ?? $guess);
            $stale = $link && $link->isLive() && $link->last_heartbeat_at && $link->last_heartbeat_at->lt(now()->subMinutes(15));
            $never_beat = $link && $link->isLive() && ! $link->last_heartbeat_at && $link->linked_at && $link->linked_at->lt(now()->subMinutes(10));
        @endphp
        <div class="ch-card" id="ch_loc_{{ $loc->id }}" data-location="{{ $loc->id }}">
            <div class="ch-loc-h">
                <div>
                    <span class="nm">{{ $loc->name }}</span>
                    @if ($link && $link->branch_label)<div class="ch-muted ch-small">{{ __('channel.eride_branch') }}: <b>{{ $link->branch_label }}</b></div>@endif
                </div>
                @if (! $link)
                    <span class="ch-pill off ch-state">{{ __('channel.st_none') }}</span>
                @elseif ($link->revoked_at)
                    <span class="ch-pill bad ch-state">{{ __('channel.st_revoked') }}</span>
                @elseif (! $link->is_active)
                    <span class="ch-pill off ch-state">{{ __('channel.st_off') }}</span>
                @elseif ($link->last_error)
                    <span class="ch-pill warn ch-state">{{ __('channel.st_problem') }}</span>
                @else
                    <span class="ch-pill ok ch-state">{{ __('channel.st_ok') }}</span>
                @endif
            </div>

            @if (session('channel_ok_'.$loc->id))<div class="ch-msg ok" role="status">{{ session('channel_ok_'.$loc->id) }}</div>@endif
            @if (session('channel_error_'.$loc->id))<div class="ch-msg bad" role="alert">{{ session('channel_error_'.$loc->id) }}</div>@endif
            @if ($link && $link->revoked_at)
                <div class="ch-msg bad">{{ __('channel.revoked_help') }}</div>
            @elseif ($link && $link->isLive() && $link->last_error && ! session('channel_error_'.$loc->id))
                <div class="ch-msg warn">{{ __('channel.last_problem', ['when' => $fmt($link->last_error_at), 'error' => $link->last_error]) }}</div>
            @endif
            @if ($stale || $never_beat)
                <div class="ch-msg warn">{{ __('channel.no_task') }}</div>
            @endif
            @if ($link && $link->clock_skew !== null && abs($link->clock_skew) > 300)
                <div class="ch-msg warn">{{ __('channel.clock_off', ['min' => (int) round(abs($link->clock_skew) / 60)]) }}</div>
            @endif

            @if ($link)
                <div class="ch-facts">
                    <div><span class="k">{{ __('channel.f_published') }}</span><span class="v">{{ $link->published }}</span></div>
                    <div><span class="k">{{ __('channel.f_heartbeat') }}</span><span class="v">{{ $fmt($link->last_heartbeat_at) }}</span></div>
                    <div><span class="k">{{ __('channel.f_push') }}</span><span class="v">{{ $fmt($link->last_push_at) }}</span></div>
                    <div><span class="k">{{ __('channel.f_full') }}</span><span class="v">{{ $fmt($link->last_full_at) }}</span></div>
                </div>
            @endif

            <form method="POST" action="{{ action([$C, 'save'], [$loc->id]) }}" class="ch-link-form" style="margin-top:12px;">
                @csrf
                <div class="ch-grid">
                    <div>
                        <label class="l" for="ch_url_{{ $loc->id }}">{{ __('channel.l_url') }}</label>
                        <input class="ch-in" type="url" name="base_url" id="ch_url_{{ $loc->id }}" required
                            value="{{ old('base_url', $link->base_url ?? '') }}" placeholder="https://…/api/channel/posinnova/v1" autocomplete="off">
                        <div class="ch-hint">{{ __('channel.h_url') }}</div>
                    </div>
                    <div>
                        <label class="l" for="ch_key_{{ $loc->id }}">{{ __('channel.l_key') }}</label>
                        <input class="ch-in" type="password" name="api_key" id="ch_key_{{ $loc->id }}" autocomplete="new-password" spellcheck="false"
                            placeholder="{{ $link ? __('channel.key_kept', ['hint' => $link->key_hint]) : 'erk_…' }}" @if (! $link) required @endif>
                        <div class="ch-hint">{{ $link ? __('channel.h_key_kept') : __('channel.h_key') }}</div>
                    </div>
                </div>
                <div class="ch-grid" style="margin-top:12px;">
                    <div>
                        <span class="l">{{ __('channel.l_display') }}</span>
                        @php $disp = old('qty_display', $link->qty_display ?? 'available'); @endphp
                        <label class="ch-radio"><input type="radio" name="qty_display" value="available" @checked($disp !== 'exact')>
                            <span><b>{{ __('channel.disp_available') }}</b><span class="ch-hint" style="display:block;">{{ __('channel.disp_available_h') }}</span></span></label>
                        <label class="ch-radio"><input type="radio" name="qty_display" value="exact" @checked($disp === 'exact')>
                            <span><b>{{ __('channel.disp_exact') }}</b><span class="ch-hint" style="display:block;">{{ __('channel.disp_exact_h') }}</span></span></label>
                    </div>
                    <div>
                        <label class="l" for="ch_pay_{{ $loc->id }}">{{ __('channel.l_pay') }}</label>
                        <select class="ch-in" name="pay_method" id="ch_pay_{{ $loc->id }}">
                            @foreach ($types as $k => $label)
                                <option value="{{ $k }}" @selected($chosen === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="ch-hint">{{ __('channel.h_pay') }}</div>
                        @if (! $guess)<div class="ch-hint" style="color:#93370d;">{{ __('channel.h_pay_none') }}</div>@endif
                        <div class="ch-hint">{{ __('channel.h_credit') }}</div>
                    </div>
                </div>
                <div class="ch-actions">
                    <button type="submit" class="ch-btn pri">{{ $link ? __('channel.b_save') : __('channel.b_link') }}</button>
                </div>
            </form>

            @if ($link)
                <div class="ch-actions" style="border-top:1px solid #eaecf0;padding-top:12px;">
                    <form method="POST" action="{{ action([$C, 'test'], [$loc->id]) }}">@csrf<button class="ch-btn sm" type="submit">{{ __('channel.b_test') }}</button></form>
                    @if ($link->isLive())
                        <form method="POST" action="{{ action([$C, 'resend'], [$loc->id]) }}">@csrf<button class="ch-btn sm" type="submit">{{ __('channel.b_resend') }}</button></form>
                    @endif
                    <form method="POST" action="{{ action([$C, 'toggle'], [$loc->id]) }}">@csrf<button class="ch-btn sm" type="submit">{{ $link->is_active ? __('channel.b_off') : __('channel.b_on') }}</button></form>
                    <form method="POST" action="{{ action([$C, 'remove'], [$loc->id]) }}" onsubmit="return confirm(@json(__('channel.remove_confirm')));">@csrf<button class="ch-btn sm danger" type="submit">{{ __('channel.b_remove') }}</button></form>
                </div>
            @endif
        </div>
    @endforeach
</section>
@endsection
