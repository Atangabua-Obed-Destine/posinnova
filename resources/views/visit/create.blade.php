@extends('layouts.app')
@section('title', __('lang_v1.open_visit'))

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.open_visit')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('lang_v1.open_visit_sub')</small>
        </h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\VisitController::class, 'store']), 'method' => 'post', 'id' => 'visit_form']) !!}

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('contact_id', __('lang_v1.patient') . ':*') !!}
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fas fa-user-injured"></i></span>
                            <select name="contact_id" id="visit_patient_id" class="form-control" required style="width:100%;">
                                @if(!empty($preselected_patient))
                                    <option value="{{ $preselected_patient->id }}" selected>
                                        {{ $preselected_patient->name }}
                                        @if(optional($preselected_patient->patientDetails)->file_number)
                                            ({{ $preselected_patient->patientDetails->file_number }})
                                        @endif
                                    </option>
                                @endif
                            </select>
                        </div>
                        <p class="help-block">
                            @lang('lang_v1.patient_not_found_hint')
                            <a href="{{ action([\App\Http\Controllers\PatientController::class, 'create']) }}" target="_blank">@lang('lang_v1.add_patient')</a>
                        </p>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('type', __('lang_v1.visit_type') . ':*') !!}
                        {!! Form::select('type', ['opd' => __('lang_v1.visit_type_opd'), 'inpatient' => __('lang_v1.visit_type_inpatient')], 'opd', ['class' => 'form-control select2', 'required']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('visit_date', __('lang_v1.date') . ':*') !!}
                        {!! Form::date('visit_date', \Carbon::today()->format('Y-m-d'), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>

                <div class="clearfix"></div>

                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                        {!! Form::select('location_id', $business_locations, null, ['class' => 'form-control select2', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('notes', __('lang_v1.additional_notes') . ':') !!}
                        {!! Form::text('notes', null, ['class' => 'form-control', 'placeholder' => __('lang_v1.additional_notes')]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">
                    @lang('lang_v1.open_visit')
                </button>
                <a href="{{ action([\App\Http\Controllers\VisitController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
    <script>
        $(document).ready(function () {
            $('#visit_patient_id').select2({
                ajax: {
                    url: '{{ route('patients.search') }}',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term };
                    },
                    processResults: function (data) {
                        return { results: data.results };
                    },
                },
                minimumInputLength: 1,
                placeholder: '{{ __('lang_v1.search_patient') }}',
                width: '100%',
            });
        });
    </script>
@endsection
