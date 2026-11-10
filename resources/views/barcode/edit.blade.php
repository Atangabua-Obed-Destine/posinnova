@extends('layouts.app')
@section('title',  __('barcode.edit_barcode_setting'))

@section('content')
<style type="text/css">



</style>
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('barcode.edit_barcode_setting')</h1>
</section>

<!-- Main content -->
<section class="content">
{!! Form::open(['url' => action([\App\Http\Controllers\BarcodeController::class, 'update'], [$barcode->id]), 'method' => 'PUT', 
'id' => 'add_barcode_settings_form' ]) !!}
  <div class="box box-solid">
    <div class="box-body">
      <div class="row">
        <div class="col-sm-12">
          <div class="form-group">
            {!! Form::label('name', __('barcode.setting_name') . ':*') !!}
              {!! Form::text('name', $barcode->name, ['class' => 'form-control', 'required',
              'placeholder' => __('barcode.setting_name')]); !!}
          </div>
        </div>
        <div class="col-sm-12">
          <div class="form-group">
            {!! Form::label('description', __('barcode.setting_description') ) !!}
              {!! Form::textarea('description', $barcode->description, ['class' => 'form-control',
              'placeholder' => __('barcode.setting_description'), 'rows' => 3]); !!}
          </div>
        </div>
        <div class="col-sm-12">
          <div class="form-group">
            <div class="checkbox">
              <label>
                {!! Form::checkbox('is_continuous', 1, $barcode->is_continuous, ['id' => 'is_continuous']); !!} @lang('barcode.is_continuous')</label>
              </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('top_margin', __('barcode.top_margin') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <span class="glyphicon glyphicon-arrow-up" aria-hidden="true"></span>
              </span>
              {!! Form::number('top_margin', $barcode->top_margin, ['class' => 'form-control',
              'placeholder' => __('barcode.top_margin'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('left_margin', __('barcode.left_margin') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <span class="glyphicon glyphicon-arrow-left" aria-hidden="true"></span>
              </span>
              {!! Form::number('left_margin', $barcode->left_margin, ['class' => 'form-control',
              'placeholder' => __('barcode.left_margin'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('width', __('barcode.width') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-text-width" aria-hidden="true"></i>
              </span>
              {!! Form::number('width', $barcode->width, ['class' => 'form-control',
              'placeholder' => __('barcode.width'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('height', __('barcode.height') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-text-height" aria-hidden="true"></i>
              </span>
              {!! Form::number('height', $barcode->height, ['class' => 'form-control',
              'placeholder' => __('barcode.height'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('barcode_width_percent', 'Barcode Image Width (%)' . ' :*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-text-width" aria-hidden="true"></i>
              </span>
              {!! Form::number('barcode_width_percent', $barcode->barcode_width_percent, ['class' => 'form-control',
              'placeholder' => 'Barcode Image Width (%)', 'min' => 1, 'max' => 100, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('barcode_height_percent', 'Barcode Image Height (%)' . ' :*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-text-height" aria-hidden="true"></i>
              </span>
              {!! Form::number('barcode_height_percent', $barcode->barcode_height_percent, ['class' => 'form-control',
              'placeholder' => 'Barcode Image Height (%)', 'min' => 1, 'max' => 100, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('paper_width', __('barcode.paper_width') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-text-width" aria-hidden="true"></i>
              </span>
              {!! Form::number('paper_width', $barcode->paper_width, ['class' => 'form-control',
              'placeholder' => __('barcode.paper_width'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="col-sm-6 paper_height_div @if( $barcode->is_continuous ) {{ 'hide' }} @endif">
          <div class="form-group">
            {!! Form::label('paper_height', __('barcode.paper_height') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-text-height" aria-hidden="true"></i>
              </span>
              {!! Form::number('paper_height', $barcode->paper_height, ['class' => 'form-control',
              'placeholder' => __('barcode.paper_height'), 'min' => 0.1, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('stickers_in_one_row', __('barcode.stickers_in_one_row'). ':*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-ellipsis-h" aria-hidden="true"></i>
              </span>
              {!! Form::number('stickers_in_one_row', $barcode->stickers_in_one_row, ['class' => 'form-control',
              'placeholder' => __('barcode.stickers_in_one_row'), 'min' => 1, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('row_distance', __('barcode.row_distance') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <span class="glyphicon glyphicon-resize-vertical" aria-hidden="true"></span>
              </span>
              {!! Form::number('row_distance', $barcode->row_distance, ['class' => 'form-control',
              'placeholder' => __('barcode.row_distance'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="form-group">
            {!! Form::label('col_distance', __('barcode.col_distance') . ' ('. __('barcode.in_in') . '):*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <span class="glyphicon glyphicon-resize-horizontal" aria-hidden="true"></span>
              </span>
              {!! Form::number('col_distance', $barcode->col_distance, ['class' => 'form-control',
              'placeholder' => __('barcode.col_distance'), 'min' => 0, 'step' => 0.00001, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-6 stickers_per_sheet_div @if( $barcode->is_continuous ) {{ 'hide' }} @endif">
          <div class="form-group">
            {!! Form::label('stickers_in_one_sheet', __('barcode.stickers_in_one_sheet') . ':*') !!}
            <div class="input-group">
              <span class="input-group-addon">
                <i class="fa fa-th" aria-hidden="true"></i>
              </span>
              {!! Form::number('stickers_in_one_sheet', $barcode->stickers_in_one_sheet, ['class' => 'form-control',
              'placeholder' => __('barcode.stickers_in_one_sheet'), 'min' => 1, 'required']); !!}
            </div>
          </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-sm-12 text-center">
          <button type="button" class="btn btn-info btn-big" id="preview_barcode_btn">@lang('labels.preview')</button>
          <button type="submit" class="btn btn-primary btn-big">@lang('messages.update')</button>
        </div>
      </div>
    </div>
  </div>
  {!! Form::close() !!}
</section>
<!-- /.content -->
@endsection

@section('javascript')
<script type="text/javascript">
$(document).on('click', '#preview_barcode_btn', function(e) {
    e.preventDefault();
    let url = '{{ action([\App\Http\Controllers\BarcodeController::class, "preview"]) }}';
    let form = $('form#add_barcode_settings_form');
    let old_action = form.attr('action');
    let old_target = form.attr('target');
    let method_input = form.find('input[name="_method"]');
    
    if (method_input.length) {
        method_input.prop('disabled', true);
    }

    form.attr('action', url);
    form.attr('target', '_blank');
    form.submit();
    
    setTimeout(() => {
        form.attr('action', old_action);
        if (old_target) {
            form.attr('target', old_target);
        } else {
            form.removeAttr('target');
        }
        if (method_input.length) {
            method_input.prop('disabled', false);
        }
    }, 100);
});
</script>
@endsection