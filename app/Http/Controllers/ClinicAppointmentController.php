<?php

namespace App\Http\Controllers;

use App\ClinicAppointment;
use App\ClinicDoctorDayOff;
use App\ClinicDoctorHour;
use App\Utils\ClinicAppointments;
use App\Utils\ClinicQueue;
use App\Utils\ClinicSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Appointments (clinic): the day's diary per doctor, the upcoming list, booking,
 * confirming patients' requests from the portal, moving a booking to another
 * slot, reminders by WhatsApp link, and the doctors' consulting hours.
 *
 * appointment.view sees every doctor's diary; appointment.manage books,
 * confirms, moves, cancels and sends reminders for every doctor;
 * appointment.manage_own lets a doctor see and do the same on their own
 * appointments only. clinic_schedule.manage sets every doctor's hours and days
 * off, clinic_schedule.manage_own a doctor's own. See ClinicAppointments::access.
 * Checking a booked patient in goes through the normal check-in screen
 * (visits/create?appointment_id=...).
 */
class ClinicAppointmentController extends Controller
{
    private function hospitalOn()
    {
        $enabled_modules = request()->session()->get('business.enabled_modules', []);
        if (! is_array($enabled_modules) || ! in_array('hospital', $enabled_modules)) {
            abort(404);
        }
    }

    private function access()
    {
        return ClinicAppointments::access(request()->session()->get('user.business_id'), auth()->user());
    }

    private function authorizeView()
    {
        $this->hospitalOn();
        $access = $this->access();
        if (! $access['see']) {
            abort(403, 'Unauthorized action.');
        }

        return $access;
    }

    /** The user may act on this doctor's appointments, or 403. */
    private function authorizeFor($doctor_id)
    {
        $this->hospitalOn();
        $access = $this->access();
        if (! ClinicAppointments::mayManage($access, $doctor_id)) {
            abort(403, 'Unauthorized action.');
        }

        return $access;
    }

    private function authorizeSchedule($doctor_id = null)
    {
        $this->hospitalOn();
        $access = $this->access();
        $ok = $doctor_id === null ? ($access['schedule_all'] || $access['schedule_own'] !== null)
            : ClinicAppointments::maySchedule($access, $doctor_id);
        if (! $ok) {
            abort(403, 'Unauthorized action.');
        }

        return $access;
    }

    /**
     * Which doctor the diary / list shows: [doctor id or null for all, value
     * to keep in links]. A doctor who cannot see the whole clinic always sees
     * their own; a doctor away from the front desk starts on their own and can
     * pick "all doctors" (kept as doctor_id=all).
     */
    private function doctorFilter(Request $request, array $access, array $all_doctors)
    {
        if (! $access['see_all']) {
            return [$access['own'], null];
        }
        $param = $request->input('doctor_id');
        if ($param === 'all') {
            return [null, 'all'];
        }
        if ($param !== null && $param !== '' && isset($all_doctors[(int) $param])) {
            return [(int) $param, (int) $param];
        }
        if ($access['mine_first']) {
            return [(int) auth()->id(), null];
        }

        return [null, null];
    }

    public function index(Request $request)
    {
        $access = $this->authorizeView();
        $business_id = $request->session()->get('user.business_id');

        try {
            $date = Carbon::parse($request->input('date', Carbon::today()->toDateString()))->startOfDay();
        } catch (\Exception $e) {
            $date = Carbon::today();
        }
        $all_doctors = ClinicQueue::doctors($business_id);
        $with_hours = ClinicAppointments::doctorsWithHours($business_id);
        [$doctor_id, $doctor_param] = $this->doctorFilter($request, $access, $all_doctors);

        // a column per doctor who consults that day (or holds bookings on it)
        $booked_doctors = ClinicAppointment::forBusiness($business_id)->whereDate('date', $date->toDateString())
            ->whereNotNull('slot_key')->distinct()->pluck('doctor_id')->all();
        $columns = [];
        foreach ($all_doctors as $id => $name) {
            if ($doctor_id && $id != $doctor_id) {
                continue;
            }
            $slots = ClinicAppointments::slots($business_id, $id, $date);
            $off = ClinicAppointments::isDayOff($business_id, $id, $date);
            if (empty($slots) && ! $off && ! in_array($id, $booked_doctors) && ! $doctor_id) {
                continue;
            }
            $columns[] = ['id' => $id, 'name' => $name, 'slots' => $slots, 'off' => $off,
                'off_reason' => $off ? ClinicDoctorDayOff::forBusiness($business_id)->where('user_id', $id)->whereDate('date', $date->toDateString())->value('reason') : null];
        }

        $scope = function ($q) use ($doctor_id) {
            return $doctor_id ? $q->where('doctor_id', $doctor_id) : $q;
        };
        $requests = $scope(ClinicAppointment::forBusiness($business_id)->where('status', 'requested'))
            ->whereDate('date', '>=', Carbon::today()->toDateString())
            ->with(['patient.patientDetails', 'doctor', 'whatsappMessages'])->orderBy('date')->orderBy('start_time')->get();
        $tomorrow = Carbon::tomorrow();
        $reminders = $scope(ClinicAppointment::forBusiness($business_id)->where('status', 'confirmed'))
            ->whereDate('date', $tomorrow->toDateString())->whereNull('reminded_at');
        if (! $access['manage_all']) {
            $reminders->where('doctor_id', (int) $access['own']);
        }
        $reminders_due = $reminders->count();
        $cancelled = $scope(ClinicAppointment::forBusiness($business_id)->whereDate('date', $date->toDateString()))
            ->whereIn('status', ['cancelled', 'declined'])->with(['patient', 'doctor'])->orderBy('start_time')->get();

        $settings = ClinicSettings::all($business_id);

        return view('clinic.appointments.index', compact('date', 'columns', 'all_doctors', 'with_hours', 'doctor_id', 'doctor_param',
            'requests', 'reminders_due', 'tomorrow', 'cancelled', 'access', 'settings'));
    }

    /** The coming days' bookings as a list, patients' requests first. */
    public function upcoming(Request $request)
    {
        $access = $this->authorizeView();
        $business_id = $request->session()->get('user.business_id');
        $all_doctors = ClinicQueue::doctors($business_id);
        [$doctor_id, $doctor_param] = $this->doctorFilter($request, $access, $all_doctors);
        $days = in_array((int) $request->input('days'), [7, 14, 30]) ? (int) $request->input('days') : 14;
        $today = Carbon::today();
        $to = $today->copy()->addDays($days - 1);

        $base = ClinicAppointment::forBusiness($business_id)->open()->whereDate('date', '>=', $today->toDateString());
        if ($doctor_id) {
            $base->where('doctor_id', $doctor_id);
        }
        $requests = (clone $base)->where('status', 'requested')
            ->with(['patient.patientDetails', 'doctor', 'whatsappMessages'])->orderBy('date')->orderBy('start_time')->get();
        $booked = (clone $base)->where('status', 'confirmed')->whereDate('date', '<=', $to->toDateString())
            ->with(['patient.patientDetails', 'doctor', 'whatsappMessages'])->orderBy('date')->orderBy('start_time')->get();
        $by_day = $booked->groupBy(fn ($a) => $a->date->toDateString());

        // days off in the range, to flag bookings that fall on one
        $offs = ClinicDoctorDayOff::forBusiness($business_id)->whereDate('date', '>=', $today->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->when($doctor_id, fn ($q) => $q->where('user_id', $doctor_id))->get()
            ->map(fn ($o) => $o->user_id.'|'.Carbon::parse($o->date)->toDateString())->all();

        return view('clinic.appointments.upcoming', compact('access', 'all_doctors', 'doctor_id', 'doctor_param',
            'days', 'today', 'to', 'requests', 'by_day', 'booked', 'offs'));
    }

    /** Free slots of a doctor on a day, for moving a booking (JSON). */
    public function freeSlots(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $doctor_id = (int) $request->input('doctor_id');
        $this->authorizeFor($doctor_id);
        try {
            $date = Carbon::parse($request->input('date'))->startOfDay();
        } catch (\Exception $e) {
            return ['off' => false, 'slots' => []];
        }
        if ($date->lt(Carbon::today()) || ! isset(ClinicQueue::doctors($business_id)[$doctor_id])) {
            return ['off' => false, 'slots' => []];
        }
        $slots = collect(ClinicAppointments::slots($business_id, $doctor_id, $date))->where('state', 'free')
            ->map(fn ($s) => ['start' => $s['start'], 'end' => $s['end']])->values();

        return ['off' => ClinicAppointments::isDayOff($business_id, $doctor_id, $date), 'slots' => $slots];
    }

    /** Reception (or the doctor, on their own column) books a free slot, confirmed at once. */
    public function store(Request $request)
    {
        $this->authorizeFor((int) $request->input('doctor_id'));
        $business_id = $request->session()->get('user.business_id');
        [$appt, $error] = ClinicAppointments::book($business_id, $request->input('contact_id'), $request->input('doctor_id'),
            $request->input('date'), (string) $request->input('start'), 'reception', $request->session()->get('user.id'),
            $request->input('reason'), $request->input('notes'));

        $back = $this->diary($request->input('date'), $request->input('doctor_filter'));
        if ($error) {
            return $back->with('status', ['success' => false, 'msg' => $error]);
        }
        app(\App\Utils\Util::class)->activityLog($appt, 'appointment_booked', null, ['patient' => optional($appt->patient)->name, 'date' => $appt->date->toDateString(), 'time' => $appt->start]);

        return $back->with('status', ['success' => true, 'msg' => __('clinic.appt_booked', ['time' => $appt->start])])
            ->with($this->whatsappFlash($appt, 'confirmation'));
    }

    /**
     * After a booking event: the automatic WhatsApp message when the clinic sends
     * them (and the patient agreed), otherwise - or when it failed - the link
     * reception opens by hand.
     */
    private function whatsappFlash(ClinicAppointment $appt, $kind)
    {
        $appt->load(['patient.patientDetails', 'doctor']);
        $sent = \App\Utils\ClinicWhatsApp::notify($appt, $kind, auth()->id());
        if ($sent && $sent->status != 'failed') {
            return ['appt_whatsapp_auto' => true];
        }
        $flash = ['appt_whatsapp' => ClinicAppointments::whatsappFor($appt, $kind == 'moved')];
        if ($sent) {
            $flash['appt_whatsapp_error'] = $sent->error_message;
        }

        return $flash;
    }

    /** Accepts a patient's request from the portal. */
    public function confirm(Request $request, $id)
    {
        $appt = $this->find($id);
        $this->authorizeFor($appt->doctor_id);
        if ($appt->status != 'requested') {
            return $this->back($request, $appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_not_requested')]);
        }
        $appt->status = 'confirmed';
        $appt->confirmed_at = now();
        $appt->confirmed_by = $request->session()->get('user.id');
        $appt->save();

        return $this->back($request, $appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_confirmed')])
            ->with($this->whatsappFlash($appt, 'confirmation'));
    }

    public function decline(Request $request, $id)
    {
        $appt = $this->find($id);
        $this->authorizeFor($appt->doctor_id);
        if ($appt->status != 'requested') {
            return $this->back($request, $appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_not_requested')]);
        }
        ClinicAppointments::release($appt, 'declined', $request->session()->get('user.id'), $request->input('reason'));

        return $this->back($request, $appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_declined')]);
    }

    public function cancel(Request $request, $id)
    {
        $appt = $this->find($id);
        $this->authorizeFor($appt->doctor_id);
        if (! in_array($appt->status, ClinicAppointment::OPEN)) {
            return $this->back($request, $appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_not_open')]);
        }
        ClinicAppointments::release($appt, 'cancelled', $request->session()->get('user.id'), $request->input('reason'));

        return $this->back($request, $appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_cancelled')]);
    }

    /** Moves a booking to another free slot (another day, time or doctor). */
    public function move(Request $request, $id)
    {
        $appt = $this->find($id);
        $to_doctor = $request->filled('doctor_id') ? (int) $request->input('doctor_id') : (int) $appt->doctor_id;
        $this->authorizeFor($appt->doctor_id);
        $this->authorizeFor($to_doctor);

        [$moved, $error, $from] = ClinicAppointments::move($appt, $to_doctor, $request->input('date'), (string) $request->input('start'), $request->session()->get('user.id'));
        if ($error) {
            return $this->back($request, $appt->date)->with('status', ['success' => false, 'msg' => $error]);
        }
        $util = app(\App\Utils\Util::class);
        $util->activityLog($moved, 'appointment_moved', null, ['patient' => optional($moved->patient)->name,
            'from' => $from['date'].' '.$from['start'], 'to' => $moved->date->toDateString().' '.$moved->start]);

        return $this->back($request, $moved->date)
            ->with('status', ['success' => true, 'msg' => __('clinic.appt_moved', ['date' => $util->format_date($moved->date->toDateString()), 'time' => $moved->start])])
            ->with($this->whatsappFlash($moved, 'moved'));
    }

    /** The patient did not come (only once the slot has started). */
    public function noShow(Request $request, $id)
    {
        $appt = $this->find($id);
        $this->authorizeFor($appt->doctor_id);
        if ($appt->status != 'confirmed' || $appt->startsAt()->isFuture()) {
            return $this->back($request, $appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_cannot_no_show')]);
        }
        $appt->status = 'no_show';
        $appt->save();

        return $this->back($request, $appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_marked_no_show')]);
    }

    /** The WhatsApp reminder was opened (AJAX). */
    public function reminded(Request $request, $id)
    {
        $appt = $this->find($id);
        $this->authorizeFor($appt->doctor_id);
        $appt->reminded_at = now();
        $appt->save();

        return ['success' => true];
    }

    /* ---------------- doctors' hours ---------------- */

    public function hours(Request $request)
    {
        $access = $this->authorizeSchedule();
        $business_id = $request->session()->get('user.business_id');
        $doctors = array_filter(ClinicQueue::doctors($business_id), fn ($id) => ClinicAppointments::maySchedule($access, $id), ARRAY_FILTER_USE_KEY);
        $hours = ClinicDoctorHour::forBusiness($business_id)->orderBy('weekday')->orderBy('start_time')->get()->groupBy('user_id');
        $days_off = ClinicDoctorDayOff::forBusiness($business_id)->whereDate('date', '>=', Carbon::today()->toDateString())
            ->orderBy('date')->get()->groupBy('user_id');

        return view('clinic.appointments.hours', compact('doctors', 'hours', 'days_off', 'access'));
    }

    public function storeHours(Request $request)
    {
        $doctor_id = (int) $request->input('user_id');
        $this->authorizeSchedule($doctor_id);
        $business_id = $request->session()->get('user.business_id');
        $doctors = ClinicQueue::doctors($business_id);
        $back = redirect()->action([self::class, 'hours']);

        $days = array_values(array_intersect(array_map('intval', (array) $request->input('weekdays', [])), [1, 2, 3, 4, 5, 6, 7]));
        $start = $request->input('start_time');
        $end = $request->input('end_time');
        $step = (int) $request->input('slot_minutes', 20);
        if (! isset($doctors[$doctor_id]) || empty($days)
            || ! preg_match('/^\d{2}:\d{2}$/', (string) $start) || ! preg_match('/^\d{2}:\d{2}$/', (string) $end)
            || $end <= $start || $step < 5 || $step > 240) {
            return $back->withInput()->with('status', ['success' => false, 'msg' => __('clinic.appt_hours_invalid')]);
        }
        foreach ($days as $d) {
            $overlap = ClinicDoctorHour::forBusiness($business_id)->where('user_id', $doctor_id)->where('weekday', $d)
                ->where('start_time', '<', $end.':00')->where('end_time', '>', $start.':00')->exists();
            if ($overlap) {
                return $back->withInput()->with('status', ['success' => false, 'msg' => __('clinic.appt_hours_overlap', ['day' => __('clinic.weekday_'.$d)])]);
            }
        }
        foreach ($days as $d) {
            ClinicDoctorHour::create(['business_id' => $business_id, 'user_id' => $doctor_id, 'weekday' => $d,
                'start_time' => $start.':00', 'end_time' => $end.':00', 'slot_minutes' => $step]);
        }

        return $back->with('status', ['success' => true, 'msg' => __('clinic.appt_hours_saved')]);
    }

    public function deleteHours(Request $request, $id)
    {
        $block = ClinicDoctorHour::forBusiness($request->session()->get('user.business_id'))->findOrFail($id);
        $this->authorizeSchedule($block->user_id);
        $block->delete();

        return redirect()->action([self::class, 'hours'])->with('status', ['success' => true, 'msg' => __('clinic.appt_hours_removed')]);
    }

    public function storeDayOff(Request $request)
    {
        $doctor_id = (int) $request->input('user_id');
        $this->authorizeSchedule($doctor_id);
        $business_id = $request->session()->get('user.business_id');
        $back = redirect()->action([self::class, 'hours']);
        try {
            $from = Carbon::parse($request->input('date'))->startOfDay();
            $to = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->startOfDay() : $from->copy();
        } catch (\Exception $e) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.appt_day_off_invalid')]);
        }
        if (! isset(ClinicQueue::doctors($business_id)[$doctor_id]) || $from->lt(Carbon::today()) || $to->lt($from) || $from->diffInDays($to) > 60) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.appt_day_off_invalid')]);
        }

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            ClinicDoctorDayOff::firstOrCreate(['user_id' => $doctor_id, 'date' => $d->toDateString()],
                ['business_id' => $business_id, 'reason' => mb_substr(trim((string) $request->input('reason')), 0, 191) ?: null]);
        }
        // patients already booked on those days: listed so they can be moved or cancelled
        $clash = ClinicAppointment::forBusiness($business_id)->open()->where('doctor_id', $doctor_id)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->with('patient')->orderBy('date')->orderBy('start_time')->get();
        $msg = __('clinic.appt_day_off_saved');
        if ($clash->isNotEmpty()) {
            $msg .= ' '.trans_choice('clinic.appt_day_off_has_bookings', $clash->count(), ['n' => $clash->count()]);
            $back->with('dayoff_clash', ['doctor_id' => $doctor_id, 'rows' => $clash->map(fn ($a) => [
                'date' => $a->date->toDateString(), 'start' => $a->start, 'patient' => optional($a->patient)->name,
                'mobile' => optional($a->patient)->mobile, 'status' => $a->status,
            ])->all()]);
        }

        return $back->with('status', ['success' => true, 'msg' => $msg]);
    }

    public function deleteDayOff(Request $request, $id)
    {
        $off = ClinicDoctorDayOff::forBusiness($request->session()->get('user.business_id'))->findOrFail($id);
        $this->authorizeSchedule($off->user_id);
        $off->delete();

        return redirect()->action([self::class, 'hours'])->with('status', ['success' => true, 'msg' => __('clinic.appt_day_off_removed')]);
    }

    private function find($id)
    {
        $this->hospitalOn();

        return ClinicAppointment::forBusiness(request()->session()->get('user.business_id'))->findOrFail($id);
    }

    /** Back to the screen the action came from: the upcoming list, or the diary on that day. */
    private function back(Request $request, $date)
    {
        if ($request->input('from') == 'upcoming') {
            return redirect()->action([self::class, 'upcoming'], array_filter([
                'doctor_id' => $request->input('doctor_filter'), 'days' => $request->input('days'),
            ]));
        }

        return $this->diary($date, $request->input('doctor_filter'));
    }

    private function diary($date, $doctor_id = null)
    {
        try {
            $d = Carbon::parse($date)->toDateString();
        } catch (\Exception $e) {
            $d = Carbon::today()->toDateString();
        }

        return redirect()->action([self::class, 'index'], array_filter(['date' => $d, 'doctor_id' => $doctor_id]));
    }
}
