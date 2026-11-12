@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.my_profile'))
@section('heading', __('lang_v1.my_profile'))
@php $portal_group = 'profile'; $pd = $patient->patientDetails; @endphp

@section('content')
<div class="tw-rounded-3xl tw-bg-gradient-to-br tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-p-6 tw-shadow-md tw-mb-4 tw-text-center">
    <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-20 tw-h-20 tw-rounded-full tw-bg-white/20 tw-text-3xl tw-font-bold tw-mb-2">
        {{ strtoupper(mb_substr($patient->name, 0, 1)) }}
    </span>
    <p class="tw-text-lg tw-font-bold">{{ $patient->name }}</p>
    <p class="tw-text-sm tw-opacity-80">{{ optional($pd)->file_number }}</p>
    <a href="{{ route('portal.profile-edit') }}" class="portal-press tw-inline-flex tw-items-center tw-gap-1.5 tw-mt-3 tw-rounded-full tw-bg-white/20 tw-px-4 tw-py-1.5 tw-text-sm tw-font-semibold"><i class="fas fa-pen"></i> @lang('lang_v1.edit_profile')</a>
</div>

@php
    $rows = [
        ['icon' => 'fa-birthday-cake', 'label' => __('lang_v1.date_of_birth'), 'value' => optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->format('d/m/Y') : '—'],
        ['icon' => 'fa-venus-mars', 'label' => __('lang_v1.sex'), 'value' => optional($pd)->sex ?: '—'],
        ['icon' => 'fa-tint', 'label' => __('lang_v1.blood_group'), 'value' => optional($pd)->blood_group ?: '—'],
        ['icon' => 'fa-mobile-alt', 'label' => __('lang_v1.mobile'), 'value' => $patient->mobile ?: '—'],
        ['icon' => 'fa-comment-dots', 'label' => __('clinic.wa_portal_row'), 'value' => optional($pd)->whatsapp_opt_in ? __('clinic.wa_portal_row_yes') : __('clinic.wa_portal_row_no')],
        ['icon' => 'fa-exclamation-triangle', 'label' => __('lang_v1.known_allergies'), 'value' => optional($pd)->known_allergies ?: __('lang_v1.none')],
        ['icon' => 'fa-user-shield', 'label' => __('lang_v1.next_of_kin'), 'value' => trim((optional($pd)->next_of_kin_name ?? '').' '.(optional($pd)->next_of_kin_phone ? '('.$pd->next_of_kin_phone.')' : '')) ?: '—'],
        ['icon' => 'fa-shield-alt', 'label' => __('lang_v1.insurance_provider'), 'value' => optional($pd)->insurance_provider ?: '—'],
        ['icon' => 'fa-hashtag', 'label' => __('lang_v1.insurance_number'), 'value' => optional($pd)->insurance_number ?: '—'],
    ];
@endphp
<div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-overflow-hidden tw-shadow-sm">
    @foreach($rows as $row)
        <div class="tw-flex tw-items-center tw-gap-3 tw-px-4 tw-py-3 tw-border-b tw-border-gray-50 last:tw-border-0">
            <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-8 tw-h-8 tw-rounded-lg tw-bg-gray-50 tw-text-gray-400 tw-shrink-0"><i class="fas {{ $row['icon'] }} tw-text-xs"></i></span>
            <span class="tw-text-sm tw-text-gray-500 tw-flex-1">{{ $row['label'] }}</span>
            <span class="tw-text-sm tw-font-medium tw-text-gray-800 tw-text-right">{{ $row['value'] }}</span>
        </div>
    @endforeach
</div>

{{-- Language + logout (also in sidebar on desktop, but handy here on mobile) --}}
<div class="md:tw-hidden tw-mt-4 tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-shadow-sm tw-overflow-hidden">
    @include('patient_portal.partials.lang_switch', ['variant' => 'sidebar'])
    <form method="POST" action="{{ route('portal.logout') }}" class="tw-border-t tw-border-gray-50">
        @csrf
        <button type="submit" class="portal-press tw-w-full tw-flex tw-items-center tw-gap-2 tw-px-4 tw-py-3 tw-text-sm tw-text-red-600"><i class="fas fa-sign-out-alt"></i> @lang('lang_v1.logout')</button>
    </form>
</div>

<p class="tw-text-xs tw-text-gray-400 tw-text-center tw-mt-4">@lang('lang_v1.portal_profile_note')</p>
@endsection
