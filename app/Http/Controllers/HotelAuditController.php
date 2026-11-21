<?php

namespace App\Http\Controllers;

use App\HotelNightAudit;
use App\Utils\Hotel;
use App\Utils\HotelAudit;
use Illuminate\Http\Request;

/** PosInnova hotel: the night audit (checklist, run) and the daily reports it leaves. */
class HotelAuditController extends HotelBaseController
{
    public function index()
    {
        $this->guard('hotel.night_audit', 'hotel.reports');
        $biz = $this->biz();
        $next = HotelAudit::nextDate($biz);
        $can_run = $next->lte(Hotel::today());
        $too_early = $next->equalTo(Hotel::today()) && (int) now()->format('H') < HotelAudit::EARLIEST_HOUR;

        return view('hotel.audit', [
            'next' => $next, 'can_run' => $can_run, 'check' => $can_run ? HotelAudit::checklist($biz, $next) : null,
            'history' => HotelNightAudit::where('business_id', $biz)->orderByDesc('audit_date')->limit(30)->get(),
            'settings' => Hotel::settings($biz), 'fmt' => fn ($v) => $this->money($v),
            'can_audit' => self::can('hotel.night_audit'), 'too_early' => $too_early, 'earliest' => HotelAudit::EARLIEST_HOUR.':00',
        ]);
    }

    public function run(Request $request)
    {
        $this->guard('hotel.night_audit');
        $biz = $this->biz();
        $decisions = array_filter((array) $request->input('decision', []), fn ($v) => in_array($v, ['no_show', 'keep', 'cancel'], true));

        return $this->attempt(fn () => HotelAudit::run($biz, Hotel::parseDate($request->input('date')), auth()->id(), $decisions),
            fn ($a) => __('hotel.audit_done', ['date' => $a->audit_date->format('d/m/Y'), 'n' => $a->nights_posted]),
            redirect()->action([self::class, 'index']));
    }

    public function show($id)
    {
        $this->guard('hotel.night_audit', 'hotel.reports');
        $audit = HotelNightAudit::where('business_id', $this->biz())->findOrFail($id);
        $names = \App\User::whereIn('id', array_filter([$audit->ran_by]))->get()->keyBy('id');

        return view('hotel.audit_report', ['audit' => $audit, 's' => $audit->summary ?: [], 'by' => optional($names->get($audit->ran_by)),
            'fmt' => fn ($v) => $this->money($v), 'methods' => $this->tu->payment_types(null, true, $this->biz()), 'print' => request()->boolean('print')]);
    }
}
