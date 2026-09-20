@extends('layouts.app')
@section('title', __('lang_v1.book_theatre'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.book_theatre')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\TheatreController::class, 'store']), 'method' => 'post', 'id' => 'theatre_form']) !!}

        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.booking_details')])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('contact_id', __('lang_v1.patient') . ':*') !!}
                        <select name="contact_id" id="theatre_patient_id" class="form-control" required style="width:100%;">
                            @if($preselected_patient)
                                <option value="{{ $preselected_patient->id }}" selected>
                                    {{ $preselected_patient->name }}
                                    @if(optional($preselected_patient->patientDetails)->file_number)({{ $preselected_patient->patientDetails->file_number }})@endif
                                </option>
                            @endif
                        </select>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('procedure_id', __('lang_v1.procedure') . ':*') !!}
                        {!! Form::select('procedure_id', $procedures->pluck('name', 'id'), null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']) !!}
                        @if($procedures->isEmpty())
                            <p class="help-block text-warning">@lang('lang_v1.no_procedures_create_first')
                                @can('theatre.manage_catalogue')<a href="{{ action([\App\Http\Controllers\TheatreController::class, 'catalogue']) }}" target="_blank">@lang('lang_v1.procedure_catalogue')</a>@endcan
                            </p>
                        @endif
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('scheduled_at', __('lang_v1.scheduled_at') . ':*') !!}
                        {!! Form::text('scheduled_at', \Carbon::now()->format('Y-m-d H:i'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('surgeon_id', __('lang_v1.surgeon') . ':') !!}
                        {!! Form::select('surgeon_id', $doctors, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('anaesthetist_id', __('lang_v1.anaesthetist') . ':') !!}
                        {!! Form::select('anaesthetist_id', $doctors, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('preop_notes', __('lang_v1.preop_notes') . ':') !!}
                        {!! Form::textarea('preop_notes', null, ['class' => 'form-control', 'rows' => 3, 'placeholder' => __('lang_v1.preop_checklist_placeholder')]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold" {{ $procedures->isEmpty() ? 'disabled' : '' }}>@lang('lang_v1.book_theatre')</button>
                <a href="{{ action([\App\Http\Controllers\TheatreController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @if(!$preselected_patient)
    $('#theatre_patient_id').select2({
        ajax: { url: '{{ route('patients.search') }}', dataType: 'json', delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; } },
        minimumInputLength: 1, placeholder: '{{ __('lang_v1.search_patient') }}', width: '100%',
    });
    @endif
});
</script>
@endsection
