<div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => action([\App\Http\Controllers\LabTestController::class, 'update'], [$lab_test->id]), 'method' => 'PUT', 'id' => 'lab_test_edit_form']) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('lang_v1.edit_lab_test')</h4>
        </div>
        <div class="modal-body">
            @include('lab.partials.test_fields')
            <div class="form-group">
                <label>
                    {!! Form::checkbox('is_active', 1, $lab_test->is_active, ['class' => 'input-icheck']) !!}
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
