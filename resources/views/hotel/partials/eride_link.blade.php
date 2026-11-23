{{-- PosInnova hotel <-> E-Ride Hotels: the link, on Hotel setup › How the hotel works. --}}
@php
    $E = \App\Http\Controllers\HotelErideController::class;
    $L = $eride_link ?? null;
    $ago = fn ($d) => $d ? $d->diffForHumans() : __('channel.never_yet');
    $stale = $L && $L->isLive() && $L->last_heartbeat_at && $L->last_heartbeat_at->lt(now()->subMinutes(15));
@endphp
<div class="ht-card" style="max-width:980px;" id="ht_eride">
    <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;justify-content:space-between;">
        <h2 style="margin:0;">{{ __('hotel.eride_title') }}</h2>
        @if (! $L)
            <span class="ht-pill">{{ __('channel.st_none') }}</span>
        @elseif ($L->revoked_at)
            <span class="ht-pill ht-p-ooo">{{ __('channel.st_revoked') }}</span>
        @elseif (! $L->is_active)
            <span class="ht-pill">{{ __('channel.st_off') }}</span>
        @elseif ($L->last_error)
            <span class="ht-pill ht-p-dirty">{{ __('channel.st_problem') }}</span>
        @else
            <span class="ht-pill ht-p-free">{{ __('channel.st_ok') }}</span>
        @endif
    </div>
    <p class="ht-muted" style="margin:6px 0 12px;">{{ __('hotel.eride_lead') }}</p>

    @if (session('eride_ok'))<div class="ht-alert ok" role="status" style="background:#dcfae6;color:#05603a;border-radius:8px;padding:10px 12px;margin-bottom:10px;">{{ session('eride_ok') }}</div>@endif
    @if (session('eride_error'))<div class="ht-alert bad" role="alert" style="background:#fee4e2;color:#912018;border-radius:8px;padding:10px 12px;margin-bottom:10px;">{{ session('eride_error') }}</div>@endif
    @if ($L && $L->revoked_at)
        <div style="background:#fee4e2;color:#912018;border-radius:8px;padding:10px 12px;margin-bottom:10px;">{{ __('channel.revoked_help') }}</div>
    @elseif ($L && $L->isLive() && $L->last_error && ! session('eride_error'))
        <div style="background:#fef0c7;color:#93370d;border-radius:8px;padding:10px 12px;margin-bottom:10px;">{{ __('channel.last_problem', ['when' => $ago($L->last_error_at), 'error' => $L->last_error]) }}</div>
    @endif
    @if ($stale)
        <div style="background:#fef0c7;color:#93370d;border-radius:8px;padding:10px 12px;margin-bottom:10px;">{{ __('channel.no_task') }}</div>
    @endif

    @if ($L)
        <div class="ht-type-p" style="margin:0 0 12px;">
            <div><span class="l">{{ __('hotel.eride_hotel') }}</span><span class="v">{{ $L->branch_label ?: '—' }}</span></div>
            <div><span class="l">{{ __('channel.f_heartbeat') }}</span><span class="v">{{ $ago($L->last_heartbeat_at) }}</span></div>
            <div><span class="l">{{ __('hotel.eride_last_push') }}</span><span class="v">{{ $ago($L->last_push_at) }}</span></div>
        </div>
    @endif

    <form method="POST" action="{{ action([$E, 'save']) }}" id="ht_eride_form">
        @csrf
        <div class="ht-fields">
            <div class="ht-f">
                <label for="ht_er_url">{{ __('channel.l_url') }}</label>
                <input class="ht-in" type="url" name="base_url" id="ht_er_url" required value="{{ $L->base_url ?? '' }}" placeholder="https://…/api/channel/posinnova/v1/hotel" autocomplete="off">
                <span class="ht-hint">{{ __('hotel.eride_url_hint') }}</span>
            </div>
            <div class="ht-f">
                <label for="ht_er_key">{{ __('channel.l_key') }}</label>
                <input class="ht-in" type="password" name="api_key" id="ht_er_key" autocomplete="new-password" spellcheck="false" @if (! $L) required @endif
                    placeholder="{{ $L ? __('channel.key_kept', ['hint' => $L->key_hint]) : 'erh_…' }}">
                <span class="ht-hint">{{ $L ? __('channel.h_key_kept') : __('hotel.eride_key_hint') }}</span>
            </div>
            <div class="ht-f">
                <label for="ht_er_hold">{{ __('hotel.eride_hold_time') }}</label>
                <input class="ht-in" type="time" name="eride_hold_time" id="ht_er_hold" value="{{ $settings['eride_hold_time'] ?? '18:00' }}">
                <span class="ht-hint">{{ __('hotel.eride_hold_hint') }}</span>
            </div>
        </div>
        <button class="ht-btn pri" type="submit">{{ $L ? __('channel.b_save') : __('hotel.eride_b_link') }}</button>
    </form>

    @if ($L)
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;padding-top:12px;border-top:1px solid #eaecf0;">
            <form method="POST" action="{{ action([$E, 'test']) }}">@csrf<button class="ht-btn sm" type="submit">{{ __('channel.b_test') }}</button></form>
            @if ($L->isLive())
                <form method="POST" action="{{ action([$E, 'resend']) }}">@csrf<button class="ht-btn sm" type="submit">{{ __('channel.b_resend') }}</button></form>
            @endif
            <form method="POST" action="{{ action([$E, 'toggle']) }}">@csrf<button class="ht-btn sm" type="submit">{{ $L->is_active ? __('channel.b_off') : __('channel.b_on') }}</button></form>
            <form method="POST" action="{{ action([$E, 'remove']) }}" onsubmit="return confirm(@json(__('hotel.eride_remove_confirm')));">@csrf<button class="ht-btn sm" type="submit" style="color:#b42318 !important;">{{ __('channel.b_remove') }}</button></form>
        </div>
    @endif
</div>
