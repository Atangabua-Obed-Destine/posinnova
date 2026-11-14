<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@lang('lang_v1.prescription') - {{ $consultation->ref_no }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 13px; color: #222; margin: 24px; }
        .rx-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
        .rx-header h2 { margin: 0; font-size: 20px; }
        .rx-header p { margin: 2px 0; font-size: 12px; color: #555; }
        .rx-meta { width: 100%; margin-bottom: 12px; font-size: 12px; }
        .rx-meta td { padding: 2px 4px; vertical-align: top; }
        .rx-symbol { font-size: 28px; font-weight: bold; font-style: italic; }
        table.rx-items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.rx-items th, table.rx-items td { border: 1px solid #999; padding: 6px 8px; text-align: left; font-size: 12px; }
        table.rx-items th { background: #f2f2f2; }
        .rx-section { margin-top: 14px; page-break-inside: avoid; }
        .rx-section h3 { margin: 0 0 4px; font-size: 14px; }
        .rx-section-elsewhere h3 { color: #b91c1c; }
        .rx-section-elsewhere table.rx-items th { background: #fde8e8; }
        .rx-section-not_given { color: #666; }
        .rx-diagnosis { margin: 8px 0; padding: 6px; background: #f8f8f8; border-left: 3px solid #666; }
        .rx-footer { margin-top: 40px; font-size: 12px; }
        .rx-sign { margin-top: 50px; border-top: 1px solid #333; width: 220px; padding-top: 4px; }
        @media print { .no-print { display: none; } body { margin: 10px; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; margin-bottom:12px;">
        <button onclick="window.print()" style="padding:6px 16px; cursor:pointer;">@lang('lang_v1.print')</button>
    </div>

    <div class="rx-header">
        <h2>{{ $business->name }}</h2>
        @if(!empty($consultation->visit) && !empty($consultation->visit->location))
            <p>{{ $consultation->visit->location->name }}</p>
        @endif
        <p>@lang('lang_v1.prescription')</p>
    </div>

    <table class="rx-meta">
        <tr>
            <td width="60%"><strong>@lang('lang_v1.patient'):</strong> {{ $consultation->patient->name }}</td>
            <td><strong>@lang('lang_v1.file_number'):</strong> {{ optional($consultation->patient->patientDetails)->file_number ?: '-' }}</td>
        </tr>
        <tr>
            <td>
                <strong>@lang('lang_v1.sex'):</strong> {{ optional($consultation->patient->patientDetails)->sex ?: '-' }}
                &nbsp;&nbsp;
                <strong>@lang('lang_v1.age'):</strong>
                {{ optional($consultation->patient->patientDetails)->date_of_birth ? \Carbon::parse($consultation->patient->patientDetails->date_of_birth)->age : '-' }}
            </td>
            <td><strong>@lang('lang_v1.date'):</strong> {{ @format_datetime($consultation->created_at) }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>@lang('purchase.ref_no'):</strong> {{ $consultation->ref_no }}</td>
        </tr>
    </table>

    @if($consultation->diagnosis)
        <div class="rx-diagnosis"><strong>@lang('lang_v1.diagnosis'):</strong> {{ $consultation->diagnosis }}</div>
    @endif

    <div class="rx-symbol">&#8478;</div>

    @php
        // PosInnova: one prescription, in the sections the patient acts on. A line partly given and the
        // rest to buy outside shows in both, each with its own quantity.
        $rx_all = $consultation->prescriptions;
        $rx_sections = [
            ['key' => 'given', 'title' => __('clinic.rx_print_given'), 'qty_label' => __('clinic.rx_print_qty_given'), 'icon' => '&#10003;',
                'lines' => $rx_all->filter(fn ($rx) => $rx->given_qty > 0), 'qty' => fn ($rx) => $rx->given_qty],
            ['key' => 'collect', 'title' => __('clinic.rx_print_to_collect'), 'qty_label' => __('sale.qty'), 'icon' => '&#9679;',
                'lines' => $rx_all->filter(fn ($rx) => $rx->outstanding_qty > 0), 'qty' => fn ($rx) => $rx->outstanding_qty],
            ['key' => 'elsewhere', 'title' => __('clinic.rx_print_elsewhere'), 'qty_label' => __('clinic.rx_print_qty_to_buy'), 'icon' => '&#10148;',
                'lines' => $rx_all->filter(fn ($rx) => $rx->elsewhere_qty > 0), 'qty' => fn ($rx) => $rx->elsewhere_qty],
            ['key' => 'not_given', 'title' => __('clinic.rx_print_not_given'), 'qty_label' => __('sale.qty'), 'icon' => '&#8211;',
                'lines' => $rx_all->filter(fn ($rx) => $rx->closed_at && $rx->elsewhere_qty <= 0 && $rx->given_qty < (float) $rx->quantity), 'qty' => fn ($rx) => max(0, (float) $rx->quantity - $rx->given_qty)],
        ];
    @endphp
    @foreach($rx_sections as $sec)
        @continue($sec['lines']->isEmpty())
        <div class="rx-section rx-section-{{ $sec['key'] }}">
            <h3>{!! $sec['icon'] !!} {{ $sec['title'] }}</h3>
            <table class="rx-items">
                <thead>
                    <tr>
                        <th style="width:4%;">#</th>
                        <th>@lang('lang_v1.medicine')</th>
                        <th>@lang('lang_v1.dosage')</th>
                        <th>@lang('lang_v1.frequency')</th>
                        <th>@lang('lang_v1.duration')</th>
                        <th style="width:12%;">{{ $sec['qty_label'] }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($sec['lines']->values() as $i => $rx)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><strong>{{ $rx->displayName() }}</strong>
                                @if ($sec['key'] == 'not_given' && $rx->close_reason)<br><small>{{ \App\Utils\Dispensing::reasonLabel($rx->close_reason) }}</small>@endif
                                @if ($rx->notes)<br><small>{{ $rx->notes }}</small>@endif
                            </td>
                            <td>{{ $rx->dosage ?: '-' }}</td>
                            <td>{{ $rx->frequency ?: '-' }}</td>
                            <td>{{ $rx->duration ?: '-' }}</td>
                            <td>{{ $sec['qty']($rx) + 0 }}@if ($sec['qty']($rx) + 0 != $rx->quantity + 0) <small>/ {{ $rx->quantity + 0 }}</small>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="rx-footer">
        <div class="rx-sign">
            {{ optional($consultation->consultedBy)->user_full_name }}<br>
            <small>@lang('lang_v1.consulted_by')</small>
        </div>
    </div>
</body>
</html>
