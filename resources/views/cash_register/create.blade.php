@extends('layouts.app')
@section('title',  __('cash_register.open_cash_register'))

@section('content')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        @if(!empty($shift_enabled))
            @lang('lang_v1.start_shift')
        @else
            @lang('cash_register.open_cash_register')
        @endif
    </h1>
</section>

<!-- Main content -->
<section class="content">
{!! Form::open(['url' => action([\App\Http\Controllers\CashRegisterController::class, 'store']), 'method' => 'post',
'id' => 'add_cash_register_form', 'data-record-confirm' => empty($shift_enabled) ? 'register_open' : null ]) !!}
  <div class="box box-solid">
    <div class="box-body">
    <br><br>
    <input type="hidden" name="sub_type" value="{{$sub_type}}">
      <div class="row">
        @if($business_locations->count() > 0)

        @if(!empty($shift_enabled))
          {{-- PosInnova hospital shift: one-click start, drawer auto-zero. --}}
          <div class="col-sm-8 col-sm-offset-2">
            <div class="tw-text-center" style="margin-bottom:20px;">
              @if($current_shift == 'morning')
                <i class="fa fa-sun" style="font-size:54px;color:#f39c12;"></i>
              @else
                <i class="fa fa-moon" style="font-size:54px;color:#34495e;"></i>
              @endif
              <h2 class="tw-font-bold" style="margin-top:10px;">{{ $current_shift_label }}</h2>
              <p class="text-muted" style="font-size:16px;">
                @if($current_shift == 'morning')
                  {{ $shift_settings['morning_start'] }} &nbsp;&rarr;&nbsp; {{ $shift_settings['morning_end'] }}
                @else
                  {{ $shift_settings['night_start'] }} &nbsp;&rarr;&nbsp; {{ $shift_settings['night_end'] }}
                @endif
              </p>
            </div>

            <div class="callout callout-info">
              <p><i class="fa fa-info-circle"></i> @lang('lang_v1.shift_starts_at_zero')</p>
            </div>

            @if(count($business_locations) > 1)
              <div class="form-group">
                {!! Form::label('location_id', __('business.business_location') . ':') !!}
                {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2',
                  'placeholder' => __('lang_v1.select_location'), 'required']); !!}
              </div>
            @else
              {!! Form::hidden('location_id', array_key_first($business_locations->toArray()) ); !!}
            @endif

            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white tw-w-full">
              <i class="fa fa-play"></i> {{ __('lang_v1.start_shift_named', ['shift' => $current_shift_label]) }}
            </button>
          </div>

        @else
          {{-- Standard cash register open. --}}
          <div class="col-sm-8 col-sm-offset-2">
            <div class="form-group">
              {!! Form::label('amount', __('cash_register.cash_in_hand') . ':*') !!}
              {!! Form::text('amount', null, ['class' => 'form-control input_number',
                'placeholder' => __('cash_register.enter_amount'), 'required', 'data-confirm-amount' => 1]); !!}
            </div>
          </div>
          @if(count($business_locations) > 1)
          <div class="clearfix"></div>
          <div class="col-sm-8 col-sm-offset-2">
            <div class="form-group">
              {!! Form::label('location_id', __('business.business_location') . ':') !!}
                {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2',
                'placeholder' => __('lang_v1.select_location')]); !!}
            </div>
          </div>
          @else
            {!! Form::hidden('location_id', array_key_first($business_locations->toArray()) ); !!}
          @endif
          <div class="col-sm-8 col-sm-offset-2">
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white pull-right">@lang('cash_register.open_register')</button>
          </div>
        @endif

        @else
        <div class="col-sm-8 col-sm-offset-2 text-center">
          <h3>@lang('lang_v1.no_location_access_found')</h3>
        </div>
      @endif
      </div>
      <br><br>
    </div>
  </div>
  {!! Form::close() !!}
</section>
<!-- /.content -->
@endsection
