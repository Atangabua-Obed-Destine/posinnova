{{--
    PosInnova: hotel permissions on the Roles screen (role/create, role/edit).
    With the hotel module off, the hotel permissions a role already holds are re-posted
    as hidden fields: RoleController syncs exactly what is posted.
--}}
@php
    $ht_perms = ['hotel.view', 'hotel.reservations', 'hotel.front_desk', 'hotel.manager', 'hotel.housekeeping', 'hotel.night_audit', 'hotel.reports', 'hotel.setup'];
    $ht_has = $role_permissions ?? [];
@endphp
@if (in_array('hotel', $enabled_modules))
    <div class="row check_group" id="hotel_permissions">
        <div class="col-md-1">
            <h4>@lang('hotel.module')</h4>
        </div>
        <div class="col-md-2">
            <div class="checkbox">
                <label>
                    <input type="checkbox" class="check_all input-icheck"> {{ __('role.select_all') }}
                </label>
            </div>
        </div>
        <div class="col-md-9">
            @foreach ($ht_perms as $ht_perm)
                <div class="col-md-12">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('permissions[]', $ht_perm, in_array($ht_perm, $ht_has), ['class' => 'input-icheck']) !!}
                            {{ __('hotel.perm_' . str_replace('hotel.', '', $ht_perm)) }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <hr>
@else
    @foreach ($ht_perms as $ht_perm)
        @if (in_array($ht_perm, $ht_has))
            <input type="hidden" name="permissions[]" value="{{ $ht_perm }}">
        @endif
    @endforeach
@endif
