<div class="row">
    <div class="col-sm-8">
        <div class="form-group">
            {!! Form::label('name', __('lang_v1.test_name') . ':*') !!}
            {!! Form::text('name', $lab_test->name ?? null, ['class' => 'form-control', 'required', 'maxlength' => 191, 'placeholder' => __('lang_v1.test_name')]) !!}
        </div>
    </div>
    <div class="col-sm-4">
        <div class="form-group">
            {!! Form::label('code', __('lang_v1.code') . ':') !!}
            {!! Form::text('code', $lab_test->code ?? null, ['class' => 'form-control', 'maxlength' => 50, 'placeholder' => 'e.g. FBC']) !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('category', __('lang_v1.category') . ':') !!}
            {!! Form::text('category', $lab_test->category ?? null, ['class' => 'form-control', 'maxlength' => 100, 'placeholder' => __('lang_v1.lab_category_placeholder')]) !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('turnaround_hours', __('lang_v1.turnaround_hours') . ':') !!}
            {!! Form::number('turnaround_hours', $lab_test->turnaround_hours ?? null, ['class' => 'form-control', 'min' => 0]) !!}
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('normal_range', __('lang_v1.normal_range') . ':') !!}
            {!! Form::text('normal_range', $lab_test->normal_range ?? null, ['class' => 'form-control', 'maxlength' => 191, 'placeholder' => 'e.g. 4.0 - 11.0']) !!}
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('unit', __('lang_v1.unit') . ':') !!}
            {!! Form::text('unit', $lab_test->unit ?? null, ['class' => 'form-control', 'maxlength' => 50, 'placeholder' => 'e.g. g/dL']) !!}
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            {!! Form::label('fee', __('lang_v1.fee') . ':') !!}
            {!! Form::text('fee', $lab_test->fee ?? 0, ['class' => 'form-control input_number']) !!}
        </div>
    </div>
    <div class="col-sm-12">
        <div class="form-group">
            {!! Form::label('result_options', __('clinic.lab_result_options') . ':') !!}
            {!! Form::text('result_options', $lab_test->result_options ?? null, ['class' => 'form-control', 'maxlength' => 500, 'placeholder' => __('clinic.lab_result_options_ph')]) !!}
            <p class="help-block">@lang('clinic.lab_result_options_help')</p>
        </div>
    </div>
</div>
