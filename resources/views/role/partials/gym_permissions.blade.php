{{--
    PosInnova: gym permissions on the Roles screen (role/create, role/edit).
    With the gym module off, the gym permissions a role already holds are re-posted
    as hidden fields: RoleController syncs exactly what is posted.
--}}
@php
    $gy_perms = ['gym.door', 'gym.members', 'gym.setup'];
    $gy_has = $role_permissions ?? [];
@endphp
@if (in_array('gym', $enabled_modules))
    <div class="row check_group" id="gym_permissions">
        <div class="col-md-1">
            <h4>@lang('gym.module')</h4>
        </div>
        <div class="col-md-2">
            <div class="checkbox">
                <label>
                    <input type="checkbox" class="check_all input-icheck"> {{ __('role.select_all') }}
                </label>
            </div>
        </div>
        <div class="col-md-9">
            @foreach ($gy_perms as $gy_perm)
                <div class="col-md-12">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('permissions[]', $gy_perm, in_array($gy_perm, $gy_has), ['class' => 'input-icheck']) !!}
                            {{ __('gym.perm_' . str_replace('gym.', '', $gy_perm)) }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <hr>
@else
    @foreach ($gy_perms as $gy_perm)
        @if (in_array($gy_perm, $gy_has))
            <input type="hidden" name="permissions[]" value="{{ $gy_perm }}">
        @endif
    @endforeach
@endif
