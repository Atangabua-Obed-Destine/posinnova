@extends('layouts.app')
@section('title', __('lang_v1.add_ward'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.add_ward')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\WardController::class, 'store']), 'method' => 'post', 'id' => 'ward_form']) !!}

        @include('ward.partials.form_fields')

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">@lang('messages.save')</button>
                <a href="{{ action([\App\Http\Controllers\WardController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection
