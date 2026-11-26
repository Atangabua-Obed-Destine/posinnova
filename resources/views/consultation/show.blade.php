@extends('layouts.app')
@section('title', __('lang_v1.view_consultation'))

@php
    $c = $consultation;
    $patient = $c->patient;
    $pd = optional($patient)->patientDetails;
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
@endphp

@section('content')
<section class="content">

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ $patient->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $c->ref_no }}</span>
                    {{ __('lang_v1.consultation_type_' . $c->consultation_type) }}{{ $c->specialty ? ' · ' . $c->specialty : '' }} ·
                    <span class="label {{ $c->status == 'final' ? 'label-success' : 'label-warning' }}">{{ __('lang_v1.consultation_status_' . $c->status) }}</span>
                    @if ($corrections->isNotEmpty())<a href="#consult_corrections" class="label label-warning" id="corr_tag"><i class="fas fa-pen"></i> @lang('clinic.corr_tag')</a>@endif ·
                    {{ @format_datetime($c->created_at) }}
                </p>
            </div>
            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 pos-actions">
                @if(! empty($can_continue))
                    <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'edit'], [$c->id]) }}" class="tw-dw-btn tw-dw-btn-sm" style="background:#d97706;color:#fff;border:none;" id="continue_consultation">
                        <i class="fas fa-pen"></i> @lang('clinic.cons_continue')
                    </a>
                @endif
                @if (! empty($can_correct))
                    <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'correct'], [$c->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" id="correct_consultation"
                        style="border-color:#dc6803;color:#b54708;" title="{{ __('clinic.corr_btn_help') }}">
                        <i class="fas fa-pen"></i> @lang('clinic.corr_btn')
                    </a>
                @endif
                @if($c->status == 'draft')
                    @can('consultation.finalize')
                        <form method="POST" action="{{ action([\App\Http\Controllers\ConsultationController::class, 'finalize'], [$c->id]) }}" style="display:inline;">
                            @csrf
                            <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                                <i class="fas fa-check"></i> @lang('lang_v1.finalize_consultation')
                            </button>
                        </form>
                    @endcan
                @endif
                @php $pending_rx = $c->prescriptions->filter(fn ($rx) => $rx->outstanding_qty > 0)->count(); @endphp
                @if($pending_rx > 0 && auth()->user()->can('sell.create'))
                    <a href="{{ action([\App\Http\Controllers\SellPosController::class, 'create']) }}?prescription_id={{ $c->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-green-600 tw-text-white tw-border-none">
                        <i class="fas fa-prescription-bottle-alt"></i> @lang('lang_v1.dispense_in_pos')
                    </a>
                @endif
                @can('lab_request.create')
                    <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'create']) }}?visit_id={{ $c->visit_id }}&consultation_id={{ $c->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-vial"></i> @lang('lang_v1.request_lab_tests')
                    </a>
                @endcan
                @can('imaging_request.create')
                    <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'create']) }}?visit_id={{ $c->visit_id }}&consultation_id={{ $c->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-x-ray"></i> @lang('lang_v1.request_imaging')
                    </a>
                @endcan
                {{-- PosInnova: the prescription is what the patient takes home (free); the full report can be a paid extra --}}
                @if($c->prescriptions->count() > 0)
                    <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'prescription'], [$c->id]) }}" target="_blank"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-primary tw-text-white" id="print_prescription" title="{{ __('clinic.rpt_print_rx_help') }}">
                        <i class="fas fa-prescription"></i> @lang('lang_v1.print_prescription')
                    </a>
                @endif
                @if (in_array($report_state, ['free', 'paid']))
                    <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'report'], [$c->id]) }}" target="_blank"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" id="print_consultation_report">
                        <i class="fas fa-file-medical"></i> @lang('clinic.cons_print_report')
                    </a>
                @elseif ($report_state == 'not_charged')
                    @php
                        $cur = session('currency');
                        $fee_text = number_format((float) $report_fee, session('business.currency_precision', 2), $cur['decimal_separator'] ?? '.', $cur['thousand_separator'] ?? ',');
                        $fee_text = session('business.currency_symbol_placement') == 'after' ? $fee_text.' '.($cur['symbol'] ?? '') : trim(($cur['symbol'] ?? '').' '.$fee_text);
                    @endphp
                    <form method="POST" action="{{ action([\App\Http\Controllers\ConsultationController::class, 'chargeReportFee'], [$c->id]) }}" style="display:inline;"
                        onsubmit="return confirm(this.getAttribute('data-confirm'));" data-confirm="{{ __('clinic.rpt_charge_confirm', ['amount' => $fee_text]) }}">
                        @csrf
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline" id="charge_report_fee">
                            <i class="fas fa-file-medical"></i> {{ __('clinic.rpt_btn_charge', ['amount' => $fee_text]) }}
                        </button>
                    </form>
                @else
                    <span class="tw-dw-btn tw-dw-btn-sm" id="report_fee_unpaid" title="{{ __('clinic.rpt_unpaid_blocked') }}" style="background:#fffaeb;color:#b54708;border:1px solid #fec84b;cursor:default;">
                        <i class="fas fa-file-medical"></i> @lang('clinic.rpt_unpaid')
                    </span>
                    @can('sell.payments')
                        <a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$report_invoice->id]) }}?post_to_register=1"
                            class="add_payment_modal tw-dw-btn tw-dw-btn-sm tw-dw-btn-success tw-text-white" id="report_fee_pay">
                            <i class="fas fa-money-bill-alt"></i> @lang('clinic.take_payment')
                        </a>
                    @endcan
                @endif
                @if ($may_staff_copy)
                    {{-- clinic admins: a staff copy without charge (referral, transfer), with a reason that is kept --}}
                    <form method="POST" action="{{ action([\App\Http\Controllers\ConsultationController::class, 'staffCopy'], [$c->id]) }}" target="_blank" style="display:inline;" id="staff_copy_form"
                        data-ask="{{ __('clinic.rpt_staff_reason_ask') }}"
                        onsubmit="var r = window.prompt(this.getAttribute('data-ask'), ''); if (r === null || r.trim().length < 3) { return false; } this.reason.value = r.trim(); setTimeout(function () { location.reload(); }, 1500); return true;">
                        @csrf
                        <input type="hidden" name="reason" value="">
                        <button type="submit" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-ghost" id="staff_copy_btn" title="{{ __('clinic.rpt_staff_help') }}">
                            <i class="fas fa-user-shield"></i> @lang('clinic.rpt_staff_btn')
                        </button>
                    </form>
                @endif
                <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$c->visit_id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('lang_v1.back_to_visit')
                </a>
            </div>
        </div>
        @if ($staff_copies->isNotEmpty())
            <div id="staff_copies" class="tw-text-xs tw-text-gray-600 tw-mt-3" style="border-top:1px dashed #d0d5dd;padding-top:8px;">
                <strong><i class="fas fa-user-shield"></i> @lang('clinic.rpt_staff_list')</strong>
                @foreach ($staff_copies as $sc)
                    <div class="staff-copy-row">{{ (new \App\Utils\Util())->format_date($sc->created_at, true) }} · {{ trim(optional($sc->causer)->user_full_name) ?: '-' }} · {{ $sc->getExtraProperty('reason') }}</div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- PosInnova: corrections made after it was finished (the earlier versions stay readable) --}}
    @if ($corrections->isNotEmpty())
        <div id="consult_corrections" style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#fffaeb;border:1px solid #fec84b;border-left:5px solid #dc6803;color:#7a2e0e;scroll-margin-top:80px;">
            <strong><i class="fas fa-pen"></i> @lang('clinic.corr_list_title')</strong>
            @foreach ($corrections->reverse() as $corr)
                <div class="corr-row" style="display:flex;flex-wrap:wrap;gap:6px 14px;align-items:flex-start;padding:8px 0;{{ $loop->first ? '' : 'border-top:1px solid #fedf89;' }}">
                    <div style="flex:1 1 280px;min-width:0;line-height:1.5;">
                        <strong>{{ __('clinic.corr_notice', ['time' => (new \App\Utils\Util())->format_date($corr->created_at, true), 'name' => trim(optional($corr->correctedBy)->user_full_name) ?: '-']) }}</strong><br>
                        <span>@lang('clinic.corr_changed'): {{ \App\Utils\ConsultationCorrections::labels($corr->changed) }}</span><br>
                        <span style="white-space:pre-line;">@lang('clinic.corr_reason_lbl'): {{ $corr->reason }}</span>
                    </div>
                    <a href="{{ action([\App\Http\Controllers\ConsultationController::class, 'correction'], [$c->id, $corr->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline corr-see" style="border-color:#dc6803;color:#b54708;background:#fff;">
                        <i class="fas fa-history"></i> @lang('clinic.corr_see_before')
                    </a>
                </div>
            @endforeach
        </div>
    @endif

    <div class="row">
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.vitals')])
                <table class="table table-bordered">
                    <tr><th width="50%">@lang('lang_v1.weight_kg')</th><td>{{ $c->vitals_weight ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.height_cm')</th><td>{{ $c->vitals_height ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.bmi')</th><td>{{ $c->bmi ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.blood_pressure')</th><td>{{ ($c->vitals_bp_systolic && $c->vitals_bp_diastolic) ? $c->vitals_bp_systolic . '/' . $c->vitals_bp_diastolic . ' mmHg' : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.temperature_c')</th><td>{{ $c->vitals_temperature ? $c->vitals_temperature . ' °C' : '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.pulse_bpm')</th><td>{{ $c->vitals_pulse ?? '-' }}</td></tr>
                    <tr><th>@lang('clinic.cons_resp_rate')</th><td>{{ $c->vitals_resp_rate ?? '-' }}</td></tr>
                    <tr><th>@lang('lang_v1.spo2')</th><td>{{ $c->vitals_spo2 ? $c->vitals_spo2 . '%' : '-' }}</td></tr>
                </table>
            @endcomponent
        </div>
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.examination_and_diagnosis')])
                <p><strong>@lang('lang_v1.chief_complaint'):</strong><br>{{ $c->chief_complaint ?: '-' }}</p>
                @if ($c->history_of_complaint)
                    <p style="white-space:pre-line;"><strong>@lang('clinic.cons_history_of_complaint'):</strong><br>{{ $c->history_of_complaint }}</p>
                @endif
                <p style="white-space:pre-line;"><strong>@lang('lang_v1.examination_notes'):</strong><br>{{ $c->examination_notes ?: '-' }}</p>
                <p style="white-space:pre-line;"><strong>@lang('clinic.cons_provisional_diagnosis'):</strong><br>{{ $c->diagnosis ?: '-' }}</p>
                @if ($c->differential_diagnosis)
                    <p style="white-space:pre-line;"><strong>@lang('clinic.cons_differential_diagnosis'):</strong><br>{{ $c->differential_diagnosis }}</p>
                @endif
                @if ($c->management_plan)
                    <p style="white-space:pre-line;"><strong>@lang('clinic.cons_management_plan'):</strong><br>{{ $c->management_plan }}</p>
                @endif
                @if ($c->review_date)
                    <p id="consult_review"><strong>@lang('clinic.cons_review_date'):</strong> <span class="label label-info" style="font-size:12px;"><i class="fas fa-calendar-check"></i> {{ @format_date($c->review_date) }}</span>
                        @if ($c->review_notes) · {{ $c->review_notes }}@endif</p>
                @endif
                @if($c->referralDepartment)
                    <p><strong>@lang('lang_v1.referred_to'):</strong> {{ $c->referralDepartment->name }}</p>
                @endif
                @if($c->admission_requested)
                    <p><span class="label label-warning"><i class="fas fa-procedures"></i> @lang('lang_v1.admission_requested')</span></p>
                @endif
                <p><strong>@lang('lang_v1.consulted_by'):</strong> {{ optional($c->consultedBy)->user_full_name ?? '-' }}</p>
                <p><strong>@lang('lang_v1.consultation_fee'):</strong> <span class="display_currency" data-currency_symbol="true">{{ $c->consultation_fee }}</span></p>
            @endcomponent
        </div>
    </div>

    {{-- PosInnova: the examination form (drawings and findings), and the photos / videos --}}
    @if ($c->exam && $c->exam->hasContent())
        @component('components.widget', ['class' => 'box-solid', 'title' => $c->exam->template_name])
            <div id="consult_exam_view">@include('consultation.exam.view', ['exam' => $c->exam])</div>
        @endcomponent
    @endif
    @if ($c->attachments->isNotEmpty() || \App\Http\Controllers\ClinicAttachmentController::canUpload('consultation'))
        @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.ex_photos_title')])
            @include('clinic.partials.attachments', ['type' => 'consultation', 'owner' => $c])
        @endcomponent
    @endif

    @if($lab_requests->isNotEmpty() || $imaging_requests->isNotEmpty())
        @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.cr_title')])
            @include('consultation.partials.results')
        @endcomponent
    @endif

    @if($c->prescriptions->count() > 0)
        @component('components.widget', ['class' => 'box-solid', 'title' => __('lang_v1.prescription')])
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>@lang('lang_v1.medicine')</th>
                            <th>@lang('clinic.rx_strength')</th>
                            <th>@lang('clinic.rx_route')</th>
                            <th>@lang('lang_v1.dosage')</th>
                            <th>@lang('lang_v1.frequency')</th>
                            <th>@lang('lang_v1.duration')</th>
                            <th>@lang('sale.qty')</th>
                            <th>@lang('lang_v1.notes')</th>
                            <th>@lang('lang_v1.dispensed')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($c->prescriptions as $rx)
                            <tr>
                                <td>{{ $rx->displayName() }}@if ($rx->isFreeText()) <br><small class="text-muted"><i class="fas fa-store-slash"></i> @lang('clinic.rx_not_in_stock_tag')</small>@endif</td>
                                <td>{{ $rx->strength ?: '-' }}</td>
                                <td>{{ $rx->route ?: '-' }}</td>
                                <td>{{ $rx->dosage ?: '-' }}</td>
                                <td>{{ $rx->frequency ?: '-' }}</td>
                                <td>{{ $rx->duration ?: '-' }}</td>
                                <td>{{ $rx->quantity + 0 }}</td>
                                <td>{{ $rx->notes ?: '-' }}</td>
                                <td>@include('clinic.partials.rx_state', ['rx' => $rx])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endcomponent
    @endif

</section>
@if ($report_state == 'due')
    <div class="modal fade payment_modal" tabindex="-1" role="dialog"></div>
    <div class="modal fade edit_payment_modal" tabindex="-1" role="dialog"></div>
@endif
@endsection

@section('javascript')
@include('consultation.exam.assets')
@if ($report_state == 'due')
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endif
@if (session('status.success'))
    {{-- just saved: the copy kept on this device in case the page was closed is no longer needed --}}
    <script>try { window.localStorage.removeItem('pi_consult_v{{ (int) $c->visit_id }}_u{{ (int) auth()->id() }}'); window.localStorage.removeItem('pi_consult_v{{ (int) $c->visit_id }}_u{{ (int) auth()->id() }}_fix{{ (int) $c->id }}'); } catch (e) { /* private mode */ }</script>
@endif
@endsection
