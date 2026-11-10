@php
    $cats = ['surgery' => __('lang_v1.procedure_cat_surgery'), 'obstetric' => __('lang_v1.procedure_cat_obstetric'), 'dental' => __('lang_v1.procedure_cat_dental'), 'other' => __('lang_v1.procedure_cat_other')];
@endphp
<div class="row">
    <div class="col-sm-8">
        <div class="form-group">
            {!! Form::label('name', __('lang_v1.procedure_name') . ':*') !!}
            {!! Form::text('name', $procedure->name ?? null, ['class' => 'form-control', 'required', 'maxlength' => 191]) !!}
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('category', __('lang_v1.category') . ':*') !!}
            {!! Form::select('category', $cats, $procedure->category ?? 'surgery', ['class' => 'form-control', 'required']) !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('fee', __('lang_v1.procedure_fee') . ':') !!}
            {!! Form::text('fee', $procedure->fee ?? 0, ['class' => 'form-control input_number']) !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('anaesthesia_fee', __('lang_v1.anaesthesia_fee') . ':') !!}
            {!! Form::text('anaesthesia_fee', $procedure->anaesthesia_fee ?? 0, ['class' => 'form-control input_number']) !!}
        </div>
    </div>
</div>
