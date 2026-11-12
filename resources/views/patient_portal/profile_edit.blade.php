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
        {{-- appointment messages on WhatsApp: the patient's own choice --}}
        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:12px;" id="portal_wa_consent">
            <label style="display:flex; gap:10px; align-items:flex-start; font-size:14px; color:#14532d; font-weight:600; cursor:pointer;">
                <input type="hidden" name="whatsapp_opt_in" value="0">
                <input type="checkbox" name="whatsapp_opt_in" value="1" style="width:20px; height:20px; margin-top:1px; flex-shrink:0;" {{ old('whatsapp_opt_in', optional($pd)->whatsapp_opt_in) ? 'checked' : '' }}>
                <span><i class="fab fa-whatsapp"></i> @lang('clinic.wa_portal_consent')</span>
            </label>
            <div style="display:flex; gap:8px; align-items:center; margin-top:8px; font-size:13px; color:#475569;">
                <label for="portal_wa_lang" style="margin:0;">@lang('clinic.wa_consent_lang')</label>
                <select name="whatsapp_language" id="portal_wa_lang" class="tw-rounded-lg tw-border tw-border-gray-300 tw-bg-white tw-px-2 tw-py-1 tw-text-sm">
                    <option value="">@lang('clinic.wa_consent_lang_default')</option>
                    <option value="fr" {{ old('whatsapp_language', optional($pd)->whatsapp_language) == 'fr' ? 'selected' : '' }}>Français</option>
                    <option value="en" {{ old('whatsapp_language', optional($pd)->whatsapp_language) == 'en' ? 'selected' : '' }}>English</option>
                </select>
            </div>
            <p style="margin:6px 0 0; font-size:12px; color:#64748b;">@lang('clinic.wa_portal_consent_help')</p>
        </div>
    </div>
    <button type="submit" class="login-press portal-press tw-w-full tw-rounded-xl tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-font-semibold tw-py-3 tw-text-sm tw-shadow-lg"><i class="fas fa-save"></i> @lang('messages.save')</button>
    <p class="tw-text-xs tw-text-gray-400 tw-text-center">@lang('lang_v1.portal_profile_note')</p>
</form>
@endsection
