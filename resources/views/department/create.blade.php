<div class="modal-dialog" role="document">
    <div class="modal-content">
        {!! Form::open([
            'url' => action([\App\Http\Controllers\DepartmentController::class, 'store']),
            'method' => 'post',
            'id' => $quick_add ? 'quick_add_department_form' : 'department_add_form',
        ]) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
            <h4 class="modal-title">@lang('lang_v1.add_department')</h4>
        </div>

        <div class="modal-body">
            <div class="form-group">
                {!! Form::label('name', __('lang_v1.department_name') . ':*') !!}
                {!! Form::text('name', null, ['class' => 'form-control', 'required', 'maxlength' => 191, 'placeholder' => __('lang_v1.department_name')]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('code', __('lang_v1.code') . ':') !!}
                {!! Form::text('code', null, ['class' => 'form-control', 'maxlength' => 50, 'placeholder' => __('lang_v1.code_optional')]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('hod_name', __('lang_v1.hod_name') . ':') !!}
                {!! Form::text('hod_name', null, ['class' => 'form-control', 'maxlength' => 191, 'placeholder' => __('lang_v1.hod_name')]) !!}
            </div>

            <div class="form-group">
                {!! Form::label('description', __('lang_v1.description') . ':') !!}
                {!! Form::textarea('description', null, ['class' => 'form-control', 'rows' => 3]) !!}
            </div>

            <div class="form-group">
                <label>
                    {!! Form::checkbox('is_active', 1, true, ['class' => 'input-icheck']) !!}
                    @lang('lang_v1.is_active')
                </label>
            </div>
        </div>

        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>

        {!! Form::close() !!}
    </div>
</div>
