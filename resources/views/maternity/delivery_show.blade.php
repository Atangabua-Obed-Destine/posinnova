@extends('layouts.app')
@section('title', __('lang_v1.delivery_record'))

@php
    $d = $delivery;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">
                    <i class="fas fa-baby tw-text-pink-500"></i> {{ optional($d->mother)->name }}
                    <small class="tw-text-gray-500">· {{ $d->ref_no }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    {{ __('lang_v1.delivery_type_' . $d->delivery_type) }} · {{ @format_datetime($d->delivery_date) }}
                </p>
            </div>
            <a href="{{ action([\App\Http\Controllers\DeliveryController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                <i class="fa fa-arrow-left"></i> @lang('messages.back')
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.delivery_details')])
                <table class="table table-bordered">
                    <tr><th width="45%">@lang('lang_v1.mother')</th><td>{{ optional($d->mother)->name }}</td></tr>
                    <tr><th>@lang('lang_v1.delivery_date')</th><td>{{ @format_datetime($d->delivery_date) }}</td></tr>
                    <tr><th>@lang('lang_v1.delivery_type')</th><td>{{ __('lang_v1.delivery_type_' . $d->delivery_type) }}</td></tr>
                    <tr><th>@lang('lang_v1.attended_by')</th><td>{{ optional($d->attendedBy)->user_full_name ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.complications')</th><td>{{ $d->complications ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.delivery_fee')</th><td><span class="display_currency" data-currency_symbol="true">{{ $d->fee }}</span></td></tr>
                </table>
            @endcomponent
        </div>
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.baby_details')])
                <table class="table table-bordered">
                    <tr><th width="45%">@lang('lang_v1.baby_sex')</th><td>{{ $d->baby_sex == 'M' ? __('lang_v1.male') : ($d->baby_sex == 'F' ? __('lang_v1.female') : __('lang_v1.unknown')) }}</td></tr>
                    <tr><th>@lang('lang_v1.baby_weight')</th><td>{{ $d->baby_weight ? $d->baby_weight + 0 . ' kg' : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.apgar_1min')</th><td>{{ $d->apgar_1min ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.apgar_5min')</th><td>{{ $d->apgar_5min ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.baby_patient')</th>
                        <td>
                            @if($d->baby)
                                <a href="{{ action([\App\Http\Controllers\PatientController::class, 'show'], [$d->baby->id]) }}">
                                    {{ $d->baby->name }}
                                    @if(optional($d->baby->patientDetails)->file_number)<span class="label label-info">{{ $d->baby->patientDetails->file_number }}</span>@endif
                                </a>
                            @else
                                <span class="text-muted">@lang('lang_v1.baby_not_registered')</span>
                            @endif
                        </td>
                    </tr>
                </table>
            @endcomponent
        </div>
    </div>

</section>
@endsection
