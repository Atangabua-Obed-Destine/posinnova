@extends('layouts.app')
@section('title', $all ? __('clinic.fee_title') : __('clinic.fee_title_own'))

@php
    $fmt = fn ($n) => app(\App\Utils\Util::class)->num_f((float) $n);
    $F = \App\Http\Controllers\ClinicFeeController::class;
@endphp

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $all ? __('clinic.fee_title') : __('clinic.fee_title_own') }}
        <small class="tw-text-gray-600">@lang('clinic.fee_sub')</small>
    </h1>
</section>

<section class="content">
    <div class="callout callout-info" style="margin-bottom:14px;">
        <p style="margin:0;"><i class="fas fa-info-circle"></i> @lang('clinic.fee_help')</p>
        @if (count($columns) == 1 && array_key_first($columns) === 0)
            <p style="margin:6px 0 0;">{{ __('clinic.fee_default_is', ['fee' => $fmt($columns[0][1])]) }}
                @can('clinic_settings.manage') <a href="{{ action([\App\Http\Controllers\ClinicSettingsController::class, 'index']) }}">@lang('clinic.fee_change_default')</a>@endcan</p>
        @else
            <p style="margin:6px 0 0;">@lang('clinic.fee_packages_note')
                @can('clinic_package.manage') <a href="{{ action([\App\Http\Controllers\ClinicPackageController::class, 'index']) }}">@lang('clinic.packages')</a>@endcan</p>
        @endif
    </div>

    @if (empty($doctors))
        <div class="callout callout-warning"><p>@lang('clinic.appt_no_doctors')</p></div>
    @else
        <form method="POST" action="{{ action([$F, 'save']) }}" id="fees_form">
            @csrf
            @component('components.widget', ['class' => 'box-primary'])
                <div class="table-responsive">
                    <table class="table table-bordered" style="margin:0;">
                        <thead>
                            <tr>
                                <th style="min-width:160px;">@lang('clinic.doctor')</th>
                                @foreach ($columns as $key => [$label, $clinic_price])
                                    <th style="min-width:170px;">{{ $label }}
                                        <br><small class="text-muted" style="font-weight:normal;">{{ __('clinic.fee_clinic_price', ['fee' => $fmt($clinic_price)]) }}</small></th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($doctors as $doctor_id => $name)
                                <tr>
                                    <td style="vertical-align:middle;"><i class="fas fa-user-md text-muted"></i> <strong>{{ $name }}</strong></td>
                                    @foreach ($columns as $key => [$label, $clinic_price])
                                        @php
                                            $own = $fees[$doctor_id][$key] ?? null;
                                            $old = old('fee.' . $doctor_id . '.' . $key);
                                        @endphp
                                        <td>
                                            <input type="text" name="fee[{{ $doctor_id }}][{{ $key }}]" class="form-control input_number js-fee {{ $own !== null ? 'has-own' : '' }}"
                                                value="{{ $old !== null ? $old : ($own !== null ? $fmt($own) : '') }}"
                                                placeholder="{{ __('clinic.fee_placeholder', ['fee' => $fmt($clinic_price)]) }}"
                                                aria-label="{{ $name }} · {{ $label }}" inputmode="decimal">
                                            <small class="text-muted js-fee-note">{{ $own !== null ? __('clinic.fee_own_price') : __('clinic.fee_uses_clinic') }}</small>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="help-block" style="margin:10px 0 0;">@lang('clinic.fee_empty_help')</p>
            @endcomponent
            <div class="text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold"><i class="fas fa-save"></i> @lang('messages.save')</button>
            </div>
        </form>
    @endif
</section>
@endsection

@section('css')
<style>
    #fees_form .js-fee.has-own { border-color: #2563eb; background: #eff6ff; font-weight: 700; }
</style>
@endsection

@section('javascript')
<script>
    $(function () {
        var own = @json(__('clinic.fee_own_price')), clinic = @json(__('clinic.fee_uses_clinic'));
        $(document).on('input', '.js-fee', function () {
            var has = $.trim($(this).val()) !== '';
            $(this).toggleClass('has-own', has).siblings('.js-fee-note').text(has ? own : clinic);
        });
    });
</script>
@endsection
