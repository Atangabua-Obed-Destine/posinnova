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
 * Appointments (clinic): the day's diary per doctor, booking, confirming
 * patients' requests from the portal, reminders by WhatsApp link, and the
 * doctors' consulting hours.
 *
 * appointment.view sees the diary; appointment.manage books, confirms,
 * cancels and sends reminders; clinic_schedule.manage sets doctors' hours
 * and days off. Checking a booked patient in goes through the normal
 * check-in screen (visits/create?appointment_id=...).
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

    private function authorizeView()
    {
        $this->hospitalOn();
        if (! auth()->user()->can('appointment.view') && ! auth()->user()->can('appointment.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function authorizeManage()
    {
        $this->hospitalOn();
        if (! auth()->user()->can('appointment.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    private function authorizeSchedule()
    {
        $this->hospitalOn();
        if (! auth()->user()->can('clinic_schedule.manage')) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index(Request $request)
    {
        $this->authorizeView();
        $business_id = $request->session()->get('user.business_id');

        try {
            $date = Carbon::parse($request->input('date', Carbon::today()->toDateString()))->startOfDay();
        } catch (\Exception $e) {
            $date = Carbon::today();
        }
        $all_doctors = ClinicQueue::doctors($business_id);
        $with_hours = ClinicAppointments::doctorsWithHours($business_id);
        $doctor_id = $request->filled('doctor_id') && isset($all_doctors[(int) $request->input('doctor_id')]) ? (int) $request->input('doctor_id') : null;

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

        $requests = ClinicAppointment::forBusiness($business_id)->where('status', 'requested')
            ->whereDate('date', '>=', Carbon::today()->toDateString())
            ->with(['patient.patientDetails', 'doctor'])->orderBy('date')->orderBy('start_time')->get();
        $tomorrow = Carbon::tomorrow();
        $reminders_due = ClinicAppointment::forBusiness($business_id)->where('status', 'confirmed')
            ->whereDate('date', $tomorrow->toDateString())->whereNull('reminded_at')->count();
        $cancelled = ClinicAppointment::forBusiness($business_id)->whereDate('date', $date->toDateString())
            ->whereIn('status', ['cancelled', 'declined'])->with(['patient', 'doctor'])->orderBy('start_time')->get();

        $can_manage = auth()->user()->can('appointment.manage');
        $settings = ClinicSettings::all($business_id);

        return view('clinic.appointments.index', compact('date', 'columns', 'all_doctors', 'with_hours', 'doctor_id',
            'requests', 'reminders_due', 'tomorrow', 'cancelled', 'can_manage', 'settings'));
    }

    /** Reception books a free slot (confirmed at once). */
    public function store(Request $request)
    {
        $this->authorizeManage();
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
            ->with('appt_whatsapp', ClinicAppointments::whatsappFor($appt->load(['patient', 'doctor'])));
    }

    /** Accepts a patient's request from the portal. */
    public function confirm(Request $request, $id)
    {
        $this->authorizeManage();
        $appt = $this->find($id);
        if ($appt->status != 'requested') {
            return $this->diary($appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_not_requested')]);
        }
        $appt->status = 'confirmed';
        $appt->confirmed_at = now();
        $appt->confirmed_by = $request->session()->get('user.id');
        $appt->save();

        return $this->diary($appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_confirmed')])
            ->with('appt_whatsapp', ClinicAppointments::whatsappFor($appt->load(['patient', 'doctor'])));
    }

    public function decline(Request $request, $id)
    {
        $this->authorizeManage();
        $appt = $this->find($id);
        if ($appt->status != 'requested') {
            return $this->diary($appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_not_requested')]);
        }
        ClinicAppointments::release($appt, 'declined', $request->session()->get('user.id'), $request->input('reason'));

        return $this->diary($appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_declined')]);
    }

    public function cancel(Request $request, $id)
    {
        $this->authorizeManage();
        $appt = $this->find($id);
        if (! in_array($appt->status, ClinicAppointment::OPEN)) {
            return $this->diary($appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_not_open')]);
        }
        ClinicAppointments::release($appt, 'cancelled', $request->session()->get('user.id'), $request->input('reason'));

        return $this->diary($appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_cancelled')]);
    }

    /** The patient did not come (only once the slot has started). */
    public function noShow(Request $request, $id)
    {
        $this->authorizeManage();
        $appt = $this->find($id);
        if ($appt->status != 'confirmed' || $appt->startsAt()->isFuture()) {
            return $this->diary($appt->date)->with('status', ['success' => false, 'msg' => __('clinic.appt_cannot_no_show')]);
        }
        $appt->status = 'no_show';
        $appt->save();

        return $this->diary($appt->date)->with('status', ['success' => true, 'msg' => __('clinic.appt_marked_no_show')]);
    }

    /** The receptionist opened the WhatsApp reminder (AJAX). */
    public function reminded(Request $request, $id)
    {
        $this->authorizeManage();
        $appt = $this->find($id);
        $appt->reminded_at = now();
        $appt->save();

        return ['success' => true];
    }

    /* ---------------- doctors' hours ---------------- */

    public function hours(Request $request)
    {
        $this->authorizeSchedule();
        $business_id = $request->session()->get('user.business_id');
        $doctors = ClinicQueue::doctors($business_id);
        $hours = ClinicDoctorHour::forBusiness($business_id)->orderBy('weekday')->orderBy('start_time')->get()->groupBy('user_id');
        $days_off = ClinicDoctorDayOff::forBusiness($business_id)->whereDate('date', '>=', Carbon::today()->toDateString())
            ->orderBy('date')->get()->groupBy('user_id');

        return view('clinic.appointments.hours', compact('doctors', 'hours', 'days_off'));
    }

    public function storeHours(Request $request)
    {
        $this->authorizeSchedule();
        $business_id = $request->session()->get('user.business_id');
        $doctors = ClinicQueue::doctors($business_id);
        $back = redirect()->action([self::class, 'hours']);

        $doctor_id = (int) $request->input('user_id');
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
        $this->authorizeSchedule();
        ClinicDoctorHour::forBusiness($request->session()->get('user.business_id'))->where('id', $id)->delete();

        return redirect()->action([self::class, 'hours'])->with('status', ['success' => true, 'msg' => __('clinic.appt_hours_removed')]);
    }

    public function storeDayOff(Request $request)
    {
        $this->authorizeSchedule();
        $business_id = $request->session()->get('user.business_id');
        $back = redirect()->action([self::class, 'hours']);
        $doctor_id = (int) $request->input('user_id');
        try {
            $from = Carbon::parse($request->input('date'))->startOfDay();
            $to = $request->filled('date_to') ? Carbon::parse($request->input('date_to'))->startOfDay() : $from->copy();
        } catch (\Exception $e) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.appt_day_off_invalid')]);
        }
        if (! isset(ClinicQueue::doctors($business_id)[$doctor_id]) || $from->lt(Carbon::today()) || $to->lt($from) || $from->diffInDays($to) > 60) {
            return $back->with('status', ['success' => false, 'msg' => __('clinic.appt_day_off_invalid')]);
        }

        $booked = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            ClinicDoctorDayOff::firstOrCreate(['user_id' => $doctor_id, 'date' => $d->toDateString()],
                ['business_id' => $business_id, 'reason' => mb_substr(trim((string) $request->input('reason')), 0, 191) ?: null]);
            $booked += ClinicAppointment::forBusiness($business_id)->open()->where('doctor_id', $doctor_id)->whereDate('date', $d->toDateString())->count();
        }
        $msg = __('clinic.appt_day_off_saved');
        if ($booked > 0) {
            $msg .= ' '.trans_choice('clinic.appt_day_off_has_bookings', $booked, ['n' => $booked]);
        }

        return $back->with('status', ['success' => true, 'msg' => $msg]);
    }

    public function deleteDayOff(Request $request, $id)
    {
        $this->authorizeSchedule();
        ClinicDoctorDayOff::forBusiness($request->session()->get('user.business_id'))->where('id', $id)->delete();

        return redirect()->action([self::class, 'hours'])->with('status', ['success' => true, 'msg' => __('clinic.appt_day_off_removed')]);
    }

    private function find($id)
    {
        return ClinicAppointment::forBusiness(request()->session()->get('user.business_id'))->findOrFail($id);
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
