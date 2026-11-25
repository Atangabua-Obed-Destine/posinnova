{{-- One band or single tax, editable: fixed amounts or rates, both shares, bounds, threshold --}}
@php
    $fixed = (int) $b->tax_type === 2;
    $n = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
@endphp
<tr data-band="{{ $b->id }}">
    @if (! empty($named))<td>{{ $b->title }}@if ($b->is_dependent) <span class="ht-muted ht-small">({{ __('payroll.of_income_tax') }})</span>@endif <span class="ht-muted ht-small">· {{ $b->liability_code }}</span></td>@endif
    <td><input name="bands[{{ $b->id }}][min_amount]" class="ht-in" style="width:110px" value="{{ $n($b->min_amount) }}" aria-label="{{ __('payroll.from') }}"></td>
    <td><input name="bands[{{ $b->id }}][max_amount]" class="ht-in" style="width:110px" value="{{ $n($b->max_amount) }}" aria-label="{{ __('payroll.to') }}"></td>
    @if ($fixed)
        <td><input name="bands[{{ $b->id }}][fixed_amount]" class="ht-in" style="width:100px" value="{{ $n($b->fixed_amount) }}" aria-label="{{ __('payroll.fixed_staff') }}"></td>
        <td><input name="bands[{{ $b->id }}][employer_fixed_amount]" class="ht-in" style="width:100px" value="{{ $n($b->employer_fixed_amount) }}" aria-label="{{ __('payroll.fixed_employer') }}" {{ $b->paid_by === 'employee' ? 'disabled' : '' }}></td>
    @else
        <td><input name="bands[{{ $b->id }}][percentage]" class="ht-in" style="width:80px" value="{{ $n($b->percentage) }}" aria-label="{{ __('payroll.rate_staff') }}" {{ $b->paid_by === 'employer' ? 'disabled' : '' }}> %</td>
        <td><input name="bands[{{ $b->id }}][employer_percentage]" class="ht-in" style="width:80px" value="{{ $n($b->employer_percentage) }}" aria-label="{{ __('payroll.rate_employer') }}" {{ $b->paid_by === 'employee' ? 'disabled' : '' }}> %</td>
    @endif
    <td><input name="bands[{{ $b->id }}][threshold]" class="ht-in" style="width:110px" value="{{ $n($b->threshold) }}" aria-label="{{ __('payroll.threshold') }}" {{ $fixed || $b->is_dependent ? 'disabled' : '' }}></td>
    <td><input type="checkbox" name="bands[{{ $b->id }}][status]" value="1" {{ $b->status ? 'checked' : '' }} aria-label="{{ __('payroll.in_force') }}"></td>
</tr>
