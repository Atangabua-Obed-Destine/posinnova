@extends('layouts.app')
@section('title', __('lang_v1.manage_slots'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.manage_slots')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.morgue')</small>
        </h1>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-4">
                @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.add_slot')])
                    {!! Form::open(['url' => action([\App\Http\Controllers\MorgueController::class, 'slots']), 'method' => 'post']) !!}
                    <div class="form-group">
                        {!! Form::label('slot_number', __('lang_v1.slot_number') . ':*') !!}
                        {!! Form::text('slot_number', null, ['class' => 'form-control', 'required', 'maxlength' => 50, 'placeholder' => 'e.g. A1']) !!}
                    </div>
                    <div class="form-group">
                        {!! Form::label('description', __('lang_v1.description') . ':') !!}
                        {!! Form::text('description', null, ['class' => 'form-control', 'placeholder' => 'e.g. Cold Chamber 1']) !!}
                    </div>
                    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.add')</button>
                    {!! Form::close() !!}
                @endcomponent
            </div>
            <div class="col-md-8">
                @component('components.widget', ['class' => 'box-primary', 'title' => __('lang_v1.slots')])
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>@lang('lang_v1.slot_number')</th><th>@lang('lang_v1.description')</th><th>@lang('lang_v1.status')</th><th>@lang('lang_v1.occupant')</th></tr></thead>
                        <tbody>
                            @forelse($slots as $slot)
                                <tr>
                                    <td>{{ $slot->slot_number }}</td>
                                    <td>{{ $slot->description ?: '-' }}</td>
                                    <td><span class="label {{ $slot->status == 'occupied' ? 'label-danger' : ($slot->status == 'maintenance' ? 'label-default' : 'label-success') }}">{{ __('lang_v1.bed_status_' . $slot->status) }}</span></td>
                                    <td>{{ optional($slot->currentRecord)->deceased_name ?: '-' }}</td>
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
