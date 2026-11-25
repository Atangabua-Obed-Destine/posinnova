{{--
    PosInnova: pressing permissions on the Roles screen (role/create, role/edit).
    With the pressing module off, the pressing permissions a role already holds are re-posted
    as hidden fields: RoleController syncs exactly what is posted.
--}}
@php
    $pr_perms = ['pressing.view', 'pressing.counter', 'pressing.workshop', 'pressing.reports', 'pressing.setup'];
    $pr_has = $role_permissions ?? [];
@endphp
@if (in_array('pressing', $enabled_modules))
    <div class="row check_group" id="pressing_permissions">
        <div class="col-md-1">
            <h4>@lang('pressing.module')</h4>
        </div>
        <div class="col-md-2">
            <div class="checkbox">
                <label>
                    <input type="checkbox" class="check_all input-icheck"> {{ __('role.select_all') }}
                </label>
            </div>
        </div>
        <div class="col-md-9">
            @foreach ($pr_perms as $pr_perm)
                <div class="col-md-12">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('permissions[]', $pr_perm, in_array($pr_perm, $pr_has), ['class' => 'input-icheck']) !!}
                            {{ __('pressing.perm_' . str_replace('pressing.', '', $pr_perm)) }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <hr>
@else
    @foreach ($pr_perms as $pr_perm)
        @if (in_array($pr_perm, $pr_has))
            <input type="hidden" name="permissions[]" value="{{ $pr_perm }}">
        @endif
    @endforeach
@endif
