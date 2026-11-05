<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('clinic.ins_claim') }} {{ $claim->ref_no }}</title>
    @php
        $util = app(\App\Utils\Util::class);
        $fmt = function ($n) use ($util) { return $util->num_f((float) $n); };
        $insurer = $claim->insurer;
    @endphp
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; margin: 24px; }
        .hd { display: flex; justify-content: space-between; gap: 20px; border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
        .hd h2 { margin: 0; font-size: 18px; }
        .hd p { margin: 2px 0; color: #555; }
        h3 { text-align: center; margin: 6px 0 12px; font-size: 16px; letter-spacing: .5px; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th, table.items td { border: 1px solid #999; padding: 5px 6px; text-align: left; vertical-align: top; }
        table.items th { background: #f2f2f2; font-size: 11px; }
        .r { text-align: right !important; white-space: nowrap; }
        tfoot td { font-weight: bold; background: #fafafa; }
        .sign { display: flex; justify-content: space-between; margin-top: 50px; }
        .sign div { border-top: 1px solid #333; width: 230px; padding-top: 4px; text-align: center; }
        .muted { color: #666; font-size: 11px; }
        @media print { .no-print { display: none; } body { margin: 10px; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; margin-bottom:12px;">
        <button onclick="window.print()" style="padding:6px 16px; cursor:pointer;">@lang('lang_v1.print')</button>
    </div>

    <div class="hd">
        <div>
            <h2>{{ $business->name }}</h2>
        </div>
        <div style="text-align:right;">
            <p><strong>{{ optional($insurer)->name }}</strong></p>
            @if (optional($insurer)->address)<p>{{ $insurer->address }}</p>@endif
            @if (optional($insurer)->phone || optional($insurer)->email)<p>{{ trim(optional($insurer)->phone.' '.optional($insurer)->email) }}</p>@endif
        </div>
    </div>

    <h3>{{ mb_strtoupper(__('clinic.ins_claim_title')) }} {{ $claim->ref_no }}</h3>
    <p>
        <strong>@lang('clinic.ins_period'):</strong> {{ @format_date($claim->date_from) }} – {{ @format_date($claim->date_to) }}
        &nbsp; · &nbsp; <strong>@lang('lang_v1.date'):</strong> {{ @format_date($claim->submitted_at ?: now()) }}
        &nbsp; · &nbsp; <strong>@lang('clinic.ins_items'):</strong> {{ $summary['count'] }}
    </p>

    <table class="items">
        <thead>
            <tr>
                <th>#</th>
                <th>@lang('lang_v1.date')</th>
                <th>@lang('lang_v1.patient')</th>
                <th>@lang('lang_v1.insurance_number')</th>
                <th>@lang('clinic.ins_services')</th>
                <th class="r">@lang('clinic.ins_full_price')</th>
                <th class="r">%</th>
                <th class="r">@lang('clinic.ins_patient_part')</th>
                <th class="r">@lang('clinic.ins_claimed')</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $n => $it)
                @php $pd = optional(optional($it->patient)->patientDetails); @endphp
                <tr>
                    <td>{{ $n + 1 }}</td>
                    <td>{{ @format_date($it->charged_on) }}<br><span class="muted">{{ $it->patient_invoice_no }}</span></td>
                    <td>{{ optional($it->patient)->name }}<br><span class="muted">{{ $pd->file_number }}</span></td>
                    <td>{{ $pd->insurance_number ?: '-' }}</td>
                    <td>
                        @foreach ((array) $it->lines as $l)
                            <div>{{ $l['name'] ?? '' }}@if (($l['qty'] ?? 1) != 1) × {{ (float) $l['qty'] }}@endif <span class="muted">({{ $fmt($l['price'] ?? 0) }})</span></div>
                        @endforeach
                    </td>
                    <td class="r">{{ $fmt($it->full_amount) }}</td>
                    <td class="r">{{ \App\Utils\ClinicInsurance::pct($it->pct) }}</td>
                    <td class="r">{{ $fmt($it->full_amount - $it->amount) }}</td>
                    <td class="r">{{ $fmt($it->amount) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">@lang('sale.total')</td>
                <td class="r">{{ $fmt($summary['full']) }}</td>
                <td></td>
                <td class="r">{{ $fmt($summary['full'] - $summary['claimed']) }}</td>
                <td class="r">{{ $fmt($summary['claimed']) }}</td>
            </tr>
        </tfoot>
    </table>

    <p style="margin-top:14px;">{{ __('clinic.ins_claim_amount_words', ['amount' => $util->num_f($summary['claimed'], true)]) }}</p>
    @if ($claim->notes)<p class="muted">{{ $claim->notes }}</p>@endif

    <div class="sign">
        <div>@lang('clinic.ins_sign_clinic')</div>
        <div>@lang('clinic.ins_sign_insurer')</div>
    </div>
</body>
</html>
