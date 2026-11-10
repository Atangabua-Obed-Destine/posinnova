@php
    $modalities = ['' => __('messages.please_select'), 'X-Ray' => 'X-Ray', 'Ultrasound' => 'Ultrasound', 'CT' => 'CT Scan', 'MRI' => 'MRI', 'Echo' => 'Echocardiography', 'Other' => __('lang_v1.other')];
@endphp
<div class="row">
    <div class="col-sm-8">
        <div class="form-group">
            {!! Form::label('name', __('lang_v1.study_name') . ':*') !!}
            {!! Form::text('name', $imaging->name ?? null, ['class' => 'form-control', 'required', 'maxlength' => 191, 'placeholder' => __('lang_v1.study_name_placeholder')]) !!}
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('code', __('lang_v1.code') . ':') !!}
            {!! Form::text('code', $imaging->code ?? null, ['class' => 'form-control', 'maxlength' => 50]) !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('modality', __('lang_v1.modality') . ':') !!}
            {!! Form::select('modality', $modalities, $imaging->modality ?? null, ['class' => 'form-control']) !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('fee', __('lang_v1.fee') . ':') !!}
            {!! Form::text('fee', $imaging->fee ?? 0, ['class' => 'form-control input_number']) !!}
        </div>
    </div>
</div>
