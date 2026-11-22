{{--
    PosInnova: a doctor who left a consultation open (page closed by mistake, phone locked, another tab...)
    finds the way back on every page: the patients still "with the doctor" and the consultations saved to
    finish later. Not shown on the consultation form itself.
--}}
@php
    $pi_resume = [];
    try {
        $pi_ru = auth()->user();
        $pi_rmods = session('business.enabled_modules', []);
        if ($pi_ru && is_array($pi_rmods) && in_array('hospital', $pi_rmods) && $pi_ru->can('consultation.create')
            && ! request()->is('consultations/create') && ! request()->is('consultations/*/edit')) {
            $pi_resume = \App\Utils\ClinicQueue::openConsultations(session('user.business_id'), $pi_ru->id);
        }
    } catch (\Throwable $e) {
        $pi_resume = [];
    }
@endphp
@if (! empty($pi_resume))
    <style>
        .pi-resume { margin: 10px 15px 0; padding: 10px 14px; border-radius: 12px; background: #fffaeb; border: 1px solid #fec84b; color: #7a2e0e; }
        .pi-resume-row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; padding: 3px 0; }
        .pi-resume-row + .pi-resume-row { border-top: 1px solid #fedf89; margin-top: 4px; padding-top: 7px; }
        .pi-resume-text { flex: 1 1 220px; min-width: 0; line-height: 1.4; }
        .pi-resume-text small { color: #93370d; }
        .pi-resume a.pi-resume-go { flex: none; display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 8px 16px; border-radius: 8px; background: #b54708; color: #fff !important; font-weight: 700; text-decoration: none !important; }
        .pi-resume a.pi-resume-go:hover { background: #93370d; }
        @media print { .pi-resume { display: none !important; } }
    </style>
    <div class="pi-resume no-print" id="pi_resume" role="status">
        @foreach ($pi_resume as $r)
            <div class="pi-resume-row" data-visit="{{ $r['visit_id'] }}" data-kind="{{ $r['kind'] }}">
                <div class="pi-resume-text"><i class="fas fa-stethoscope" aria-hidden="true"></i>
                    {{ __($r['kind'] === 'draft' ? 'clinic.rs_draft' : 'clinic.rs_open') }} <strong>{{ $r['name'] }}</strong>
                    <small>· {{ __($r['kind'] === 'draft' ? 'clinic.rs_saved_at' : 'clinic.rs_since', ['time' => $r['time']]) }}</small></div>
                <a class="pi-resume-go" href="{{ $r['url'] }}">{{ __('clinic.continue') }} <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
            </div>
        @endforeach
    </div>
@endif
