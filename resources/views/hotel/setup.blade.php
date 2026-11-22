@extends('layouts.app')
@section('title', __('hotel.menu_setup'))

@section('content')
@include('hotel.partials.styles')
@php
    $S = \App\Http\Controllers\HotelSetupController::class;
    $cur = session('currency')['symbol'] ?? (session('currency')['code'] ?? '');
    $n_rooms = $rooms->where('is_active', true)->count();
    $has_types = $types->isNotEmpty();
    $steps = [
        'types' => ['done' => $has_types, 'd' => $has_types ? trans_choice('hotel.n_types', $types->count(), ['n' => $types->count()]) : __('hotel.step_types_d')],
        'rooms' => ['done' => $n_rooms > 0, 'd' => $n_rooms > 0 ? trans_choice('hotel.n_rooms', $n_rooms, ['n' => $n_rooms]) : __('hotel.step_rooms_d')],
        'settings' => ['done' => false, 'd' => __('hotel.step_settings_d')],
    ];
@endphp
<style>
.ht-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
.ht-step { display: flex; gap: 10px; align-items: center; padding: 12px 14px; border: 1px solid #eaecf0; border-radius: 12px; background: #fff; text-decoration: none !important; color: #344054 !important; min-height: 60px; }
.ht-step .n { width: 30px; height: 30px; border-radius: 50%; background: #f2f4f7; color: #475467; font-weight: 700; display: flex; align-items: center; justify-content: center; flex: none; }
.ht-step .t { font-weight: 700; display: block; line-height: 1.25; }
.ht-step .d { font-size: 12.5px; color: #667085; display: block; line-height: 1.3; margin-top: 2px; }
.ht-step.done .n { background: #067647; color: #fff; }
.ht-step.on { border-color: #1849a9; box-shadow: 0 0 0 1px #1849a9; }
.ht-step.on .n { background: #1849a9; color: #fff; }
.ht-hint { font-size: 12.5px; color: #667085; line-height: 1.35; }
.ht-prices { border-top: 1px solid #eaecf0; padding-top: 14px; margin-top: 2px; }
.ht-prices h3 { margin: 0 0 2px; }
.ht-type { border: 1px solid #eaecf0; border-radius: 12px; padding: 14px; margin-bottom: 10px; }
.ht-type:last-child { margin-bottom: 0; }
.ht-type-h { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; }
.ht-type-h .nm { font-size: 16px; font-weight: 700; }
.ht-type-p { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; margin: 12px 0; }
.ht-type-p > div { background: #f9fafb; border-radius: 8px; padding: 8px 10px; }
.ht-type-p .l { font-size: 12px; color: #667085; display: block; }
.ht-type-p .v { font-weight: 700; font-variant-numeric: tabular-nums; }
.ht-type-p .v.off { font-weight: 500; color: #98a2b3; }
.ht-type form { border-top: 1px solid #eaecf0; margin-top: 12px; padding-top: 14px; }
.ht-next { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; justify-content: space-between; padding: 12px 14px; border-radius: 12px; background: #e5f4e4; color: #05603a; font-weight: 600; margin-bottom: 16px; }
.ht-t .ht-in { min-height: 36px; padding: 6px 8px; }
.ht-t .w-s { width: 90px; } .ht-t .w-m { width: 130px; }
@media (max-width: 760px) { .ht-steps { grid-template-columns: 1fr; } }
</style>
<section class="content ht" id="ht_setup">
    <div class="ht-head"><div><h1>{{ __('hotel.menu_setup') }}</h1><p>{{ __('hotel.setup_lead') }}</p></div></div>

    <nav class="ht-steps" aria-label="{{ __('hotel.menu_setup') }}">
        @foreach (array_keys($steps) as $i => $k)
            <a href="{{ action([$S, 'index'], ['tab' => $k]) }}" class="ht-step {{ $tab === $k ? 'on' : '' }} {{ $steps[$k]['done'] ? 'done' : '' }}" id="ht_tab_{{ $k }}" @if ($tab === $k) aria-current="step" @endif>
                <span class="n">@if ($steps[$k]['done'])&#10003;@else{{ $i + 1 }}@endif</span>
                <span><span class="t">{{ __('hotel.step_n', ['n' => $i + 1]) }} · {{ __('hotel.tab_' . $k) }}</span><span class="d">{{ $steps[$k]['d'] }}</span></span>
            </a>
        @endforeach
    </nav>

    @if ($tab === 'types')
        @if ($has_types)
            <div class="ht-next" id="ht_next">
                <span>{{ $n_rooms > 0 ? __('hotel.next_more_rooms_t') : __('hotel.next_rooms_t') }}</span>
                <a class="ht-btn ok" href="{{ action([$S, 'index'], ['tab' => 'rooms']) }}">{{ __('hotel.next_rooms_b') }} &rarr;</a>
            </div>
            <div class="ht-card" id="ht_types_list">
                <h2>{{ __('hotel.your_types') }}</h2>
                @foreach ($types as $t)
                    <div class="ht-type" data-type="{{ $t->id }}">
                        <div class="ht-type-h">
                            <div>
                                <span class="nm">{{ $t->name }}</span>
                                @if (! $t->is_active)<span class="ht-pill ht-p-ooo">{{ __('hotel.type_off') }}</span>@endif
                                <div class="ht-muted ht-small">{{ trans_choice('hotel.sleeps_n', $t->capacity, ['n' => $t->capacity]) }}@if ($t->description) · {{ $t->description }}@endif</div>
                            </div>
                            @if ($t->rooms_count > 0)
                                <span class="ht-pill ht-p-free">{{ trans_choice('hotel.n_rooms', $t->rooms_count, ['n' => $t->rooms_count]) }}</span>
                            @else
                                <a class="ht-btn sm ht-add-rooms" href="{{ action([$S, 'index'], ['tab' => 'rooms', 'type' => $t->id]) }}" style="background:#fef0c7;border-color:#fedf89;color:#93370d !important;min-height:40px;">{{ __('hotel.no_rooms_add') }} &rarr;</a>
                            @endif
                        </div>
                        <div class="ht-type-p">
                            @foreach (['rate_room_only' => 'plan_room_only', 'rate_bb' => 'price_bb', 'rate_corporate' => 'price_co', 'tourist_tax' => 'col_tourist_tax'] as $col => $label)
                                <div><span class="l">{{ __('hotel.' . $label) }}</span>
                                    @if ($t->{$col} === null)<span class="v off">{{ __('hotel.not_offered') }}</span>
                                    @else<span class="v">{{ $num($t->{$col}) }} {{ $cur }}</span>@endif
                                </div>
                            @endforeach
                        </div>
                        <details class="ht-more">
                            <summary class="ht-btn sm">{{ __('hotel.change_type') }}</summary>
                            <form method="POST" action="{{ action([$S, 'updateType'], [$t->id]) }}" id="ht_type_f{{ $t->id }}">
                                @csrf
                                @include('hotel.partials.type_fields', ['t' => $t])
                                <div class="ht-f" style="max-width:320px;">
                                    <label for="ht_t{{ $t->id }}_active">{{ __('hotel.type_sold') }}</label>
                                    <select class="ht-in" name="is_active" id="ht_t{{ $t->id }}_active"><option value="1" @selected($t->is_active)>{{ __('hotel.type_sold_yes') }}</option><option value="0" @selected(! $t->is_active)>{{ __('hotel.type_sold_no') }}</option></select>
                                </div>
                                <button class="ht-btn pri">{{ __('hotel.save_changes') }}</button>
                            </form>
                        </details>
                    </div>
                @endforeach
            </div>
        @else
            <div class="ht-note blue" id="ht_types_intro"><strong>{{ __('hotel.types_intro_title') }}</strong><span style="display:block;font-weight:400;margin-top:2px;">{{ __('hotel.types_intro') }}</span></div>
        @endif

        <form method="POST" action="{{ action([$S, 'storeType']) }}" class="ht-card" id="ht_type_new" style="max-width:980px;">
            @csrf
            <h2>{{ $has_types ? __('hotel.add_another_type') : __('hotel.add_first_type') }}</h2>
            @include('hotel.partials.type_fields', ['t' => null])
            <button class="ht-btn pri">{{ __('hotel.save_type') }}</button>
        </form>

    @elseif ($tab === 'rooms')
        @if (! $has_types)
            <a class="ht-note amber" href="{{ action([$S, 'index'], ['tab' => 'types']) }}">{{ __('hotel.types_first') }} &rarr;</a>
        @else
            @if ($n_rooms > 0)
                <div class="ht-next" id="ht_next">
                    <span>{{ __('hotel.next_settings_t') }}</span>
                    <a class="ht-btn ok" href="{{ action([$S, 'index'], ['tab' => 'settings']) }}">{{ __('hotel.next_settings_b') }} &rarr;</a>
                </div>
            @endif
            <form method="POST" action="{{ action([$S, 'storeRooms']) }}" class="ht-card" id="ht_rooms_new" style="max-width:980px;">
                @csrf
                <h2>{{ $n_rooms > 0 ? __('hotel.add_more_rooms') : __('hotel.add_rooms') }}</h2>
                <p class="ht-muted" style="margin:-6px 0 14px;">{{ __('hotel.rooms_intro') }}</p>
                <div class="ht-fields">
                    <div class="ht-f"><label for="ht_nr_type">{{ __('hotel.room_type') }}</label><select class="ht-in" name="room_type_id" id="ht_nr_type">@foreach ($types as $t)<option value="{{ $t->id }}" @selected((string) $t->id === (string) request('type'))>{{ $t->name }}</option>@endforeach</select><span class="ht-hint">{{ __('hotel.rooms_type_hint') }}</span></div>
                    <div class="ht-f"><label for="ht_nr_numbers">{{ __('hotel.room_numbers') }} *</label><input class="ht-in" name="numbers" id="ht_nr_numbers" required placeholder="101-112"><span class="ht-hint">{{ __('hotel.room_numbers_help') }}</span></div>
                    <div class="ht-f"><label for="ht_nr_floor">{{ __('hotel.floor_optional') }}</label><input class="ht-in" name="floor" id="ht_nr_floor" maxlength="30" placeholder="1"><span class="ht-hint">{{ __('hotel.floor_hint') }}</span></div>
                </div>
                <button class="ht-btn pri">{{ __('hotel.add_rooms') }}</button>
            </form>
        @endif
        @if ($rooms->isNotEmpty())
        <div class="ht-card" style="padding:6px 6px 2px;">
            <h2 style="padding:8px 10px 0;">{{ __('hotel.your_rooms') }}</h2>
            <p class="ht-muted ht-small" style="padding:0 10px;margin:4px 0 6px;">{{ __('hotel.rooms_list_help') }}</p>
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
        </div>
        @endif

    @else
        @if ($has_types && $n_rooms > 0)
            <div class="ht-next" id="ht_next">
                <span>{{ __('hotel.setup_done_t') }}</span>
                <a class="ht-btn ok" href="{{ action([\App\Http\Controllers\HotelController::class, 'home']) }}">{{ __('hotel.setup_done_b') }} &rarr;</a>
            </div>
        @endif
        <form method="POST" action="{{ action([$S, 'saveSettings']) }}" class="ht-card" style="max-width:980px;" id="ht_settings_form">
            @csrf
            <h2>{{ __('hotel.how_hotel_works') }}</h2>
            <p class="ht-muted" style="margin:-6px 0 14px;">{{ __('hotel.settings_intro') }}</p>
            <div class="ht-fields">
                @if (count($locations) > 1)
                    <div class="ht-f"><label for="ht_s_loc">{{ __('hotel.hotel_location') }}</label><select class="ht-in" name="location_id" id="ht_s_loc">@foreach ($locations as $id => $n)<option value="{{ $id }}" @selected($id == $settings['location_id'])>{{ $n }}</option>@endforeach</select></div>
                @endif
                <div class="ht-f"><label for="ht_s_ci">{{ __('hotel.checkin_from') }}</label><input class="ht-in" type="time" name="checkin_time" id="ht_s_ci" value="{{ $settings['checkin_time'] }}"><span class="ht-hint">{{ __('hotel.checkin_hint') }}</span></div>
                <div class="ht-f"><label for="ht_s_co">{{ __('hotel.checkout_by') }}</label><input class="ht-in" type="time" name="checkout_time" id="ht_s_co" value="{{ $settings['checkout_time'] }}"><span class="ht-hint">{{ __('hotel.checkout_hint') }}</span></div>
                <div class="ht-f"><label for="ht_s_lc">{{ __('hotel.late_checkout') }}</label><select class="ht-in" name="late_checkout" id="ht_s_lc"><option value="half" @selected($settings['late_checkout'] === 'half')>{{ __('hotel.late_half') }}</option><option value="none" @selected($settings['late_checkout'] === 'none')>{{ __('hotel.late_none') }}</option></select><span class="ht-hint">{{ __('hotel.late_hint') }}</span></div>
                <div class="ht-f"><label for="ht_s_dp">{{ __('hotel.deposit_asked') }}</label><select class="ht-in" name="deposit_rule" id="ht_s_dp">@foreach (['first_night', 'none', 'full'] as $o)<option value="{{ $o }}" @selected($settings['deposit_rule'] === $o)>{{ __('hotel.dep_' . $o) }}</option>@endforeach</select><span class="ht-hint">{{ __('hotel.deposit_hint') }}</span></div>
                <div class="ht-f"><label for="ht_s_au">{{ __('hotel.audit_runs_at') }}</label><input class="ht-in" type="time" name="audit_time" id="ht_s_au" value="{{ $settings['audit_time'] }}"><span class="ht-hint">{{ __('hotel.audit_runs_help') }}</span></div>
            </div>
            <h3>{{ __('hotel.when_charged') }}</h3>
            <div class="ht-grid ht-g2" style="margin-bottom:16px;">
                <label class="ht-card" style="margin:0;display:flex;gap:10px;font-weight:400;cursor:pointer;"><input type="radio" name="charge_mode" value="nightly" @checked($settings['charge_mode'] === 'nightly')> <span><strong>{{ __('hotel.mode_nightly') }}</strong><span class="ht-muted ht-small" style="display:block;">{{ __('hotel.mode_nightly_desc') }}</span></span></label>
                <label class="ht-card" style="margin:0;display:flex;gap:10px;font-weight:400;cursor:pointer;"><input type="radio" name="charge_mode" value="checkin" @checked($settings['charge_mode'] === 'checkin')> <span><strong>{{ __('hotel.mode_checkin') }}</strong><span class="ht-muted ht-small" style="display:block;">{{ __('hotel.mode_checkin_desc') }}</span></span></label>
            </div>
            <label style="display:flex;gap:10px;align-items:center;font-weight:500;"><input type="checkbox" name="pay_first" value="1" @checked($settings['pay_first'])> {{ __('hotel.pay_first_setting') }}</label>
            <label style="display:flex;gap:10px;align-items:center;font-weight:500;"><input type="checkbox" name="police_card" value="1" @checked($settings['police_card'])> {{ __('hotel.police_card_setting') }}</label>
            <label style="display:flex;gap:10px;align-items:center;font-weight:500;margin-bottom:14px;"><input type="checkbox" name="require_inspection" value="1" @checked($settings['require_inspection'])> {{ __('hotel.inspection_setting') }}</label>
            <button class="ht-btn pri">{{ __('hotel.save') }}</button>
        </form>
    @endif
</section>
@endsection
