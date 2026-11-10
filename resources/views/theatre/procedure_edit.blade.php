<div class="modal-dialog" role="document">
    <div class="modal-content">
        {!! Form::open(['url' => action([\App\Http\Controllers\TheatreController::class, 'updateProcedure'], [$procedure->id]), 'method' => 'PUT', 'id' => 'procedure_edit_form']) !!}
        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang('lang_v1.edit_procedure')</h4>
        </div>
        <div class="modal-body">
            @include('theatre.partials.procedure_fields')
            <div class="form-group">
                <label>{!! Form::checkbox('is_active', 1, $procedure->is_active, ['class' => 'input-icheck']) !!} @lang('lang_v1.is_active')</label>
            </div>
        </div>
        <div class="modal-footer">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.update')</button>
            <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
        </div>
        {!! Form::close() !!}
    </div>
</div>
