@extends('layouts.app')
@section('title', __('lang_v1.morgue_record'))

@php
    $r = $record;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">
                    <i class="fas fa-snowflake tw-text-sky-400"></i> {{ $r->deceased_name }}
                    <small class="tw-text-gray-500">· {{ $r->ref_no }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label {{ $r->status == 'in_storage' ? 'label-warning' : 'label-success' }}">{{ __('lang_v1.morgue_status_' . $r->status) }}</span>
                    @if($r->legal_hold)<span class="label label-danger">@lang('lang_v1.legal_hold')</span>@endif ·
                    {{ __('lang_v1.source_' . $r->source) }} · {{ $r->days_stored }} @lang('lang_v1.days')
                </p>
            </div>
            <a href="{{ action([\App\Http\Controllers\MorgueController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                <i class="fa fa-arrow-left"></i> @lang('messages.back')
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.deceased_details')])
                <table class="table table-bordered">
                    <tr><th width="45%">@lang('lang_v1.deceased_name')</th><td>{{ $r->deceased_name }}</td></tr>
                    <tr><th>@lang('lang_v1.age')</th><td>{{ $r->deceased_age ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.sex')</th><td>{{ $r->deceased_sex ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.date_of_death')</th><td>{{ @format_datetime($r->date_of_death) }}</td></tr>
                    <tr><th>@lang('lang_v1.cause_of_death')</th><td>{{ $r->cause_of_death ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.certified_by')</th><td>{{ optional($r->certifiedBy)->user_full_name ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.slot')</th><td>{{ optional($r->slot)->slot_number ?: '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.received_at')</th><td>{{ @format_datetime($r->received_at) }}</td></tr>
                    <tr><th>@lang('lang_v1.family_contact')</th><td>{{ $r->family_contact_name ?: '-' }} {{ $r->family_contact_phone ? '(' . $r->family_contact_phone . ')' : '' }}</td></tr>
                </table>
            @endcomponent

            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.storage_fees')])
                <table class="table table-bordered">
                    <tr><th width="55%">@lang('lang_v1.daily_storage_fee')</th><td><span class="display_currency" data-currency_symbol="true">{{ $r->daily_storage_fee }}</span></td></tr>
                    <tr><th>@lang('lang_v1.days')</th><td>{{ $r->days_stored }}</td></tr>
                    <tr><th>@lang('lang_v1.preparation_fee')</th><td><span class="display_currency" data-currency_symbol="true">{{ $r->preparation_fee }}</span></td></tr>
                    <tr><th>@lang('lang_v1.estimated_total')</th><td><strong><span class="display_currency" data-currency_symbol="true">{{ $r->computed_fee }}</span></strong></td></tr>
                    @if($r->status == 'released')
                        <tr><th>@lang('lang_v1.total_charged')</th><td><span class="display_currency" data-currency_symbol="true">{{ $r->total_fee_charged }}</span></td></tr>
                        <tr><th>@lang('lang_v1.released_to')</th><td>{{ $r->released_to }}</td></tr>
                        <tr><th>@lang('lang_v1.released_at')</th><td>{{ @format_datetime($r->released_at) }}</td></tr>
                    @endif
                </table>
            @endcomponent
        </div>
        <div class="col-md-7">
            @if($r->status == 'in_storage' && auth()->user()->can('morgue.release'))
                @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.release_body')])
                    @if($r->legal_hold)
                        <div class="alert alert-danger"><i class="fas fa-gavel"></i> @lang('lang_v1.legal_hold_blocks_release')</div>
                    @else
                        {!! Form::open(['url' => action([\App\Http\Controllers\MorgueController::class, 'release'], [$r->id]), 'method' => 'post']) !!}
                        <div class="row">
                            <div class="col-sm-6"><div class="form-group">{!! Form::label('released_to', __('lang_v1.released_to') . ':*') !!}{!! Form::text('released_to', null, ['class' => 'form-control', 'required', 'placeholder' => __('lang_v1.released_to_placeholder')]) !!}</div></div>
                            <div class="col-sm-6"><div class="form-group">{!! Form::label('released_at', __('lang_v1.released_at') . ':') !!}{!! Form::text('released_at', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control']) !!}</div></div>
                            <div class="col-sm-12"><div class="form-group">{!! Form::label('release_authorized_by', __('lang_v1.release_authorized_by') . ':') !!}{!! Form::text('release_authorized_by', null, ['class' => 'form-control']) !!}</div></div>
                        </div>
                        <p class="help-block">@lang('lang_v1.release_fee_note', ['amount' => ''])<span class="display_currency" data-currency_symbol="true">{{ $r->computed_fee }}</span></p>
                        <button type="submit" class="tw-dw-btn tw-bg-green-600 tw-text-white tw-border-none tw-font-bold">@lang('lang_v1.confirm_release')</button>
                        {!! Form::close() !!}
                    @endif
                @endcomponent
            @endif

            @if(auth()->user()->can('morgue.record_visit') && $r->status == 'in_storage')
                @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.record_family_visit')])
                    {!! Form::open(['url' => action([\App\Http\Controllers\MorgueController::class, 'recordVisit'], [$r->id]), 'method' => 'post', 'class' => 'form-inline']) !!}
                        {!! Form::text('visitor_name', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.visitor_name'), 'required']) !!}
                        {!! Form::text('visitor_phone', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.phone')]) !!}
                        {!! Form::text('relationship', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.relationship')]) !!}
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white">@lang('messages.add')</button>
                    {!! Form::close() !!}
                @endcomponent
            @endif

            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.family_visits')])
                <table class="table table-bordered table-striped">
                    <thead><tr><th>@lang('lang_v1.visitor_name')</th><th>@lang('lang_v1.phone')</th><th>@lang('lang_v1.relationship')</th><th>@lang('lang_v1.date')</th></tr></thead>
                    <tbody>
                        @forelse($r->familyVisits->sortByDesc('visited_at') as $v)
                            <tr>
                                <td>{{ $v->visitor_name }}</td>
                                <td>{{ $v->visitor_phone ?: '-' }}</td>
                                <td>{{ $v->relationship ?: '-' }}</td>
                                <td>{{ @format_datetime($v->visited_at) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endcomponent
        </div>
    </div>

</section>
@endsection
