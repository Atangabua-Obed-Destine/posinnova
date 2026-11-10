@extends('layouts.app')
@section('title', $insurer->exists ? __('clinic.ins_edit_insurer') : __('clinic.ins_add_insurer'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ $insurer->exists ? __('clinic.ins_edit_insurer') : __('clinic.ins_add_insurer') }}</h1>
</section>

<section class="content">
    <form method="POST" action="{{ $insurer->exists ? action([\App\Http\Controllers\ClinicInsurerController::class, 'update'], [$insurer->id]) : action([\App\Http\Controllers\ClinicInsurerController::class, 'store']) }}">
        @csrf
        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-6">
                    <div class="form-group">
                        <label for="name">@lang('clinic.ins_insurer_name'):*</label>
                        <input type="text" name="name" id="name" class="form-control" maxlength="191" required value="{{ old('name', $insurer->name) }}">
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="form-group">
                        <label for="default_pct">@lang('clinic.ins_default_pct'):</label>
                        <div class="input-group">
                            <input type="text" name="default_pct" id="default_pct" class="form-control input_number" value="{{ old('default_pct', is_null($insurer->default_pct) ? '' : \App\Utils\ClinicInsurance::pct($insurer->default_pct)) }}" placeholder="80">
                            <span class="input-group-addon">%</span>
                        </div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="checkbox" style="margin-top:28px;">
                        <label><input type="checkbox" name="is_active" value="1" {{ old('is_active', $insurer->is_active) ? 'checked' : '' }}> @lang('clinic.ins_active')</label>
                    </div>
                </div>
            </div>
            <p class="help-block" style="margin-top:-6px;">@lang('clinic.ins_default_pct_help')</p>
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        <label for="phone">@lang('contact.mobile'):</label>
                        <input type="text" name="phone" id="phone" class="form-control" maxlength="60" value="{{ old('phone', $insurer->phone) }}">
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label for="email">@lang('business.email'):</label>
                        <input type="email" name="email" id="email" class="form-control" maxlength="191" value="{{ old('email', $insurer->email) }}">
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        <label for="address">@lang('business.address'):</label>
                        <input type="text" name="address" id="address" class="form-control" maxlength="1000" value="{{ old('address', $insurer->address) }}">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="notes">@lang('lang_v1.notes'):</label>
                <textarea name="notes" id="notes" class="form-control" rows="2" maxlength="2000" placeholder="{{ __('clinic.ins_notes_placeholder') }}">{{ old('notes', $insurer->notes) }}</textarea>
            </div>
        @endcomponent
        <div class="text-center" style="margin-bottom:20px;">
            <a href="{{ action([\App\Http\Controllers\ClinicInsurerController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-outline">@lang('messages.cancel')</a>
            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.save')</button>
        </div>
    </form>

    @if ($insurer->exists)
        <form method="POST" action="{{ action([\App\Http\Controllers\ClinicInsurerController::class, 'destroy'], [$insurer->id]) }}" class="text-center js-confirm" data-confirm="{{ __('clinic.ins_delete_confirm') }}">
            @csrf
            <button type="submit" class="btn btn-link text-danger"><i class="fas fa-trash"></i> @lang('clinic.ins_delete_insurer')</button>
            <p class="help-block">@lang('clinic.ins_delete_help')</p>
        </form>
    @endif
</section>
@endsection

@section('javascript')
<script>
    $(function () { $(document).on('submit', 'form.js-confirm', function () { return confirm($(this).data('confirm')); }); });
</script>
@endsection
