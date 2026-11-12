<?php

namespace App\Utils;

use App\ClinicAppointment;
use App\ClinicDoctorDayOff;
use App\ClinicDoctorHour;
use App\Contact;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

/**
 * PosInnova clinic: appointments.
 *
 * Each doctor has weekly hours (ClinicDoctorHour) cut into fixed slots; a day
 * off removes the day. A slot is held by at most one open appointment, which
 * the database enforces (clinic_appointments.slot_key is unique and cleared
 * when a booking is cancelled or declined). Patients ask from the portal
 * (status requested) and reception confirms; reception can book directly
 * (confirmed). Nothing is charged at booking: the check-in bills as usual.
 * Reminders are WhatsApp links the receptionist opens (no SMS gateway).
 */
class ClinicAppointments
{
    const MAX_OPEN_PER_PATIENT = 3;

    public static function enabled($business_id)
    {
        return (bool) ClinicSettings::get($business_id, 'appointments_enabled');
    }

    public static function portalBooking($business_id)
    {
        return self::enabled($business_id) && (bool) ClinicSettings::get($business_id, 'appointments_portal');
    }

    /**
     * Doctors who have consulting hours: [user_id => name].
     */
    public static function doctorsWithHours($business_id)
    {
        $with_hours = ClinicDoctorHour::forBusiness($business_id)->distinct()->pluck('user_id')->all();

        return array_intersect_key(ClinicQueue::doctors($business_id), array_flip($with_hours));
    }

    /**
     * What a user may do with appointments:
     *  - see_all / manage_all: appointment.view|manage, every doctor's column;
     *  - own: the user's id when they are a doctor who may see and act on their
     *    own appointments (appointment.manage_own), else null;
     *  - see: may open the diary at all;
     *  - mine_first: a doctor who does not work the front desk starts on their
     *    own column (the same rule as their home page counts);
     *  - schedule_all / schedule_own: hours and days off of every doctor, or of
     *    themselves only (clinic_schedule.manage_own).
     * Admins pass every permission check but are doctors only when ticked in
     * Clinic settings.
     */
    public static function access($business_id, $user)
    {
        // once per request (the doctor list checks every user's role)
        $key = 'clinic_appt_access_'.$business_id.'_'.$user->id;
        if (request()->attributes->has($key)) {
            return request()->attributes->get($key);
        }
        $is_doctor = isset(ClinicQueue::doctors($business_id)[$user->id]);
        $see_all = $user->can('appointment.view') || $user->can('appointment.manage');
        $own = $is_doctor && ($user->can('appointment.manage_own') || $user->can('appointment.manage')) ? (int) $user->id : null;

        $access = [
            'is_doctor' => $is_doctor,
            'see_all' => $see_all,
            'manage_all' => $user->can('appointment.manage'),
            'own' => $own,
            'see' => $see_all || $own !== null,
            'mine_first' => $is_doctor && ! $user->can('visit.create'),
            'schedule_all' => $user->can('clinic_schedule.manage'),
            'schedule_own' => $is_doctor && $user->can('clinic_schedule.manage_own') ? (int) $user->id : null,
        ];
        request()->attributes->set($key, $access);

        return $access;
    }

    /** May the user book / confirm / cancel / move appointments of this doctor? */
    public static function mayManage(array $access, $doctor_id)
    {
        return $access['manage_all'] || ($access['own'] !== null && (int) $doctor_id === $access['own']);
    }

    /** May the user change this doctor's hours and days off? */
    public static function maySchedule(array $access, $doctor_id)
    {
        return $access['schedule_all'] || ($access['schedule_own'] !== null && (int) $doctor_id === $access['schedule_own']);
    }

    public static function slotKey($doctor_id, $date, $start)
    {
        return $doctor_id.'-'.Carbon::parse($date)->toDateString().'-'.substr($start, 0, 5);
    }

    public static function isDayOff($business_id, $doctor_id, $date)
    {
        return ClinicDoctorDayOff::forBusiness($business_id)->where('user_id', $doctor_id)
            ->whereDate('date', Carbon::parse($date)->toDateString())->exists();
    }

    /**
     * The doctor's slots on a date, in order:
     * [start 'H:i', end 'H:i', state free|taken|past, appointment (when taken)].
     * Empty on a day off or a day without hours. $notice_hours makes slots
     * starting sooner than that count as past (the portal's minimum notice).
     */
    public static function slots($business_id, $doctor_id, $date, $notice_hours = 0)
    {
        $date = Carbon::parse($date)->startOfDay();
        $held = ClinicAppointment::forBusiness($business_id)->where('doctor_id', $doctor_id)
            ->whereDate('date', $date->toDateString())->whereNotNull('slot_key')
            ->with(['patient.patientDetails', 'whatsappMessages'])->get()
            ->keyBy(fn ($a) => $a->start);

        // a day off (or a day without hours) offers nothing, but bookings already
        // made for it still show so reception can move or cancel them
        $blocks = self::isDayOff($business_id, $doctor_id, $date) ? collect()
            : ClinicDoctorHour::forBusiness($business_id)->where('user_id', $doctor_id)
                ->where('weekday', $date->dayOfWeekIso)->orderBy('start_time')->get();
        $cutoff = Carbon::now()->addHours((int) $notice_hours);

        $out = [];
        foreach ($blocks as $b) {
            $step = max(5, (int) $b->slot_minutes);
            $t = Carbon::parse($date->toDateString().' '.$b->start_time);
            $end = Carbon::parse($date->toDateString().' '.$b->end_time);
            while ($t->copy()->addMinutes($step)->lte($end)) {
                $start = $t->format('H:i');
                if (! isset($out[$start])) {
                    $appt = $held[$start] ?? null;
                    $out[$start] = [
                        'start' => $start,
                        'end' => $t->copy()->addMinutes($step)->format('H:i'),
                        'state' => $appt ? 'taken' : ($t->lt($cutoff) ? 'past' : 'free'),
                        'appointment' => $appt,
                    ];
                }
                $t->addMinutes($step);
            }
        }
        // a booking outside today's hours (hours changed after booking) still shows
        foreach ($held as $start => $appt) {
            if (! isset($out[$start])) {
                $out[$start] = ['start' => $start, 'end' => $appt->end, 'state' => 'taken', 'appointment' => $appt];
            }
        }
        ksort($out);

        return array_values($out);
    }

    /**
     * Books a slot.
     *
     * @return array [ClinicAppointment|null, error message|null]
     */
    public static function book($business_id, $contact_id, $doctor_id, $date, $start, $source, $user_id = null, $reason = null, $notes = null, $location_id = null)
    {
        if (! self::enabled($business_id)) {
            return [null, __('clinic.appt_disabled')];
        }
        $patient = Contact::where('business_id', $business_id)->onlyPatients()->find($contact_id);
        if (! $patient) {
            return [null, __('clinic.appt_choose_patient')];
        }
        if (! isset(ClinicQueue::doctors($business_id)[(int) $doctor_id])) {
            return [null, __('clinic.choose_doctor')];
        }
        try {
            $date = Carbon::parse($date)->startOfDay();
        } catch (\Exception $e) {
            return [null, __('clinic.appt_bad_slot')];
        }
        $days_ahead = (int) ClinicSettings::get($business_id, 'appointments_days_ahead');
        if ($date->lt(Carbon::today()) || ($source == 'portal' && $date->gt(Carbon::today()->addDays($days_ahead)))) {
            return [null, __('clinic.appt_bad_date', ['days' => $days_ahead])];
        }

        $notice = $source == 'portal' ? (int) ClinicSettings::get($business_id, 'appointments_min_notice_hours') : 0;
        $slot = collect(self::slots($business_id, $doctor_id, $date, $notice))->firstWhere('start', substr((string) $start, 0, 5));
        if (! $slot) {
            return [null, __('clinic.appt_bad_slot')];
        }
        if ($slot['state'] == 'taken') {
            return [null, __('clinic.appt_slot_taken')];
        }
        if ($slot['state'] == 'past') {
            return [null, __('clinic.appt_slot_past')];
        }

        $open = ClinicAppointment::forBusiness($business_id)->open()->where('contact_id', $patient->id)->whereDate('date', '>=', Carbon::today()->toDateString());
        if ((clone $open)->whereDate('date', $date->toDateString())->exists()) {
            // the portal speaks to the patient, reception about the patient
            return [null, __($source == 'portal' ? 'clinic.appt_portal_same_day' : 'clinic.appt_same_day')];
        }
        if ($source == 'portal' && (clone $open)->count() >= self::MAX_OPEN_PER_PATIENT) {
            return [null, __('clinic.appt_too_many', ['n' => self::MAX_OPEN_PER_PATIENT])];
        }

        $confirmed = $source != 'portal';
        try {
            $appt = ClinicAppointment::create([
                'business_id' => $business_id,
                'contact_id' => $patient->id,
                'doctor_id' => (int) $doctor_id,
                'location_id' => $location_id ?: \App\BusinessLocation::where('business_id', $business_id)->value('id'),
                'date' => $date->toDateString(),
                'start_time' => $slot['start'].':00',
                'end_time' => $slot['end'].':00',
                'status' => $confirmed ? 'confirmed' : 'requested',
                'source' => $source,
                'slot_key' => self::slotKey($doctor_id, $date, $slot['start']),
                'reason' => $reason ? mb_substr(trim($reason), 0, 500) : null,
                'notes' => $notes ? mb_substr(trim($notes), 0, 500) : null,
                'created_by' => $user_id,
                'confirmed_at' => $confirmed ? now() : null,
                'confirmed_by' => $confirmed ? $user_id : null,
            ]);
        } catch (QueryException $e) {
            // someone else took the slot a moment ago (unique slot_key)
            if (($e->errorInfo[0] ?? '') == '23000') {
                return [null, __('clinic.appt_slot_taken')];
            }
            throw $e;
        }

        return [$appt, null];
    }

    /**
     * Moves an open appointment to another free slot (same or another doctor).
     * Staff chose the new time, so a patient's request becomes confirmed, and
     * the reminder has to be sent again.
     *
     * @return array [ClinicAppointment|null, error message|null, ['doctor_id', 'date', 'start'] it came from]
     */
    public static function move(ClinicAppointment $appt, $doctor_id, $date, $start, $user_id = null)
    {
        $business_id = $appt->business_id;
        if (! self::enabled($business_id)) {
            return [null, __('clinic.appt_disabled'), null];
        }
        if (! in_array($appt->status, ClinicAppointment::OPEN)) {
            return [null, __('clinic.appt_not_open'), null];
        }
        if (! isset(ClinicQueue::doctors($business_id)[(int) $doctor_id])) {
            return [null, __('clinic.choose_doctor'), null];
        }
        try {
            $date = Carbon::parse($date)->startOfDay();
        } catch (\Exception $e) {
            return [null, __('clinic.appt_bad_slot'), null];
        }
        if ($date->lt(Carbon::today())) {
            return [null, __('clinic.appt_slot_past'), null];
        }
        $start = substr((string) $start, 0, 5);
        $slot = collect(self::slots($business_id, $doctor_id, $date))->firstWhere('start', $start);
        if (! $slot) {
            return [null, __('clinic.appt_bad_slot'), null];
        }
        if ($slot['state'] == 'taken') {
            $same = $slot['appointment'] && $slot['appointment']->id == $appt->id;

            return [null, __($same ? 'clinic.appt_move_same' : 'clinic.appt_slot_taken'), null];
        }
        if ($slot['state'] == 'past') {
            return [null, __('clinic.appt_slot_past'), null];
        }
        $other = ClinicAppointment::forBusiness($business_id)->open()->where('contact_id', $appt->contact_id)
            ->where('id', '!=', $appt->id)->whereDate('date', $date->toDateString())->exists();
        if ($other) {
            return [null, __('clinic.appt_same_day'), null];
        }

        $from = ['doctor_id' => $appt->doctor_id, 'date' => $appt->date->toDateString(), 'start' => $appt->start];
        $appt->doctor_id = (int) $doctor_id;
        $appt->date = $date->toDateString();
        $appt->start_time = $slot['start'].':00';
        $appt->end_time = $slot['end'].':00';
        $appt->slot_key = self::slotKey($doctor_id, $date, $slot['start']);
        if ($appt->status == 'requested') {
            $appt->status = 'confirmed';
            $appt->confirmed_at = now();
            $appt->confirmed_by = $user_id;
        }
        $appt->reminded_at = null;
        try {
            $appt->save();
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') == '23000') {
                return [null, __('clinic.appt_slot_taken'), null];
            }
            throw $e;
        }

        return [$appt, null, $from];
    }

    /**
     * Frees the slot: cancelled (by staff, or by the patient when $user_id is
     * null) or declined.
     */
    public static function release(ClinicAppointment $appt, $status, $user_id = null, $reason = null)
    {
        $appt->status = $status;
        $appt->slot_key = null;
        $appt->cancelled_at = now();
        $appt->cancelled_by = $user_id;
        $appt->cancel_reason = $reason ? mb_substr(trim($reason), 0, 191) : null;
        $appt->save();

        return $appt;
    }

    /**
     * A wa.me link that opens WhatsApp with the message ready to send, or
     * null without a usable phone number. Local numbers get the clinic's
     * country code.
     */
    public static function whatsappLink($business_id, $phone, $text)
    {
        // the same number the automatic messages use
        $digits = ClinicWhatsApp::waNumber($business_id, $phone);

        return $digits ? 'https://wa.me/'.$digits.'?text='.rawurlencode($text) : null;
    }

    /**
     * The WhatsApp message for an appointment: a confirmation that doubles as
     * the reminder.
     */
    public static function message(ClinicAppointment $appt, $moved = false)
    {
        $util = new Util();

        return __($moved ? 'clinic.appt_whatsapp_moved_text' : 'clinic.appt_whatsapp_text', [
            'name' => optional($appt->patient)->first_name ?: optional($appt->patient)->name,
            'clinic' => \App\Business::where('id', $appt->business_id)->value('name'),
            'doctor' => trim(optional($appt->doctor)->user_full_name),
            'date' => $util->format_date($appt->date->toDateString()),
            'time' => $appt->start,
        ]);
    }

    public static function whatsappFor(ClinicAppointment $appt, $moved = false)
    {
        return self::whatsappLink($appt->business_id, optional($appt->patient)->mobile, self::message($appt, $moved));
    }
}
