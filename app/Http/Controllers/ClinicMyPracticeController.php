<?php

namespace App\Http\Controllers;

use App\ClinicAppointment;
use App\ClinicDoctorDayOff;
use App\ClinicDoctorHour;
use App\Notifications\ClinicAppointmentNotification;
use App\Utils\ClinicAppointments;
use App\Utils\ClinicFees;
use App\Utils\ClinicQueue;
use App\Utils\ClinicSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * My practice: a doctor's own workspace. What is new for him (appointments booked,
 * moved or cancelled since he last looked), his day, the days ahead with his
 * hours / days off / daily limit, and what he may set himself: days off, daily
 * limit, consultation fee, the WhatsApp number his alerts go to, and his personal
 * booking link. For any user who sees patients (ClinicQueue::isDoctor), Admins
 * ticked as doctors included. Every doctor lands here once per sign-in.
 */
class ClinicMyPracticeController extends Controller
{
    /** [business id, user] of the signed-in doctor, or 404. */
    private function doctor()
    {
        $mods = (array) (session('business.enabled_modules') ?: []);
        $bid = session('user.business_id');
        $user = auth()->user();
        if (! in_array('hospital', $mods) || ! ClinicQueue::isDoctor($user, $bid)) {
            abort(404);
        }

        return [$bid, $user];
    }

    /** Unread appointment news for a doctor (the menu badge and the landing). */
    public static function unreadCount($user)
    {
        return $user ? $user->unreadNotifications()->where('type', ClinicAppointmentNotification::class)->count() : 0;
    }

    public function index(Request $request)
    {
        [$bid, $user] = $this->doctor();
        $me = (int) $user->id;
        $locale = app()->getLocale();
        $access = ClinicAppointments::access($bid, $user);
        $today = Carbon::today();
        $days_n = (int) $request->input('days') === 14 ? 14 : 7;

        // what is new since he last looked; shown once, then read
        $unread = $user->unreadNotifications()->where('type', ClinicAppointmentNotification::class)
            ->orderByDesc('created_at')->limit(30)->get();
        $news = $unread->map(fn ($n) => [
            'kind' => $n->data['kind'] ?? 'booked',
            'msg' => ClinicAppointmentNotification::message($n->data),
            'icon' => ClinicAppointmentNotification::icon($n->data['kind'] ?? 'booked'),
            'when' => $n->created_at->locale($locale)->diffForHumans(),
            'date' => $n->data['date'] ?? null,
        ])->values();
        $new_ids = $unread->map(fn ($n) => (int) ($n->data['appointment_id'] ?? 0))->filter()->unique()->values()->all();
        if ($unread->isNotEmpty()) {
            $user->unreadNotifications()->where('type', ClinicAppointmentNotification::class)
                ->whereIn('id', $unread->pluck('id'))->update(['read_at' => now()]);
        }

        // his appointments from today on, his hours, days off and limit
        $end = $today->copy()->addDays($days_n - 1);
        $appts = ClinicAppointment::forBusiness($bid)->where('doctor_id', $me)
            ->whereDate('date', '>=', $today->toDateString())->whereDate('date', '<=', $end->toDateString())
            ->whereIn('status', ['requested', 'confirmed', 'checked_in'])
            ->with('patient')->orderBy('date')->orderBy('start_time')->get()
            ->groupBy(fn ($a) => $a->date->toDateString());
        $hours = ClinicDoctorHour::forBusiness($bid)->where('user_id', $me)->orderBy('weekday')->orderBy('start_time')->get()->groupBy('weekday');
        $offs = ClinicDoctorDayOff::forBusiness($bid)->where('user_id', $me)->whereDate('date', '>=', $today->toDateString())->orderBy('date')->get();
        $off_dates = $offs->map(fn ($o) => Carbon::parse($o->date)->toDateString())->all();
        $cap = ClinicAppointments::dailyCap($bid, $me);
        $span = fn ($blocks) => collect($blocks)->map(fn ($h) => substr($h->start_time, 0, 5).'–'.substr($h->end_time, 0, 5))->implode(', ');

        $days = [];
        for ($i = 0; $i < $days_n; $i++) {
            $d = $today->copy()->addDays($i);
            $key = $d->toDateString();
            $list = $appts->get($key, collect());
            $days[] = [
                'date' => $key,
                'label' => $i === 0 ? __('clinic.mp_today') : ($i === 1 ? __('clinic.mp_tomorrow') : $d->copy()->locale($locale)->isoFormat('dddd')),
                'sub' => $d->copy()->locale($locale)->isoFormat('D MMMM'),
                'hours' => $span($hours->get($d->dayOfWeekIso, [])),
                'off' => in_array($key, $off_dates, true),
                'count' => $list->count(),
                'full' => $cap > 0 && $list->count() >= $cap,
                'appts' => $list->map(fn ($a) => [
                    'id' => $a->id, 'time' => $a->start, 'end' => $a->end, 'name' => $a->display_name,
                    'guest' => $a->isGuest(), 'status' => $a->status, 'status_label' => __('clinic.appt_status_'.$a->status),
                    'reason' => $a->reason, 'new' => in_array($a->id, $new_ids, true),
                ])->values()->all(),
            ];
        }

        $today_list = $appts->get($today->toDateString(), collect());
        $waiting = ClinicQueue::waitingFor($bid, $me);
        $requests = ClinicAppointment::forBusiness($bid)->where('doctor_id', $me)->where('status', 'requested')
            ->whereDate('date', '>=', $today->toDateString())->count();

        // what he may set himself
        $can_schedule = ClinicAppointments::maySchedule($access, $me);
        $can_manage = ClinicAppointments::mayManage($access, $me);
        $can_fee = $user->can('clinic_fee.manage_own') || $user->can('clinic_package.manage');
        $can_call = $user->can('consultation.create');
        $fee_cols = ClinicFees::columns($bid);
        $own_fees = ClinicFees::overrides($bid)[$me] ?? [];
        $week = [];
        foreach (range(1, 7) as $w) {
            $week[$w] = $span($hours->get($w, []));
        }
        $slug = \App\Business::where('id', $bid)->value('slug');
        $link = ($slug && ClinicSettings::get($bid, 'public_page_enabled')) ? route('clinic-public.doctor', [$slug, $me]) : null;
        $default_cap = (int) ClinicSettings::get($bid, 'appointments_daily_cap');
        $own_cap = (int) (((array) ClinicSettings::get($bid, 'appointments_doctor_caps'))[$me] ?? 0);

        return view('clinic.my_practice.index', compact(
            'user', 'news', 'days', 'days_n', 'today_list', 'waiting', 'requests', 'cap', 'own_cap', 'default_cap',
            'can_schedule', 'can_manage', 'can_fee', 'can_call', 'fee_cols', 'own_fees', 'week', 'offs', 'link'
        ));
    }

    /** His own consultation fee(s) only. Blank = the clinic's price. */
    public function saveFee(Request $request)
    {
        [$bid, $user] = $this->doctor();
        if (! ($user->can('clinic_fee.manage_own') || $user->can('clinic_package.manage'))) {
            abort(403);
        }
        $util = app(\App\Utils\Util::class);
        $input = (array) $request->input('fee', []);
        $values = [];
        foreach (ClinicFees::columns($bid) as $key => [$label]) {
            $raw = trim((string) ($input[$key] ?? ''));
            if ($raw === '') {
                $values[$key] = null;
                continue;
            }
            $price = preg_match('/^\d[\d\s.,\']*$/', $raw) ? $util->num_uf($raw) : null;
            if (! is_numeric($price) || $price < 0 || $price > 999999999) {
                return $this->back()->with('status', ['success' => false, 'msg' => __('clinic.mp_fee_invalid', ['column' => $label])]);
            }
            $values[$key] = (float) $price;
        }
        $changed = 0;
        foreach ($values as $key => $price) {
            if (ClinicFees::set($bid, $user->id, $key, $price, $user->id)) {
                $changed++;
            }
        }
        if ($changed) {
            $util->activityLog(\App\Business::find($bid), 'doctor_fees_changed', null, ['changes' => $changed, 'doctor' => $user->id]);
        }

        return $this->back()->with('status', ['success' => true, 'msg' => $changed ? __('clinic.mp_fee_saved') : __('clinic.fee_nothing_changed')]);
    }

    /** The mobile number his WhatsApp alerts go to (his own profile only). */
    public function savePhone(Request $request)
    {
        [$bid, $user] = $this->doctor();
        $raw = trim((string) $request->input('contact_number'));
        if ($raw !== '' && (! preg_match('/^\+?[\d\s\-().]{6,24}$/', $raw) || strlen(preg_replace('/\D+/', '', $raw)) < 8)) {
            return $this->back()->with('status', ['success' => false, 'msg' => __('clinic.mp_phone_invalid')]);
        }
        $user->contact_number = $raw !== '' ? mb_substr($raw, 0, 20) : null;
        $user->save();

        return $this->back()->with('status', ['success' => true, 'msg' => __('clinic.mp_phone_saved')]);
    }

    private function back()
    {
        return redirect()->to(action([self::class, 'index']).'#mp_settings');
    }
}
