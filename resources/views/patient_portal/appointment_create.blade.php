@extends('patient_portal.layouts.portal')
@section('title', __('clinic.appt_portal_request'))
@section('heading', __('clinic.appt_portal_request'))
@php $portal_group = 'appointments'; @endphp

@section('content')
<style>
    .apb-card { display:block; border:2px solid #e2e8f0; border-radius:14px; padding:12px 14px; background:#fff; cursor:pointer; margin-bottom:8px; }
    .apb-card.is-on { border-color:#4f46e5; background:#eef2ff; }
    .apb-card input { position:absolute; opacity:0; pointer-events:none; }
    .apb-slots { display:grid; grid-template-columns:repeat(auto-fill,minmax(74px,1fr)); gap:8px; }
    .apb-slot { border:1px solid #c7d2fe; border-radius:10px; padding:9px 0; text-align:center; font-weight:600; color:#3730a3; background:#fff; cursor:pointer; }
    .apb-slot.is-on { background:#4f46e5; color:#fff; border-color:#4f46e5; }
    .apb-label { font-size:13px; font-weight:700; color:#334155; margin:14px 0 8px; display:block; }
    .apb-input { width:100%; border:1px solid #e2e8f0; border-radius:12px; padding:11px 12px; font-size:15px; background:#fff; }
</style>

<form method="POST" action="{{ route('portal.appointment-store') }}" id="apb_form">
    @csrf
    <span class="apb-label">1. @lang('clinic.appt_portal_pick_doctor')</span>
    @foreach ($doctors as $id => $name)
        <label class="apb-card {{ (old('doctor_id', count($doctors) == 1 ? $id : null) == $id) ? 'is-on' : '' }}">
            <input type="radio" name="doctor_id" value="{{ $id }}" {{ (old('doctor_id', count($doctors) == 1 ? $id : null) == $id) ? 'checked' : '' }}>
            <i class="fas fa-user-md" style="color:#4f46e5;"></i> <strong>{{ $name }}</strong>
        </label>
    @endforeach

    <label class="apb-label" for="apb_date">2. @lang('clinic.appt_portal_pick_date')</label>
    <input type="date" name="date" id="apb_date" class="apb-input" value="{{ old('date', \Carbon::today()->toDateString()) }}"
        min="{{ \Carbon::today()->toDateString() }}" max="{{ \Carbon::today()->addDays($days_ahead)->toDateString() }}" required>

    <span class="apb-label">3. @lang('clinic.appt_portal_pick_time')</span>
    <input type="hidden" name="start" id="apb_start" value="{{ old('start') }}">
    <div id="apb_slots" class="apb-slots"></div>
    <p id="apb_msg" style="font-size:13px;color:#64748b;margin:6px 0 0;"></p>

    <label class="apb-label" for="apb_reason">4. @lang('clinic.appt_portal_reason')</label>
    <textarea name="reason" id="apb_reason" class="apb-input" rows="2" maxlength="500" placeholder="{{ __('clinic.appt_portal_reason_ph') }}">{{ old('reason') }}</textarea>

    <button type="submit" id="apb_submit" class="portal-press" style="width:100%;margin-top:16px;border:0;border-radius:14px;background:#4f46e5;color:#fff;font-weight:700;padding:14px;font-size:15px;" disabled>
        <i class="fas fa-paper-plane"></i> @lang('clinic.appt_portal_send')
    </button>
    <p style="font-size:12px;color:#94a3b8;margin:8px 0 0;text-align:center;">@lang('clinic.appt_portal_send_help')</p>
</form>

<script>
    (function () {
        var form = document.getElementById('apb_form'), box = document.getElementById('apb_slots'), msg = document.getElementById('apb_msg'),
            start = document.getElementById('apb_start'), submit = document.getElementById('apb_submit'), date = document.getElementById('apb_date');
        var T = { loading: @json(__('clinic.appt_portal_loading')), none: @json(__('clinic.appt_portal_no_slots')), off: @json(__('clinic.appt_portal_day_off')), pick: @json(__('clinic.appt_portal_choose_doctor_first')) };
        function doctor() { var r = form.querySelector('input[name=doctor_id]:checked'); return r ? r.value : null; }
        function refresh() {
            submit.disabled = !start.value;
        }
        var seq = 0; // only the latest answer is shown: a slow earlier one (other doctor or day) is dropped
        function load() {
            var mine = ++seq;
            box.innerHTML = ''; var keep = start.value; start.value = ''; refresh();
            if (!doctor()) { msg.textContent = T.pick; return; }
            msg.textContent = T.loading;
            fetch(@json(route('portal.appointment-slots')) + '?doctor_id=' + encodeURIComponent(doctor()) + '&date=' + encodeURIComponent(date.value), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (mine !== seq) { return; }
                    box.innerHTML = '';
                    msg.textContent = d.day_off ? T.off : (d.slots.length ? '' : T.none);
                    d.slots.forEach(function (s) {
                        var b = document.createElement('button'); b.type = 'button'; b.className = 'apb-slot'; b.textContent = s;
                        if (s === keep) { b.classList.add('is-on'); start.value = s; }
                        b.addEventListener('click', function () {
                            box.querySelectorAll('.apb-slot').forEach(function (x) { x.classList.remove('is-on'); });
                            b.classList.add('is-on'); start.value = s; refresh();
                        });
                        box.appendChild(b);
                    });
                    refresh();
                })
                .catch(function () { if (mine === seq) { msg.textContent = T.none; } });
        }
        form.querySelectorAll('input[name=doctor_id]').forEach(function (r) {
            r.addEventListener('change', function () {
                form.querySelectorAll('.apb-card').forEach(function (c) { c.classList.remove('is-on'); });
                r.closest('.apb-card').classList.add('is-on'); load();
            });
        });
        date.addEventListener('change', load);
        load();
    })();
</script>
@endsection
