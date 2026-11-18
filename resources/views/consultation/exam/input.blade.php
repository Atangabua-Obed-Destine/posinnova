{{-- One answer of an examination form block: $b, $name, $oname (other), $val, $aria --}}
@php
    $opts = $b['options'] ?? [];
@endphp
@if ($b['type'] == 'choice' || $b['type'] == 'multi')
    @php
        $picked = $b['type'] == 'multi' ? (array) ($val ?? []) : ($val === null ? [] : [(string) $val]);
        $other = implode(', ', array_filter($picked, fn ($p) => ! in_array($p, $opts, true)));
    @endphp
    <div class="ex-chips" role="group" aria-label="{{ $aria }}">
        @foreach ($opts as $o)
            <label class="ex-chip">
                @if ($b['type'] == 'multi')
                    <input type="checkbox" name="{{ $name }}[]" value="{{ $o }}" {{ in_array($o, $picked, true) ? 'checked' : '' }}>
                @else
                    <input type="radio" name="{{ $name }}" value="{{ $o }}" {{ in_array($o, $picked, true) ? 'checked' : '' }}>
                @endif
                <span>{{ $o }}</span>
            </label>
        @endforeach
        @if (! empty($b['other']))
            <input type="text" class="ex-other form-control input-sm" name="{{ $oname }}" value="{{ $other }}" maxlength="191"
                placeholder="{{ __('clinic.ex_other_ph') }}" aria-label="{{ $aria }} - {{ __('clinic.ex_other') }}">
        @endif
    </div>
@elseif ($b['type'] == 'number')
    <div class="input-group input-group-sm ex-number">
        <input type="number" step="any" class="form-control" name="{{ $name }}" value="{{ $val }}" aria-label="{{ $aria }}">
        @if (! empty($b['unit']))<span class="input-group-addon">{{ $b['unit'] }}</span>@endif
    </div>
@elseif ($b['type'] == 'notes')
    <textarea class="form-control input-sm" name="{{ $name }}" rows="2" aria-label="{{ $aria }}">{{ $val }}</textarea>
@else
    <input type="text" class="form-control input-sm" name="{{ $name }}" value="{{ $val }}" maxlength="500" aria-label="{{ $aria }}">
@endif
