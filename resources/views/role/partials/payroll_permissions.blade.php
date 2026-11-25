{{--
    PosInnova: payroll permissions on the Roles screen (role/create, role/edit).
    With the payroll module off, the payroll permissions a role already holds are re-posted
    as hidden fields: RoleController syncs exactly what is posted.
--}}
@php
    $py_perms = ['payroll-view', 'payroll-action', 'payroll-approve', 'payroll-print', 'payroll-report', 'payroll-setting', 'payroll-loans', 'payroll-remit'];
    $py_has = $role_permissions ?? [];
@endphp
@if (in_array('payroll', $enabled_modules))
    <div class="row check_group" id="payroll_permissions">
        <div class="col-md-1">
            <h4>@lang('payroll.module')</h4>
        </div>
        <div class="col-md-2">
            <div class="checkbox">
                <label>
                    <input type="checkbox" class="check_all input-icheck"> {{ __('role.select_all') }}
                </label>
            </div>
        </div>
        <div class="col-md-9">
            @foreach ($py_perms as $py_perm)
                <div class="col-md-12">
                    <div class="checkbox">
                        <label>
                            {!! Form::checkbox('permissions[]', $py_perm, in_array($py_perm, $py_has), ['class' => 'input-icheck']) !!}
                            {{ __('payroll.perm_' . str_replace('payroll-', '', $py_perm)) }}
                        </label>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    <hr>
@else
    @foreach ($py_perms as $py_perm)
        @if (in_array($py_perm, $py_has))
            <input type="hidden" name="permissions[]" value="{{ $py_perm }}">
        @endif
    @endforeach
@endif
