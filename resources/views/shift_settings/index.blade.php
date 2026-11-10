@extends('layouts.app')
@section('title', __('lang_v1.shift_settings'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.shift_settings')</h1>
</section>

<!-- Main content -->
<section class="content">
  {!! Form::open(['url' => action([\App\Http\Controllers\ShiftSettingController::class, 'update']), 'method' => 'post', 'id' => 'shift_settings_form']) !!}
  <div class="row">
    <div class="col-md-8 col-md-offset-2">
      <div class="box box-solid">
        <div class="box-header">
          <h3 class="box-title">@lang('lang_v1.shift_settings')</h3>
        </div>
        <div class="box-body">
          <p class="text-muted">@lang('lang_v1.shift_settings_help')</p>

          <div class="form-group">
            <label>
              {!! Form::checkbox('enable_shift', 1, !empty($settings['enabled']), ['id' => 'enable_shift']) !!}
              @lang('lang_v1.enable_shift')
            </label>
            <p class="help-block">@lang('lang_v1.enable_shift_help')</p>
          </div>

          <div id="shift_time_fields" class="@if(empty($settings['enabled'])) hide @endif">
            <div class="row">
              <div class="col-sm-6">
                <div class="form-group">
                  {!! Form::label('shift_morning_start', __('lang_v1.morning_shift_start') . ':*') !!}
                  {!! Form::input('time', 'shift_morning_start', $settings['morning_start'], ['class' => 'form-control', 'id' => 'shift_morning_start']) !!}
                </div>
              </div>
              <div class="col-sm-6">
                <div class="form-group">
                  {!! Form::label('shift_morning_end', __('lang_v1.morning_shift_end') . ':*') !!}
                  {!! Form::input('time', 'shift_morning_end', $settings['morning_end'], ['class' => 'form-control', 'id' => 'shift_morning_end']) !!}
                </div>
              </div>
            </div>
            <div class="callout callout-info tw-mt-2">
              <h4><i class="fa fa-moon"></i> @lang('lang_v1.night_shift')</h4>
              <p>
                <strong id="night_window_display"></strong>
                <br><small>@lang('lang_v1.night_shift_auto_help')</small>
              </p>
            </div>
          </div>

          <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white pull-right">@lang('messages.update')</button>
          <div class="clearfix"></div>
        </div>
      </div>
    </div>
  </div>
  {!! Form::close() !!}
</section>
<!-- /.content -->

@endsection

@section('javascript')
<script>
  $(function () {
    function refreshNight() {
      var s = $('#shift_morning_start').val() || '06:00';
      var e = $('#shift_morning_end').val() || '18:00';
      // Night runs from the end of morning until the next morning start.
      $('#night_window_display').text(e + '  →  ' + s);
    }
    $('#enable_shift').on('change', function () {
      $('#shift_time_fields').toggleClass('hide', !this.checked);
    });
    $('#shift_morning_start, #shift_morning_end').on('change input', refreshNight);
    refreshNight();
  });
</script>
@endsection
