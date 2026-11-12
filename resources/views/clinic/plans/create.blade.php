@extends('layouts.app')
@section('title', __('clinic.plan_add_patient'))

@php $fmt = function ($n) { return app(\App\Utils\Util::class)->num_f((float) $n); }; @endphp

@section('css')
<style>
    .ci-cards { display: flex; flex-wrap: wrap; gap: 10px; }
    .ci-card { position: relative; flex: 1 1 220px; max-width: 320px; border: 2px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; cursor: pointer; background: #fff; margin: 0; font-weight: normal; }
    .ci-card:hover { border-color: #93c5fd; }
    .ci-card input { position: absolute; opacity: 0; pointer-events: none; }
    .ci-card.is-on { border-color: #2563eb; background: #eff6ff; }
    .ci-title { font-weight: 700; font-size: 15px; color: #0f172a; }
    .ci-price { font-weight: 700; font-size: 18px; color: #1d4ed8; margin-top: 6px; }
    .ci-sub { color: #64748b; font-size: 12px; margin-top: 2px; }
    .ci-covers { color: #334155; font-size: 12px; white-space: pre-line; margin-top: 6px; }
</style>
@endsection

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.plan_add_patient')</h1>
</section>

<section class="content">
    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicPlanController::class, 'store']) }}" id="plan_form">
        @csrf
        @component('components.widget', ['class' => 'box-solid'])
            <div class="form-group" style="max-width:520px;">
                <label for="plan_patient">@lang('lang_v1.patient'):*</label>
                <select name="contact_id" id="plan_patient" class="form-control" required style="width:100%;" aria-label="{{ __('lang_v1.patient') }}">
                    @if ($preselected)
                        <option value="{{ $preselected->id }}" selected>{{ $preselected->name }} @if(optional($preselected->patientDetails)->file_number)({{ $preselected->patientDetails->file_number }})@endif</option>
                    @endif
                </select>
            </div>

            <label>@lang('clinic.plan'):*</label>
            <div class="ci-cards" style="margin-bottom:14px;">
                @foreach ($plans as $p)
                    @php $on = old('package_id', $plans->count() == 1 ? $p->id : null) == $p->id; @endphp
                    <label class="ci-card {{ $on ? 'is-on' : '' }}">
                        <input type="radio" name="package_id" value="{{ $p->id }}" {{ $on ? 'checked' : '' }} data-price="{{ (float) $p->price }}">
                        <div class="ci-title">{{ $p->name }}</div>
                        <div class="ci-price">{{ $fmt($p->price) }} <small class="text-muted" style="font-size:12px;">/ @lang('clinic.per_month')</small></div>
                        <div class="ci-sub">{{ $p->visits_per_month ? __('clinic.visits_per_month_n', ['n' => $p->visits_per_month]) : __('clinic.visits_unlimited') }}</div>
                        @if ($p->description)<div class="ci-covers">{{ $p->description }}</div>@endif
                    </label>
                @endforeach
            </div>

            <div class="row">
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="start_date">@lang('clinic.plan_start_date'):*</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="{{ old('start_date', \Carbon::today()->toDateString()) }}" required>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label for="location_id">@lang('purchase.business_location'):</label>
                        {!! Form::select('location_id', $locations, old('location_id', $locations->keys()->first()), ['class' => 'form-control', 'id' => 'location_id']) !!}
                    </div>
                </div>
                <div class="col-sm-5">
                    <div class="form-group">
                        <label for="notes">@lang('lang_v1.notes'):</label>
                        <input type="text" name="notes" id="notes" class="form-control" maxlength="1000" value="{{ old('notes') }}">
                    </div>
                </div>
            </div>
            <p class="help-block"><i class="fas fa-file-invoice"></i>
                @if ($settings['plan_billing_anchor'] == 'fixed')
                    {{ __('clinic.plan_create_note_fixed', ['day' => $settings['plan_fixed_day']]) }}
                @else
                    @lang('clinic.plan_create_note_start')
                @endif
            </p>
        @endcomponent
        <div class="text-center" style="margin-bottom:20px;">
            <button type="submit" class="tw-dw-btn tw-dw-btn-lg tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" id="plan_submit"><i class="fas fa-id-card"></i> @lang('clinic.plan_subscribe')</button>
            <a href="{{ action([\App\Http\Controllers\ClinicPlanController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
        </div>
    </form>
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        $('#plan_patient').select2({
            ajax: { url: '{{ route('patients.search') }}', dataType: 'json', delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return { results: data.results }; } },
            minimumInputLength: 1, placeholder: @json(__('lang_v1.search_patient')), width: '100%',
        });
        $(document).on('change', '.ci-card input[type=radio]', function () {
            $('input[name="' + this.name + '"]').closest('.ci-card').removeClass('is-on');
            $(this).closest('.ci-card').addClass('is-on');
        });
        $('#plan_form').on('submit', function () {
            if (! $('input[name=package_id]:checked').length) { toastr.error(@json(__('clinic.plan_choose_patient_and_plan'))); return false; }
            // "check before recording": the first period is billed straight away
            var pform = this;
            if (window.PosConfirm && PosConfirm.enabled('plan') && pform.getAttribute('data-rc-ok') !== '1') {
                var $pk = $('input[name=package_id]:checked');
                PosConfirm.ask({ kind: 'plan', amount: parseFloat($pk.data('price')) || 0, rows: [
                    [@json(__('lang_v1.patient')), $.trim($('#plan_patient option:selected').text())],
                    [@json(__('clinic.plan')), $.trim($pk.closest('.ci-card').find('.ci-title').text())],
                    [@json(__('clinic.plan_start_date')), $('#start_date').val()],
                ] }, function () {
                    pform.setAttribute('data-rc-ok', '1');
                    $(pform).trigger('submit');
                });
                return false;
            }
            pform.removeAttribute('data-rc-ok');
            $('#plan_submit').prop('disabled', true);
        });
    });
</script>
@endsection
