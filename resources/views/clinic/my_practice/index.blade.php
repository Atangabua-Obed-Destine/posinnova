@extends('layouts.app')
@section('title', __('clinic.mp_title'))

@php
    $A = \App\Http\Controllers\ClinicAppointmentController::class;
    $M = \App\Http\Controllers\ClinicMyPracticeController::class;
    $hour = (int) now()->format('G');
    $greet = $hour < 12 ? __('clinic.mp_good_morning') : ($hour < 17 ? __('clinic.mp_good_afternoon') : __('clinic.mp_good_evening'));
    $name = trim($user->surname.' '.$user->first_name) ?: $user->first_name;
    $today_n = count($today_list);
    $arrived = collect($today_list)->where('status', 'checked_in')->count();
    $waiting_n = count($waiting['waiting'] ?? []);
    $clash = session('dayoff_clash');
@endphp

@section('content')
<style>
    .mp { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --brand:#0e7490; --brand-l:#ecfeff; --ok:#067647; --ok-l:#ecfdf3; --warn:#b54708; --warn-l:#fffaeb; --red:#b42318; --red-l:#fef3f2; color:var(--ink); padding:16px; }
    .mp [hidden] { display:none !important; }
    .mp h1 { margin:0; font-size:24px; font-weight:800; }
    .mp-top { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin-bottom:14px; }
    .mp-top .sub { color:var(--muted); margin-top:2px; }
    .mp-top a.home { display:inline-flex; align-items:center; gap:6px; min-height:40px; padding:6px 14px; border:1px solid var(--line); border-radius:10px; background:#fff; font-weight:700; font-size:14px; text-decoration:none !important; }
    .mp-seg { display:inline-flex; border:1px solid var(--line); border-radius:10px; overflow:hidden; }
    .mp-seg a { display:inline-flex; align-items:center; min-height:36px; padding:4px 12px; font-size:13px; font-weight:700; color:var(--ink) !important; text-decoration:none !important; background:#fff; }
    .mp-seg a.on { background:var(--brand); color:#fff !important; }
    .mp-strip { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:10px; margin-bottom:16px; }
    @media (max-width:700px) { .mp-strip { grid-template-columns:repeat(2, minmax(0,1fr)); } }
    .mp-stat { background:#fff; border:1px solid var(--line); border-radius:14px; padding:12px 14px; text-decoration:none !important; color:inherit !important; display:block; }
    .mp-stat b { display:block; font-size:26px; line-height:1.1; font-weight:800; }
    .mp-stat span { color:var(--muted); font-size:13px; font-weight:600; }
    .mp-stat.hot { border-color:#fda29b; background:var(--red-l); }
    .mp-stat.hot b { color:var(--red); }
    .mp-grid { display:grid; grid-template-columns:1fr; gap:16px; }
    @media (min-width:1100px) { .mp-grid { grid-template-columns:minmax(0,1.7fr) minmax(0,1fr); align-items:start; } }
    .mp-card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:16px; margin-bottom:16px; }
    .mp-card h2 { margin:0 0 10px; font-size:17px; font-weight:800; display:flex; align-items:center; gap:8px; }
    .mp-card h2 .count { background:var(--red); color:#fff; border-radius:999px; font-size:12px; padding:1px 9px; }
    .mp-muted { color:var(--muted); font-size:13.5px; }
    .mp-news { list-style:none; margin:0; padding:0; }
    .mp-news li { display:flex; gap:10px; align-items:flex-start; padding:9px 0; border-top:1px solid #f1f5f9; }
    .mp-news li:first-child { border-top:0; }
    .mp-news .ic { width:32px; height:32px; border-radius:10px; display:flex; align-items:center; justify-content:center; color:#fff; flex:none; font-size:14px; }
    .mp-news .ic.bg-green { background:#12b76a; } .mp-news .ic.bg-yellow { background:#f79009; } .mp-news .ic.bg-red { background:#f04438; }
    .mp-news .ic.bg-aqua { background:#06aed4; } .mp-news .ic.bg-gray { background:#98a2b3; }
    .mp-news .t { font-weight:600; } .mp-news .w { color:var(--muted); font-size:12.5px; }
    .mp-day { border:1px solid var(--line); border-radius:14px; margin-bottom:10px; overflow:hidden; }
    .mp-day-h { display:flex; flex-wrap:wrap; align-items:center; gap:6px 12px; padding:10px 14px; background:#f8fafc; }
    .mp-day-h .d { font-weight:800; font-size:15px; min-width:110px; }
    .mp-day-h .d small { display:block; color:var(--muted); font-weight:600; font-size:12.5px; }
    .mp-day-h .hrs { color:var(--muted); font-size:13px; flex:1; }
    @media (max-width:600px) {
        .mp-day-h { justify-content:space-between; }
        .mp-day-h .hrs { order:3; flex-basis:100%; }   /* the hours go under the day, not squeezed beside it */
    }
    .mp-load { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700; }
    .mp-bar { width:90px; height:8px; border-radius:99px; background:#e2e8f0; overflow:hidden; }
    .mp-bar i { display:block; height:100%; background:var(--brand); }
    .mp-bar.full i { background:var(--red); }
    .mp-tag { display:inline-block; padding:1px 9px; border-radius:999px; font-size:12px; font-weight:700; }
    .mp-tag.off { background:var(--red-l); color:var(--red); }
    .mp-tag.full { background:var(--red-l); color:var(--red); }
    .mp-tag.new { background:#d1fadf; color:var(--ok); }
    .mp-tag.guest { background:#e0f2fe; color:#075985; }
    .mp-tag.requested { background:var(--warn-l); color:var(--warn); } .mp-tag.confirmed { background:var(--ok-l); color:var(--ok); } .mp-tag.checked_in { background:#eef4ff; color:#3538cd; }
    .mp-appts { list-style:none; margin:0; padding:0; }
    .mp-appts li { display:flex; align-items:center; gap:10px; padding:9px 14px; border-top:1px solid #f1f5f9; }
    .mp-appts li.isnew { background:#f6fef9; }
    .mp-appts .tm { width:48px; font-weight:800; font-variant-numeric:tabular-nums; }
    .mp-appts .nm { flex:1; min-width:0; }
    .mp-appts .nm b { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mp-appts .nm small { color:var(--muted); display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .mp-empty { padding:10px 14px; color:var(--muted); font-size:13.5px; }
    .mp-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; min-height:40px; padding:7px 14px; border-radius:10px; border:1px solid #cbd5e1; background:#fff; color:var(--ink) !important; font-weight:700; font-size:14px; cursor:pointer; text-decoration:none !important; }
    .mp-btn.pri { background:var(--brand); border-color:var(--brand); color:#fff !important; }
    .mp-btn.ok { background:var(--ok); border-color:var(--ok); color:#fff !important; }
    .mp-btn.sm { min-height:34px; padding:4px 10px; font-size:13px; }
    .mp-wait { display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:10px 12px; border-radius:12px; background:var(--red-l); border:1px solid #fda29b; margin-bottom:10px; }
    .mp-wait .grow { flex:1; min-width:160px; font-weight:600; }
    .mp-set h3 { font-size:14px; font-weight:800; margin:14px 0 6px; text-transform:uppercase; letter-spacing:.03em; color:var(--muted); }
    .mp-set h3:first-child { margin-top:0; }
    .mp-week { width:100%; border-collapse:collapse; font-size:13.5px; }
    .mp-week td { padding:5px 0; border-top:1px solid #f1f5f9; } .mp-week td:first-child { width:90px; font-weight:700; }
    .mp-row { display:flex; flex-wrap:wrap; gap:8px; align-items:flex-end; }
    .mp-row > div { flex:1 1 120px; }
    .mp-set label { display:block; font-weight:600; font-size:13px; margin:0 0 4px; }
    .mp-set input[type=text], .mp-set input[type=date], .mp-set input[type=number], .mp-set input[type=tel] { width:100%; min-height:40px; padding:7px 10px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; }
    .mp-offs { list-style:none; margin:0 0 8px; padding:0; font-size:13.5px; }
    .mp-offs li { padding:4px 0; }
    .mp-link { display:flex; gap:6px; } .mp-link input { flex:1; min-width:0; }
    .mp-note { background:var(--warn-l); border:1px solid #fedf89; color:var(--warn); border-radius:12px; padding:10px 12px; font-size:13.5px; margin-bottom:10px; }
</style>

<section class="content mp">
    <div class="mp-top">
        <div>
            <h1>{{ $greet }}, {{ $name }}</h1>
            <div class="sub">{{ __('clinic.mp_sub') }} · {{ now()->locale(app()->getLocale())->isoFormat('dddd D MMMM YYYY') }}</div>
        </div>
        <a class="home" href="{{ url('home') }}?home=1"><i class="fas fa-th-large"></i> {{ __('clinic.mp_clinic_home') }}</a>
    </div>

    <div class="mp-strip" id="mp_strip">
        <a class="mp-stat {{ count($news) ? 'hot' : '' }}" href="#mp_news"><b id="mp_new_n">{{ count($news) }}</b><span>{{ __('clinic.mp_stat_new') }}</span></a>
        <a class="mp-stat" href="#mp_days"><b>{{ $today_n }}</b><span>{{ __('clinic.mp_stat_today') }}</span></a>
        <a class="mp-stat {{ $waiting_n ? 'hot' : '' }}" href="#mp_today"><b>{{ $waiting_n }}</b><span>{{ __('clinic.mp_stat_waiting') }}</span></a>
        <a class="mp-stat" href="#mp_days"><b>{{ $requests }}</b><span>{{ __('clinic.mp_stat_requests') }}</span></a>
    </div>

    @if ($clash)
        <div class="mp-note"><b><i class="fas fa-exclamation-triangle"></i> {{ trans_choice('clinic.appt_clash_title', count($clash['rows']), ['n' => count($clash['rows'])]) }}</b>
            <ul style="margin:6px 0 0; padding-left:18px;">
                @foreach ($clash['rows'] as $c)<li>{{ \Carbon::parse($c['date'])->locale(app()->getLocale())->isoFormat('ddd D MMM') }} {{ $c['start'] }} · {{ $c['patient'] }}</li>@endforeach
            </ul>
            <div style="margin-top:4px">{{ __('clinic.mp_clash_help') }}</div>
        </div>
    @endif

    <div class="mp-grid">
        <div>
            {{-- What is new --}}
            <div class="mp-card" id="mp_news">
                <h2><i class="fas fa-bell"></i> {{ __('clinic.mp_new_title') }} @if (count($news))<span class="count">{{ count($news) }}</span>@endif</h2>
                @if (count($news))
                    <ul class="mp-news">
                        @foreach ($news as $n)
                            <li><span class="ic {{ \Illuminate\Support\Str::after($n['icon'], ' ') }}"><i class="{{ \Illuminate\Support\Str::before($n['icon'], ' bg-') }}"></i></span>
                                <div><div class="t">{{ $n['msg'] }}</div><div class="w">{{ $n['when'] }}</div></div></li>
                        @endforeach
                    </ul>
                @else
                    <p class="mp-muted" style="margin:0">{{ __('clinic.mp_nothing_new') }}</p>
                @endif
            </div>

            {{-- Today --}}
            <div class="mp-card" id="mp_today">
                <h2><i class="fas fa-user-md"></i> {{ __('clinic.mp_today_title') }}</h2>
                @if ($waiting_n)
                    @php $next = $waiting['waiting'][0]; @endphp
                    <div class="mp-wait">
                        <span class="grow"><i class="fas fa-bell"></i> {{ trans_choice('clinic.mp_waiting_now', $waiting_n, ['n' => $waiting_n]) }} — {{ __('clinic.mp_next') }} <b>{{ $next['name'] }}</b> ({{ __('clinic.wq_minutes', ['n' => $next['minutes']]) }})</span>
                        @if ($can_call)
                            <form method="POST" action="{{ url('clinic/queue/'.$next['id'].'/start') }}">@csrf<button class="mp-btn ok" type="submit"><i class="fas fa-door-open"></i> {{ __('clinic.mp_call_in') }}</button></form>
                        @endif
                        <a class="mp-btn sm" href="{{ action([\App\Http\Controllers\ClinicQueueController::class, 'index']) }}">{{ __('clinic.mp_open_queue') }}</a>
                    </div>
                @endif
                <p class="mp-muted" style="margin:0">{{ trans_choice('clinic.mp_today_line', $today_n, ['n' => $today_n, 'arrived' => $arrived]) }}</p>
            </div>

            {{-- The days ahead --}}
            <div class="mp-card" id="mp_days">
                <h2 style="justify-content:space-between"><span><i class="fas fa-calendar-alt"></i> {{ __('clinic.mp_days_title') }}</span>
                    <span class="mp-seg">
                        <a href="{{ action([$M, 'index']) }}" class="{{ $days_n == 7 ? 'on' : '' }}">{{ __('clinic.mp_7_days') }}</a>
                        <a href="{{ action([$M, 'index'], ['days' => 14]) }}" class="{{ $days_n == 14 ? 'on' : '' }}">{{ __('clinic.mp_14_days') }}</a>
                    </span></h2>
                @foreach ($days as $d)
                    <div class="mp-day" data-date="{{ $d['date'] }}">
                        <div class="mp-day-h">
                            <span class="d">{{ $d['label'] }}<small>{{ $d['sub'] }}</small></span>
                            <span class="hrs">
                                @if ($d['off'])<span class="mp-tag off">{{ __('clinic.mp_day_off') }}</span>
                                @elseif ($d['hours']){{ __('clinic.mp_hours', ['h' => $d['hours']]) }}
                                @else {{ __('clinic.mp_no_hours') }}@endif
                            </span>
                            <span class="mp-load">
                                @if ($d['off'] && ! $d['count'])
                                @elseif ($cap > 0)
                                    <span class="mp-bar {{ $d['full'] ? 'full' : '' }}"><i style="width:{{ min(100, round($d['count'] / $cap * 100)) }}%"></i></span>
                                    {{ $d['count'] }}/{{ $cap }} @if ($d['full'])<span class="mp-tag full">{{ __('clinic.mp_full') }}</span>@endif
                                @else
                                    {{ trans_choice('clinic.mp_n_appts', $d['count'], ['n' => $d['count']]) }}
                                @endif
                            </span>
                        </div>
                        @if (count($d['appts']))
                            <ul class="mp-appts">
                                @foreach ($d['appts'] as $ap)
                                    <li class="{{ $ap['new'] ? 'isnew' : '' }}">
                                        <span class="tm">{{ $ap['time'] }}</span>
                                        <span class="nm"><b>{{ $ap['name'] }}</b>@if ($ap['reason'])<small>{{ $ap['reason'] }}</small>@endif</span>
                                        @if ($ap['new'])<span class="mp-tag new">{{ __('clinic.mp_new') }}</span>@endif
                                        @if ($ap['guest'])<span class="mp-tag guest">{{ __('clinic.pr_guest') }}</span>@endif
                                        <span class="mp-tag {{ $ap['status'] }}">{{ $ap['status_label'] }}</span>
                                        @if ($ap['status'] == 'requested' && $can_manage)
                                            <button type="button" class="mp-btn sm mp-review" data-id="{{ $ap['id'] }}">{{ __('clinic.pr_review') }}</button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
                <a class="mp-btn sm" href="{{ action([$A, 'upcoming']) }}">{{ __('clinic.mp_open_diary') }}</a>
            </div>
        </div>

        {{-- My settings --}}
        <div class="mp-card mp-set" id="mp_settings">
            <h2><i class="fas fa-sliders-h"></i> {{ __('clinic.mp_settings_title') }}</h2>

            <h3>{{ __('clinic.mp_week_title') }}</h3>
            <table class="mp-week">
                @foreach ($week as $w => $span)
                    <tr><td>{{ __('clinic.weekday_'.$w) }}</td><td>{{ $span ?: '—' }}</td></tr>
                @endforeach
            </table>
            @if ($can_schedule)
                <a class="mp-btn sm" style="margin-top:8px" href="{{ action([$A, 'hours']) }}"><i class="fas fa-clock"></i> {{ __('clinic.mp_change_hours') }}</a>
            @endif

            <h3>{{ __('clinic.mp_days_off_title') }}</h3>
            @if (count($offs))
                <ul class="mp-offs">@foreach ($offs as $o)<li><i class="fas fa-umbrella-beach"></i> {{ \Carbon::parse($o->date)->locale(app()->getLocale())->isoFormat('ddd D MMM') }}{{ $o->reason ? ' · '.$o->reason : '' }}</li>@endforeach</ul>
            @else
                <p class="mp-muted">{{ __('clinic.appt_no_days_off') }}</p>
            @endif
            @if ($can_schedule)
                <form method="POST" action="{{ action([$A, 'storeDayOff']) }}" id="mp_dayoff_form">@csrf
                    <input type="hidden" name="user_id" value="{{ $user->id }}"><input type="hidden" name="return" value="my-practice">
                    <div class="mp-row">
                        <div><label for="mp_off_from">{{ __('clinic.appt_from') }}</label><input type="date" name="date" id="mp_off_from" min="{{ \Carbon::today()->toDateString() }}" required></div>
                        <div><label for="mp_off_to">{{ __('clinic.appt_to_optional') }}</label><input type="date" name="date_to" id="mp_off_to" min="{{ \Carbon::today()->toDateString() }}"></div>
                    </div>
                    <div class="mp-row" style="margin-top:6px">
                        <div><label for="mp_off_reason">{{ __('clinic.appt_reason') }}</label><input type="text" name="reason" id="mp_off_reason" maxlength="191"></div>
                        <button class="mp-btn" type="submit"><i class="fas fa-plus"></i> {{ __('clinic.appt_add_day_off') }}</button>
                    </div>
                </form>
            @endif

            <h3>{{ __('clinic.appt_cap_label') }}</h3>
            @if ($can_schedule)
                <form method="POST" action="{{ url('clinic/appointments/doctor-cap/'.$user->id) }}" id="mp_cap_form" class="mp-row">@csrf
                    <input type="hidden" name="return" value="my-practice">
                    <div><input type="number" name="max_per_day" id="mp_cap" min="0" max="100" value="{{ $own_cap ?: '' }}" placeholder="{{ $default_cap ?: '∞' }}" aria-label="{{ __('clinic.appt_cap_label') }}"></div>
                    <button class="mp-btn" type="submit"><i class="fas fa-save"></i> {{ __('messages.save') }}</button>
                </form>
                <p class="mp-muted" style="margin:4px 0 0">{{ __('clinic.appt_cap_hint') }}</p>
            @else
                <p class="mp-muted">{{ $cap > 0 ? $cap : __('clinic.rv_no_limit') }}</p>
            @endif

            <h3>{{ __('clinic.mp_fee_title') }}</h3>
            @if ($can_fee)
                <form method="POST" action="{{ action([$M, 'saveFee']) }}" id="mp_fee_form">@csrf
                    @foreach ($fee_cols as $key => [$label, $clinic_price])
                        <div style="margin-bottom:6px"><label for="mp_fee_{{ $key }}">{{ $label }}</label>
                            <input type="text" inputmode="decimal" name="fee[{{ $key }}]" id="mp_fee_{{ $key }}" value="{{ isset($own_fees[$key]) ? (float) $own_fees[$key] : '' }}" placeholder="{{ (float) $clinic_price }}"></div>
                    @endforeach
                    <p class="mp-muted" style="margin:0 0 6px">{{ __('clinic.mp_fee_hint') }}</p>
                    <button class="mp-btn" type="submit"><i class="fas fa-save"></i> {{ __('messages.save') }}</button>
                </form>
            @else
                @foreach ($fee_cols as $key => [$label, $clinic_price])
                    <p class="mp-muted" style="margin:0">{{ $label }}: {{ (float) ($own_fees[$key] ?? $clinic_price) }}</p>
                @endforeach
            @endif

            <h3>{{ __('clinic.mp_phone_title') }}</h3>
            <form method="POST" action="{{ action([$M, 'savePhone']) }}" id="mp_phone_form" class="mp-row">@csrf
                <div><input type="tel" name="contact_number" id="mp_phone" value="{{ $user->contact_number }}" placeholder="+237 6XX XX XX XX" autocomplete="tel" aria-label="{{ __('clinic.mp_phone_title') }}"></div>
                <button class="mp-btn" type="submit"><i class="fas fa-save"></i> {{ __('messages.save') }}</button>
            </form>
            <p class="mp-muted" style="margin:4px 0 0">{{ __('clinic.mp_phone_hint') }}</p>

            @if ($link)
                <h3>{{ __('clinic.mp_link_title') }}</h3>
                <div class="mp-link"><input type="text" id="mp_link" value="{{ $link }}" readonly aria-label="{{ __('clinic.mp_link_title') }}">
                    <button type="button" class="mp-btn sm" id="mp_copy"><i class="fas fa-copy"></i> {{ __('clinic.mp_copy') }}</button></div>
                <a class="mp-btn sm" style="margin-top:6px; color:#16a34a !important" target="_blank" rel="noopener"
                    href="https://wa.me/?text={{ rawurlencode(__('clinic.mp_share_text', ['link' => $link])) }}"><i class="fab fa-whatsapp"></i> {{ __('clinic.mp_share') }}</a>
                <p class="mp-muted" style="margin:4px 0 0">{{ __('clinic.mp_link_hint') }}</p>
            @endif
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    (function () {
        document.querySelectorAll('.mp-review').forEach(function (b) {
            b.addEventListener('click', function () { if (window.piApptReview) { window.piApptReview(b.getAttribute('data-id'), false); } });
        });
        var c = document.getElementById('mp_copy');
        if (c) {
            c.addEventListener('click', function () {
                var i = document.getElementById('mp_link'); i.select();
                try { navigator.clipboard.writeText(i.value); } catch (e) { document.execCommand('copy'); }
                c.innerHTML = '<i class="fas fa-check"></i> ' + @json(__('clinic.mp_copied'));
            });
        }
    })();
</script>
@endsection
