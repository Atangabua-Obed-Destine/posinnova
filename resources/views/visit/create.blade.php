@extends('layouts.app')
@section('title', __('clinic.check_in_patient'))

@php
    $fmt = function ($n) { return app(\App\Utils\Util::class)->num_f((float) $n); };
    $old_doctor = old('doctor_id', count($doctors) == 1 ? array_key_first($doctors) : null);
    $old_package = old('package_id', optional($packages->first())->id);
@endphp

@section('css')
<style>
    .ci-cards { display: flex; flex-wrap: wrap; gap: 10px; }
    .ci-card { position: relative; flex: 1 1 200px; max-width: 320px; border: 2px solid #e2e8f0; border-radius: 10px;
        padding: 12px 14px; cursor: pointer; background: #fff; margin: 0; font-weight: normal; }
    .ci-card:hover { border-color: #93c5fd; }
    .ci-card input { position: absolute; opacity: 0; pointer-events: none; }
    .ci-card.is-on { border-color: #2563eb; background: #eff6ff; }
    .ci-card .ci-title { font-weight: 700; font-size: 15px; color: #0f172a; }
    .ci-card .ci-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
    .ci-card .ci-price { font-weight: 700; font-size: 18px; color: #1d4ed8; margin-top: 6px; }
    .ci-card .ci-covers { color: #334155; font-size: 12px; white-space: pre-line; margin-top: 6px; }
    .ci-step { font-weight: 700; color: #334155; margin: 4px 0 10px; }
</style>
@endsection

@section('content')
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.check_in_patient')
            <small class="tw-text-sm md:tw-text-base tw-text-gray-700 tw-font-semibold">@lang('clinic.check_in_sub')</small>
        </h1>
    </section>

    <section class="content">
        {!! Form::open(['url' => action([\App\Http\Controllers\VisitController::class, 'store']), 'method' => 'post', 'id' => 'visit_form']) !!}
        {!! Form::hidden('type', 'opd', ['id' => 'visit_type_hidden']) !!}

        @component('components.widget', ['class' => 'box-solid'])
            <div class="ci-step">1. @lang('lang_v1.patient')</div>
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
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
                @if ($wards_on)
                    <div class="col-sm-3">
                        <div class="form-group">
                            {!! Form::select('type_select', ['opd' => __('lang_v1.visit_type_opd'), 'inpatient' => __('lang_v1.visit_type_inpatient')], old('type', 'opd'), ['class' => 'form-control', 'id' => 'visit_type_select']) !!}
                        </div>
                    </div>
                @endif
            </div>

            <div class="opd-only">
                @if (! empty($doctors))
                    <div class="ci-step">2. @lang('clinic.doctor')</div>
                    <div class="ci-cards" style="margin-bottom:16px;">
                        @foreach ($doctors as $doc_id => $doc_name)
                            <label class="ci-card {{ $old_doctor == $doc_id ? 'is-on' : '' }}">
                                <input type="radio" name="doctor_id" value="{{ $doc_id }}" {{ $old_doctor == $doc_id ? 'checked' : '' }}>
                                <div class="ci-title"><i class="fas fa-user-md"></i> {{ $doc_name }}</div>
                                <div class="ci-sub">{{ __('clinic.n_waiting', ['n' => $waiting[$doc_id] ?? 0]) }}</div>
                            </label>
                        @endforeach
                    </div>
                @endif

                @if ($has_plans)
                    {{-- monthly plan of the chosen patient, filled in by the script below --}}
                    <div id="plan_box" style="margin-bottom:10px;"></div>
                @endif

                @if ($packages->isNotEmpty())
                    <div class="ci-step">{{ empty($doctors) ? 2 : 3 }}. @lang('clinic.package')</div>
                    <div class="ci-cards" style="margin-bottom:8px;">
                        @foreach ($packages as $p)
                            <label class="ci-card {{ $old_package == $p->id ? 'is-on' : '' }}">
                                <input type="radio" name="package_id" value="{{ $p->id }}" {{ $old_package == $p->id ? 'checked' : '' }}>
                                <div class="ci-title">{{ $p->name }}</div>
                                <div class="ci-price">{{ $fmt($p->price) }}</div>
                                @if ($p->description)<div class="ci-covers">{{ $p->description }}</div>@endif
                            </label>
                        @endforeach
                    </div>
                @elseif ($default_fee > 0)
                    <p class="text-muted"><i class="fas fa-info-circle"></i> {{ __('clinic.default_fee_note', ['fee' => $fmt($default_fee)]) }}</p>
                @endif

                <p class="help-block">
                    <i class="fas fa-cash-register"></i>
                    {{ $pay_first ? __('clinic.checkin_pay_first_note') : __('clinic.checkin_pay_after_note') }}
                </p>
            </div>
        @endcomponent

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        {!! Form::label('visit_date', __('lang_v1.date') . ':*') !!}
                        {!! Form::date('visit_date', old('visit_date', \Carbon::today()->format('Y-m-d')), ['class' => 'form-control', 'required']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('location_id', __('purchase.business_location') . ':') !!}
                        {!! Form::select('location_id', $business_locations, old('location_id', $business_locations->keys()->first()), ['class' => 'form-control select2']) !!}
                    </div>
                </div>
                <div class="col-sm-5">
                    <div class="form-group">
                        {!! Form::label('notes', __('lang_v1.additional_notes') . ':') !!}
                        {!! Form::text('notes', old('notes'), ['class' => 'form-control', 'placeholder' => __('clinic.checkin_notes_placeholder')]) !!}
                    </div>
                </div>
            </div>
        @endcomponent

        <div class="row">
            <div class="col-md-12 text-center" style="margin-bottom:20px;">
                <button type="submit" class="tw-dw-btn tw-dw-btn-lg tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none tw-font-bold" id="checkin_submit">
                    <i class="fas fa-sign-in-alt"></i> @lang('clinic.check_in')
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

            // selectable cards
            $(document).on('change', '.ci-card input[type=radio]', function () {
                $('input[name="' + this.name + '"]').closest('.ci-card').removeClass('is-on');
                $(this).closest('.ci-card').addClass('is-on');
            });

            // in-patient visits go to the ward, not the consulting-room queue
            $('#visit_type_select').on('change', function () {
                var v = $(this).val();
                $('#visit_type_hidden').val(v);
                $('.opd-only').toggle(v === 'opd');
            }).trigger('change');

            @if ($has_plans)
            // What the patient's monthly plan covers on the visit date.
            var planBlocks = false;
            function loadPlan() {
                var cid = $('#visit_patient_id').val();
                var $box = $('#plan_box').empty();
                planBlocks = false;
                $('#checkin_submit').prop('disabled', false);
                $('input[name=package_id][value=plan]').closest('.ci-card').remove();
                if (! cid) { return; }
                $.getJSON('{{ action([\App\Http\Controllers\ClinicPlanController::class, 'coverage']) }}', { contact_id: cid, date: $('input[name=visit_date]').val() }, function (c) {
                    if (! c || c.state === 'none') { return; }
                    if (c.covers) {
                        var $card = $('<label class="ci-card is-on" style="max-width:420px;"></label>')
                            .append($('<input type="radio" name="package_id" value="plan" checked>'))
                            .append($('<div class="ci-title"></div>').append('<i class="fas fa-id-card"></i> ').append(document.createTextNode(@json(__('clinic.plan_card_title')) + ': ' + c.plan)))
                            .append($('<div class="ci-price"></div>').text(@json(__('clinic.plan_card_price'))))
                            .append($('<div class="ci-covers"></div>').text(c.message));
                        $('input[name=package_id]').closest('.ci-card').removeClass('is-on');
                        $('input[name=package_id]').prop('checked', false);
                        $box.append($('<div class="ci-cards"></div>').append($card));
                    } else {
                        var cls = c.blocks ? 'callout-danger' : 'callout-warning';
                        $box.append($('<div class="callout ' + cls + '" style="margin-bottom:0;"></div>').text(c.message));
                        if (c.blocks) { planBlocks = true; $('#checkin_submit').prop('disabled', true); }
                    }
                });
            }
            $('#visit_patient_id').on('change', loadPlan);
            $('input[name=visit_date]').on('change', loadPlan);
            loadPlan();
            @endif

            $('#visit_form').on('submit', function (e) {
                if ($('#visit_type_hidden').val() !== 'opd') { return true; }
                if ($('input[name=doctor_id]').length && ! $('input[name=doctor_id]:checked').length) {
                    e.preventDefault(); toastr.error('{{ __('clinic.choose_doctor') }}'); return false;
                }
                if ($('input[name=package_id]').length && ! $('input[name=package_id]:checked').length) {
                    e.preventDefault(); toastr.error('{{ __('clinic.choose_package') }}'); return false;
                }
                $('#checkin_submit').prop('disabled', true);
            });
        });
    </script>
@endsection
