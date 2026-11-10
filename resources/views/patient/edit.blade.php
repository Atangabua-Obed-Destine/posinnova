@extends('layouts.app')
@section('title', __('lang_v1.edit_patient'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.edit_patient')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">
                @lang('lang_v1.file_number'): <strong>{{ optional($contact->patientDetails)->file_number ?: '-' }}</strong>
            </small>
        </h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\PatientController::class, 'update'], [$contact->id]), 'method' => 'put', 'id' => 'patient_edit_form']) !!}

        @include('patient.partials.form_fields')

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom: 20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">
                    @lang('messages.update')
                </button>
                <a href="{{ action([\App\Http\Controllers\PatientController::class, 'show'], [$contact->id]) }}" class="tw-dw-btn tw-dw-btn-outline">
                    @lang('messages.cancel')
                </a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection
