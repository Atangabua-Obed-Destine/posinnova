{{--
    PosInnova: accounting permissions on the Roles screen (role/create, role/edit), with
    paxhitestbed's permission names. With accounting off, the ones a role already holds are
    re-posted as hidden fields (RoleController syncs exactly what is posted).
--}}
@php
    $lg_has = $role_permissions ?? [];
@endphp
@if (in_array('accounting', $enabled_modules))
    @foreach (\App\Ledger\Permissions::GROUPS as $lg_group => $lg_perms)
        <div class="row check_group" id="ledger_permissions_{{ $lg_group }}">
            <div class="col-md-1">
                <h4>{{ $loop->first ? __('ledger.module') : '' }}</h4>
            </div>
            <div class="col-md-2">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" class="check_all input-icheck"> {{ __('ledger.perm_group_' . $lg_group) }}
                    </label>
                </div>
            </div>
            <div class="col-md-9">
                @foreach ($lg_perms as $lg_perm)
                    <div class="col-md-4">
                        <div class="checkbox">
                            <label>
                                {!! Form::checkbox('permissions[]', $lg_perm, in_array($lg_perm, $lg_has), ['class' => 'input-icheck']) !!}
                                {{ \App\Ledger\Permissions::label($lg_perm) }}
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
    <hr>
@else
    @foreach (\App\Ledger\Permissions::all() as $lg_perm)
        @if (in_array($lg_perm, $lg_has))
            <input type="hidden" name="permissions[]" value="{{ $lg_perm }}">
        @endif
    @endforeach
@endif
