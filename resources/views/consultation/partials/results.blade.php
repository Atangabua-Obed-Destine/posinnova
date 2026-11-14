{{--
    Lab tests and imaging asked for in a consultation, with where they stand, the results and the files.
    $lab_requests, $imaging_requests (attachments loaded). Shown on the consultation page and in the
    consultation form while the doctor continues it.
--}}
@php
    $pu = new \App\Utils\Util();
    $pay_cls = ['paid' => 'label-success', 'partial' => 'label-warning', 'due' => 'label-danger', 'none' => 'label-default', 'not_billed' => 'label-default'];
    $pay_label = function ($req) use ($pu) {
        $p = \App\Utils\ClinicRequestPayment::of($req);

        return [$p['state'], __('clinic.pay_state_' . $p['state'], ['due' => $pu->num_f($p['due'], true)])];
    };
@endphp
<div class="consult-results">
    @if ($lab_requests->isEmpty() && $imaging_requests->isEmpty())
        <p class="text-muted" style="margin:0;">@lang('clinic.cr_none')</p>
    @endif

    @foreach ($lab_requests as $lr)
        @php [$ps, $pl] = $pay_label($lr); $done = in_array($lr->status, ['resulted', 'validated']); @endphp
        <div class="cr-item" data-lab="{{ $lr->id }}" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">
            <div style="display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;">
                <strong><i class="fas fa-vial"></i> {{ $lr->ref_no }}</strong>
                <span class="label {{ $done ? 'label-success' : 'label-warning' }} cr-status">{{ __('lang_v1.lab_status_' . $lr->status) }}</span>
                <span class="label {{ $pay_cls[$ps] ?? 'label-default' }}">{{ $pl }}</span>
                <span class="text-muted" style="font-size:12px;">{{ @format_datetime($lr->requested_at) }}</span>
                <span style="margin-left:auto;display:flex;gap:6px;">
                    @if (! $done && $lr->status != 'validated' && auth()->user()->can('lab_result.enter'))
                        <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'enterResult'], [$lr->id]) }}" target="_blank" class="btn btn-xs btn-default"><i class="fas fa-pen"></i> @lang('lang_v1.enter_results')</a>
                    @endif
                    <a href="{{ action([\App\Http\Controllers\LabRequestController::class, 'show'], [$lr->id]) }}" target="_blank" class="btn btn-xs btn-default">@lang('clinic.hist_view') <i class="fas fa-external-link-alt"></i></a>
                </span>
            </div>
            <table class="table table-condensed" style="margin:6px 0 0;">
                <tbody>
                    @foreach ($lr->lines as $line)
                        <tr>
                            <td style="width:40%;">{{ $line->test_name }}</td>
                            <td>
                                @if (trim((string) $line->result_value) !== '')
                                    <strong>{{ $line->result_value }}</strong> {{ $line->unit }}
                                    @if (in_array($line->result_flag, ['high', 'low', 'critical']))
                                        <span class="label {{ $line->result_flag == 'critical' ? 'label-danger' : 'label-warning' }}">@lang('lang_v1.lab_flag_' . $line->result_flag)</span>
                                    @endif
                                    @if ($line->result_notes)<br><small class="text-muted">{{ $line->result_notes }}</small>@endif
                                @else
                                    <span class="text-muted">@lang('clinic.cr_waiting')</span>
                                @endif
                            </td>
                            <td class="text-muted" style="width:22%;font-size:12px;">{{ $line->normal_range }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($lr->attachments->isNotEmpty())
                @include('clinic.partials.attachments', ['type' => 'lab_request', 'owner' => $lr, 'readonly' => true])
            @endif
        </div>
    @endforeach

    @foreach ($imaging_requests as $ir)
        @php [$ps, $pl] = $pay_label($ir); @endphp
        <div class="cr-item" data-imaging="{{ $ir->id }}" style="border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;margin-bottom:10px;">
            <div style="display:flex;flex-wrap:wrap;gap:6px 10px;align-items:center;">
                <strong><i class="fas fa-x-ray"></i> {{ $ir->study_name }}</strong> <span class="text-muted">{{ $ir->ref_no }}</span>
                <span class="label {{ $ir->status == 'done' ? 'label-success' : 'label-warning' }} cr-status">{{ __('lang_v1.imaging_status_' . $ir->status) }}</span>
                <span class="label {{ $pay_cls[$ps] ?? 'label-default' }}">{{ $pl }}</span>
                <span style="margin-left:auto;display:flex;gap:6px;">
                    @if ($ir->status != 'done' && auth()->user()->can('imaging_result.enter'))
                        <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'enterReport'], [$ir->id]) }}" target="_blank" class="btn btn-xs btn-default"><i class="fas fa-pen"></i> @lang('lang_v1.enter_report')</a>
                    @endif
                    <a href="{{ action([\App\Http\Controllers\ImagingController::class, 'show'], [$ir->id]) }}" target="_blank" class="btn btn-xs btn-default">@lang('clinic.hist_view') <i class="fas fa-external-link-alt"></i></a>
                </span>
            </div>
            @if ($ir->status == 'done' && $ir->report)
                <div style="white-space:pre-wrap;margin-top:6px;font-size:13px;">{{ $ir->report }}</div>
            @elseif ($ir->status != 'done')
                <p class="text-muted" style="margin:6px 0 0;">@lang('clinic.cr_waiting')</p>
            @endif
            @if ($ir->attachments->isNotEmpty())
                @include('clinic.partials.attachments', ['type' => 'imaging_request', 'owner' => $ir, 'readonly' => true])
            @endif
        </div>
    @endforeach
</div>
