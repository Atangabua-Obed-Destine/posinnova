@extends('layouts.app')
@section('title', $package->exists ? __('clinic.edit_package') : __('clinic.add_package'))

@php $fmt = function ($n) { return app(\App\Utils\Util::class)->num_f((float) $n); }; @endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $package->exists ? __('clinic.edit_package') : __('clinic.add_package') }}</h1>
</section>

<section class="content">
    {!! Form::open(['url' => $package->exists
        ? action([\App\Http\Controllers\ClinicPackageController::class, 'update'], [$package->id])
        : action([\App\Http\Controllers\ClinicPackageController::class, 'store']),
        'method' => 'post', 'id' => 'clinic_package_form']) !!}

    @component('components.widget', ['class' => 'box-solid'])
        <div class="row">
            <div class="col-sm-6">
                <div class="form-group">
                    {!! Form::label('name', __('clinic.package_name') . ':*') !!}
                    {!! Form::text('name', old('name', $package->name), ['class' => 'form-control', 'required', 'maxlength' => 120, 'placeholder' => __('clinic.package_name_placeholder')]) !!}
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('price', __('clinic.package_price') . ':*') !!}
                    {!! Form::text('price', old('price', $package->exists ? $fmt($package->price) : ''), ['class' => 'form-control input_number', 'required']) !!}
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    {!! Form::label('sort_order', __('clinic.sort_order') . ':') !!}
                    {!! Form::number('sort_order', old('sort_order', $package->sort_order ?? 0), ['class' => 'form-control', 'min' => 0, 'max' => 999]) !!}
                </div>
            </div>
            <div class="clearfix"></div>
            <div class="col-sm-12">
                <div class="form-group">
                    {!! Form::label('description', __('clinic.package_covers') . ':') !!}
                    {!! Form::textarea('description', old('description', $package->description), ['class' => 'form-control', 'rows' => 3, 'maxlength' => 2000, 'placeholder' => __('clinic.package_covers_placeholder')]) !!}
                    <p class="help-block">@lang('clinic.package_covers_help')</p>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="form-group">
                    <label>@lang('clinic.package_billing'):</label>
                    <div class="radio">
                        <label>{!! Form::radio('billing', 'per_visit', old('billing', $package->billing) != 'monthly', ['class' => 'pkg_billing']) !!} <strong>@lang('clinic.billing_per_visit')</strong></label>
                        <p class="help-block" style="margin-left:20px;">@lang('clinic.billing_per_visit_help')</p>
                    </div>
                    <div class="radio">
                        <label>{!! Form::radio('billing', 'monthly', old('billing', $package->billing) == 'monthly', ['class' => 'pkg_billing']) !!} <strong>@lang('clinic.billing_monthly')</strong></label>
                        <p class="help-block" style="margin-left:20px;">@lang('clinic.billing_monthly_help')</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-3" id="visits_per_month_wrap">
                <div class="form-group">
                    {!! Form::label('visits_per_month', __('clinic.visits_per_month') . ':') !!}
                    {!! Form::number('visits_per_month', old('visits_per_month', $package->visits_per_month), ['class' => 'form-control', 'min' => 1, 'max' => 999, 'placeholder' => __('clinic.visits_unlimited')]) !!}
                    <p class="help-block">@lang('clinic.visits_per_month_help')</p>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>@lang('lang_v1.status'):</label>
                    <div class="checkbox">
                        <label>{!! Form::checkbox('is_active', 1, (bool) old('is_active', $package->is_active)) !!} @lang('clinic.package_active_help')</label>
                    </div>
                </div>
            </div>
        </div>
    @endcomponent

    <div class="text-center" style="margin-bottom:20px;">
        <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">@lang('messages.save')</button>
        <a href="{{ action([\App\Http\Controllers\ClinicPackageController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
    </div>
    {!! Form::close() !!}
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        function toggleVisits() {
            $('#visits_per_month_wrap').toggle($('input.pkg_billing:checked').val() === 'monthly');
        }
        $(document).on('change', 'input.pkg_billing', toggleVisits);
        toggleVisits();
    });
</script>
@endsection
