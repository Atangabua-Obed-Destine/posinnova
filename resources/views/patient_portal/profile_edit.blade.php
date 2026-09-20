@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.edit_profile'))
@section('heading', __('lang_v1.edit_profile'))
@php $portal_group = 'profile'; $pd = $patient->patientDetails; @endphp

@section('back')
    <a href="{{ route('portal.profile') }}" class="portal-press tw-inline-flex tw-items-center tw-gap-1 tw-text-indigo-600 tw-font-medium"><i class="fas fa-chevron-left"></i> <span class="tw-hidden sm:tw-inline">@lang('lang_v1.my_profile')</span></a>
@endsection

@section('content')
<form method="POST" action="{{ route('portal.profile-update') }}" class="tw-space-y-4">
    @csrf
    <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-shadow-sm tw-p-4 tw-space-y-4">
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1.5">@lang('lang_v1.mobile')</label>
            <input type="tel" name="mobile" inputmode="tel" value="{{ old('mobile', $patient->mobile) }}" class="tw-w-full tw-rounded-xl tw-border tw-border-gray-300 tw-bg-white tw-text-gray-900 tw-px-3 tw-py-3 tw-text-sm focus:tw-ring-2 focus:tw-ring-indigo-500 focus:tw-border-indigo-500">
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1.5">@lang('lang_v1.next_of_kin')</label>
            <input type="text" name="next_of_kin_name" value="{{ old('next_of_kin_name', optional($pd)->next_of_kin_name) }}" placeholder="@lang('lang_v1.name')" class="tw-w-full tw-rounded-xl tw-border tw-border-gray-300 tw-bg-white tw-text-gray-900 tw-px-3 tw-py-3 tw-text-sm focus:tw-ring-2 focus:tw-ring-indigo-500 focus:tw-border-indigo-500">
        </div>
        <div class="tw-grid tw-grid-cols-2 tw-gap-3">
            <input type="tel" name="next_of_kin_phone" inputmode="tel" value="{{ old('next_of_kin_phone', optional($pd)->next_of_kin_phone) }}" placeholder="@lang('lang_v1.mobile')" class="tw-rounded-xl tw-border tw-border-gray-300 tw-bg-white tw-text-gray-900 tw-px-3 tw-py-3 tw-text-sm focus:tw-ring-2 focus:tw-ring-indigo-500 focus:tw-border-indigo-500">
            <input type="text" name="next_of_kin_relationship" value="{{ old('next_of_kin_relationship', optional($pd)->next_of_kin_relationship) }}" placeholder="@lang('lang_v1.relationship')" class="tw-rounded-xl tw-border tw-border-gray-300 tw-bg-white tw-text-gray-900 tw-px-3 tw-py-3 tw-text-sm focus:tw-ring-2 focus:tw-ring-indigo-500 focus:tw-border-indigo-500">
        </div>
        <div>
            <label class="tw-block tw-text-sm tw-font-medium tw-text-gray-700 tw-mb-1.5">@lang('lang_v1.known_allergies')</label>
            <textarea name="known_allergies" rows="2" class="tw-w-full tw-rounded-xl tw-border tw-border-gray-300 tw-bg-white tw-text-gray-900 tw-px-3 tw-py-3 tw-text-sm focus:tw-ring-2 focus:tw-ring-indigo-500 focus:tw-border-indigo-500">{{ old('known_allergies', optional($pd)->known_allergies) }}</textarea>
        </div>
    </div>
    <button type="submit" class="login-press portal-press tw-w-full tw-rounded-xl tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-font-semibold tw-py-3 tw-text-sm tw-shadow-lg"><i class="fas fa-floppy-disk"></i> @lang('messages.save')</button>
    <p class="tw-text-xs tw-text-gray-400 tw-text-center">@lang('lang_v1.portal_profile_note')</p>
</form>
@endsection
