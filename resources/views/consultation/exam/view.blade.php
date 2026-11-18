{{--
    PosInnova: an examination form as filled in (read only), by section: what was drawn, then the answers.
    $exam (ConsultationExam); $mini: drawings only, small (patient history).
    Sections with nothing drawn or answered are left out. Needs the styles of consultation.exam.assets
    (or the print page's own).
--}}
@php
    $ex_widths = \App\Utils\ClinicExamTemplates::WIDTHS;
    $ex_side_lbl = ['R' => __('clinic.ex_right_short'), 'L' => __('clinic.ex_left_short')];
    $answers = $exam->answers();
    $sections = [];
    $cur = ['title' => null, 'draws' => [], 'rows' => []];
    foreach ($exam->blocks ?: [] as $b) {
        if ($b['type'] == 'heading') {
            $sections[] = $cur;
            $cur = ['title' => $b['label'], 'draws' => [], 'rows' => []];
        } elseif ($b['type'] == 'drawing') {
            if ($url = $exam->drawingUrl($b['id'])) {
                $cur['draws'][] = ['b' => $b, 'url' => $url];
            }
        } elseif (\App\Utils\ClinicExamTemplates::filled($answers[$b['id']] ?? null)) {
            $v = $answers[$b['id']];
            if (! empty($b['sides']) && is_array($v) && (isset($v['R']) || isset($v['L']))) {
                $parts = [];
                foreach (['R', 'L'] as $s) {
                    if (\App\Utils\ClinicExamTemplates::filled($v[$s] ?? null)) {
                        $parts[] = $ex_side_lbl[$s] . ': ' . \App\Utils\ClinicExamTemplates::text($v[$s]) . (! empty($b['unit']) ? ' ' . $b['unit'] : '');
                    }
                }
                $text = implode('  |  ', $parts);
            } else {
                $text = \App\Utils\ClinicExamTemplates::text($v) . (! empty($b['unit']) ? ' ' . $b['unit'] : '');
            }
            $cur['rows'][] = ['label' => $b['label'], 'text' => $text];
        }
    }
    $sections[] = $cur;
    $sections = array_values(array_filter($sections, fn ($s) => ! empty($s['draws']) || (empty($mini) && ! empty($s['rows']))));
@endphp
<div class="ex-view {{ ! empty($mini) ? 'ex-view-mini' : '' }}">
    @if (! empty($mini))
        <div class="ex-view-draws">
            @foreach ($sections as $sec)
                @foreach ($sec['draws'] as $d)
                    <figure class="ex-view-draw">
                        <div class="ex-draw-stage">
                            @if (! empty($d['b']['image']))<img class="ex-draw-base" src="{{ asset($d['b']['image']) }}" alt="">@else<div class="ex-draw-blank"></div>@endif
                            <img class="ex-draw-over" src="{{ $d['url'] }}" alt="{{ $d['b']['label'] }}">
                        </div>
                        <figcaption>{{ $d['b']['label'] }}</figcaption>
                    </figure>
                @endforeach
            @endforeach
        </div>
    @else
        @forelse ($sections as $sec)
            <div class="ex-view-sec">
                @if ($sec['title'])<h4>{{ $sec['title'] }}</h4>@endif
                @if (! empty($sec['draws']))
                    <div class="ex-view-draws">
                        @foreach ($sec['draws'] as $d)
                            <figure class="ex-view-draw ex-w-{{ $ex_widths[$d['b']['width'] ?? 'half'] ?? 6 }}">
                                <div class="ex-draw-stage">
                                    @if (! empty($d['b']['image']))<img class="ex-draw-base" src="{{ asset($d['b']['image']) }}" alt="">@else<div class="ex-draw-blank"></div>@endif
                                    <img class="ex-draw-over" src="{{ $d['url'] }}" alt="{{ $d['b']['label'] }}">
                                </div>
                                <figcaption>{{ $d['b']['label'] }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                @endif
                @if (! empty($sec['rows']))
                    <dl class="ex-view-rows">
                        @foreach ($sec['rows'] as $r)
                            <div><dt>{{ $r['label'] }}</dt><dd>{{ $r['text'] }}</dd></div>
                        @endforeach
                    </dl>
                @endif
            </div>
        @empty
            <p class="text-muted">@lang('clinic.ex_nothing_recorded')</p>
        @endforelse
    @endif
</div>
