<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@lang('lang_v1.lab_result') - {{ $lab_request->ref_no }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color: #222; margin: 24px; }
        .lab-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
        .lab-header h2 { margin: 0; font-size: 20px; }
        .lab-header p { margin: 2px 0; font-size: 12px; color: #555; }
        .lab-meta { width: 100%; margin-bottom: 12px; font-size: 12px; }
        .lab-meta td { padding: 2px 4px; vertical-align: top; }
        table.lab-items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.lab-items th, table.lab-items td { border: 1px solid #999; padding: 6px 8px; text-align: left; font-size: 12px; }
        table.lab-items th { background: #f2f2f2; }
        .flag-critical { color: #c0392b; font-weight: bold; }
        .flag-high { color: #e67e22; font-weight: bold; }
        .flag-low { color: #2980b9; font-weight: bold; }
        .lab-footer { margin-top: 40px; font-size: 12px; }
        .lab-sign { margin-top: 50px; border-top: 1px solid #333; width: 220px; padding-top: 4px; }
        @media print { .no-print { display: none; } body { margin: 10px; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; margin-bottom:12px;">
        <button onclick="window.print()" style="padding:6px 16px; cursor:pointer;">@lang('lang_v1.print')</button>
    </div>

    <div class="lab-header">
        <h2>{{ $business->name }}</h2>
        @if($lab_request->location)<p>{{ $lab_request->location->name }}</p>@endif
        <p>@lang('lang_v1.laboratory_result')</p>
    </div>

    <table class="lab-meta">
        <tr>
            <td width="60%"><strong>@lang('lang_v1.patient'):</strong> {{ optional($lab_request->patient)->name }}</td>
            <td><strong>@lang('lang_v1.file_number'):</strong> {{ optional($lab_request->patient->patientDetails)->file_number ?: '-' }}</td>
        </tr>
        <tr>
            <td>
                <strong>@lang('lang_v1.sex'):</strong> {{ optional($lab_request->patient->patientDetails)->sex ?: '-' }}
                &nbsp;&nbsp;
                <strong>@lang('lang_v1.age'):</strong>
                {{ optional($lab_request->patient->patientDetails)->date_of_birth ? \Carbon::parse($lab_request->patient->patientDetails->date_of_birth)->age : '-' }}
            </td>
            <td><strong>@lang('lang_v1.date'):</strong> {{ @format_datetime($lab_request->resulted_at ?? $lab_request->requested_at) }}</td>
        </tr>
        <tr>
            <td><strong>@lang('purchase.ref_no'):</strong> {{ $lab_request->ref_no }}</td>
            <td><strong>@lang('lang_v1.requested_by'):</strong> {{ optional($lab_request->requestedBy)->user_full_name }}</td>
        </tr>
    </table>

    <table class="lab-items">
        <thead>
            <tr>
                <th>@lang('lang_v1.test_name')</th>
                <th>@lang('lang_v1.result')</th>
                <th>@lang('lang_v1.normal_range')</th>
                <th>@lang('lang_v1.unit')</th>
                <th>@lang('lang_v1.flag')</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lab_request->lines as $line)
                <tr>
                    <td>{{ $line->test_name }}</td>
                    <td class="{{ in_array($line->result_flag, ['high','low','critical']) ? 'flag-' . $line->result_flag : '' }}">{{ $line->result_value ?: '-' }}</td>
                    <td>{{ $line->normal_range ?: '-' }}</td>
                    <td>{{ $line->unit ?: '-' }}</td>
                    <td>{{ $line->result_flag ? __('lang_v1.lab_flag_' . $line->result_flag) : '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- PosInnova: corrections made after validation, with the original value --}}
    @if (($amendments ?? collect())->count())
        <div style="margin-top:10px; font-size:11px;">
            <strong>@lang('clinic.amendments')</strong>
            @foreach ($amendments as $am)
                <div>{{ __('clinic.amended_from_to', ['test' => $am->test_name, 'from' => $am->old_value ?? '-', 'to' => $am->new_value]) }}
                    ({{ __('clinic.amended_by_on', ['user' => $am->amended_by_name ?: '-', 'date' => app(\App\Utils\Util::class)->format_date($am->created_at, true)]) }}): {{ $am->reason }}</div>
            @endforeach
        </div>
    @endif

    <div class="lab-footer">
        <div class="lab-sign">
            {{ optional($lab_request->validatedBy)->user_full_name ?? optional($lab_request->requestedBy)->user_full_name }}<br>
            <small>{{ $lab_request->validatedBy ? __('lang_v1.validated_by') : __('lang_v1.laboratory') }}</small>
        </div>
    </div>
</body>
</html>
