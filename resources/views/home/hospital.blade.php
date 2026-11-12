@extends('layouts.app')
@section('title', __('home.home'))

{{-- PosInnova: home page when the hospital module is on (data: App\Utils\ClinicDashboard) --}}
@section('content')
@php
    $tc = session('business.theme_color') ?: 'primary';
    $hour = (int) now()->format('G');
    $greet = $hour < 12 ? 'clinic.dash_good_morning' : ($hour < 18 ? 'clinic.dash_good_afternoon' : 'clinic.dash_good_evening');
    $who = trim(preg_replace('/\s+/', ' ', (auth()->user()->surname ?? '') . ' ' . auth()->user()->first_name));
    $tones = [
        'amber' => ['#fffaeb', '#b54708'], 'green' => ['#ecfdf3', '#067647'], 'blue' => ['#eff8ff', '#175cd3'],
        'violet' => ['#f4f3ff', '#5925dc'], 'teal' => ['#f0fdf9', '#107569'], 'red' => ['#fef3f2', '#b42318'],
        'slate' => ['#f2f4f7', '#344054'],
    ];
    $pill = function ($text, $tone) use ($tones) {
        return '<span class="hd-pill" style="background:' . $tones[$tone][0] . ';color:' . $tones[$tone][1] . ';">' . e($text) . '</span>';
    };
    $queue_tone = ['waiting' => 'amber', 'with_doctor' => 'blue'];
    $appt_tone = ['requested' => 'amber', 'confirmed' => 'blue', 'checked_in' => 'green'];
    $lab_tone = ['pending' => 'slate', 'sample_collected' => 'blue', 'processing' => 'amber', 'resulted' => 'violet'];
@endphp
<style>
    .hd-band { padding: 18px 20px 22px; }
    .hd-hello { color: #fff; font-size: 28px; line-height: 1.2; font-weight: 700; margin: 0; letter-spacing: -.01em; }
    .hd-date { color: rgba(255,255,255,.8); font-size: 14px; margin-top: 4px; }
    .hd-top { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-start; justify-content: space-between; }
    .hd-search { flex: 0 1 420px; min-width: 260px; }
    .hd-search .select2-container .select2-selection--single { height: 42px; border-radius: 10px; border: 0; }
    .hd-search .select2-container .select2-selection__rendered { line-height: 42px; padding-left: 38px; }
    .hd-search .select2-container .select2-selection__arrow { height: 40px; }
    .hd-search-box { position: relative; }
    .hd-search-box > svg { position: absolute; left: 12px; top: 11px; z-index: 2; color: #667085; pointer-events: none; }
    .hd-tabs { display: flex; gap: 6px; margin-top: 16px; }
    .hd-tab { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 999px; font-size: 13px; font-weight: 600; color: #fff; background: rgba(255,255,255,.12); text-decoration: none !important; }
    .hd-tab:hover { background: rgba(255,255,255,.22); color: #fff; }
    .hd-tab.is-on { background: #fff; color: #101828; }
    .hd-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
    .hd-act { display: inline-flex; align-items: center; gap: 8px; min-height: 40px; padding: 8px 14px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none !important;
        color: #fff; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.25); }
    .hd-act:hover { background: rgba(255,255,255,.22); color: #fff; }
    .hd-act.is-primary { background: #fff; color: #101828; border-color: #fff; }
    .hd-act.is-primary:hover { background: #f2f4f7; color: #101828; }
    .hd-body { padding: 20px; }
    .hd-tiles { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
    .hd-tile { display: flex; gap: 12px; align-items: flex-start; background: #fff; border: 1px solid #eaecf0; border-radius: 14px; padding: 16px; text-decoration: none !important; color: #101828;
        box-shadow: 0 1px 2px rgba(16,24,40,.05); transition: box-shadow .15s, transform .15s; }
    .hd-tile:hover { box-shadow: 0 6px 16px rgba(16,24,40,.10); transform: translateY(-1px); color: #101828; }
    .hd-ico { flex: none; width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
    .hd-ico svg { width: 22px; height: 22px; }
    .hd-tile-label { font-size: 13px; color: #475467; font-weight: 500; }
    .hd-tile-value { font-size: 26px; line-height: 1.15; font-weight: 700; color: #101828; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .hd-tile-value.is-money { font-size: 20px; }
    .hd-tile-sub { font-size: 12px; color: #667085; margin-top: 2px; }
    .hd-tile-sub.is-alert { color: #b54708; font-weight: 600; }
    .hd-panels { display: grid; grid-template-columns: 1fr; gap: 16px; margin-top: 18px; }
    @media (min-width: 992px) { .hd-panels { grid-template-columns: 1fr 1fr; } }
    .hd-panel { background: #fff; border: 1px solid #eaecf0; border-radius: 14px; box-shadow: 0 1px 2px rgba(16,24,40,.05); overflow: hidden; }
    .hd-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 14px 16px; border-bottom: 1px solid #f2f4f7; }
    .hd-panel-title { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 15px; font-weight: 700; color: #101828; }
    .hd-panel-title svg { width: 20px; height: 20px; color: #475467; }
    .hd-more { font-size: 13px; font-weight: 600; white-space: nowrap; display: inline-block; padding: 8px 6px; margin: -8px -6px; }
    .hd-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-bottom: 1px solid #f2f4f7; color: #101828; text-decoration: none !important; }
    a.hd-row:hover { background: #f9fafb; color: #101828; }
    .hd-row:last-child { border-bottom: 0; }
    .hd-no { flex: none; width: 34px; height: 34px; border-radius: 10px; background: #f2f4f7; color: #344054; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    .hd-no.is-mine { background: #eff8ff; color: #175cd3; }
    .hd-time { flex: none; width: 48px; font-weight: 700; font-variant-numeric: tabular-nums; color: #344054; }
    .hd-main { flex: 1; min-width: 0; }
    .hd-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hd-meta { font-size: 12px; color: #667085; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .hd-right { flex: none; display: flex; flex-wrap: wrap; gap: 4px; justify-content: flex-end; max-width: 50%; }
    .hd-pill { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .hd-amount { font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .hd-empty { padding: 22px 16px; color: #667085; font-size: 14px; text-align: center; }
    .hd-foot { padding: 8px 16px; font-size: 12px; color: #667085; background: #fcfcfd; }
    @media (max-width: 600px) { .hd-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; } .hd-tile { flex-direction: column; gap: 8px; padding: 12px; }
        .hd-tile-value.is-money { font-size: 16px; } .hd-hello { font-size: 22px; } .hd-band, .hd-body { padding-left: 14px; padding-right: 14px; } .hd-search { flex-basis: 100%; } }
</style>

<div class="tw-bg-gradient-to-r tw-from-{{ $tc }}-800 tw-to-{{ $tc }}-900 hd-band">
    <div class="hd-top">
        <div>
            <h1 class="hd-hello">{{ __($greet, ['name' => $who]) }}</h1>
            <div class="hd-date">{{ ucfirst(now()->locale(app()->getLocale())->isoFormat('dddd D MMMM YYYY')) }} · {{ session('business.name') }}</div>
        </div>
        @if ($dash['can_search'])
            <div class="hd-search">
                <div class="hd-search-box">
                    {!! \App\Utils\ClinicIcons::svg('user-search', '') !!}
                    <select id="hd_patient_search" style="width:100%" aria-label="{{ __('clinic.dash_find_patient') }}"></select>
                </div>
            </div>
        @endif
    </div>

    @if ($dash['can_sales'])
        <div class="hd-tabs" role="tablist">
            <a class="hd-tab is-on" role="tab" aria-selected="true" href="{{ action([\App\Http\Controllers\HomeController::class, 'index']) }}">{!! \App\Utils\ClinicIcons::svg('building-hospital', '') !!} {{ __('clinic.dash_tab_hospital') }}</a>
            <a class="hd-tab" role="tab" aria-selected="false" href="{{ action([\App\Http\Controllers\HomeController::class, 'index'], ['view' => 'sales']) }}">{!! \App\Utils\ClinicIcons::svg('building-store', '') !!} {{ __('clinic.dash_tab_sales') }}</a>
        </div>
    @endif

    @if (! empty($dash['actions']))
        <div class="hd-actions">
            @foreach ($dash['actions'] as $a)
                <a href="{{ $a['url'] }}" class="hd-act {{ ! empty($a['primary']) ? 'is-primary' : '' }}">{!! \App\Utils\ClinicIcons::svg($a['icon'], '') !!} {{ $a['label'] }}</a>
            @endforeach
        </div>
    @endif
</div>

<section class="hd-body">
    @if (empty($dash['tiles']) && empty($dash['actions']))
        <div class="callout callout-info">{{ __('clinic.dash_nothing') }}</div>
    @endif

    @if (! empty($dash['tiles']))
        <div class="hd-tiles">
            @foreach ($dash['tiles'] as $t)
                <a href="{{ $t['url'] }}" class="hd-tile" id="hd_tile_{{ $t['key'] }}">
                    <span class="hd-ico" style="background:{{ $tones[$t['tone']][0] }};color:{{ $tones[$t['tone']][1] }};">{!! \App\Utils\ClinicIcons::svg($t['icon'], '') !!}</span>
                    <span style="min-width:0;">
                        <span class="hd-tile-label" style="display:block;">{{ $t['label'] }}</span>
                        @if (array_key_exists('money', $t))
                            <span class="hd-tile-value is-money" style="display:block;"><span class="display_currency" data-currency_symbol="true">{{ $t['money'] }}</span></span>
                        @else
                            <span class="hd-tile-value" style="display:block;">{{ number_format($t['value']) }}</span>
                        @endif
                        @if (! empty($t['sub']))
                            <span class="hd-tile-sub {{ ! empty($t['alert']) ? 'is-alert' : '' }}" style="display:block;">{{ $t['sub'] }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @if (! empty($dash['panels']))
        <div class="hd-panels">
            {{-- waiting room / my queue --}}
            @if (isset($dash['panels']['queue']))
                @php $p = $dash['panels']['queue']; @endphp
                <div class="hd-panel" id="hd_panel_queue">
                    <div class="hd-panel-head">
                        <h2 class="hd-panel-title">{!! \App\Utils\ClinicIcons::svg('list-numbers', '') !!} {{ $p['title'] }}</h2>
                        <a class="hd-more" href="{{ $p['url'] }}">{{ __('clinic.dash_see_all') }} →</a>
                    </div>
                    @forelse ($p['rows'] as $r)
                        <a class="hd-row" href="{{ $r['url'] }}">
                            <span class="hd-no {{ $r['mine'] ? 'is-mine' : '' }}">{{ $r['no'] }}</span>
                            <span class="hd-main">
                                <span class="hd-name" style="display:block;">{{ $r['name'] }}</span>
                                <span class="hd-meta" style="display:block;">{{ implode(' · ', array_filter([$r['file'], $r['mine'] ? __('clinic.dash_mine') : $r['doctor'], $r['booked'] ? __('clinic.dash_booked', ['time' => $r['booked']]) : null, $r['since'] ? __('clinic.dash_since', ['time' => $r['since']->format('H:i')]) : null])) }}</span>
                            </span>
                            <span class="hd-right">
                                @if ($r['unpaid']) {!! $pill(__('clinic.dash_not_paid'), 'red') !!} @endif
                                @if (! empty($r['no_vitals'])) {!! $pill(__('clinic.dash_no_vitals'), 'amber') !!} @endif
                                {!! $pill(__('clinic.queue_' . $r['status']), $queue_tone[$r['status']] ?? 'slate') !!}
                            </span>
                        </a>
                    @empty
                        <div class="hd-empty">{{ __('clinic.dash_empty_queue') }}</div>
                    @endforelse
                    @if ($p['more']) <div class="hd-foot">{{ __('clinic.dash_more_n', ['n' => $p['more']]) }}</div> @endif
                </div>
            @endif

            {{-- appointments today --}}
            @if (isset($dash['panels']['appointments']))
                @php $p = $dash['panels']['appointments']; @endphp
                <div class="hd-panel" id="hd_panel_appointments">
                    <div class="hd-panel-head">
                        <h2 class="hd-panel-title">{!! \App\Utils\ClinicIcons::svg('calendar-event', '') !!} {{ $p['title'] }}</h2>
                        <a class="hd-more" href="{{ $p['url'] }}">{{ __('clinic.dash_see_all') }} →</a>
                    </div>
                    @forelse ($p['rows'] as $r)
                        <a class="hd-row" href="{{ $p['url'] }}">
                            <span class="hd-time">{{ $r['time'] }}</span>
                            <span class="hd-main">
                                <span class="hd-name" style="display:block;">{{ $r['name'] }}</span>
                                <span class="hd-meta" style="display:block;">{{ $r['doctor'] }}</span>
                            </span>
                            <span class="hd-right">
                                @if ($r['late']) {!! $pill(__('clinic.dash_late'), 'red') !!} @endif
                                {!! $pill(__('clinic.appt_status_' . $r['status']), $appt_tone[$r['status']] ?? 'slate') !!}
                            </span>
                        </a>
                    @empty
                        <div class="hd-empty">{{ __('clinic.dash_empty_appointments') }}</div>
                    @endforelse
                    @if ($p['more'] || $p['to_confirm'])
                        <div class="hd-foot">{{ implode(' · ', array_filter([$p['more'] ? __('clinic.dash_more_n', ['n' => $p['more']]) : null, $p['to_confirm'] ? __('clinic.dash_to_confirm_n', ['n' => $p['to_confirm']]) : null])) }}</div>
                    @endif
                </div>
            @endif

            {{-- laboratory --}}
            @if (isset($dash['panels']['lab']))
                @php $p = $dash['panels']['lab']; @endphp
                <div class="hd-panel" id="hd_panel_lab">
                    <div class="hd-panel-head">
                        <h2 class="hd-panel-title">{!! \App\Utils\ClinicIcons::svg('microscope', '') !!} {{ $p['title'] }}</h2>
                        <a class="hd-more" href="{{ $p['url'] }}">{{ __('clinic.dash_see_all') }} →</a>
                    </div>
                    @forelse ($p['rows'] as $r)
                        <a class="hd-row" href="{{ $r['url'] }}">
                            <span class="hd-main">
                                <span class="hd-name" style="display:block;">{{ $r['name'] }}</span>
                                <span class="hd-meta" style="display:block;">{{ implode(' · ', array_filter([$r['ref'], $r['file'], trans_choice('clinic.dash_tests_n', $r['tests'], ['n' => $r['tests']]), $r['since'] ? $r['since']->format(\App\Utils\ClinicReports::dayMonth() . ' H:i') : null])) }}</span>
                            </span>
                            <span class="hd-right">{!! $pill(__('lang_v1.lab_status_' . $r['status']), $lab_tone[$r['status']] ?? 'slate') !!}</span>
                        </a>
                    @empty
                        <div class="hd-empty">{{ __('clinic.dash_empty_lab') }}</div>
                    @endforelse
                    @if ($p['more']) <div class="hd-foot">{{ __('clinic.dash_more_n', ['n' => $p['more']]) }}</div> @endif
                </div>
            @endif

            {{-- dispensary --}}
            @if (isset($dash['panels']['dispensary']))
                @php $p = $dash['panels']['dispensary']; @endphp
                <div class="hd-panel" id="hd_panel_dispensary">
                    <div class="hd-panel-head">
                        <h2 class="hd-panel-title">{!! \App\Utils\ClinicIcons::svg('pill', '') !!} {{ $p['title'] }}</h2>
                        <a class="hd-more" href="{{ $p['url'] }}">{{ __('clinic.dash_see_all') }} →</a>
                    </div>
                    @forelse ($p['rows'] as $r)
                        <a class="hd-row" href="{{ $r['url'] }}">
                            <span class="hd-main">
                                <span class="hd-name" style="display:block;">{{ $r['name'] }}</span>
                                <span class="hd-meta" style="display:block;">{{ implode(' · ', array_filter([$r['doctor'], $r['since']->format(\App\Utils\ClinicReports::dayMonth() . ' H:i')])) }}</span>
                            </span>
                            <span class="hd-right">{!! $pill(trans_choice('clinic.dash_items_n', $r['lines'], ['n' => $r['lines']]), 'teal') !!}</span>
                        </a>
                    @empty
                        <div class="hd-empty">{{ __('clinic.dash_empty_rx') }}</div>
                    @endforelse
                    @if ($p['more']) <div class="hd-foot">{{ __('clinic.dash_more_n', ['n' => $p['more']]) }}</div> @endif
                </div>
            @endif

            {{-- unpaid bills --}}
            @if (isset($dash['panels']['bills']))
                @php $p = $dash['panels']['bills']; @endphp
                <div class="hd-panel" id="hd_panel_bills">
                    <div class="hd-panel-head">
                        <h2 class="hd-panel-title">{!! \App\Utils\ClinicIcons::svg('receipt', '') !!} {{ $p['title'] }}</h2>
                        <a class="hd-more" href="{{ $p['url'] }}">{{ __('clinic.dash_see_all') }} →</a>
                    </div>
                    @forelse ($p['rows'] as $r)
                        <a class="hd-row" href="{{ $r['url'] }}">
                            <span class="hd-main">
                                <span class="hd-name" style="display:block;">{{ $r['name'] }}</span>
                                <span class="hd-meta" style="display:block;">{{ $r['file'] }}</span>
                            </span>
                            <span class="hd-amount" style="color:#b42318;"><span class="display_currency" data-currency_symbol="true">{{ $r['balance'] }}</span></span>
                        </a>
                    @empty
                        <div class="hd-empty">{{ __('clinic.dash_empty_bills') }}</div>
                    @endforelse
                    @if ($p['more']) <div class="hd-foot">{{ __('clinic.dash_more_n', ['n' => $p['more']]) }}</div> @endif
                </div>
            @endif
        </div>
    @endif
</section>
@endsection

@section('javascript')
<script>
    $(function () {
        var $s = $('#hd_patient_search');
        if (!$s.length) return;
        $s.select2({
            placeholder: @json(__('clinic.dash_find_patient')),
            minimumInputLength: 1,
            width: '100%',
            ajax: { url: @json(route('patients.search')), dataType: 'json', delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return data; } }
        }).on('select2:select', function (e) {
            window.location = @json(url('patients')) + '/' + encodeURIComponent(e.params.data.id);
        });
    });
</script>
@endsection
