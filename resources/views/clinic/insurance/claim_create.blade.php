@extends('layouts.app')
@section('title', __('clinic.ins_new_claim'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('clinic.ins_new_claim')</h1>
</section>

<section class="content">
    <form method="POST" action="{{ action([\App\Http\Controllers\ClinicClaimController::class, 'store']) }}">
        @csrf
        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label for="insurer_id">@lang('clinic.ins_insurer'):*</label>
                        {!! Form::select('insurer_id', $insurers, old('insurer_id', $insurer_id), ['class' => 'form-control', 'id' => 'insurer_id', 'required', 'placeholder' => __('messages.please_select')]) !!}
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="date_from">@lang('clinic.ins_charges_from'):*</label>
                        <input type="date" name="date_from" id="date_from" class="form-control" required value="{{ old('date_from', $from) }}">
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="date_to">@lang('clinic.ins_charges_to'):*</label>
                        <input type="date" name="date_to" id="date_to" class="form-control" required value="{{ old('date_to', $to) }}">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="notes">@lang('lang_v1.notes'):</label>
                <input type="text" name="notes" id="notes" class="form-control" maxlength="2000" value="{{ old('notes') }}">
            </div>
            <p class="help-block"><i class="fas fa-info-circle"></i> @lang('clinic.ins_new_claim_help')</p>
        @endcomponent
        <div class="text-center" style="margin-bottom:20px;">
            <a href="{{ action([\App\Http\Controllers\ClinicClaimController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white"><i class="fas fa-file-medical"></i> @lang('clinic.ins_prepare_claim')</button>
        </div>
    </form>
</section>
@endsection
