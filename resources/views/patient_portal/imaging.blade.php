@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.my_imaging'))
@section('heading', __('lang_v1.my_imaging'))
@php $portal_group = 'records'; @endphp

@section('content')
@include('patient_portal.partials.records_nav', ['active' => 'imaging'])

<div class="tw-space-y-3" data-list>
    @forelse($imaging_requests as $ir)
        <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm">
            <div class="tw-flex tw-items-center tw-gap-3 tw-mb-1">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-text-white tw-shrink-0 tw-shadow" style="background:linear-gradient(135deg,#a855f7,#7e22ce);"><i class="fas fa-x-ray"></i></span>
                <div class="tw-flex-1 tw-min-w-0">
                    <p class="tw-text-sm tw-font-semibold tw-text-gray-800">{{ $ir->study_name }}</p>
                    <p class="tw-text-xs tw-text-gray-400">{{ @format_date($ir->requested_at) }} · {{ $ir->ref_no }}</p>
                </div>
                <span class="tw-text-[10px] tw-px-2 tw-py-0.5 tw-rounded-full tw-shrink-0 {{ $ir->status == 'done' ? 'tw-bg-green-100 tw-text-green-700' : 'tw-bg-amber-100 tw-text-amber-700' }}">{{ __('lang_v1.imaging_status_' . $ir->status) }}</span>
            </div>
            @php $img_locked = $ir->status == 'done' && $ir->report && ! \App\Utils\ClinicRequestPayment::releasedToPatient($pays[$ir->id]); @endphp
            @if($img_locked)
                {{-- PosInnova: the report waits for payment (it is not sent) --}}
                <div class="tw-mt-2 tw-rounded-xl tw-bg-red-50 tw-ring-1 tw-ring-red-200 tw-p-3 tw-text-sm tw-text-red-700">
                    <i class="fas fa-lock"></i> <strong>@lang('clinic.portal_pay_locked')</strong> ·
                    {{ __('clinic.portal_pay_locked_msg', ['due' => (new \App\Utils\Util())->num_f($pays[$ir->id]['due'], true)]) }}
                </div>
            @elseif($ir->status == 'done' && $ir->report)
                <div class="tw-mt-2 tw-rounded-xl tw-bg-gray-50 tw-p-3 tw-text-sm tw-text-gray-700" style="white-space: pre-wrap;">{{ $ir->report }}</div>
                <p class="tw-text-[11px] tw-text-gray-400 tw-mt-1">@lang('lang_v1.reported_by'): {{ optional($ir->reportedBy)->user_full_name ?? '—' }}</p>
                @include('patient_portal.partials.attachments', ['files' => $ir->attachments])
            @endif
        </div>
    @empty
        @include('patient_portal.partials.empty', ['msg' => __('lang_v1.no_imaging'), 'icon' => 'fa-x-ray'])
    @endforelse
</div>
@include('patient_portal.partials.infinite', ['paginator' => $imaging_requests])
@endsection
