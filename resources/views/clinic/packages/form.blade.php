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
            {{-- per visit: may the fee also cover the patient's return visits for a while? --}}
            @php $cm = old('cover_mode', $package->cover_mode ?: 'none'); @endphp
            <div class="col-sm-12" id="cover_wrap">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:12px 14px; margin-bottom:14px;">
                    <label style="margin-bottom:2px;"><i class="fas fa-redo"></i> @lang('clinic.cover_title')</label>
                    <p class="help-block" style="margin-top:0;">@lang('clinic.cover_help')</p>
                    <div class="row">
                        <div class="col-sm-5">
                            <div class="form-group">
                                <label for="cover_mode">@lang('clinic.cover_mode'):</label>
                                <select name="cover_mode" id="cover_mode" class="form-control">
                                    @foreach (['none', 'days', 'month', 'calendar_month'] as $m)
                                        <option value="{{ $m }}" {{ $cm == $m ? 'selected' : '' }}>@lang('clinic.cover_mode_' . $m)</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-sm-2 cover-opt" id="cover_days_wrap">
                            <div class="form-group">
                                <label for="cover_days">@lang('clinic.cover_days'):</label>
                                <input type="number" name="cover_days" id="cover_days" class="form-control" min="1" max="366" value="{{ old('cover_days', $package->cover_days ?: 30) }}">
                            </div>
                        </div>
                        <div class="col-sm-2 cover-opt">
                            <div class="form-group">
                                <label for="cover_max_visits">@lang('clinic.cover_max'):</label>
                                <input type="number" name="cover_max_visits" id="cover_max_visits" class="form-control" min="1" max="99" placeholder="{{ __('clinic.visits_unlimited') }}" value="{{ old('cover_max_visits', $package->cover_max_visits) }}">
                            </div>
                        </div>
                        <div class="col-sm-3 cover-opt">
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <div class="checkbox" style="margin-top:4px;">
                                    <label><input type="checkbox" name="cover_same_doctor" value="1" {{ old('cover_same_doctor', $package->cover_same_doctor) ? 'checked' : '' }}> @lang('clinic.cover_same_doctor')</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p class="help-block cover-opt" style="margin:0;" id="cover_example"></p>
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
        @php
            $cover_examples = [];
            foreach (['none', 'days', 'month', 'calendar_month'] as $m) { $cover_examples[$m] = __('clinic.cover_example_' . $m); }
        @endphp
        var examples = @json($cover_examples);
        function toggleVisits() {
            var monthly = $('input.pkg_billing:checked').val() === 'monthly';
            $('#visits_per_month_wrap').toggle(monthly);
            $('#cover_wrap').toggle(! monthly);
            var m = $('#cover_mode').val();
            $('.cover-opt').toggle(m !== 'none');
            $('#cover_days_wrap').toggle(m === 'days');
            $('#cover_example').text((examples[m] || '').replace(':n', $('#cover_days').val() || 30));
        }
        $(document).on('change', 'input.pkg_billing, #cover_mode', toggleVisits);
        $(document).on('input', '#cover_days', toggleVisits);
        toggleVisits();
    });
</script>
@endsection
