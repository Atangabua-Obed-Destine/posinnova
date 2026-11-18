<!DOCTYPE html>
@php
    $c = $consultation;
    $patient = $c->patient;
    $pd = optional($patient)->patientDetails;
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
    $exam = $c->exam && $c->exam->hasContent() ? $c->exam : null;
    $title = $c->exam ? $c->exam->template_name : __('clinic.cons_report_title');
    $residence = collect([optional($patient)->address_line_1, optional($patient)->city])->filter()->implode(', ');
    $phone = optional($patient)->mobile;
    $marital = optional($pd)->marital_status ? __('clinic.ms_' . $pd->marital_status) : null;
    $location = optional(optional($c->visit)->location);
    $photos = $c->attachments->filter(fn ($a) => $a->isImage())->take(6);
    $u = new \App\Utils\Util();
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} - {{ $patient->name }} - {{ $c->ref_no }}</title>
    <style>
        @page { margin: 14mm 12mm 18mm; }
        body { font-family: "Segoe UI", Arial, sans-serif; font-size: 12.5px; color: #111; margin: 20px; }
        .rp-head { display:flex; align-items:center; gap:14px; border-bottom:2px solid #111; padding-bottom:8px; }
        .rp-head img { max-height:64px; max-width:120px; }
        .rp-head h1 { margin:0; font-size:20px; letter-spacing:.5px; }
        .rp-head p { margin:2px 0 0; color:#444; font-size:12px; }
        .rp-title { text-align:center; font-weight:800; font-size:15px; text-transform:uppercase; letter-spacing:1px; margin:12px 0 10px; }
        .rp-grid { display:grid; grid-template-columns: 1fr 1fr; gap:4px 24px; margin-bottom:10px; }
        .rp-grid div { border-bottom:1px dotted #777; padding:2px 0; }
        .rp-grid .rp-wide { grid-column: 1 / -1; }
        .rp-grid strong { margin-right:4px; }
        h3.rp-h { font-size:13.5px; margin:12px 0 4px; padding-bottom:2px; border-bottom:1px solid #bbb; }
        .rp-text { white-space:pre-line; margin:0 0 4px; min-height:14px; }
        .rp-vitals { display:flex; flex-wrap:wrap; gap:4px 22px; margin:4px 0; }
        .rp-vitals span { border-bottom:1px dotted #777; padding:0 4px 1px 0; }
        table.rp-t { width:100%; border-collapse:collapse; margin-top:4px; }
        table.rp-t th, table.rp-t td { border:1px solid #333; padding:5px 6px; text-align:left; vertical-align:top; font-size:12px; }
        table.rp-t th { background:#f1f1f1; }
        .rp-photos { display:flex; flex-wrap:wrap; gap:8px; }
        .rp-photos img { height:130px; border:1px solid #999; border-radius:4px; }
        .rp-sign { margin-top:38px; display:flex; justify-content:flex-end; }
        .rp-sign div { border-top:1px solid #333; min-width:230px; text-align:center; padding-top:4px; }
        .rp-foot { position:fixed; left:0; right:0; bottom:0; text-align:center; font-style:italic; font-size:11px; color:#333; border-top:1px solid #333; padding-top:3px; background:#fff; }
        /* the examination form */
        .ex-view-sec { margin-bottom:10px; page-break-inside:avoid; }
        .ex-view-sec h4 { margin:8px 0 4px; font-size:13px; font-weight:700; }
        .ex-view-draws { display:flex; flex-wrap:wrap; gap:10px; margin-bottom:4px; }
        .ex-view-draw { margin:0; width:165px; break-inside:avoid; page-break-inside:avoid; }
        .ex-view-draws, .ex-view-rows div, table.rp-t tr, .rp-photos img { break-inside:avoid; page-break-inside:avoid; }
        .ex-view-draw.ex-w-6, .ex-view-draw.ex-w-8, .ex-view-draw.ex-w-9, .ex-view-draw.ex-w-12 { width:230px; }
        .ex-view-draw figcaption { font-size:10.5px; text-align:center; color:#333; }
        .ex-draw-stage { position:relative; border:1px solid #bbb; border-radius:4px; overflow:hidden; background:#fff; }
        .ex-draw-base { display:block; width:100%; height:auto; }
        .ex-draw-blank { width:100%; aspect-ratio:3/2; }
        .ex-draw-over { position:absolute; inset:0; width:100%; height:100%; }
        .ex-view-rows { margin:0; }
        .ex-view-rows div { display:flex; gap:8px; padding:2px 0; border-bottom:1px dotted #bbb; }
        .ex-view-rows dt { flex:0 0 36%; font-weight:600; }
        .ex-view-rows dd { flex:1 1 auto; margin:0; white-space:pre-line; }
        .no-print { text-align:center; margin-bottom:12px; }
        @media print { .no-print { display:none; } body { margin:0 0 20px; } }
    </style>
</head>
<body>
    <div class="no-print"><button type="button" onclick="window.print()" style="padding:6px 16px;cursor:pointer;">@lang('lang_v1.print')</button></div>

    <div class="rp-head">
        @if (! empty($business->logo))
            <img src="{{ asset('uploads/business_logos/' . $business->logo) }}" alt="">
        @endif
        <div>
            <h1>{{ $business->name }}</h1>
            @if ($location->name)
                <p>{{ collect([$location->name, $location->city, $location->mobile])->filter()->implode(' · ') }}</p>
            @endif
        </div>
    </div>

    <div class="rp-title">{{ $title }}</div>

    <div class="rp-grid">
        <div><strong>@lang('lang_v1.date'):</strong> {{ $u->format_date($c->created_at, true) }}</div>
        <div><strong>@lang('purchase.ref_no'):</strong> {{ $c->ref_no }}@if (optional($pd)->file_number) · @lang('lang_v1.file_number'): {{ $pd->file_number }}@endif</div>
        <div class="rp-wide"><strong>@lang('clinic.rp_name'):</strong> {{ $patient->name }}</div>
        <div><strong>@lang('lang_v1.age'):</strong> {{ $age !== null ? $age : '' }}</div>
        <div><strong>@lang('clinic.rp_gender'):</strong> {{ optional($pd)->sex ? __('clinic.rp_sex_' . $pd->sex) : '' }}</div>
        <div><strong>@lang('clinic.pf_occupation'):</strong> {{ optional($pd)->occupation }}</div>
        <div><strong>@lang('clinic.rp_residence'):</strong> {{ $residence }}</div>
        <div><strong>@lang('clinic.pf_marital_status'):</strong> {{ $marital }}</div>
        <div><strong>@lang('clinic.rp_phone'):</strong> {{ $phone }}</div>
    </div>

    <h3 class="rp-h">@lang('clinic.rp_presenting_complaint')</h3>
    <p class="rp-text">{{ $c->chief_complaint }}</p>

    <h3 class="rp-h">@lang('clinic.rp_history')</h3>
    <p class="rp-text">{{ $c->history_of_complaint }}</p>

    <h3 class="rp-h">@lang('clinic.rp_physical_exam')</h3>
    <div class="rp-vitals">
        <span><strong>@lang('clinic.rp_bp'):</strong> {{ ($c->vitals_bp_systolic && $c->vitals_bp_diastolic) ? $c->vitals_bp_systolic . '/' . $c->vitals_bp_diastolic . ' mmHg' : '' }}</span>
        <span><strong>@lang('clinic.rp_pulse'):</strong> {{ $c->vitals_pulse ? $c->vitals_pulse . ' /min' : '' }}</span>
        <span><strong>@lang('clinic.rp_resp_rate'):</strong> {{ $c->vitals_resp_rate ? $c->vitals_resp_rate . ' /min' : '' }}</span>
        <span><strong>@lang('clinic.rp_temperature'):</strong> {{ $c->vitals_temperature ? ($c->vitals_temperature + 0) . ' °C' : '' }}</span>
        @if ($c->vitals_spo2)<span><strong>SpO₂:</strong> {{ $c->vitals_spo2 }}%</span>@endif
        @if ($c->vitals_weight)<span><strong>@lang('lang_v1.weight_kg'):</strong> {{ $c->vitals_weight + 0 }}</span>@endif
    </div>
    @if ($c->examination_notes)
        <p class="rp-text"><strong>@lang('clinic.rp_general'):</strong> {{ $c->examination_notes }}</p>
    @endif
    @if ($exam)
        @include('consultation.exam.view', ['exam' => $exam])
    @endif
    @if ($photos->isNotEmpty())
        <h3 class="rp-h">@lang('clinic.ex_photos_title')</h3>
        <div class="rp-photos">
            @foreach ($photos as $ph)
                <img src="{{ $ph->url() }}" alt="{{ $ph->original_name }}">
            @endforeach
        </div>
    @endif

    <h3 class="rp-h">@lang('clinic.cons_provisional_diagnosis')</h3>
    <p class="rp-text">{{ $c->diagnosis }}</p>

    <h3 class="rp-h">@lang('clinic.cons_differential_diagnosis')</h3>
    <p class="rp-text">{{ $c->differential_diagnosis }}</p>

    <h3 class="rp-h">@lang('clinic.rp_investigations')</h3>
    @if ($lab_requests->isEmpty() && $imaging_requests->isEmpty())
        <p class="rp-text"></p>
    @else
        @foreach ($lab_requests as $lr)
            @foreach ($lr->lines as $line)
                <div>• {{ $line->test_name }}@if (trim((string) $line->result_value) !== ''): <strong>{{ $line->result_value }}</strong> {{ $line->unit }}@if (in_array($line->result_flag, ['high', 'low', 'critical'])) ({{ __('lang_v1.lab_flag_' . $line->result_flag) }})@endif @if ($line->normal_range)<small>[{{ $line->normal_range }}]</small>@endif @endif</div>
            @endforeach
        @endforeach
        @foreach ($imaging_requests as $ir)
            <div>• {{ $ir->study_name }}@if ($ir->status == 'done' && $ir->report): <span style="white-space:pre-line;">{{ $ir->report }}</span>@endif</div>
        @endforeach
    @endif

    <h3 class="rp-h">@lang('clinic.rp_management')</h3>
    @if ($c->prescriptions->isNotEmpty())
        <table class="rp-t">
            <thead>
                <tr>
                    <th>@lang('clinic.rp_drug')</th>
                    <th>@lang('clinic.rx_strength')</th>
                    <th>@lang('clinic.rx_route')</th>
                    <th>@lang('lang_v1.dosage')</th>
                    <th>@lang('lang_v1.frequency')</th>
                    <th>@lang('lang_v1.duration')</th>
                    <th>@lang('sale.qty')</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($c->prescriptions as $rx)
                    <tr>
                        <td>{{ $rx->displayName() }}@if ($rx->notes)<br><small>{{ $rx->notes }}</small>@endif</td>
                        <td>{{ $rx->strength }}</td>
                        <td>{{ $rx->route }}</td>
                        <td>{{ $rx->dosage }}</td>
                        <td>{{ $rx->frequency }}</td>
                        <td>{{ $rx->duration }}</td>
                        <td>{{ $rx->quantity + 0 }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if ($c->management_plan)
        <p class="rp-text" style="margin-top:6px;">{{ $c->management_plan }}</p>
    @endif
    @if ($c->referralDepartment)
        <p class="rp-text"><strong>@lang('lang_v1.referred_to'):</strong> {{ $c->referralDepartment->name }}</p>
    @endif

    <h3 class="rp-h">@lang('clinic.rp_review')</h3>
    <p class="rp-text">@if ($c->review_date){{ $u->format_date($c->review_date) }}@endif @if ($c->review_notes) — {{ $c->review_notes }}@endif</p>

    <div class="rp-sign">
        <div>{{ trim(optional($c->consultedBy)->user_full_name) }}<br><small>@lang('lang_v1.consulted_by')</small></div>
    </div>

    <div class="rp-foot">{{ $business->name }}</div>
</body>
</html>
