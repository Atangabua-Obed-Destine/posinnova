@extends('layouts.app')
@section('title', __('lang_v1.view_visit'))

@php
    $pd = optional($visit->patient)->patientDetails;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">
                    {{ optional($visit->patient)->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $visit->ref_no }}</span>
                    {{ __('lang_v1.visit_type_' . $visit->type) }} ·
                    {{ @format_date($visit->visit_date) }} ·
                    <span class="label {{ $visit->status == 'open' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.visit_status_' . $visit->status) }}</span>
                </p>
            </div>
            <div class="tw-flex tw-items-center tw-gap-2">
                @if($visit->status == 'open')
                    @can('consultation.create')
                        <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'create']) }}?visit_id={{ $visit->id }}"
                            class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                            <i class="fas fa-stethoscope"></i> @lang('lang_v1.new_consultation')
                        </a>
                    @endcan
                    @can('visit.close')
                        <a href="{{ action([\App\Http\Controllers\VisitController::class, 'close'], [$visit->id]) }}"
                            class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                            <i class="fas fa-check-circle"></i> @lang('lang_v1.close_visit')
                        </a>
                    @endcan
                @endif
                <a href="{{ action([\App\Http\Controllers\VisitController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('messages.back')
                </a>
            </div>
        </div>
    </div>

    @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.consultations')])
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>@lang('purchase.ref_no')</th>
                        <th>@lang('lang_v1.consultation_type')</th>
                        <th>@lang('lang_v1.diagnosis')</th>
                        <th>@lang('lang_v1.prescriptions')</th>
                        <th>@lang('lang_v1.consulted_by')</th>
                        <th>@lang('lang_v1.status')</th>
                        <th>@lang('lang_v1.date')</th>
                        <th>@lang('messages.action')</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consultations as $c)
                        <tr>
                            <td>{{ $c->ref_no }}</td>
                            <td>{{ __('lang_v1.consultation_type_' . $c->consultation_type) }}{{ $c->specialty ? ' · ' . $c->specialty : '' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($c->diagnosis, 60) ?: '-' }}</td>
                            <td>{{ $c->prescriptions->count() }}</td>
                            <td>{{ optional($c->consultedBy)->user_full_name ?? '-' }}</td>
                            <td><span class="label {{ $c->status == 'final' ? 'label-success' : 'label-warning' }}">{{ __('lang_v1.consultation_status_' . $c->status) }}</span></td>
                            <td>{{ @format_datetime($c->created_at) }}</td>
                            <td>
                                <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'show'], [$c->id]) }}" class="btn btn-xs btn-info">
                                    <i class="fas fa-eye"></i> @lang('messages.view')
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent

</section>
@endsection
