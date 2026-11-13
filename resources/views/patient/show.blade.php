@extends('layouts.app')
@section('title', __('lang_v1.view_patient'))

@php
    $pd = $contact->patientDetails;
    $age = optional($pd)->date_of_birth ? \Carbon::parse($pd->date_of_birth)->age : null;
    $has_allergies = ! empty(optional($pd)->known_allergies);
    $wards_on = \App\Utils\ClinicSettings::sectionOn($contact->business_id, 'wards');
@endphp

@section('content')
<section class="content">

    @if (session('patient_created'))
        @php $pc = session('patient_created'); @endphp
        <div class="callout callout-success" id="patient_created_box" style="font-size:15px;">
            <strong><i class="fas fa-check-circle"></i> @lang('clinic.reg_done_title')</strong><br>
            @if (! empty($pc['pin']))
                {!! __('clinic.reg_done_tell', ['file' => '<strong style="font-size:18px;">' . e($pc['file']) . '</strong>', 'pin' => '<strong style="font-size:18px;">' . e($pc['pin']) . '</strong>']) !!}
            @else
                {!! __('clinic.reg_done_file', ['file' => '<strong style="font-size:18px;">' . e($pc['file']) . '</strong>']) !!}
            @endif
        </div>
    @endif

    {{-- Patient header card --}}
    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div class="tw-flex tw-items-center tw-gap-4">
                <div class="tw-inline-flex tw-items-center tw-justify-center tw-h-14 tw-w-14 tw-rounded-full tw-bg-sky-100 tw-text-sky-600">
                    <i class="fas fa-user-injured tw-text-2xl"></i>
                </div>
                <div>
                    <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-0">{{ $contact->name }}</p>
                    <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                        <span class="tw-font-medium tw-text-gray-700">@lang('lang_v1.file_number'):</span>
                        {{ optional($pd)->file_number ?: '-' }}
                    </p>
                </div>
            </div>
            <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2 pos-actions">
                {{-- PosInnova: the usual next steps for this patient, by permission --}}
                @can('visit.create')
                    <a href="{{ action([\App\Http\Controllers\VisitController::class, 'create']) }}?contact_id={{ $contact->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none">
                        <i class="fas fa-sign-in-alt"></i> @lang('clinic.check_in_patient')
                    </a>
                @endcan
                @can('patient.view_bill')
                    <a href="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$contact->id]) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-file-invoice-dollar"></i> @lang('clinic.patient_account')
                    </a>
                @endcan
                @if (\App\Utils\ClinicAppointments::enabled($contact->business_id) && auth()->user()->can('appointment.manage'))
                    <a href="{{ action([\App\Http\Controllers\ClinicAppointmentController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-calendar-plus"></i> @lang('clinic.dash_book_appointment')
                    </a>
                @endif
                @if ($wards_on && auth()->user()->can('admission.create'))
                    <a href="{{ action([\App\Http\Controllers\AdmissionController::class, 'create']) }}?contact_id={{ $contact->id }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fas fa-procedures"></i> @lang('lang_v1.admit')
                    </a>
                @endif
                @can('patient.update')
                    <a href="{{ action([\App\Http\Controllers\PatientController::class, 'edit'], [$contact->id]) }}"
                        class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                        <i class="fa fa-edit"></i> @lang('messages.edit')
                    </a>
                @endcan
                <a href="{{ action([\App\Http\Controllers\PatientController::class, 'index']) }}" class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                    <i class="fa fa-arrow-left"></i> @lang('messages.back')
                </a>
            </div>
        </div>

        @if($has_allergies)
            <div class="tw-mt-3 tw-rounded-lg tw-bg-red-50 tw-ring-1 tw-ring-red-200 tw-px-3 tw-py-2 tw-text-sm tw-text-red-700">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>@lang('lang_v1.known_allergies'):</strong> {{ $pd->known_allergies }}
            </div>
        @endif
    </div>

    <div class="nav-tabs-custom">
        <ul class="nav nav-tabs nav-justified pos-tabs-scroll">
            <li class="{{ $view_type == 'info' ? 'active' : '' }}"><a href="#patient_info_tab" data-toggle="tab"><i class="fas fa-id-card"></i> @lang('lang_v1.clinical_info')</a></li>
            <li><a href="#patient_timeline_tab" data-toggle="tab"><i class="fas fa-stream"></i> @lang('lang_v1.timeline')</a></li>
            <li><a href="#patient_visits_tab" data-toggle="tab"><i class="fas fa-stethoscope"></i> @lang('lang_v1.visits')</a></li>
            <li><a href="#patient_prescriptions_tab" data-toggle="tab"><i class="fas fa-prescription"></i> @lang('lang_v1.prescriptions')</a></li>
            <li><a href="#patient_lab_tab" data-toggle="tab"><i class="fas fa-vial"></i> @lang('lang_v1.lab_results')</a></li>
            @if ($wards_on)
            <li><a href="#patient_admissions_tab" data-toggle="tab"><i class="fas fa-procedures"></i> @lang('lang_v1.admissions')</a></li>
            @endif
            @can('patient.view_bill')
            <li><a href="#patient_billing_tab" data-toggle="tab"><i class="fas fa-file-invoice-dollar"></i> @lang('lang_v1.billing')</a></li>
            @endcan
            <li><a href="#patient_activities_tab" data-toggle="tab"><i class="fas fa-history"></i> @lang('lang_v1.activities')</a></li>
        </ul>

        <div class="tab-content">
            {{-- Clinical Info --}}
            <div class="tab-pane {{ $view_type == 'info' ? 'active' : '' }}" id="patient_info_tab">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr><th width="40%">@lang('lang_v1.sex')</th><td>{{ optional($pd)->sex ? __('lang_v1.' . ['M'=>'male','F'=>'female','Other'=>'other'][$pd->sex]) : '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.date_of_birth')</th><td>{{ optional($pd)->dobLabel() ?: '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.age')</th><td>{{ !is_null($age) ? $age . ' ' . __('lang_v1.years') : '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.blood_group')</th><td>{{ optional($pd)->blood_group ?: '-' }}</td></tr>
                            <tr><th>@lang('contact.mobile')</th><td>{{ $contact->mobile ?: '-' }}</td></tr>
                            <tr><th><i class="fab fa-whatsapp" style="color:#16a34a;"></i> @lang('clinic.wa_messages_short')</th>
                                <td id="wa_consent_cell">
                                    @if (optional($pd)->whatsapp_opt_in)
                                        <span class="text-success"><i class="fas fa-check"></i> {{ __('clinic.wa_consent_yes', ['lang' => optional($pd)->whatsapp_language == 'en' ? 'English' : (optional($pd)->whatsapp_language == 'fr' ? 'Français' : __('clinic.wa_consent_lang_default'))]) }}</span>
                                    @elseif (optional($pd)->whatsapp_opt_out_at)
                                        <span class="text-warning"><i class="fas fa-ban"></i> {{ __('clinic.wa_consent_stopped', ['date' => @format_date($pd->whatsapp_opt_out_at)]) }}</span>
                                    @else
                                        <span class="text-muted">@lang('clinic.wa_consent_no')</span>
                                    @endif
                                </td></tr>
                            <tr><th>@lang('contact.alternate_contact_number')</th><td>{{ $contact->alternate_number ?: '-' }}</td></tr>
                            <tr><th>@lang('business.email')</th><td>{{ $contact->email ?: '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.address')</th><td>{{ implode(', ', array_filter([$contact->address_line_1, $contact->city, $contact->state, $contact->country])) ?: '-' }}</td></tr>
                            @if(optional($pd)->mother)
                                <tr><th>@lang('lang_v1.mother')</th><td>{{ $pd->mother->name }} ({{ optional($pd->mother->patientDetails)->file_number }})</td></tr>
                            @endif
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-bordered">
                            <tr><th colspan="2" class="bg-light">@lang('lang_v1.next_of_kin')</th></tr>
                            <tr><th width="40%">@lang('lang_v1.next_of_kin_name')</th><td>{{ optional($pd)->next_of_kin_name ?: '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.next_of_kin_phone')</th><td>{{ optional($pd)->next_of_kin_phone ?: '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.next_of_kin_relationship')</th><td>{{ optional($pd)->next_of_kin_relationship ?: '-' }}</td></tr>
                            <tr><th colspan="2" class="bg-light">@lang('lang_v1.insurance')</th></tr>
                            <tr><th>@lang('lang_v1.insurance_provider')</th><td>{{ optional($pd)->insurance_provider ?: '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.insurance_number')</th><td>{{ optional($pd)->insurance_number ?: '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.insurance_coverage_pct')</th><td>{{ !is_null(optional($pd)->insurance_coverage_pct) ? $pd->insurance_coverage_pct . '%' : '-' }}</td></tr>
                            <tr><th>@lang('lang_v1.insurance_expiry_date')</th><td>{{ optional($pd)->insurance_expiry_date ? optional($pd)->insurance_expiry_date->format('d-m-Y') : '-' }}</td></tr>
                        </table>
                    </div>
                    @if(optional($pd)->notes)
                        <div class="col-md-12">
                            <div class="well well-sm"><strong>@lang('lang_v1.additional_notes'):</strong> {{ $pd->notes }}</div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- 360° Timeline --}}
            <div class="tab-pane" id="patient_timeline_tab">
                @if($timeline->isEmpty())
                    <p class="text-muted tw-text-center tw-py-8"><i class="fas fa-stream tw-text-2xl tw-text-gray-300"></i><br>@lang('lang_v1.no_recent_activity')</p>
                @else
                    <ul class="tw-relative tw-pl-6 tw-m-0 tw-list-none">
                        <span class="tw-absolute tw-left-2 tw-top-2 tw-bottom-2 tw-w-px tw-bg-gray-200"></span>
                        @php $colors = ['sky'=>'tw-bg-sky-100 tw-text-sky-600','indigo'=>'tw-bg-indigo-100 tw-text-indigo-600','amber'=>'tw-bg-amber-100 tw-text-amber-600','purple'=>'tw-bg-purple-100 tw-text-purple-600','green'=>'tw-bg-green-100 tw-text-green-600','rose'=>'tw-bg-rose-100 tw-text-rose-600']; @endphp
                        @foreach($timeline as $e)
                            <li class="tw-relative tw-mb-4">
                                <span class="tw-absolute tw--left-6 tw-inline-flex tw-items-center tw-justify-center tw-w-5 tw-h-5 tw-rounded-full tw-ring-2 tw-ring-white {{ $colors[$e['cls']] ?? 'tw-bg-gray-100 tw-text-gray-600' }}"><i class="fas {{ $e['icon'] }} tw-text-[10px]"></i></span>
                                <div class="tw-flex tw-items-center tw-justify-between tw-gap-2">
                                    <div class="tw-min-w-0">
                                        @if($e['url'])<a href="{{ $e['url'] }}" class="tw-text-sm tw-font-semibold tw-text-gray-800 hover:tw-text-indigo-600">{{ $e['title'] }}</a>@else<span class="tw-text-sm tw-font-semibold tw-text-gray-800">{{ $e['title'] }}</span>@endif
                                        @if(!empty($e['sub']))<p class="tw-text-xs tw-text-gray-500 tw-mt-0.5">{{ $e['sub'] }}</p>@endif
                                    </div>
                                    <span class="tw-text-xs tw-text-gray-400 tw-shrink-0">{{ @format_date($e['t']) }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Visits --}}
            <div class="tab-pane" id="patient_visits_tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('lang_v1.visit_type')</th>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('purchase.business_location')</th>
                                <th>@lang('lang_v1.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($visits as $v)
                                <tr>
                                    <td>{{ $v->ref_no }}</td>
                                    <td>{{ __('lang_v1.visit_type_' . $v->type) }}</td>
                                    <td>{{ @format_date($v->visit_date) }}</td>
                                    <td>{{ optional($v->location)->name ?: '-' }}</td>
                                    <td><span class="label {{ $v->status == 'open' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.visit_status_' . $v->status) }}</span></td>
                                    <td>
                                        @can('visit.view')
                                            <a href="{{ action([\App\Http\Controllers\VisitController::class, 'show'], [$v->id]) }}" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Prescriptions --}}
            <div class="tab-pane" id="patient_prescriptions_tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('lang_v1.medicine')</th>
                                <th>@lang('lang_v1.dosage')</th>
                                <th>@lang('lang_v1.frequency')</th>
                                <th>@lang('lang_v1.duration')</th>
                                <th>@lang('sale.qty')</th>
                                <th>@lang('lang_v1.status')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prescriptions as $rx)
                                <tr>
                                    <td>{{ @format_date(optional($rx->consultation)->created_at) }}</td>
                                    <td>{{ $rx->displayName() }}</td>
                                    <td>{{ $rx->dosage ?: '-' }}</td>
                                    <td>{{ $rx->frequency ?: '-' }}</td>
                                    <td>{{ $rx->duration ?: '-' }}</td>
                                    <td>{{ $rx->quantity + 0 }}</td>
                                    <td>@include('clinic.partials.rx_state', ['rx' => $rx])</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Lab results --}}
            <div class="tab-pane" id="patient_lab_tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('lang_v1.tests')</th>
                                <th>@lang('lang_v1.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lab_requests as $lr)
                                <tr>
                                    <td>{{ $lr->ref_no }}</td>
                                    <td>{{ @format_date($lr->requested_at) }}</td>
                                    <td>{{ $lr->lines->count() }}</td>
                                    <td><span class="label {{ $lr->status == 'validated' ? 'label-success' : ($lr->status == 'resulted' ? 'label-primary' : 'label-default') }}">{{ __('lang_v1.lab_status_' . $lr->status) }}</span></td>
                                    <td>
                                        @if(auth()->user()->can('lab_request.view') || auth()->user()->can('lab_result.view'))
                                            <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lr->id]) }}" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <h4 class="tw-mt-4 tw-font-semibold">@lang('lang_v1.imaging_requests')</h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('lang_v1.study')</th>
                                <th>@lang('lang_v1.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($imaging_requests as $ir)
                                <tr>
                                    <td>{{ $ir->ref_no }}</td>
                                    <td>{{ @format_date($ir->requested_at) }}</td>
                                    <td>{{ $ir->study_name }}</td>
                                    <td><span class="label {{ $ir->status == 'done' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.imaging_status_' . $ir->status) }}</span></td>
                                    <td>
                                        @if(auth()->user()->can('imaging_request.view') || auth()->user()->can('imaging_result.view'))
                                            <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'show'], [$ir->id]) }}" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="tab-pane" id="patient_admissions_tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('purchase.ref_no')</th>
                                <th>@lang('lang_v1.ward')</th>
                                <th>@lang('lang_v1.bed')</th>
                                <th>@lang('lang_v1.admission_date')</th>
                                <th>@lang('lang_v1.discharge_date')</th>
                                <th>@lang('lang_v1.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($admissions as $adm)
                                <tr>
                                    <td>{{ $adm->ref_no }}</td>
                                    <td>{{ optional($adm->ward)->name ?: '-' }}</td>
                                    <td>{{ optional($adm->bed)->bed_number ?: '-' }}</td>
                                    <td>{{ @format_datetime($adm->admission_date) }}</td>
                                    <td>{{ $adm->discharge_date ? @format_datetime($adm->discharge_date) : '-' }}</td>
                                    <td><span class="label {{ $adm->status == 'active' ? 'label-success' : 'label-default' }}">{{ __('lang_v1.admission_status_' . $adm->status) }}</span></td>
                                    <td>
                                        @can('admission.view')
                                            <a href="{{ action([\App\Http\Controllers\AdmissionController::class, 'show'], [$adm->id]) }}" class="btn btn-xs btn-info"><i class="fas fa-eye"></i></a>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Billing (reuses the customer due + Pay-due + ledger flow).
                 PosInnova: only for roles with "View patient bills". --}}
            @can('patient.view_bill')
            <div class="tab-pane" id="patient_billing_tab">
                <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-3 tw-mb-3">
                    <div>
                        <span class="tw-text-sm tw-text-gray-500">@lang('lang_v1.outstanding_due')</span>
                        <div class="tw-text-2xl tw-font-bold {{ $total_due > 0 ? 'tw-text-red-600' : 'tw-text-green-600' }}">
                            <span class="display_currency" data-currency_symbol="true">{{ $total_due }}</span>
                        </div>
                    </div>
                    <div class="tw-flex tw-items-center tw-gap-2">
                        {{-- PosInnova clinic: payments go through the patient account
                             screen (receipt + cash register), not the generic pay-due modal. --}}
                        <a href="{{ action([\App\Http\Controllers\ClinicAccountController::class, 'show'], [$contact->id]) }}"
                            class="tw-dw-btn tw-dw-btn-sm {{ auth()->user()->can('sell.payments') && $total_due > 0 ? 'tw-bg-green-600 tw-text-white tw-border-none' : 'tw-dw-btn-outline' }}">
                            <i class="fas fa-wallet"></i> {{ auth()->user()->can('sell.payments') && $total_due > 0 ? __('clinic.acc_receive_payment') : __('clinic.patient_account') }}
                        </a>
                        <a href="{{ action([\App\Http\Controllers\ContactController::class, 'show'], [$contact->id]) }}?view=ledger"
                            class="tw-dw-btn tw-dw-btn-sm tw-dw-btn-outline">
                            <i class="fas fa-scroll"></i> @lang('lang_v1.ledger')
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('sale.invoice_no')</th>
                                <th>@lang('lang_v1.description')</th>
                                <th class="text-right">@lang('lang_v1.amount')</th>
                                <th>@lang('lang_v1.status')</th>
                                <th>@lang('messages.action')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($charge_transactions as $ct)
                                <tr>
                                    <td>{{ @format_date($ct->transaction_date) }}</td>
                                    <td>{{ $ct->invoice_no }}</td>
                                    <td>{{ $ct->additional_notes ?: '-' }}</td>
                                    <td class="text-right"><span class="display_currency" data-currency_symbol="true">{{ $ct->final_total }}</span></td>
                                    <td>
                                        @if($ct->payment_status == 'paid')
                                            <span class="label label-success">@lang('lang_v1.paid')</span>
                                        @elseif($ct->payment_status == 'partial')
                                            <span class="label label-info">@lang('lang_v1.partial')</span>
                                        @else
                                            <span class="label label-warning">@lang('lang_v1.due')</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(auth()->user()->can('sell.payments') && $ct->payment_status != 'paid')
                                            <a href="{{ action([\App\Http\Controllers\TransactionPaymentController::class, 'addPayment'], [$ct->id]) }}"
                                                class="btn btn-xs btn-success add_payment_modal"><i class="fas fa-money-bill-alt"></i> @lang('lang_v1.pay')</a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">@lang('lang_v1.no_charges_yet')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="help-block">@lang('lang_v1.patient_billing_note')</p>
            </div>
            @endcan

            {{-- Activities --}}
            <div class="tab-pane" id="patient_activities_tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('lang_v1.date')</th>
                                <th>@lang('lang_v1.action')</th>
                                <th>@lang('lang_v1.by')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activities as $activity)
                                <tr>
                                    <td>{{ @format_datetime($activity->created_at) }}</td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $activity->description)) }}</td>
                                    <td>{{ optional($activity->causer)->user_full_name ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted">@lang('lang_v1.no_records_found')</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- Payment modals (reuse the customer payment flow) --}}
    <div class="modal fade payment_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>
    <div class="modal fade pay_contact_due_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel"></div>

</section>
@endsection

@section('javascript')
    <script src="{{ asset('js/payment.js?v=' . $asset_v) }}"></script>
@endsection
