@extends('layouts.app')
@section('title', __('gym.menu_new'))

@section('content')
@include('pressing.partials.styles')
@php
    $G = \App\Http\Controllers\GymController::class;
    $fmt = fn ($v) => \App\Utils\Gym::format(session('business.id'), $v);
@endphp
<section class="content ht" id="gy_new">
    <div class="ht-head"><div><h1>{{ __('gym.menu_new') }}</h1><p>{{ __('gym.new_lead') }}</p></div>
        <div class="ht-actions"><a class="ht-btn" href="{{ action([$G, 'members']) }}">{{ __('gym.menu_members') }}</a></div></div>
    @if ($plans->isEmpty())
        <a class="ht-note amber" href="{{ action([\App\Http\Controllers\GymSetupController::class, 'index']) }}">{{ __('gym.needs_setup') }}</a>
    @endif
    <form method="post" action="{{ action([$G, 'store']) }}" id="gy_member_form">@csrf
        <div class="ht-card">
            <h2 class="pr-h"><span class="pr-step">1</span>{{ __('gym.step_who') }}</h2>
            <div class="ht-fields">
                <div class="ht-f"><label for="gm_name">{{ __('gym.name') }} *</label><input id="gm_name" name="name" class="ht-in" value="{{ old('name') }}" required></div>
                <div class="ht-f"><label for="gm_mobile">{{ __('gym.mobile') }} *</label><input id="gm_mobile" name="mobile" type="tel" class="ht-in" value="{{ old('mobile') }}" required><span class="ht-muted ht-small">{{ __('gym.mobile_hint') }}</span></div>
                <div class="ht-f"><label for="gm_lang">{{ __('gym.lang') }}</label><select id="gm_lang" name="lang" class="ht-in"><option value="fr">Français</option><option value="en" {{ app()->getLocale() === 'en' ? 'selected' : '' }}>English</option></select></div>
            </div>
            <label style="display:flex;gap:8px;align-items:center;font-weight:600"><input type="checkbox" name="whatsapp" value="1" checked style="width:20px;height:20px"> {{ __('gym.wa_opt') }}</label>
            <div class="ht-f" style="margin-top:8px"><label for="gm_note">{{ __('gym.note') }}</label><input id="gm_note" name="note" class="ht-in" value="{{ old('note') }}"></div>
        </div>
        <div class="ht-card">
            <h2 class="pr-h"><span class="pr-step">2</span>{{ __('gym.step_plan') }}</h2>
            <div class="ht-grid ht-g3">
                @foreach ($plans as $i => $p)
                    <label style="display:flex;gap:10px;align-items:flex-start;border:1px solid #d0d5dd;border-radius:10px;padding:12px;margin:0;cursor:pointer">
                        <input type="radio" name="plan_id" value="{{ $p->id }}" {{ (string) old('plan_id', $i === 0 ? $p->id : '') === (string) $p->id ? 'checked' : '' }} style="width:20px;height:20px;margin-top:2px">
                        <span><strong>{{ $p->name }}</strong><br><span class="ht-muted">{{ $fmt($p->price) }} · {{ $p->kind === 'visits' ? trans_choice('gym.n_visits', $p->visits, ['n' => $p->visits]) : trans_choice('gym.n_months', $p->months, ['n' => $p->months]) }}</span></span>
                    </label>
                @endforeach
            </div>
            <div class="ht-fields" style="margin-top:12px">
                <div class="ht-f"><label for="gm_start">{{ __('gym.start_date') }}</label><input id="gm_start" name="start_date" type="date" class="ht-in" value="{{ old('start_date', today()->toDateString()) }}"><span class="ht-muted ht-small">{{ __('gym.start_hint') }}</span></div>
                <div class="ht-f"><label for="gm_method">{{ __('gym.method') }}</label><select id="gm_method" name="method" class="ht-in">@foreach ($methods as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
            </div>
            <button class="ht-btn pri" type="submit" style="min-height:48px" id="gy_save_member">{{ __('gym.save_member') }}</button>
            <p class="ht-muted ht-small">{{ __('gym.paid_now_hint') }}</p>
        </div>
    </form>
</section>
@endsection
