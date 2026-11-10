@extends('patient_portal.layouts.portal')
@section('title', __('clinic.appt_portal_title'))
@section('heading', __('clinic.appt_portal_title'))
@php
    $portal_group = 'appointments';
    // inline colours: the portal's compiled CSS lacks most colour utilities
    $chip = [
        'requested' => 'background:#fef3c7;color:#b45309;',
        'confirmed' => 'background:#dbeafe;color:#1d4ed8;',
        'checked_in' => 'background:#dcfce7;color:#15803d;',
        'cancelled' => 'background:#f1f5f9;color:#64748b;',
        'declined' => 'background:#fee2e2;color:#b91c1c;',
        'no_show' => 'background:#f1f5f9;color:#64748b;',
    ];
@endphp

@section('content')
@if ($can_book)
    <a href="{{ route('portal.appointment-create') }}" class="portal-press" style="display:flex;align-items:center;justify-content:center;gap:8px;border-radius:16px;background:linear-gradient(135deg,#4f46e5,#3b82f6);color:#fff;font-weight:700;padding:14px;margin-bottom:16px;text-decoration:none;">
        <i class="fas fa-calendar-plus"></i> @lang('clinic.appt_portal_request')
    </a>
@endif

<p style="font-size:13px;font-weight:700;color:#334155;margin:0 0 8px;">@lang('clinic.appt_portal_upcoming')</p>
<div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-overflow-hidden tw-shadow-sm" style="margin-bottom:18px;">
    @forelse ($upcoming as $a)
        <div style="padding:14px 16px;border-bottom:1px solid #f1f5f9;">
            <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                <div style="min-width:0;">
                    <p style="margin:0;font-size:15px;font-weight:700;color:#0f172a;">{{ $a->date->translatedFormat('l') }} {{ @format_date($a->date) }} · {{ $a->start }}</p>
                    <p style="margin:2px 0 0;font-size:13px;color:#475569;"><i class="fas fa-user-md"></i> {{ trim(optional($a->doctor)->user_full_name) }}</p>
                    @if ($a->reason)<p style="margin:2px 0 0;font-size:12px;color:#94a3b8;">{{ $a->reason }}</p>@endif
                </div>
                <span style="font-size:11px;padding:3px 9px;border-radius:9999px;white-space:nowrap;{{ $chip[$a->status] ?? '' }}">@lang('clinic.appt_portal_status_' . $a->status)</span>
            </div>
            @if ($a->status == 'requested')
                <p style="margin:6px 0 0;font-size:12px;color:#b45309;">@lang('clinic.appt_portal_waiting')</p>
            @endif
            @if (in_array($a->status, ['requested', 'confirmed']) && $a->startsAt()->isFuture())
                <form method="POST" action="{{ route('portal.appointment-cancel', $a->id) }}" style="margin:8px 0 0;" onsubmit="return confirm({{ json_encode(__('clinic.appt_portal_cancel_confirm')) }});">
                    @csrf
                    <button type="submit" style="background:none;border:1px solid #e2e8f0;border-radius:10px;padding:5px 12px;font-size:12px;color:#b91c1c;cursor:pointer;"><i class="fas fa-ban"></i> @lang('clinic.appt_portal_cancel')</button>
                </form>
            @endif
        </div>
    @empty
        @include('patient_portal.partials.empty', ['msg' => __('clinic.appt_portal_none'), 'icon' => 'fa-calendar'])
    @endforelse
</div>

@if ($past->isNotEmpty())
    <p style="font-size:13px;font-weight:700;color:#334155;margin:0 0 8px;">@lang('clinic.appt_portal_past')</p>
    <div class="tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-overflow-hidden tw-shadow-sm">
        @foreach ($past as $a)
            <div style="padding:12px 16px;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;gap:10px;">
                <div style="min-width:0;">
                    <p style="margin:0;font-size:14px;color:#334155;">{{ @format_date($a->date) }} · {{ $a->start }}</p>
                    <p style="margin:2px 0 0;font-size:12px;color:#94a3b8;">{{ trim(optional($a->doctor)->user_full_name) }}@if ($a->status == 'declined' && $a->cancel_reason) · {{ $a->cancel_reason }}@endif</p>
                </div>
                <span style="font-size:11px;padding:3px 9px;border-radius:9999px;white-space:nowrap;align-self:flex-start;{{ $chip[$a->status] ?? '' }}">@lang('clinic.appt_portal_status_' . $a->status)</span>
            </div>
        @endforeach
    </div>
@endif
@endsection
