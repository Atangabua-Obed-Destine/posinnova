{{-- PosInnova hotel setup: the fields of one room type (new: $t = null, ids ht_nt_*; existing: ids ht_t{id}_*) --}}
@php
    $p = $t ? 'ht_t' . $t->id : 'ht_nt';
    $val = fn ($col) => $t ? $num($t->{$col}) : old($col, $col === 'tourist_tax' ? '0' : '');
@endphp
<div class="ht-fields">
    <div class="ht-f">
        <label for="{{ $p }}_name">{{ __('hotel.type_name') }} *</label>
        <input class="ht-in" name="name" id="{{ $p }}_name" required maxlength="100" value="{{ $t ? $t->name : old('name') }}" placeholder="{{ __('hotel.type_name_ph') }}">
        <span class="ht-hint">{{ __('hotel.type_name_hint') }}</span>
    </div>
    <div class="ht-f">
        <label for="{{ $p }}_cap">{{ __('hotel.sleeps_label') }}</label>
        <input class="ht-in" type="number" min="1" max="20" name="capacity" id="{{ $p }}_cap" value="{{ $t ? $t->capacity : old('capacity', 2) }}">
        <span class="ht-hint">{{ __('hotel.sleeps_hint') }}</span>
    </div>
    <div class="ht-f">
        <label for="{{ $p }}_desc">{{ __('hotel.type_note') }}</label>
        <input class="ht-in" name="description" id="{{ $p }}_desc" maxlength="255" value="{{ $t ? $t->description : old('description') }}" placeholder="{{ __('hotel.type_note_ph') }}">
    </div>
</div>
<div class="ht-prices">
    <h3>{{ __('hotel.prices_title') }}</h3>
    <p class="ht-hint" style="margin:0 0 12px;">{{ __('hotel.prices_sub', ['cur' => $cur]) }}</p>
    <div class="ht-fields">
        <div class="ht-f">
            <label for="{{ $p }}_ro">{{ __('hotel.plan_room_only') }} *</label>
            <input class="ht-in" name="rate_room_only" id="{{ $p }}_ro" required inputmode="decimal" value="{{ $val('rate_room_only') }}" placeholder="25000">
            <span class="ht-hint">{{ __('hotel.price_ro_hint') }}</span>
        </div>
        <div class="ht-f">
            <label for="{{ $p }}_bb">{{ __('hotel.price_bb') }}</label>
            <input class="ht-in" name="rate_bb" id="{{ $p }}_bb" inputmode="decimal" value="{{ $val('rate_bb') }}">
            <span class="ht-hint">{{ __('hotel.price_bb_hint') }}</span>
        </div>
        <div class="ht-f">
            <label for="{{ $p }}_co">{{ __('hotel.price_co') }}</label>
            <input class="ht-in" name="rate_corporate" id="{{ $p }}_co" inputmode="decimal" value="{{ $val('rate_corporate') }}">
            <span class="ht-hint">{{ __('hotel.price_co_hint') }}</span>
        </div>
        <div class="ht-f">
            <label for="{{ $p }}_tax">{{ __('hotel.col_tourist_tax') }}</label>
            <input class="ht-in" name="tourist_tax" id="{{ $p }}_tax" inputmode="decimal" value="{{ $val('tourist_tax') }}">
            <span class="ht-hint">{{ __('hotel.price_tax_hint') }}</span>
        </div>
    </div>
</div>
