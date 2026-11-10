@extends('patient_portal.layouts.portal')
@section('title', __('lang_v1.portal_home'))
@section('heading', __('lang_v1.portal_home'))
@php $portal_group = 'home'; $pd = $patient->patientDetails; @endphp

@section('content')
@php
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
    $hasAllergies = !empty(optional($pd)->known_allergies);
@endphp

<div class="tw-grid tw-grid-cols-1 lg:tw-grid-cols-3 tw-gap-4">
    {{-- LEFT / MAIN column --}}
    <div class="lg:tw-col-span-2 tw-space-y-4">

        {{-- Patient ID card (reflows on small phones) --}}
        <div class="tw-rounded-3xl tw-bg-gradient-to-br tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-p-5 tw-shadow-md">
            <div class="tw-flex tw-flex-col sm:tw-flex-row sm:tw-items-center tw-justify-between tw-gap-3">
                <div class="tw-flex tw-items-center tw-gap-3 tw-min-w-0">
                    <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-12 tw-h-12 tw-rounded-2xl tw-bg-white/20 tw-text-xl tw-font-bold tw-shrink-0">{{ strtoupper(mb_substr($patient->name, 0, 1)) }}</span>
                    <div class="tw-min-w-0">
                        <p class="tw-text-xs tw-opacity-80">@lang('lang_v1.portal_greeting')</p>
                        <p class="tw-text-lg sm:tw-text-xl tw-font-bold tw-truncate">{{ $patient->name }}</p>
                        <p class="tw-text-xs tw-opacity-90 tw-mt-0.5"><i class="fas fa-id-card"></i> {{ optional($pd)->file_number ?: '—' }}</p>
                    </div>
                </div>
                <div class="tw-flex tw-gap-2 tw-text-center tw-shrink-0">
                    @if(optional($pd)->blood_group)<div class="tw-rounded-xl tw-bg-white/15 tw-px-2.5 tw-py-1.5"><p class="tw-text-[10px] tw-opacity-70">@lang('lang_v1.blood_group')</p><p class="tw-text-sm tw-font-bold">{{ $pd->blood_group }}</p></div>@endif
                    @if(!is_null($age))<div class="tw-rounded-xl tw-bg-white/15 tw-px-2.5 tw-py-1.5"><p class="tw-text-[10px] tw-opacity-70">@lang('lang_v1.age')</p><p class="tw-text-sm tw-font-bold">{{ $age }}</p></div>@endif
                    @if(optional($pd)->sex)<div class="tw-rounded-xl tw-bg-white/15 tw-px-2.5 tw-py-1.5"><p class="tw-text-[10px] tw-opacity-70">@lang('lang_v1.sex')</p><p class="tw-text-sm tw-font-bold">{{ $pd->sex }}</p></div>@endif
                </div>
            </div>
            @if($hasAllergies)
                <div class="tw-mt-3 tw-rounded-xl tw-bg-red-500/25 tw-ring-1 tw-ring-red-300/40 tw-px-3 tw-py-2 tw-text-sm">
                    <i class="fas fa-exclamation-triangle"></i> <strong>@lang('lang_v1.known_allergies'):</strong> {{ $pd->known_allergies }}
                </div>
            @endif
        </div>

        {{-- Active admission banner --}}
        @if($active_admission)
            <div class="tw-rounded-2xl tw-bg-green-50 tw-ring-1 tw-ring-green-200 tw-p-4 tw-flex tw-items-center tw-gap-3">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-bg-green-100 tw-text-green-600 tw-shrink-0"><i class="fas fa-bed"></i></span>
                <div>
                    <p class="tw-text-sm tw-font-semibold tw-text-green-800">@lang('lang_v1.currently_admitted')</p>
                    <p class="tw-text-sm tw-text-green-700">{{ optional($active_admission->ward)->name }}@if($active_admission->bed) · @lang('lang_v1.bed') {{ $active_admission->bed->bed_number }} @endif · {{ $active_admission->days_admitted }} {{ $active_admission->days_admitted == 1 ? __('lang_v1.day') : __('lang_v1.days') }}</p>
                </div>
            </div>
        @endif

        {{-- Antenatal countdown --}}
        @if(!empty($antenatal) && $antenatal->edd)
            @php $ga = $antenatal->gestational_age_weeks; $weeks_left = max(0, 40 - ($ga ?? 0)); $prog = min(100, round((($ga ?? 0) / 40) * 100)); @endphp
            <div class="tw-rounded-2xl tw-bg-pink-50 tw-ring-1 tw-ring-pink-200 tw-p-4">
                <div class="tw-flex tw-items-center tw-gap-3">
                    <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-bg-pink-100 tw-text-pink-600 tw-shrink-0"><i class="fas fa-baby"></i></span>
                    <div class="tw-flex-1 tw-min-w-0">
                        <p class="tw-text-sm tw-font-semibold tw-text-pink-800">@lang('lang_v1.pregnancy_week') {{ $ga ?? 0 }} · {{ $weeks_left }} @lang('lang_v1.weeks_to_go')</p>
                        <p class="tw-text-xs tw-text-pink-600">@lang('lang_v1.due_date'): {{ @format_date($antenatal->edd) }}</p>
                    </div>
                </div>
                <div class="tw-mt-2 tw-h-1.5 tw-rounded-full tw-bg-pink-100 tw-overflow-hidden"><div class="tw-h-full tw-bg-pink-500" style="width: {{ $prog }}%"></div></div>
            </div>
        @endif

        {{-- Next booked appointment (inline colours: the portal CSS lacks sky/amber utilities) --}}
        @if(!empty($next_booking))
            <a href="{{ route('portal.appointments') }}" style="display:flex;align-items:center;gap:12px;border-radius:16px;background:#eff6ff;border:1px solid #bfdbfe;padding:14px 16px;text-decoration:none;">
                <span style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:12px;background:#dbeafe;color:#1d4ed8;flex-shrink:0;"><i class="fas fa-calendar-check"></i></span>
                <span style="min-width:0;">
                    <span style="display:block;font-size:14px;font-weight:700;color:#1e3a8a;">{{ $next_booking->status == 'requested' ? __('clinic.appt_portal_next_requested') : __('clinic.appt_portal_next') }}</span>
                    <span style="display:block;font-size:13px;color:#1d4ed8;">{{ @format_date($next_booking->date) }} · {{ $next_booking->start }} · {{ trim(optional($next_booking->doctor)->user_full_name) }}</span>
                </span>
            </a>
        @endif

        {{-- Next appointment --}}
        @if(!empty($next_appointment))
            <div class="tw-rounded-2xl tw-bg-sky-50 tw-ring-1 tw-ring-sky-200 tw-p-4 tw-flex tw-items-center tw-gap-3">
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-bg-sky-100 tw-text-sky-600 tw-shrink-0"><i class="fas fa-calendar-check"></i></span>
                <div>
                    <p class="tw-text-sm tw-font-semibold tw-text-sky-800">@lang('lang_v1.next_appointment')</p>
                    <p class="tw-text-sm tw-text-sky-700">{{ @format_date($next_appointment->next_visit_date) }} · {{ \Carbon::parse($next_appointment->next_visit_date)->diffForHumans() }}</p>
                </div>
            </div>
        @endif

        {{-- Quick stats --}}
        <div class="portal-stats" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
            @php
                $stats = [
                    ['route'=>'portal.visits','icon'=>'fa-stethoscope','grad'=>'#0ea5e9,#0284c7','val'=>$visits_count,'label'=>__('lang_v1.my_visits')],
                    ['route'=>'portal.prescriptions','icon'=>'fa-prescription','grad'=>'#6366f1,#4f46e5','val'=>$active_rx,'label'=>__('lang_v1.active_prescriptions')],
                    ['route'=>'portal.lab-results','icon'=>'fa-vial','grad'=>'#f59e0b,#d97706','val'=>$pending_labs,'label'=>__('lang_v1.pending_labs')],
                    ['route'=>'portal.admissions','icon'=>'fa-procedures','grad'=>'#22c55e,#16a34a','val'=>($active_admission ? __('lang_v1.admitted') : __('lang_v1.none')),'label'=>__('lang_v1.admission')],
                ];
                // no wards at this clinic: show the next useful thing instead of an "Admission: none" tile
                $dash_patient = \Illuminate\Support\Facades\Auth::guard('patient')->user();
                if ($dash_patient && ! \App\Utils\ClinicSettings::sectionOn($dash_patient->business_id, 'wards')) {
                    $stats[3] = \App\Utils\ClinicAppointments::enabled($dash_patient->business_id)
                        ? ['route'=>'portal.appointments','icon'=>'fa-calendar-check','grad'=>'#22c55e,#16a34a','val'=>\App\ClinicAppointment::where('contact_id', $dash_patient->id)->open()->whereDate('date', '>=', \Carbon::today())->count(),'label'=>__('clinic.appt_portal_title')]
                        : ['route'=>'portal.bills','icon'=>'fa-file-invoice-dollar','grad'=>'#22c55e,#16a34a','val'=>'','label'=>__('lang_v1.my_bills')];
                }
            @endphp
            @foreach($stats as $s)
                <a href="{{ route($s['route']) }}" class="portal-card tw-rounded-2xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm tw-block">
                    <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-10 tw-h-10 tw-rounded-xl tw-text-white tw-mb-2 tw-shadow" style="background:linear-gradient(135deg,{{ $s['grad'] }});"><i class="fas {{ $s['icon'] }}"></i></span>
                    <p class="tw-text-2xl tw-font-extrabold tw-text-gray-900 tw-leading-none">{{ $s['val'] }}</p>
                    <p class="tw-text-xs tw-text-gray-500 tw-mt-1">{{ $s['label'] }}</p>
                </a>
            @endforeach
        </div>
    </div>

    {{-- RIGHT column (balance + activity) --}}
    <div class="tw-space-y-4">
        {{-- Outstanding balance --}}
        <a href="{{ route('portal.bills') }}" class="portal-card tw-block tw-rounded-3xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-5 tw-shadow-sm">
            <div class="tw-flex tw-items-center tw-justify-between">
                <div>
                    <p class="tw-text-sm tw-text-gray-500">@lang('lang_v1.outstanding_balance')</p>
                    <p class="tw-text-2xl tw-font-extrabold {{ $due > 0 ? 'tw-text-red-600' : 'tw-text-green-600' }} tw-mt-0.5">
                        <span class="display_currency" data-currency_symbol="true">{{ $due }}</span>
                    </p>
                </div>
                <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-12 tw-h-12 tw-rounded-2xl tw-bg-amber-100 tw-text-amber-600"><i class="fas fa-file-invoice-dollar tw-text-lg"></i></span>
            </div>
            @if($due > 0)<p class="tw-text-xs tw-text-gray-400 tw-mt-2"><i class="fas fa-info-circle"></i> @lang('lang_v1.portal_pay_at_cashier')</p>@endif
        </a>

        {{-- Weight trend sparkline --}}
        @if(!empty($weight_trend) && $weight_trend->count() >= 2)
            @php
                $vals = $weight_trend->pluck('v'); $min = $vals->min(); $max = $vals->max(); $range = max(0.1, $max - $min);
                $n = $vals->count(); $pts = [];
                foreach ($vals as $i => $v) { $x = $n > 1 ? round($i / ($n - 1) * 100, 1) : 0; $y = round(30 - (($v - $min) / $range * 26) - 2, 1); $pts[] = "$x,$y"; }
                $line = implode(' ', $pts); $latest = $vals->last();
            @endphp
            <div class="tw-rounded-3xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-5 tw-shadow-sm">
                <div class="tw-flex tw-items-center tw-justify-between tw-mb-2">
                    <p class="tw-text-sm tw-text-gray-500">@lang('lang_v1.weight_trend')</p>
                    <p class="tw-text-lg tw-font-bold tw-text-gray-900">{{ $latest }} <span class="tw-text-xs tw-text-gray-400">kg</span></p>
                </div>
                <svg viewBox="0 0 100 30" preserveAspectRatio="none" class="tw-w-full tw-h-12">
                    <polyline points="{{ $line }}" fill="none" stroke="#4f46e5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke"/>
                </svg>
                <p class="tw-text-[11px] tw-text-gray-400 tw-mt-1">{{ @format_date($weight_trend->first()['t']) }} → {{ @format_date($weight_trend->last()['t']) }}</p>
            </div>
        @endif

        {{-- Latest activity --}}
        <div class="tw-rounded-3xl tw-bg-white tw-ring-1 tw-ring-gray-200 tw-p-4 tw-shadow-sm">
            <p class="tw-text-sm tw-font-semibold tw-text-gray-700 tw-mb-2">@lang('lang_v1.recent_activity')</p>
            @php
                $feed = collect();
                foreach($recent_consultations as $c){ $feed->push(['icon'=>'fa-stethoscope','fg'=>'tw-text-sky-600','bg'=>'tw-bg-sky-100','t'=>$c->created_at,'label'=>__('lang_v1.consultation').($c->diagnosis ? ' · '.\Illuminate\Support\Str::limit($c->diagnosis,32) : ''),'link'=>route('portal.visits')]); }
                foreach($recent_labs as $l){ $feed->push(['icon'=>'fa-vial','fg'=>'tw-text-amber-600','bg'=>'tw-bg-amber-100','t'=>$l->requested_at,'label'=>__('lang_v1.lab_request').' · '.$l->ref_no,'link'=>route('portal.lab-show',$l->id)]); }
                $feed = $feed->filter(fn($f)=>$f['t'])->sortByDesc('t')->take(6);
            @endphp
            @forelse($feed as $f)
                <a href="{{ $f['link'] }}" class="tw-flex tw-items-center tw-gap-3 tw-py-2.5 tw-border-b tw-border-gray-50 last:tw-border-0">
                    <span class="tw-inline-flex tw-items-center tw-justify-center tw-w-8 tw-h-8 tw-rounded-lg {{ $f['bg'] }} {{ $f['fg'] }} tw-shrink-0"><i class="fas {{ $f['icon'] }} tw-text-xs"></i></span>
                    <span class="tw-flex-1 tw-text-sm tw-text-gray-700 tw-truncate">{{ $f['label'] }}</span>
                    <span class="tw-text-xs tw-text-gray-400 tw-shrink-0">{{ \Carbon::parse($f['t'])->diffForHumans(null, true) }}</span>
                </a>
            @empty
                <div class="tw-text-center tw-py-6">
                    <i class="fas fa-inbox tw-text-2xl tw-text-gray-200"></i>
                    <p class="tw-text-sm tw-text-gray-400 tw-mt-1">@lang('lang_v1.no_recent_activity')</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
