@extends('layouts.app')
@section('title', __('lang_v1.antenatal_record'))

@php
    $r = $record;
    $pd = optional($r->mother)->patientDetails;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ optional($r->mother)->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $r->ref_no }}</span>
                    <span class="label {{ $r->status == 'active' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.anc_status_' . $r->status) }}</span>
                    @if($r->gestational_age_weeks) · {{ $r->gestational_age_weeks }} @lang('lang_v1.weeks') @endif
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                @if($r->status == 'active' && auth()->user()->can('delivery.record'))
                    <a href="{{ action([\App\Http\Controllers\DeliveryController::class, 'create']) }}?antenatal_record_id={{ $r->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                        <i class="fas fa-baby"></i> @lang('lang_v1.record_delivery')
                    </a>
                @endif
                <a href="{{ action([\App\Http\Controllers\AntenatalController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('messages.back')
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.pregnancy_details')])
                <table class="table table-bordered">
                    <tr><th width="45%">@lang('lang_v1.lmp')</th><td>{{ $r->lmp ? @format_date($r->lmp) : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.edd')</th><td>{{ $r->edd ? @format_date($r->edd) : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.gravida')</th><td>{{ $r->gravida ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.para')</th><td>{{ $r->para ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.blood_group_mother')</th><td>{{ $r->blood_group_mother ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.blood_group_father')</th><td>{{ $r->blood_group_father ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.hiv_status')</th><td>{{ $r->hiv_status ? __('lang_v1.' . $r->hiv_status) : '-' }}</td></tr>
                </table>
                @if($r->delivery)
                    <a href="{{ action([\App\Http\Controllers\DeliveryController::class, 'show'], [$r->delivery->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline tw-w-full">
                        <i class="fas fa-baby"></i> @lang('lang_v1.view_delivery')
                    </a>
                @endif
            @endcomponent
        </div>
        <div class="col-md-8">
            @if($r->status == 'active')
                @can('antenatal.update')
                    @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.record_anc_visit')])
                        {!! Form::open(['url' => action([\App\Http\Controllers\AntenatalController::class, 'recordVisit'], [$r->id]), 'method' => 'post']) !!}
                        <div class="row">
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('visit_date', __('lang_v1.date') . ':*') !!}{!! Form::date('visit_date', \Carbon::today()->format('Y-m-d'), ['class' => 'form-control', 'required']) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('gestational_age_weeks', __('lang_v1.ga_weeks') . ':') !!}{!! Form::number('gestational_age_weeks', $r->gestational_age_weeks, ['class' => 'form-control', 'min' => 0, 'max' => 45]) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('weight', __('lang_v1.weight_kg') . ':') !!}{!! Form::number('weight', null, ['class' => 'form-control', 'step' => '0.01', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('fee', __('lang_v1.fee') . ':') !!}{!! Form::text('fee', 0, ['class' => 'form-control input_number']) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('bp', __('lang_v1.blood_pressure') . ':') !!}
                                <div class="tw-flex tw-items-center tw-gap-1">{!! Form::number('bp_systolic', null, ['class' => 'form-control', 'placeholder' => 'Sys']) !!}<span>/</span>{!! Form::number('bp_diastolic', null, ['class' => 'form-control', 'placeholder' => 'Dia']) !!}</div></div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('fundal_height', __('lang_v1.fundal_height') . ':') !!}{!! Form::number('fundal_height', null, ['class' => 'form-control', 'step' => '0.1', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('fetal_heart_rate', __('lang_v1.fetal_heart_rate') . ':') !!}{!! Form::number('fetal_heart_rate', null, ['class' => 'form-control', 'min' => 0]) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('presentation', __('lang_v1.presentation') . ':') !!}{!! Form::text('presentation', null, ['class' => 'form-control']) !!}</div></div>
                            <div class="col-sm-6"><div class="form-group">{!! Form::label('complications', __('lang_v1.complications') . ':') !!}{!! Form::text('complications', null, ['class' => 'form-control']) !!}</div></div>
                            <div class="col-sm-3"><div class="form-group">{!! Form::label('next_visit_date', __('lang_v1.next_visit') . ':') !!}{!! Form::date('next_visit_date', null, ['class' => 'form-control']) !!}</div></div>
                        </div>
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white">@lang('lang_v1.record_anc_visit')</button>
                        {!! Form::close() !!}
                    @endcomponent
                @endcan
            @endif

            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.anc_visits')])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('lang_v1.ga_weeks')</th>
                                <th>@lang('lang_v1.weight_kg')</th>
                                <th>@lang('lang_v1.blood_pressure')</th>
                                <th>@lang('lang_v1.fetal_heart_rate')</th>
                                <th>@lang('lang_v1.next_visit')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($r->antenatalVisits->sortByDesc('visit_date') as $v)
                                <tr>
                                    <td>{{ $v->visit_number }}</td>
                                    <td>{{ @format_date($v->visit_date) }}</td>
                                    <td>{{ $v->gestational_age_weeks ?? '-' }}</td>
                                    <td>{{ $v->weight ? $v->weight + 0 : '-' }}</td>
                                    <td>{{ ($v->bp_systolic && $v->bp_diastolic) ? $v->bp_systolic . '/' . $v->bp_diastolic : '-' }}</td>
                                    <td>{{ $v->fetal_heart_rate ?? '-' }}</td>
                                    <td>{{ $v->next_visit_date ? @format_date($v->next_visit_date) : '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>

</section>
@endsection
