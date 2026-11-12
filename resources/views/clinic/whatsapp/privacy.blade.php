<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('clinic.privacy_title') }} - {{ optional($business)->name ?: config('app.name') }}</title>
    <style>
        :root { --ink: #0f172a; --muted: #475569; --bg: #f8fafc; --card: #ffffff; --line: #e2e8f0; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 16px/1.6 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        main { max-width: 760px; margin: 0 auto; padding: 32px 16px 48px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 24px 22px; }
        h1 { font-size: 26px; margin: 0 0 4px; }
        h2 { font-size: 18px; margin: 24px 0 6px; }
        p, li { color: var(--muted); }
        .lang { text-align: right; font-size: 14px; margin-bottom: 10px; }
        .lang a { color: #1d4ed8; }
    </style>
</head>
<body>
<main>
    <div class="lang"><a href="?lang=fr">Français</a> · <a href="?lang=en">English</a></div>
    <div class="card">
        @php $name = optional($business)->name ?: config('app.name'); @endphp
        <h1>{{ __('clinic.privacy_title') }}</h1>
        <p>{{ $name }}</p>
        <h2>{{ __('clinic.privacy_h_what') }}</h2>
        <p>{{ __('clinic.privacy_what', ['name' => $name]) }}</p>
        <h2>{{ __('clinic.privacy_h_whatsapp') }}</h2>
        <p>{{ __('clinic.privacy_whatsapp') }}</p>
        <h2>{{ __('clinic.privacy_h_share') }}</h2>
        <p>{{ __('clinic.privacy_share') }}</p>
        <h2>{{ __('clinic.privacy_h_keep') }}</h2>
        <p>{{ __('clinic.privacy_keep') }}</p>
        <h2>{{ __('clinic.privacy_h_rights') }}</h2>
        <p>{{ __('clinic.privacy_rights') }}</p>
        <h2>{{ __('clinic.privacy_h_contact') }}</h2>
        <p>{{ __('clinic.privacy_contact', ['name' => $name]) }}
            @if (optional($business)->email) <a href="mailto:{{ $business->email }}">{{ $business->email }}</a>@endif</p>
    </div>
</main>
</body>
</html>
