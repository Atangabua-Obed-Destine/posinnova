@extends('layouts.app')
@section('title', __('lang_v1.new_antenatal_record'))

@php
    $blood_groups = ['' => __('messages.please_select'), 'A+' => 'A+', 'A-' => 'A-', 'B+' => 'B+', 'B-' => 'B-', 'AB+' => 'AB+', 'AB-' => 'AB-', 'O+' => 'O+', 'O-' => 'O-'];
@endphp

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.new_antenatal_record')</h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\AntenatalController::class, 'store']), 'method' => 'post', 'id' => 'antenatal_form']) !!}

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('contact_id', __('lang_v1.mother') . ':*') !!}
                        <select name="contact_id" id="anc_patient_id" class="form-control" required style="width:100%;">
                            @if($preselected_patient)
                                <option value="{{ $preselected_patient->id }}" selected>
                                    {{ $preselected_patient->name }}
                                    @if(optional($preselected_patient->patientDetails)->file_number)({{ $preselected_patient->patientDetails->file_number }})@endif
                                </option>
                            @endif
                        </select>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('lmp', __('lang_v1.lmp') . ':') !!}
                        {!! Form::date('lmp', null, ['class' => 'form-control', 'id' => 'lmp']) !!}
                        <p class="help-block">@lang('lang_v1.edd_auto_note')</p>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('edd_preview', __('lang_v1.edd') . ':') !!}
                        {!! Form::text('edd_preview', null, ['class' => 'form-control', 'id' => 'edd_preview', 'readonly']) !!}
                    </div>
                </div>

                <div class="clearfix"></div>

                <div class="col-sm-2">
                    <div class="form-group">
                        {!! Form::label('gravida', __('lang_v1.gravida') . ':') !!}
                        {!! Form::number('gravida', null, ['class' => 'form-control', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        {!! Form::label('para', __('lang_v1.para') . ':') !!}
                        {!! Form::number('para', null, ['class' => 'form-control', 'min' => 0]) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('blood_group_mother', __('lang_v1.blood_group_mother') . ':') !!}
                        {!! Form::select('blood_group_mother', $blood_groups, null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('blood_group_father', __('lang_v1.blood_group_father') . ':') !!}
                        {!! Form::select('blood_group_father', $blood_groups, null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-2">
                    <div class="form-group">
                        {!! Form::label('hiv_status', __('lang_v1.hiv_status') . ':') !!}
                        {!! Form::select('hiv_status', ['' => __('messages.please_select'), 'negative' => __('lang_v1.negative'), 'positive' => __('lang_v1.positive'), 'unknown' => __('lang_v1.unknown')], null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('notes', __('lang_v1.notes') . ':') !!}
                        {!! Form::textarea('notes', null, ['class' => 'form-control', 'rows' => 2]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold">@lang('messages.save')</button>
                <a href="{{ action([\App\Http\Controllers\AntenatalController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            </div>
        </div>

        {!! Form::close() !!}
    </section>
@endsection

@section('javascript')
<script>
$(document).ready(function () {
    @if(!$preselected_patient)
    $('#anc_patient_id').select2({
        ajax: {
            url: '{{ route('patients.search') }}', dataType: 'json', delay: 250,
            data: function (params) { return { q: params.term }; },
            processResults: function (data) { return { results: data.results }; },
        },
        minimumInputLength: 1, placeholder: '{{ __('lang_v1.search_patient') }}', width: '100%',
    });
    @endif

    // EDD preview = LMP + 280 days
    $('#lmp').on('change', function () {
        var v = $(this).val();
        if (v) {
            var d = new Date(v);
            d.setDate(d.getDate() + 280);
            $('#edd_preview').val(d.toISOString().slice(0, 10));
        } else {
            $('#edd_preview').val('');
        }
    });
});
</script>
@endsection
