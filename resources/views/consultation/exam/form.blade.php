{{--
    PosInnova: an examination form (ENT, eye...) to fill in: $blocks (the template's), $values (answers so far),
    $exam (ConsultationExam or null: its drawings). Needs consultation.exam.assets on the page.
--}}
@php
    $ex_widths = \App\Utils\ClinicExamTemplates::WIDTHS;
    $ex_sides = ['R' => __('clinic.ex_right'), 'L' => __('clinic.ex_left')];
@endphp
<div class="ex-form">
    <div class="ex-grid">
        @foreach ($blocks as $b)
            @php
                $cols = $ex_widths[$b['width'] ?? 'full'] ?? 12;
                $val = $values[$b['id']] ?? null;
            @endphp
            @if ($b['type'] == 'heading')
                <h4 class="ex-heading">{{ $b['label'] }}</h4>
            @elseif ($b['type'] == 'drawing')
                <div class="ex-cell ex-cols-{{ $cols }}">
                    @include('consultation.exam.drawing', ['b' => $b, 'exam' => $exam])
                </div>
            @else
                <div class="ex-cell ex-cols-{{ $cols }} ex-field" data-type="{{ $b['type'] }}">
                    <div class="ex-label">{{ $b['label'] }}@if (! empty($b['unit'])) <span class="text-muted">({{ $b['unit'] }})</span>@endif</div>
                    @if (! empty($b['sides']))
                        <div class="ex-sides">
                            @foreach ($ex_sides as $s => $s_label)
                                <div class="ex-side">
                                    <span class="ex-side-tag ex-side-{{ $s }}" title="{{ $s_label }}">{{ $s_label }}</span>
                                    @include('consultation.exam.input', ['b' => $b, 'name' => 'exam[v][' . $b['id'] . '][' . $s . ']',
                                        'oname' => 'exam[o][' . $b['id'] . '][' . $s . ']', 'val' => is_array($val) ? ($val[$s] ?? null) : null, 'aria' => $b['label'] . ' - ' . $s_label])
                                </div>
                            @endforeach
                        </div>
                    @else
                        @include('consultation.exam.input', ['b' => $b, 'name' => 'exam[v][' . $b['id'] . ']', 'oname' => 'exam[o][' . $b['id'] . ']', 'val' => $val, 'aria' => $b['label']])
                    @endif
                    @if (! empty($b['help']))<p class="help-block" style="margin:2px 0 0;">{{ $b['help'] }}</p>@endif
                </div>
            @endif
        @endforeach
    </div>
</div>
