<div class="modal-dialog" role="document">
    <div class="modal-content">
        {!! Form::open([
            'url' => action([\App\Http\Controllers\DepartmentController::class, 'update'], [$department->id]),
            'method' => 'PUT',
            'id' => 'department_edit_form',
        ]) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('lang_v1.edit_department')</h4>
        </div>

        <div class="modal-body">
            <div class="form-group">
                {!! Form::label('name', __('lang_v1.department_name') . ':*') !!}
                {!! Form::text('name', $department->name, ['class' => 'form-control', 'required', 'maxlength' => 191]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('code', __('lang_v1.code') . ':') !!}
                {!! Form::text('code', $department->code, ['class' => 'form-control', 'maxlength' => 50]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('hod_name', __('lang_v1.hod_name') . ':') !!}
                {!! Form::text('hod_name', $department->hod_name, ['class' => 'form-control', 'maxlength' => 191]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('department_type', __('lang_v1.department_type') . ':') !!}
                {!! Form::select('department_type', \App\Http\Controllers\WardController::departmentTypes(), $department->department_type ?? 'other', ['class' => 'form-control']) !!}
            </div>

            <div class="form-group">
                {!! Form::label('location_id', __('lang_v1.mapped_location') . ':') !!}
                {!! Form::select('location_id', $business_locations ?? [], $department->location_id ?? null, ['class' => 'form-control']) !!}
                <p class="help-block">@lang('lang_v1.mapped_location_help')</p>
            </div>

            <div class="form-group">
                {!! Form::label('description', __('lang_v1.description') . ':') !!}
                {!! Form::textarea('description', $department->description, ['class' => 'form-control', 'rows' => 3]) !!}
            </div>

            <div class="form-group">
                <label>
                    {!! Form::checkbox('is_active', 1, (bool) $department->is_active, ['class' => 'input-icheck']) !!}
                    @lang('lang_v1.is_active')
                </label>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.update')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>

        {!! Form::close() !!}
    </div>
</div>
