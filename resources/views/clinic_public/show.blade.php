@php
    $logo = $business->logo ? asset('uploads/business_logos/'.$business->logo) : null;
    $words = preg_split('/\s+/', trim($business->name));
    $initials = count($words) > 1 ? mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1))
        : (mb_strlen($words[0]) <= 4 ? mb_strtoupper($words[0]) : mb_strtoupper(mb_substr($words[0], 0, 2)));
    $meta_desc = $tagline ?: ($description ? \Illuminate\Support\Str::limit(strip_tags($description), 160) : __('clinic.pub_meta_default', ['name' => $business->name]));
    $solo = ! empty($solo) && $selected !== null;
    $specialties = $specialties ?? [];
    $solo_name = $solo ? ($doctors[$selected] ?? '') : null;
    $solo_spec = $solo ? ($specialties[$selected] ?? null) : null;
    $clinic_url = route('clinic-public.show', $slug);
    $page_url = $solo ? route('clinic-public.doctor', [$slug, $selected]) : $clinic_url;
    $sp_used = collect($specialties)->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
    $finder = ! $solo && count($doctors) > 1;
    $n0 = $solo ? 0 : 1;   // step numbers: the doctor step is skipped on a doctor's own link
    $page_title = $solo ? $solo_name.' · '.$business->name : $business->name.' · '.__('clinic.pub_title_suffix');
    $status = session('pub_status');
    $done = session('pub_done');
    $tel_digits = ($location && $location->mobile) ? \App\Utils\ClinicWhatsApp::waNumber($business->id, $location->mobile) : null;
    $tel = $tel_digits ? 'tel:+'.$tel_digits : null;
    // handed to the page script (kept here: Blade directives cannot span lines)
    $js_labels = [
        'loading' => __('clinic.pub_loading'), 'noSlots' => __('clinic.pub_no_slots'),
        'morning' => __('clinic.pub_morning'), 'afternoon' => __('clinic.pub_afternoon'), 'evening' => __('clinic.pub_evening'),
        'errName' => __('clinic.appt_guest_name_required'), 'errPhone' => __('clinic.appt_guest_phone_required'),
        'slotGone' => __('clinic.pub_slot_gone'), 'sending' => __('clinic.pub_sending'),
    ];
    $js_old = ['doctor' => old('doctor_id'), 'date' => old('date'), 'start' => old('start')];
    $js_err = ($status && ! $status['ok']) ? ['field' => $status['field'] ?? '', 'msg' => $status['msg']] : null;
    $ico = function ($name, $size = 18) {
        $p = [
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'chat' => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8v.5z"/>',
            'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
            'mail' => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
            'cal' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'login' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>',
            'check' => '<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><polyline points="22 4 12 14 9 11"/>',
            'nav' => '<polygon points="3 11 22 2 13 21 11 13 3 11"/>',
            'doc' => '<path d="M4.8 2.3A.3.3 0 1 0 5 2H4a2 2 0 0 0-2 2v5a6 6 0 0 0 6 6 6 6 0 0 0 6-6V4a2 2 0 0 0-2-2h-1a.2.2 0 1 0 .3.3"/><path d="M8 15v1a6 6 0 0 0 6 6 6 6 0 0 0 6-6v-4"/><circle cx="20" cy="10" r="2"/>',
            'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
            'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
            'arrow' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        ][$name] ?? '';

        return '<svg class="ico" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$p.'</svg>';
    };
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page_title }}</title>
    <meta name="description" content="{{ $meta_desc }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $page_title }}">
    <meta property="og:description" content="{{ $meta_desc }}">
    <meta property="og:url" content="{{ $page_url }}">
    @if ($logo)<meta property="og:image" content="{{ $logo }}">@endif
    <link rel="canonical" href="{{ $page_url }}">
    <meta name="theme-color" content="#0e7490">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --brand:#0e7490; --brand-d:#155e75; --brand-l:#ecfeff; --bg:#f1f5f9; --ok:#067647; --ok-l:#ecfdf3; --err:#b42318; --err-l:#fef3f2; --wa:#16a34a; }
        * { box-sizing: border-box; }
        [hidden] { display:none !important; }
        html { scroll-behavior: smooth; }
        body { margin:0; font-family:'Inter',system-ui,-apple-system,Segoe UI,Roboto,sans-serif; color:var(--ink); background:var(--bg); line-height:1.5; -webkit-text-size-adjust:100%; }
        a { color:inherit; }
        .ico { flex:none; vertical-align:middle; }
        .wrap { max-width: 980px; margin:0 auto; padding: 0 16px; }

        /* hero */
        .hero { background:linear-gradient(135deg,var(--brand) 0%,var(--brand-d) 100%); color:#fff; padding: 28px 0 22px; }
        .brand { display:flex; align-items:center; gap:14px; }
        .badge { width:60px; height:60px; border-radius:16px; background:#fff; color:var(--brand-d); display:flex; align-items:center; justify-content:center; font-weight:800; font-size:20px; flex:none; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.12); }
        .badge img { width:100%; height:100%; object-fit:contain; padding:6px; }
        .hero h1 { margin:0; font-size:26px; font-weight:800; letter-spacing:-.01em; line-height:1.2; }
        .hero p.tag { margin:4px 0 0; opacity:.92; font-size:15px; }
        .quick { display:flex; flex-wrap:wrap; gap:10px; margin-top:18px; }
        .qbtn { display:inline-flex; align-items:center; gap:8px; min-height:44px; padding:9px 16px; border-radius:12px; font-weight:600; font-size:15px; text-decoration:none; border:1px solid rgba(255,255,255,.35); color:#fff; background:rgba(255,255,255,.12); }
        .qbtn.main { background:#fff; color:var(--brand-d); border-color:#fff; }
        .how { display:flex; flex-wrap:wrap; gap:8px 18px; margin-top:18px; font-size:13px; opacity:.95; }
        .how span { display:inline-flex; align-items:center; gap:7px; }
        .how b { width:22px; height:22px; border-radius:50%; background:rgba(255,255,255,.2); display:inline-flex; align-items:center; justify-content:center; font-size:12px; }

        /* layout */
        .grid { display:grid; grid-template-columns:1fr; gap:16px; padding:18px 0 40px; }
        @media (min-width:800px){ .grid { grid-template-columns: 1.45fr 1fr; align-items:start; } }
        .card { background:#fff; border:1px solid var(--line); border-radius:18px; padding:20px; box-shadow:0 1px 2px rgba(15,23,42,.04); }
        .card + .card { margin-top:16px; }
        .card h2 { margin:0 0 4px; font-size:20px; font-weight:800; }
        .sub { color:var(--muted); font-size:14px; margin:0 0 14px; }
        .ctitle { margin:0 0 10px; font-size:13px; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }

        /* steps */
        .step { margin-top:18px; }
        .step[hidden] { display:none !important; }
        .shead { display:flex; align-items:center; gap:10px; margin:0 0 10px; font-weight:700; font-size:15px; }
        .num { width:26px; height:26px; border-radius:50%; background:var(--brand-l); color:var(--brand-d); display:inline-flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; flex:none; }
        .done .num { background:var(--brand); color:#fff; }

        .docs { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px; }
        .doccard { display:flex; align-items:center; gap:12px; text-align:left; width:100%; min-height:64px; padding:12px 14px; border:1.5px solid var(--line); border-radius:14px; background:#fff; cursor:pointer; font:inherit; color:inherit; transition:border-color .15s, background .15s; }
        .doccard:hover { border-color:#94a3b8; }
        .doccard .av { width:38px; height:38px; border-radius:50%; background:var(--brand-l); color:var(--brand-d); display:flex; align-items:center; justify-content:center; flex:none; }
        .doccard b { display:block; font-size:15px; line-height:1.25; }
        .doccard small { display:block; color:var(--muted); font-size:12.5px; margin-top:2px; }
        .doccard small.soon { color:var(--ok); font-weight:600; }
        .doccard small.spec { color:#5b21b6; font-weight:700; }
        .doccard small.spec.gen { color:var(--muted); font-weight:600; }
        .doccard.hide { display:none; }
        .finder { display:flex; flex-direction:column; gap:10px; margin:0 0 12px; }
        .search { position:relative; }
        .search .ico { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:var(--muted); }
        .search input[type=text] { padding-left:42px; }
        .fchips { display:flex; gap:8px; overflow-x:auto; padding-bottom:2px; -webkit-overflow-scrolling:touch; scrollbar-width:thin; }
        .fchip { flex:none; min-height:38px; padding:6px 14px; border:1.5px solid var(--line); border-radius:999px; background:#fff; cursor:pointer; font:inherit; font-size:13.5px; font-weight:600; color:inherit; white-space:nowrap; }
        .fchip[aria-pressed="true"] { background:var(--ink); color:#fff; border-color:var(--ink); }
        .nomatch { color:var(--muted); font-size:14px; margin:4px 0 0; }
        .nomatch button { background:none; border:0; padding:0; font:inherit; font-weight:700; color:var(--brand-d); text-decoration:underline; cursor:pointer; min-height:32px; }
        .solo { display:flex; align-items:center; gap:14px; padding:14px; border-radius:16px; background:var(--brand-l); border:1px solid #a5f3fc; margin:4px 0 6px; }
        .solo .av { width:54px; height:54px; border-radius:50%; background:#fff; color:var(--brand-d); display:flex; align-items:center; justify-content:center; flex:none; }
        .solo b { display:block; font-size:18px; line-height:1.25; }
        .solo .spec { display:inline-block; margin-top:3px; padding:1px 10px; border-radius:999px; background:#ede9fe; color:#5b21b6; font-size:13px; font-weight:700; }
        .solo .spec.gen { background:#fff; color:var(--muted); }
        .solo small { display:block; color:var(--ok); font-weight:600; font-size:13px; margin-top:3px; }
        .others { display:flex; align-items:center; justify-content:center; gap:8px; width:100%; min-height:46px; margin-top:16px; padding:9px 14px; border-radius:12px; border:1.5px dashed #94a3b8; background:#fff; color:var(--ink); font-weight:700; font-size:14.5px; text-decoration:none; }
        .others:hover { border-color:var(--brand); color:var(--brand-d); }
        .doccard[aria-pressed="true"] { border-color:var(--brand); background:var(--brand-l); box-shadow:0 0 0 3px rgba(14,116,144,.12); }

        .chips { display:flex; flex-wrap:wrap; gap:8px; }
        .chip { min-height:44px; min-width:64px; padding:9px 14px; border:1.5px solid var(--line); border-radius:12px; background:#fff; cursor:pointer; font:inherit; font-size:14px; font-weight:600; color:inherit; }
        .chip:hover { border-color:#94a3b8; }
        .chip[aria-pressed="true"] { background:var(--brand); color:#fff; border-color:var(--brand); }
        .tgroup { margin-bottom:10px; }
        .tgroup .glabel { font-size:12.5px; color:var(--muted); font-weight:600; margin:0 0 6px; display:flex; align-items:center; gap:6px; }

        .summary { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; padding:12px 14px; border-radius:12px; background:var(--brand-l); color:var(--brand-d); font-weight:600; font-size:14.5px; }
        .summary a { display:inline-flex; align-items:center; min-height:44px; padding:0 8px; margin:-10px -8px; font-size:13.5px; font-weight:700; text-decoration:underline; cursor:pointer; }
        .who { display:grid; gap:10px; margin-top:12px; }
        .whobtn { display:flex; align-items:center; gap:12px; width:100%; min-height:60px; padding:12px 16px; border-radius:14px; font:inherit; text-align:left; cursor:pointer; text-decoration:none; }
        .whobtn b { display:block; font-size:15.5px; }
        .whobtn small { display:block; font-size:13px; opacity:.85; }
        .whobtn.primary { background:var(--brand); color:#fff; border:0; }
        .whobtn.secondary { background:#fff; color:var(--ink); border:1.5px solid var(--line); }
        .whobtn .grow { flex:1; }

        label { display:block; font-weight:600; font-size:14px; margin:14px 0 6px; }
        .opt { font-weight:400; color:var(--muted); }
        input[type=text], input[type=tel], textarea { width:100%; min-height:46px; padding:11px 13px; border:1.5px solid var(--line); border-radius:12px; font:inherit; font-size:16px; background:#fff; color:var(--ink); }
        input:focus, textarea:focus, .chip:focus-visible, .doccard:focus-visible, .whobtn:focus-visible, .btn:focus-visible { outline:3px solid rgba(14,116,144,.35); outline-offset:1px; border-color:var(--brand); }
        .ferr { color:var(--err); font-size:13.5px; font-weight:600; margin:6px 0 0; }
        .ferr[hidden] { display:none; }
        input.bad { border-color:var(--err); background:var(--err-l); }
        .note { display:flex; gap:8px; align-items:flex-start; color:var(--muted); font-size:13px; margin:12px 0 14px; }
        .hp { position:absolute; left:-9999px; width:1px; height:1px; overflow:hidden; }

        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:48px; padding:10px 18px; border-radius:12px; border:1.5px solid var(--line); background:#fff; font:inherit; font-weight:700; font-size:15.5px; cursor:pointer; text-decoration:none; color:var(--ink); }
        .btn-brand { background:var(--brand); color:#fff; border-color:var(--brand); }
        .btn-wa-o { color:var(--wa); border-color:#bbf7d0; background:#f0fdf4; }
        .btn-block { width:100%; }
        .btn[disabled] { opacity:.6; cursor:wait; }
        .alt { margin-top:18px; padding-top:16px; border-top:1px dashed var(--line); text-align:center; }
        .alt p { margin:0 0 8px; color:var(--muted); font-size:13.5px; }

        .flash { display:flex; gap:10px; align-items:flex-start; padding:12px 14px; border-radius:12px; margin:0 0 6px; font-weight:600; font-size:14.5px; }
        .flash.err { background:var(--err-l); color:var(--err); border:1px solid #fda29b; }
        .success { text-align:center; padding:8px 4px 4px; }
        .success .big { width:64px; height:64px; border-radius:50%; background:var(--ok-l); color:var(--ok); display:flex; align-items:center; justify-content:center; margin:0 auto 12px; }
        .success h2 { margin:0 0 6px; }
        .success .what { display:inline-block; margin:10px 0 6px; padding:12px 16px; border-radius:12px; background:var(--bg); font-weight:700; }
        .success p { color:var(--muted); margin:8px 0 16px; }

        .crow { display:flex; align-items:center; gap:12px; min-height:52px; padding:8px 10px; margin:0 -10px; border-radius:12px; text-decoration:none; }
        a.crow:hover { background:var(--bg); }
        .crow .ci { width:38px; height:38px; border-radius:10px; background:var(--brand-l); color:var(--brand-d); display:flex; align-items:center; justify-content:center; flex:none; }
        .crow.wa .ci { background:#f0fdf4; color:var(--wa); }
        .crow small { display:block; color:var(--muted); font-size:12.5px; }
        .crow span { font-weight:600; font-size:14.5px; }
        .map { width:100%; height:230px; border:0; border-radius:12px; background:var(--bg); display:block; }
        .about { white-space:normal; color:#334155; }
        footer { text-align:center; color:var(--muted); font-size:12.5px; padding:6px 0 30px; }
        @media (max-width:520px) { .hero h1 { font-size:22px; } .card { padding:16px; border-radius:16px; } .docs { grid-template-columns:1fr; } .qbtn { flex:1 1 auto; justify-content:center; } }
    </style>
</head>
<body>
    <header class="hero">
        <div class="wrap">
            <div class="brand">
                <div class="badge">@if ($logo)<img src="{{ $logo }}" alt="">@else{{ $initials }}@endif</div>
                <div>
                    <h1>{{ $business->name }}</h1>
                    @if ($tagline)<p class="tag">{{ $tagline }}</p>@endif
                </div>
            </div>
            <div class="quick">
                <a class="qbtn main" href="#book">{!! $ico('cal') !!} {{ __('clinic.pub_book_heading') }}</a>
                @if ($solo)<a class="qbtn" href="{{ $clinic_url }}" id="heroOthers">{!! $ico('user') !!} {{ __('clinic.pub_our_doctors') }}</a>@endif
                @if ($tel)<a class="qbtn" href="{{ $tel }}">{!! $ico('phone') !!} {{ __('clinic.pub_call') }}</a>@endif
                @if ($whatsapp)<a class="qbtn" href="{{ $whatsapp }}" target="_blank" rel="noopener">{!! $ico('chat') !!} {{ __('clinic.pub_whatsapp') }}</a>@endif
            </div>
            <div class="how" aria-label="{{ __('clinic.pub_how_title') }}">
                <span><b>1</b>{{ __('clinic.pub_how_1') }}</span>
                <span><b>2</b>{{ __('clinic.pub_how_2') }}</span>
                <span><b>3</b>{{ __('clinic.pub_how_3') }}</span>
            </div>
        </div>
    </header>

    <main class="wrap">
        <div class="grid">
            {{-- Booking --}}
            <section class="card" id="book" aria-labelledby="book_h">
                @if ($done)
                    <div class="success" id="doneBox" role="status">
                        <div class="big">{!! $ico('check', 34) !!}</div>
                        <h2>{{ __('clinic.pub_done_title') }}</h2>
                        <div class="what">{{ $done['doctor'] }}<br>{{ $done['date'] }} · {{ $done['time'] }}</div>
                        <p>{{ __('clinic.pub_done_body') }}</p>
                        <button type="button" class="btn btn-block" id="anotherBtn">{!! $ico('cal') !!} {{ __('clinic.pub_book_another') }}</button>
                    </div>
                @endif

                <div id="booker" @if ($done) hidden @endif>
                    <h2 id="book_h">{{ $solo ? __('clinic.pub_book_with', ['name' => $solo_name]) : __('clinic.pub_book_heading') }}</h2>
                    <p class="sub">{{ $solo ? __('clinic.pub_solo_sub') : __('clinic.pub_book_sub') }}</p>

                    @if ($status && ! $status['ok'] && (($status['field'] ?? '') === '' || ! isset($schedules[(int) old('doctor_id')])))
                        <div class="flash err" role="alert">{{ $status['msg'] }}</div>
                    @endif

                    @if (empty($doctors))
                        <p class="sub">{{ __('clinic.pub_no_days') }}</p>
                    @else
                        @if ($solo)
                            @php $first = $schedules[$selected]['days'][0] ?? null; @endphp
                            <div class="solo" id="soloDoc">
                                <span class="av">{!! $ico('doc', 26) !!}</span>
                                <span>
                                    <b>{{ $solo_name }}</b>
                                    <span class="spec {{ $solo_spec ? '' : 'gen' }}">{{ $solo_spec ?: __('clinic.sp_general') }}</span>
                                    @if ($first)<small>{{ __('clinic.pub_next') }} {{ $first['label'] }}</small>@endif
                                </span>
                            </div>
                        @endif
                        <div class="step" id="docStep" style="margin-top:6px;" @if ($solo) hidden @endif>
                            <p class="shead"><span class="num">1</span>{{ __('clinic.pub_choose_doctor') }}</p>
                            @if ($finder)
                                <div class="finder" id="finder">
                                    <div class="search">{!! $ico('search') !!}
                                        <input type="text" id="docSearch" placeholder="{{ __('clinic.sp_search') }}" aria-label="{{ __('clinic.sp_search') }}" autocomplete="off" enterkeyhint="search"></div>
                                    @if (count($sp_used))
                                        <div class="fchips" id="docFilter" role="group" aria-label="{{ __('clinic.sp_specialists') }}">
                                            <button type="button" class="fchip" data-f="all" aria-pressed="true">{{ __('clinic.sp_all') }}</button>
                                            <button type="button" class="fchip" data-f="general" aria-pressed="false">{{ __('clinic.sp_generals') }}</button>
                                            <button type="button" class="fchip" data-f="specialist" aria-pressed="false">{{ __('clinic.sp_specialists') }}</button>
                                            @foreach ($sp_used as $sp)
                                                <button type="button" class="fchip" data-f="sp:{{ $sp }}" aria-pressed="false">{{ $sp }}</button>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endif
                            <div class="docs" id="docChips">
                                @foreach ($doctors as $id => $name)
                                    @continue($solo && $id !== $selected)
                                    @php $first = $schedules[$id]['days'][0] ?? null; $dsp = $specialties[$id] ?? null; @endphp
                                    <button type="button" class="doccard" data-doc="{{ $id }}" data-name="{{ $name }}" data-spec="{{ $dsp }}" aria-pressed="false">
                                        <span class="av">{!! $ico('doc', 20) !!}</span>
                                        <span>
                                            <b>{{ $name }}</b>
                                            <small class="spec {{ $dsp ? '' : 'gen' }}">{{ $dsp ?: __('clinic.sp_general') }}</small>
                                            @if ($first)
                                                <small class="soon">{{ __('clinic.pub_next') }} {{ $first['label'] }}</small>
                                            @else
                                                <small>{{ __('clinic.pub_no_days_short') }}</small>
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                            <p class="nomatch" id="noMatch" hidden>{{ __('clinic.sp_none_match') }} <button type="button" id="showAll">{{ __('clinic.sp_show_all') }}</button></p>
                        </div>

                        <div class="step" id="dayStep" hidden>
                            <p class="shead"><span class="num">{{ 1 + $n0 }}</span>{{ __('clinic.pub_choose_day') }}</p>
                            <div class="chips" id="dayChips"></div>
                            <p class="sub" id="noDays" hidden>{{ __('clinic.pub_no_days') }}</p>
                        </div>

                        <div class="step" id="timeStep" hidden>
                            <p class="shead"><span class="num">{{ 2 + $n0 }}</span>{{ __('clinic.pub_choose_time') }}</p>
                            <p class="ferr" id="err_slot" role="alert" hidden></p>
                            <div id="timeChips"></div>
                            <p class="sub" id="loadingSlots" hidden>{{ __('clinic.pub_loading') }}</p>
                            <p class="sub" id="noSlots" hidden>{{ __('clinic.pub_no_slots') }}</p>
                        </div>

                        <div class="step" id="whoStep" hidden>
                            <p class="shead"><span class="num">{{ 3 + $n0 }}</span>{{ __('clinic.pub_your_details') }}</p>
                            <div class="summary"><span id="chosenSummary"></span><a id="changeLink" role="button" tabindex="0">{{ __('clinic.pub_change') }}</a></div>
                            <div class="who" id="whoChoice">
                                <button type="button" class="whobtn primary" id="newBtn">
                                    {!! $ico('user', 22) !!}<span class="grow"><b>{{ __('clinic.pub_new') }}</b><small>{{ __('clinic.pub_new_sub') }}</small></span>{!! $ico('arrow') !!}
                                </button>
                                <a class="whobtn secondary" href="{{ $portal_url }}">
                                    {!! $ico('login', 22) !!}<span class="grow"><b>{{ __('clinic.pub_returning') }}</b><small>{{ __('clinic.pub_returning_sub') }}</small></span>{!! $ico('arrow') !!}
                                </a>
                            </div>

                            <form method="POST" action="{{ route('clinic-public.book', $slug) }}" id="guestForm" novalidate hidden>
                                @csrf
                                <input type="hidden" name="doctor_id" id="f_doctor" value="{{ old('doctor_id') }}">
                                <input type="hidden" name="date" id="f_date" value="{{ old('date') }}">
                                <input type="hidden" name="start" id="f_start" value="{{ old('start') }}">
                                @if ($solo)<input type="hidden" name="from_doctor" value="{{ $selected }}">@endif
                                <div class="hp" aria-hidden="true"><label for="hp_website">Website</label><input type="text" id="hp_website" name="website" tabindex="-1" autocomplete="off"></div>

                                <label for="g_name">{{ __('clinic.pub_form_name') }}</label>
                                <input type="text" id="g_name" name="guest_name" maxlength="150" autocomplete="name" value="{{ old('guest_name') }}" aria-describedby="err_guest_name">
                                <p class="ferr" id="err_guest_name" role="alert" hidden></p>

                                <label for="g_phone">{{ __('clinic.pub_form_phone') }}</label>
                                <input type="tel" id="g_phone" name="guest_phone" maxlength="40" autocomplete="tel" inputmode="tel" placeholder="{{ \App\Utils\ClinicSettings::get($business->id, 'whatsapp_country_code') === '237' ? '6XX XX XX XX' : '' }}" value="{{ old('guest_phone') }}" aria-describedby="err_guest_phone">
                                <p class="ferr" id="err_guest_phone" role="alert" hidden></p>

                                <label for="g_reason">{{ __('clinic.pub_form_reason') }} <span class="opt">({{ __('clinic.pub_optional') }})</span></label>
                                <textarea id="g_reason" name="reason" rows="2" maxlength="500">{{ old('reason') }}</textarea>

                                <p class="note">{!! $ico('check', 16) !!}<span>{{ __('clinic.pub_form_note') }}</span></p>
                                <button type="submit" class="btn btn-brand btn-block" id="sendBtn">{{ __('clinic.pub_form_submit') }}</button>
                            </form>
                        </div>
                    @endif

                    @if ($solo)
                        <a class="others" href="{{ $clinic_url }}" id="otherDoctors">{!! $ico('user') !!} {{ count($doctors) > 1 ? __('clinic.pub_see_others') : __('clinic.pub_see_clinic') }}</a>
                    @endif

                    @if ($whatsapp)
                        <div class="alt">
                            <p>{{ __('clinic.pub_prefer_wa') }}</p>
                            <a class="btn btn-wa-o btn-block" href="{{ $whatsapp }}" target="_blank" rel="noopener">{!! $ico('chat') !!} {{ __('clinic.pub_book_whatsapp') }}</a>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Side: about, contact, map --}}
            <aside class="side">
                @if ($description)
                    <div class="card">
                        <p class="ctitle">{{ __('clinic.pub_about') }}</p>
                        <div class="about">{!! nl2br(e($description)) !!}</div>
                    </div>
                @endif

                <div class="card">
                    <p class="ctitle">{{ __('clinic.pub_contact') }}</p>
                    @if ($tel)
                        <a class="crow" href="{{ $tel }}"><span class="ci">{!! $ico('phone') !!}</span><span>{{ $location->mobile }}<small>{{ __('clinic.pub_call') }}</small></span></a>
                    @endif
                    @if ($whatsapp)
                        <a class="crow wa" href="{{ $whatsapp }}" target="_blank" rel="noopener"><span class="ci">{!! $ico('chat') !!}</span><span>{{ __('clinic.pub_whatsapp') }}<small>{{ __('clinic.pub_wa_sub') }}</small></span></a>
                    @endif
                    @if ($location && $location->email)
                        <a class="crow" href="mailto:{{ $location->email }}"><span class="ci">{!! $ico('mail') !!}</span><span>{{ $location->email }}<small>Email</small></span></a>
                    @endif
                    @if ($address)
                        <div class="crow"><span class="ci">{!! $ico('pin') !!}</span><span style="font-weight:500">{!! $address !!}</span></div>
                    @endif
                    <a class="btn btn-block" style="margin-top:10px;" href="{{ $portal_url }}">{!! $ico('login') !!} {{ __('clinic.pub_portal_btn') }}</a>
                </div>

                @if ($map_lat && $map_lng)
                    <div class="card">
                        <p class="ctitle">{{ __('clinic.pub_find_us') }}</p>
                        <iframe class="map" loading="lazy" title="{{ __('clinic.pub_find_us') }}" referrerpolicy="no-referrer-when-downgrade"
                            src="https://www.google.com/maps?q={{ $map_lat }},{{ $map_lng }}&z=16&output=embed"></iframe>
                        <a class="btn btn-block" style="margin-top:12px;" target="_blank" rel="noopener"
                            href="https://www.google.com/maps/dir/?api=1&destination={{ $map_lat }},{{ $map_lng }}">{!! $ico('nav') !!} {{ __('clinic.pub_directions') }}</a>
                    </div>
                @elseif ($address)
                    <div class="card">
                        <p class="ctitle">{{ __('clinic.pub_find_us') }}</p>
                        <a class="btn btn-block" target="_blank" rel="noopener"
                            href="https://www.google.com/maps/search/?api=1&query={{ urlencode($business->name.' '.strip_tags(str_replace('<br>', ' ', $address))) }}">{!! $ico('nav') !!} {{ __('clinic.pub_directions') }}</a>
                    </div>
                @endif
            </aside>
        </div>
    </main>

    <footer>{{ $business->name }} · {{ __('clinic.pub_powered') }}</footer>

    <script>
    (function () {
        var SCHED = @json($schedules);
        var SLOTS_URL = @json(route('clinic-public.slots', $slug));
        var L = @json($js_labels);
        var OLD = @json($js_old);
        var ERR = @json($js_err);
        var SEL = @json($selected);
        var state = { doc: null, date: null, start: null };
        var $ = function (id) { return document.getElementById(id); };
        var narrow = function () { return window.innerWidth < 800; };

        function show(id, on) { var e = $(id); if (e) { e.hidden = ! on; } }
        function press(container, el) {
            Array.prototype.forEach.call(container.querySelectorAll('[aria-pressed]'), function (c) { c.setAttribute('aria-pressed', 'false'); });
            if (el) { el.setAttribute('aria-pressed', 'true'); }
        }
        function bring(id) { if (narrow()) { var e = $(id); if (e) { setTimeout(function () { e.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 60); } } }
        function fieldErr(name, msg) {
            var p = $('err_' + name), i = document.querySelector('[name="' + name + '"]');
            if (! p) { return; }
            p.textContent = msg || ''; p.hidden = ! msg;
            if (i) { i.classList.toggle('bad', !! msg); }
        }

        function chooseDoctor(id, quiet) {
            id = String(id);
            state.doc = id; state.date = null; state.start = null;
            press($('docChips'), document.querySelector('#docChips [data-doc="' + id + '"]'));
            show('dayStep', true); show('timeStep', false); show('whoStep', false);
            var days = (SCHED[id] && SCHED[id].days) || [];
            var box = $('dayChips'); box.innerHTML = '';
            show('noDays', days.length === 0);
            days.forEach(function (d) {
                var c = document.createElement('button'); c.type = 'button'; c.className = 'chip';
                c.textContent = d.label; c.setAttribute('data-date', d.date); c.setAttribute('aria-pressed', 'false');
                c.onclick = function () { chooseDate(d.date); };
                box.appendChild(c);
            });
            if (! quiet) { bring('dayStep'); }
        }

        function chooseDate(date, after) {
            state.date = date; state.start = null;
            press($('dayChips'), document.querySelector('#dayChips [data-date="' + date + '"]'));
            show('timeStep', true); show('whoStep', false); show('err_slot', false);
            var box = $('timeChips'); box.innerHTML = '';
            show('noSlots', false); show('loadingSlots', true);
            var x = new XMLHttpRequest();
            x.open('GET', SLOTS_URL + '?doctor_id=' + encodeURIComponent(state.doc) + '&date=' + encodeURIComponent(date));
            x.setRequestHeader('Accept', 'application/json');
            x.onload = function () {
                show('loadingSlots', false);
                var slots = [];
                try { slots = JSON.parse(x.responseText).slots || []; } catch (e) { slots = []; }
                show('noSlots', slots.length === 0);
                var groups = [['morning', []], ['afternoon', []], ['evening', []]];
                slots.forEach(function (s) { var h = parseInt(s, 10); groups[h < 12 ? 0 : (h < 17 ? 1 : 2)][1].push(s); });
                groups.forEach(function (g) {
                    if (! g[1].length) { return; }
                    var wrap = document.createElement('div'); wrap.className = 'tgroup';
                    var lab = document.createElement('p'); lab.className = 'glabel'; lab.textContent = L[g[0]]; wrap.appendChild(lab);
                    var row = document.createElement('div'); row.className = 'chips';
                    g[1].forEach(function (s) {
                        var c = document.createElement('button'); c.type = 'button'; c.className = 'chip';
                        c.textContent = s; c.setAttribute('data-start', s); c.setAttribute('aria-pressed', 'false');
                        c.onclick = function () { chooseSlot(s); };
                        row.appendChild(c);
                    });
                    wrap.appendChild(row); box.appendChild(wrap);
                });
                if (after) { after(slots); } else { bring('timeStep'); }
            };
            x.onerror = function () { show('loadingSlots', false); show('noSlots', true); };
            x.send();
        }

        function dayLabel() {
            var lbl = state.date;
            ((SCHED[state.doc] && SCHED[state.doc].days) || []).forEach(function (d) { if (d.date === state.date) { lbl = d.label; } });
            return lbl;
        }

        function chooseSlot(start, quiet) {
            state.start = start;
            press($('timeChips'), document.querySelector('#timeChips [data-start="' + start + '"]'));
            show('err_slot', false);
            show('whoStep', true);
            $('chosenSummary').textContent = (SCHED[state.doc] ? SCHED[state.doc].name : '') + ' · ' + dayLabel() + ' · ' + start;
            $('f_doctor').value = state.doc; $('f_date').value = state.date; $('f_start').value = start;
            if (! quiet) { bring('whoStep'); }
        }

        function openForm(focus) {
            show('guestForm', true); show('whoChoice', false);
            if (focus) { setTimeout(function () { var n = $('g_name'); if (n && ! n.value) { n.focus(); } }, 80); }
        }

        Array.prototype.forEach.call(document.querySelectorAll('#docChips .doccard'), function (c) {
            c.onclick = function () { chooseDoctor(c.getAttribute('data-doc')); };
        });

        // find a doctor: by name or specialty, general or specialists
        var sq = $('docSearch'), filt = 'all', GEN = @json(__('clinic.sp_general'));
        var fold = function (s) { return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); };
        function applyFind() {
            var words = sq ? fold(sq.value).split(/\s+/).filter(Boolean) : [], shown = 0;
            Array.prototype.forEach.call(document.querySelectorAll('#docChips .doccard'), function (c) {
                var sp = c.getAttribute('data-spec') || '', hay = fold(c.getAttribute('data-name') + ' ' + (sp || GEN));
                var ok = words.every(function (w) { return hay.indexOf(w) >= 0; });
                if (filt === 'general') { ok = ok && ! sp; }
                else if (filt === 'specialist') { ok = ok && !! sp; }
                else if (filt.indexOf('sp:') === 0) { ok = ok && sp === filt.slice(3); }
                if (c.getAttribute('aria-pressed') === 'true') { ok = true; }
                c.classList.toggle('hide', ! ok);
                if (ok) { shown++; }
            });
            show('noMatch', shown === 0);
        }
        function setFilter(f) {
            filt = f;
            Array.prototype.forEach.call(document.querySelectorAll('#docFilter .fchip'), function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-f') === f ? 'true' : 'false'); });
            applyFind();
        }
        if (sq) { sq.addEventListener('input', applyFind); }
        Array.prototype.forEach.call(document.querySelectorAll('#docFilter .fchip'), function (b) { b.onclick = function () { setFilter(b.getAttribute('data-f')); }; });
        var sa = $('showAll'); if (sa) { sa.onclick = function () { if (sq) { sq.value = ''; } setFilter('all'); }; }
        var nb = $('newBtn'); if (nb) { nb.onclick = function () { openForm(true); bring('guestForm'); }; }
        var ch = $('changeLink');
        if (ch) {
            var change = function () { show('whoStep', false); var t = $('timeStep'); if (t) { t.scrollIntoView({ behavior: 'smooth', block: 'start' }); } };
            ch.onclick = change; ch.onkeydown = function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); change(); } };
        }
        var an = $('anotherBtn'); if (an) { an.onclick = function () { show('doneBox', false); show('booker', true); }; }

        // check the form here first, so a mistake never costs a page reload
        var f = $('guestForm');
        if (f) {
            f.addEventListener('submit', function (e) {
                var name = $('g_name').value.trim(), phone = $('g_phone').value.replace(/\D+/g, '');
                var bad = false;
                fieldErr('guest_name', name.length < 2 ? L.errName : ''); bad = bad || name.length < 2;
                fieldErr('guest_phone', phone.length < 6 ? L.errPhone : ''); bad = bad || phone.length < 6;
                if (bad) {
                    e.preventDefault(); e.stopPropagation();
                    var first = document.querySelector('#guestForm input.bad'); if (first) { first.focus(); }
                    return;
                }
                var b = $('sendBtn'); b.disabled = true; b.textContent = L.sending;
            });
            ['g_name', 'g_phone'].forEach(function (id) {
                var i = $(id); if (i) { i.addEventListener('input', function () { fieldErr(i.name, ''); }); }
            });
        }

        // coming back from the server with an error: put everything back as the visitor left it
        if (OLD.doctor && SCHED[OLD.doctor]) {
            chooseDoctor(OLD.doctor, true);
            if (OLD.date) {
                chooseDate(OLD.date, function (slots) {
                    var still = OLD.start && slots.indexOf(OLD.start) >= 0;
                    if (still) {
                        chooseSlot(OLD.start, true);
                        openForm(false);
                        if (ERR && (ERR.field === 'guest_name' || ERR.field === 'guest_phone')) { fieldErr(ERR.field, ERR.msg); }
                        var target = (ERR && ERR.field) ? document.querySelector('[name="' + ERR.field + '"]') : $('guestForm');
                        setTimeout(function () { if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'center' }); if (target.focus) { target.focus(); } } }, 120);
                    } else {
                        var es = $('err_slot'); es.textContent = (ERR && ERR.field === 'slot') ? ERR.msg : L.slotGone; es.hidden = false;
                        $('timeStep').scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }
        } else if (SEL !== null && SCHED[SEL]) {
            chooseDoctor(SEL, true);
        }
    })();
    </script>
</body>
</html>
