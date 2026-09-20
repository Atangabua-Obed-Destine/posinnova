@extends('layouts.app')
@section('title', __('lang_v1.shift_summary_report'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.shift_summary_report')</h1>
</section>

<!-- Main content -->
<section class="content">

  @component('components.filters', ['title' => __('report.filters')])
    {!! Form::open(['url' => action([\App\Http\Controllers\ReportController::class, 'getShiftSummaryReport']), 'method' => 'get', 'id' => 'shift_summary_filter_form']) !!}
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('start_date', __('business.start_date') . ':') !!}
          {!! Form::input('date', 'start_date', $start_date, ['class' => 'form-control']) !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('end_date', __('lang_v1.end_date') . ':') !!}
          {!! Form::input('date', 'end_date', $end_date, ['class' => 'form-control']) !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          {!! Form::label('location_id', __('business.business_location') . ':') !!}
          {!! Form::select('location_id', $business_locations, $location_id, ['class' => 'form-control select2', 'placeholder' => __('report.all')]) !!}
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          <label>&nbsp;</label><br>
          <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('report.apply_filters')</button>
        </div>
      </div>
    {!! Form::close() !!}
  @endcomponent

  @php
    $morning = $by_shift->get('morning');
    $night = $by_shift->get('night');
    $morning_sales = $morning->total_sales ?? 0;
    $night_sales = $night->total_sales ?? 0;
    $morning_inv = $morning->total_invoices ?? 0;
    $night_inv = $night->total_invoices ?? 0;
  @endphp

  <div class="row">
    <div class="col-md-6">
      <div class="box box-solid" style="border-top:3px solid #f39c12;">
        <div class="box-body text-center">
          <i class="fa fa-sun-o" style="font-size:38px;color:#f39c12;"></i>
          <h3 class="tw-font-bold" style="margin:8px 0;">{{ $shift_labels['morning'] }}</h3>
          <p class="text-muted">{{ $shift_settings['morning_start'] }} &rarr; {{ $shift_settings['morning_end'] }}</p>
          <h2 class="tw-font-bold"><span class="display_currency" data-currency_symbol="true">{{ $morning_sales }}</span></h2>
          <p>{{ $morning_inv }} @lang('lang_v1.invoices')</p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="box box-solid" style="border-top:3px solid #34495e;">
        <div class="box-body text-center">
          <i class="fa fa-moon-o" style="font-size:38px;color:#34495e;"></i>
          <h3 class="tw-font-bold" style="margin:8px 0;">{{ $shift_labels['night'] }}</h3>
          <p class="text-muted">{{ $shift_settings['night_start'] }} &rarr; {{ $shift_settings['night_end'] }}</p>
          <h2 class="tw-font-bold"><span class="display_currency" data-currency_symbol="true">{{ $night_sales }}</span></h2>
          <p>{{ $night_inv }} @lang('lang_v1.invoices')</p>
        </div>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div class="box box-primary">
        <div class="box-header">
          <h3 class="box-title">@lang('lang_v1.shift_sales_by_cashier')</h3>
        </div>
        <div class="box-body">
          <table class="table table-bordered table-striped">
            <thead>
              <tr>
                <th>@lang('report.user')</th>
                <th>{{ $shift_labels['morning'] }}</th>
                <th>{{ $shift_labels['night'] }}</th>
                <th>@lang('sale.total')</th>
              </tr>
            </thead>
            <tbody>
              @forelse($by_cashier as $row)
              <tr>
                <td>{{ trim($row->cashier_name) ?: '-' }}</td>
                <td><span class="display_currency" data-currency_symbol="true">{{ $row->morning_sales }}</span> <small class="text-muted">({{ $row->morning_invoices }})</small></td>
                <td><span class="display_currency" data-currency_symbol="true">{{ $row->night_sales }}</span> <small class="text-muted">({{ $row->night_invoices }})</small></td>
                <td><span class="display_currency" data-currency_symbol="true">{{ $row->total_sales }}</span></td>
              </tr>
              @empty
              <tr><td colspan="4" class="text-center">@lang('lang_v1.no_shift_sales')</td></tr>
              @endforelse
            </tbody>
            <tfoot>
              <tr class="bg-gray">
                <td><strong>@lang('sale.total')</strong></td>
                <td><strong><span class="display_currency" data-currency_symbol="true">{{ $morning_sales }}</span></strong></td>
                <td><strong><span class="display_currency" data-currency_symbol="true">{{ $night_sales }}</span></strong></td>
                <td><strong><span class="display_currency" data-currency_symbol="true">{{ $morning_sales + $night_sales }}</span></strong></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>

</section>
<!-- /.content -->
@endsection
