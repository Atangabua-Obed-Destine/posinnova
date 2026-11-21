@extends('layouts.app')
@section('title', __('hotel.menu_setup'))

@section('content')
@include('hotel.partials.styles')
@php $S = \App\Http\Controllers\HotelSetupController::class; @endphp
<style>
.ht-t .ht-in { min-height: 36px; padding: 6px 8px; }
.ht-t .w-s { width: 90px; } .ht-t .w-m { width: 130px; } .ht-t .w-l { min-width: 150px; }
</style>
<section class="content ht" id="ht_setup">
    <div class="ht-head"><div><h1>{{ __('hotel.menu_setup') }}</h1><p>{{ __('hotel.setup_lead') }}</p></div></div>
    <nav class="ht-tabs">
        <a href="{{ action([$S, 'index'], ['tab' => 'types']) }}" class="{{ $tab === 'types' ? 'on' : '' }}" id="ht_tab_types">{{ __('hotel.tab_types') }}</a>
        <a href="{{ action([$S, 'index'], ['tab' => 'rooms']) }}" class="{{ $tab === 'rooms' ? 'on' : '' }}" id="ht_tab_rooms">{{ __('hotel.tab_rooms') }} ({{ $rooms->where('is_active', true)->count() }})</a>
        <a href="{{ action([$S, 'index'], ['tab' => 'settings']) }}" class="{{ $tab === 'settings' ? 'on' : '' }}" id="ht_tab_settings">{{ __('hotel.tab_settings') }}</a>
    </nav>

    @if ($tab === 'types')
        <div class="ht-card" style="padding:6px 6px 2px;">
            <h2 style="padding:8px 10px 0;">{{ __('hotel.tab_types') }}</h2>
            <div class="ht-scroll"><table class="ht-t" id="ht_types_table">
                <thead><tr><th>{{ __('hotel.room_type') }}</th><th>{{ __('hotel.sleeps') }}</th><th>{{ __('hotel.rooms') }}</th><th>{{ __('hotel.plan_room_only') }} *</th><th>{{ __('hotel.plan_bb') }}</th><th>{{ __('hotel.plan_corporate') }}</th><th>{{ __('hotel.tourist_tax_night') }}</th><th>{{ __('hotel.active') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($types as $t)
                    <tr>
                        <td><form method="POST" action="{{ action([$S, 'updateType'], [$t->id]) }}" id="ht_type_f{{ $t->id }}">@csrf</form>
                            <input class="ht-in w-l" name="name" value="{{ $t->name }}" form="ht_type_f{{ $t->id }}" required aria-label="{{ __('hotel.room_type') }}">
                            <input class="ht-in w-l" name="description" value="{{ $t->description }}" form="ht_type_f{{ $t->id }}" placeholder="{{ __('hotel.description') }}" style="margin-top:4px;" aria-label="{{ __('hotel.description') }}"></td>
                        <td><input class="ht-in w-s" type="number" min="1" max="20" name="capacity" value="{{ $t->capacity }}" form="ht_type_f{{ $t->id }}" aria-label="{{ __('hotel.sleeps') }}"></td>
                        <td>{{ $t->rooms_count }}</td>
                        @foreach (['rate_room_only', 'rate_bb', 'rate_corporate', 'tourist_tax'] as $col)
                            <td><input class="ht-in w-m" name="{{ $col }}" value="{{ $num($t->{$col}) }}" form="ht_type_f{{ $t->id }}" inputmode="decimal" aria-label="{{ __('hotel.col_' . $col) }}"></td>
                        @endforeach
                        <td><select class="ht-in w-s" name="is_active" form="ht_type_f{{ $t->id }}" aria-label="{{ __('hotel.active') }}"><option value="1" @selected($t->is_active)>{{ __('hotel.yes') }}</option><option value="0" @selected(! $t->is_active)>{{ __('hotel.no') }}</option></select></td>
                        <td><button class="ht-btn sm" form="ht_type_f{{ $t->id }}">{{ __('hotel.save') }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <p class="ht-muted ht-small" style="padding:4px 10px 10px;">{{ __('hotel.types_help') }}</p>
        </div>
        <form method="POST" action="{{ action([$S, 'storeType']) }}" class="ht-card" id="ht_type_new" style="max-width:980px;">
            @csrf
            <h2>+ {{ __('hotel.add_type') }}</h2>
            <div class="ht-fields">
                <div class="ht-f"><label for="ht_nt_name">{{ __('hotel.room_type') }} *</label><input class="ht-in" name="name" id="ht_nt_name" required maxlength="100" placeholder="{{ __('hotel.type_ph') }}"></div>
                <div class="ht-f"><label for="ht_nt_desc">{{ __('hotel.description') }}</label><input class="ht-in" name="description" id="ht_nt_desc" maxlength="255"></div>
                <div class="ht-f"><label for="ht_nt_cap">{{ __('hotel.sleeps') }}</label><input class="ht-in" type="number" min="1" max="20" name="capacity" id="ht_nt_cap" value="2"></div>
                <div class="ht-f"><label for="ht_nt_ro">{{ __('hotel.plan_room_only') }} *</label><input class="ht-in" name="rate_room_only" id="ht_nt_ro" required inputmode="decimal"></div>
                <div class="ht-f"><label for="ht_nt_bb">{{ __('hotel.plan_bb') }}</label><input class="ht-in" name="rate_bb" id="ht_nt_bb" inputmode="decimal"></div>
                <div class="ht-f"><label for="ht_nt_co">{{ __('hotel.plan_corporate') }}</label><input class="ht-in" name="rate_corporate" id="ht_nt_co" inputmode="decimal"></div>
                <div class="ht-f"><label for="ht_nt_tax">{{ __('hotel.tourist_tax_night') }}</label><input class="ht-in" name="tourist_tax" id="ht_nt_tax" inputmode="decimal" value="0"></div>
            </div>
            <button class="ht-btn pri">{{ __('hotel.add_type') }}</button>
        </form>

    @elseif ($tab === 'rooms')
        @if ($types->isEmpty())
            <div class="ht-note amber">{{ __('hotel.types_first') }}</div>
        @else
            <form method="POST" action="{{ action([$S, 'storeRooms']) }}" class="ht-card" id="ht_rooms_new" style="max-width:980px;">
                @csrf
                <h2>+ {{ __('hotel.add_rooms') }}</h2>
                <div class="ht-fields">
                    <div class="ht-f"><label for="ht_nr_type">{{ __('hotel.room_type') }}</label><select class="ht-in" name="room_type_id" id="ht_nr_type">@foreach ($types as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach</select></div>
                    <div class="ht-f"><label for="ht_nr_numbers">{{ __('hotel.room_numbers') }}</label><input class="ht-in" name="numbers" id="ht_nr_numbers" required placeholder="101-112"></div>
                    <div class="ht-f"><label for="ht_nr_floor">{{ __('hotel.floor') }}</label><input class="ht-in" name="floor" id="ht_nr_floor" maxlength="30" placeholder="1"></div>
                </div>
                <p class="ht-muted ht-small" style="margin:0 0 10px;">{{ __('hotel.room_numbers_help') }}</p>
                <button class="ht-btn pri">{{ __('hotel.add_rooms') }}</button>
            </form>
        @endif
        <div class="ht-card" style="padding:6px 6px 2px;">
            <h2 style="padding:8px 10px 0;">{{ __('hotel.tab_rooms') }}</h2>
            @if ($rooms->isEmpty())<div class="ht-empty" style="padding:10px;">{{ __('hotel.no_rooms') }}</div>@else
            <div class="ht-scroll"><table class="ht-t" id="ht_rooms_table">
                <thead><tr><th>{{ __('hotel.room') }}</th><th>{{ __('hotel.floor') }}</th><th>{{ __('hotel.room_type') }}</th><th>{{ __('hotel.housekeeping') }}</th><th>{{ __('hotel.in_service') }}</th><th>{{ __('hotel.active') }}</th><th></th></tr></thead>
                <tbody>
                @foreach ($rooms as $r)
                    <tr>
                        <td><form method="POST" action="{{ action([$S, 'updateRoom'], [$r->id]) }}" id="ht_room_f{{ $r->id }}">@csrf</form>
                            <input class="ht-in w-s" name="number" value="{{ $r->number }}" form="ht_room_f{{ $r->id }}" aria-label="{{ __('hotel.room') }}"></td>
                        <td><input class="ht-in w-s" name="floor" value="{{ $r->floor }}" form="ht_room_f{{ $r->id }}" aria-label="{{ __('hotel.floor') }}"></td>
                        <td><select class="ht-in w-m" name="room_type_id" form="ht_room_f{{ $r->id }}" aria-label="{{ __('hotel.room_type') }}">@foreach ($types as $t)<option value="{{ $t->id }}" @selected($t->id == $r->room_type_id)>{{ $t->name }}</option>@endforeach</select></td>
                        <td><span class="ht-pill ht-p-{{ $r->hk_status === 'dirty' ? 'dirty' : 'free' }}">{{ __('hotel.hk_' . $r->hk_status) }}</span></td>
                        <td>
                            @if ($r->ooo_from && (! $r->ooo_to || $r->ooo_to->gte($today)))
                                <span class="ht-pill ht-p-ooo">{{ __('hotel.ooo_range', ['from' => $r->ooo_from->format('d/m'), 'to' => $r->ooo_to ? $r->ooo_to->format('d/m') : '…']) }}</span>
                                <div class="ht-muted ht-small">{{ $r->ooo_reason }}</div>
                                <form method="POST" action="{{ action([$S, 'outOfOrder'], [$r->id]) }}" style="margin-top:4px;">@csrf<input type="hidden" name="clear" value="1"><button class="ht-btn sm">{{ __('hotel.back_in_service') }}</button></form>
                            @else
                                <details class="ht-more"><summary class="ht-btn sm">{{ __('hotel.set_out_of_order') }}</summary>
                                    <form method="POST" action="{{ action([$S, 'outOfOrder'], [$r->id]) }}" style="padding:10px;border:1px solid #eaecf0;border-radius:10px;margin-top:6px;background:#fcfcfd;min-width:240px;">@csrf
                                        <div class="ht-f"><label for="ht_oo_from{{ $r->id }}">{{ __('hotel.from') }}</label><input class="ht-in" type="date" name="from" id="ht_oo_from{{ $r->id }}" value="{{ $today->toDateString() }}" required></div>
                                        <div class="ht-f"><label for="ht_oo_to{{ $r->id }}">{{ __('hotel.to_included') }}</label><input class="ht-in" type="date" name="to" id="ht_oo_to{{ $r->id }}"></div>
                                        <div class="ht-f"><label for="ht_oo_reason{{ $r->id }}">{{ __('hotel.reason') }}</label><input class="ht-in" name="reason" id="ht_oo_reason{{ $r->id }}" maxlength="150"></div>
                                        <button class="ht-btn sm">{{ __('hotel.save') }}</button></form></details>
                            @endif
                        </td>
                        <td><select class="ht-in w-s" name="is_active" form="ht_room_f{{ $r->id }}" aria-label="{{ __('hotel.active') }}"><option value="1" @selected($r->is_active)>{{ __('hotel.yes') }}</option><option value="0" @selected(! $r->is_active)>{{ __('hotel.no') }}</option></select></td>
                        <td><button class="ht-btn sm" form="ht_room_f{{ $r->id }}">{{ __('hotel.save') }}</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @endif
        </div>

    @else
        <form method="POST" action="{{ action([$S, 'saveSettings']) }}" class="ht-card" style="max-width:980px;" id="ht_settings_form">
            @csrf
            <h2>{{ __('hotel.how_hotel_works') }}</h2>
            <div class="ht-fields">
                @if (count($locations) > 1)
                    <div class="ht-f"><label for="ht_s_loc">{{ __('hotel.hotel_location') }}</label><select class="ht-in" name="location_id" id="ht_s_loc">@foreach ($locations as $id => $n)<option value="{{ $id }}" @selected($id == $settings['location_id'])>{{ $n }}</option>@endforeach</select></div>
                @endif
                <div class="ht-f"><label for="ht_s_ci">{{ __('hotel.checkin_from') }}</label><input class="ht-in" type="time" name="checkin_time" id="ht_s_ci" value="{{ $settings['checkin_time'] }}"></div>
                <div class="ht-f"><label for="ht_s_co">{{ __('hotel.checkout_by') }}</label><input class="ht-in" type="time" name="checkout_time" id="ht_s_co" value="{{ $settings['checkout_time'] }}"></div>
                <div class="ht-f"><label for="ht_s_au">{{ __('hotel.audit_runs_at') }}</label><input class="ht-in" type="time" name="audit_time" id="ht_s_au" value="{{ $settings['audit_time'] }}"><span class="ht-muted ht-small">{{ __('hotel.audit_runs_help') }}</span></div>
                <div class="ht-f"><label for="ht_s_lc">{{ __('hotel.late_checkout') }}</label><select class="ht-in" name="late_checkout" id="ht_s_lc"><option value="half" @selected($settings['late_checkout'] === 'half')>{{ __('hotel.late_half') }}</option><option value="none" @selected($settings['late_checkout'] === 'none')>{{ __('hotel.late_none') }}</option></select></div>
                <div class="ht-f"><label for="ht_s_dp">{{ __('hotel.deposit_asked') }}</label><select class="ht-in" name="deposit_rule" id="ht_s_dp">@foreach (['first_night', 'none', 'full'] as $o)<option value="{{ $o }}" @selected($settings['deposit_rule'] === $o)>{{ __('hotel.dep_' . $o) }}</option>@endforeach</select></div>
            </div>
            <h3>{{ __('hotel.when_charged') }}</h3>
            <div class="ht-grid ht-g2" style="margin-bottom:16px;">
                <label class="ht-card" style="margin:0;display:flex;gap:10px;font-weight:400;cursor:pointer;"><input type="radio" name="charge_mode" value="nightly" @checked($settings['charge_mode'] === 'nightly')> <span><strong>{{ __('hotel.mode_nightly') }}</strong><span class="ht-muted ht-small" style="display:block;">{{ __('hotel.mode_nightly_desc') }}</span></span></label>
                <label class="ht-card" style="margin:0;display:flex;gap:10px;font-weight:400;cursor:pointer;"><input type="radio" name="charge_mode" value="checkin" @checked($settings['charge_mode'] === 'checkin')> <span><strong>{{ __('hotel.mode_checkin') }}</strong><span class="ht-muted ht-small" style="display:block;">{{ __('hotel.mode_checkin_desc') }}</span></span></label>
            </div>
            <label style="display:flex;gap:10px;align-items:center;font-weight:500;"><input type="checkbox" name="police_card" value="1" @checked($settings['police_card'])> {{ __('hotel.police_card_setting') }}</label>
            <label style="display:flex;gap:10px;align-items:center;font-weight:500;margin-bottom:14px;"><input type="checkbox" name="require_inspection" value="1" @checked($settings['require_inspection'])> {{ __('hotel.inspection_setting') }}</label>
            <button class="ht-btn pri">{{ __('hotel.save') }}</button>
        </form>
    @endif
</section>
@endsection
