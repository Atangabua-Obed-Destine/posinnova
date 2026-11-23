@extends('layouts.app')
@section('title', __('clinic.corr_page_title', ['no' => $correction->no, 'total' => $total]))

{{--
    PosInnova: one correction to a finished consultation: who, when, why; what was changed (before / after);
    and the whole consultation as it was just before. Read only.
    $consultation, $correction (->no), $before / $after (pictures: App\Utils\ConsultationCorrections), $exam_before, $total
--}}
@php
    $CC = \App\Utils\ConsultationCorrections::class;
    $u = new \App\Utils\Util();
    $patient = $consultation->patient;
    $pd = optional($patient)->patientDetails;
    $changed = (array) $correction->changed;
    $show_url = action([\App\Http\Controllers\ConsultationController::class, 'show'], [$consultation->id]);
    $val = fn ($snap, $part) => $CC::text($snap, $part);
@endphp

@section('content')
<section class="content">
    <style>
        .corr-pre { white-space:pre-line; margin:0; }
        .corr-none { color:#98a2b3; font-style:italic; }
        .corr-diff { width:100%; border-collapse:separate; border-spacing:0; }
        .corr-diff th, .corr-diff td { padding:10px 12px; vertical-align:top; border-bottom:1px solid #eaecf0; text-align:left; }
        .corr-diff thead th { font-size:12px; text-transform:uppercase; letter-spacing:.4px; color:#475467; background:#f9fafb; }
        .corr-diff td.b { background:#fff6f5; }
        .corr-diff td.a { background:#f3fbf6; }
        .corr-diff th.p { width:22%; font-weight:600; color:#101828; }
        .corr-was { border-left:4px solid #dc6803; padding-left:10px; }
        .corr-chg { display:inline-block; margin-left:6px; padding:1px 7px; border-radius:10px; background:#fef0c7; color:#93370d; font-size:11px; font-weight:600; vertical-align:middle; }
        @media (max-width: 767px) {
            .corr-diff thead { display:none; }
            .corr-diff, .corr-diff tbody, .corr-diff tr, .corr-diff th, .corr-diff td { display:block; width:100% !important; }
            .corr-diff tr { margin-bottom:12px; border:1px solid #eaecf0; border-radius:10px; overflow:hidden; }
            .corr-diff td::before { content:attr(data-l); display:block; font-size:11px; text-transform:uppercase; letter-spacing:.4px; color:#667085; margin-bottom:2px; }
        }
    </style>

    <div class="tw-bg-white tw-shadow-sm tw-rounded-xl tw-ring-1 tw-ring-gray-200 tw-p-4 sm:tw-p-5 tw-mb-4">
        <div class="tw-flex tw-flex-wrap tw-items-center tw-justify-between tw-gap-4">
            <div>
                <p class="tw-text-xl tw-font-semibold tw-text-gray-900 tw-mb-1">{{ $patient->name }}
                    <small class="tw-text-gray-500">{{ optional($pd)->file_number ? '· ' . $pd->file_number : '' }}</small>
                </p>
                <p class="tw-text-sm tw-text-gray-500 tw-mb-0">
                    <span class="label label-info">{{ $consultation->ref_no }}</span>
                    <span class="label label-warning"><i class="fas fa-pen"></i> {{ __('clinic.corr_page_title', ['no' => $correction->no, 'total' => $total]) }}</span>
                </p>
            </div>
            <a href="{{ $show_url }}" class="tw-dw-btn tw-dw-btn-sm tw-bg-gradient-to-r tw-from-indigo-600 tw-to-blue-500 tw-text-white tw-border-none" id="corr_back">
                <i class="fa fa-arrow-left"></i> @lang('clinic.corr_page_current')
            </a>
        </div>
    </div>

    <div id="corr_head" style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#fffaeb;border:1px solid #fec84b;border-left:5px solid #dc6803;color:#7a2e0e;line-height:1.55;">
        <strong>{{ __('clinic.corr_notice', ['time' => $u->format_date($correction->created_at, true), 'name' => trim(optional($correction->correctedBy)->user_full_name) ?: '-']) }}</strong><br>
        <span style="white-space:pre-line;"><strong>@lang('clinic.corr_reason_lbl'):</strong> {{ $correction->reason }}</span><br>
        <small><i class="fas fa-info-circle"></i> @lang('clinic.corr_page_banner')</small>
    </div>

    @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.corr_what_changed')])
        <table class="corr-diff" id="corr_diff">
            <thead><tr><th>@lang('clinic.corr_col_part')</th><th>@lang('clinic.corr_col_before')</th><th>@lang('clinic.corr_col_after')</th></tr></thead>
            <tbody>
                @foreach ($changed as $part)
                    @php $b = $val($before, $part); $a = $val($after, $part); @endphp
                    <tr data-part="{{ $part }}">
                        <th class="p">{{ $CC::label($part) }}</th>
                        <td class="b" data-l="{{ __('clinic.corr_col_before') }}">
                            @if ($b !== '')<p class="corr-pre">{{ $b }}</p>@else<span class="corr-none">@lang('clinic.corr_empty')</span>@endif
                            @if ($part == 'exam' && $b !== '')<small class="text-muted">(@lang('clinic.corr_exam_below'))</small>@endif
                        </td>
                        <td class="a" data-l="{{ __('clinic.corr_col_after') }}">
                            @if ($a !== '')<p class="corr-pre">{{ $a }}</p>@else<span class="corr-none">@lang('clinic.corr_empty')</span>@endif
                            @if ($part == 'exam' && $a !== '')<small class="text-muted">(@lang('clinic.corr_exam_now'))</small>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endcomponent

    @component('components.widget', ['class' => 'box-solid', 'title' => __('clinic.corr_as_it_was')])
        <div class="corr-was" id="corr_was">
            @foreach (['type', 'complaint', 'history', 'vitals', 'exam_notes', 'diagnosis', 'differential', 'plan', 'review', 'referral'] as $part)
                @php $t = $val($before, $part); @endphp
                @if ($t !== '' || in_array($part, $changed))
                    <p style="margin-bottom:10px;"><strong>{{ $CC::label($part) }}:</strong>@if (in_array($part, $changed))<span class="corr-chg">@lang('clinic.corr_changed')</span>@endif<br>
                        @if ($t !== '')<span class="corr-pre" style="display:block;">{{ $t }}</span>@else<span class="corr-none">@lang('clinic.corr_empty')</span>@endif</p>
                @endif
            @endforeach

            @if ($exam_before && $exam_before->hasContent())
                <p style="margin:14px 0 6px;"><strong>{{ $CC::label('exam') }}: {{ $exam_before->template_name }}</strong>@if (in_array('exam', $changed))<span class="corr-chg">@lang('clinic.corr_changed')</span>@endif</p>
                <div id="corr_exam_view">@include('consultation.exam.view', ['exam' => $exam_before])</div>
            @endif

            @if (! empty($before['rx']))
                <p style="margin:14px 0 6px;"><strong>{{ $CC::label('prescription') }}:</strong>@if (in_array('prescription', $changed))<span class="corr-chg">@lang('clinic.corr_changed')</span>@endif</p>
                <div class="table-responsive">
                    <table class="table table-bordered table-condensed" id="corr_rx">
                        <thead><tr>
                            <th>@lang('lang_v1.medicine')</th><th>@lang('clinic.rx_strength')</th><th>@lang('clinic.rx_route')</th><th>@lang('lang_v1.dosage')</th>
                            <th>@lang('lang_v1.frequency')</th><th>@lang('lang_v1.duration')</th><th>@lang('sale.qty')</th><th>@lang('lang_v1.notes')</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($before['rx'] as $r)
                                <tr>
                                    <td>{{ $r['name'] ?? '' }}@if (! empty($r['outside'])) <br><small class="text-muted">@lang('clinic.rx_buys_outside')</small>@endif</td>
                                    <td>{{ $r['strength'] ?? '' ?: '-' }}</td>
                                    <td>{{ $r['route'] ?? '' ?: '-' }}</td>
                                    <td>{{ $r['dosage'] ?? '' ?: '-' }}</td>
                                    <td>{{ $r['frequency'] ?? '' ?: '-' }}</td>
                                    <td>{{ $r['duration'] ?? '' ?: '-' }}</td>
                                    <td>{{ ($r['quantity'] ?? 0) + 0 }}</td>
                                    <td>{{ $r['notes'] ?? '' ?: '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endcomponent

</section>
@endsection

@section('javascript')
@include('consultation.exam.assets')
@endsection
