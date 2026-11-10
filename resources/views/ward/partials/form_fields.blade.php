@php $ward = $ward ?? null; @endphp
@component('components.widget', ['class' => 'box-solid'])
    <div class="row">
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('name', __('lang_v1.ward_name') . ':*') !!}
                {!! Form::text('name', $ward->name ?? null, ['class' => 'form-control', 'required', 'maxlength' => 191, 'placeholder' => __('lang_v1.ward_name_placeholder')]) !!}
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('location_id', __('lang_v1.mapped_location') . ':') !!}
                {!! Form::select('location_id', $business_locations, $ward->location_id ?? null, ['class' => 'form-control select2']) !!}
                <p class="help-block">@lang('lang_v1.mapped_location_help')</p>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="form-group">
                {!! Form::label('department_id', __('lang_v1.department') . ':') !!}
                {!! Form::select('department_id', $departments, $ward->department_id ?? null, ['class' => 'form-control select2']) !!}
            </div>
        </div>
        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('total_beds', __('lang_v1.number_of_beds') . ':') !!}
                {!! Form::number('total_beds', $ward->total_beds ?? 0, ['class' => 'form-control', 'min' => 0, 'max' => 500]) !!}
                <p class="help-block">@lang('lang_v1.number_of_beds_help')</p>
            </div>
        </div>
        <div class="col-sm-3">
            <div class="form-group">
                {!! Form::label('bed_prefix', __('lang_v1.bed_prefix') . ':') !!}
                {!! Form::text('bed_prefix', null, ['class' => 'form-control', 'maxlength' => 10, 'placeholder' => 'e.g. M, F']) !!}
            </div>
        </div>
        <div class="col-sm-12">
            <div class="form-group">
                {!! Form::label('description', __('lang_v1.description') . ':') !!}
                {!! Form::textarea('description', $ward->description ?? null, ['class' => 'form-control', 'rows' => 2]) !!}
            </div>
        </div>
    </div>
@endcomponent
