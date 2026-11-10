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
            ->with('patient.patientDetails')->get()
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
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if (str_starts_with((string) trim($phone), '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 8) {
            return null;
        }
        $cc = preg_replace('/\D+/', '', (string) ClinicSettings::get($business_id, 'whatsapp_country_code'));
        if ($cc !== '' && ! str_starts_with($digits, $cc) && strlen($digits) <= 10) {
            $digits = $cc.ltrim($digits, '0');
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($text);
    }

    /**
     * The WhatsApp message for an appointment: a confirmation that doubles as
     * the reminder.
     */
    public static function message(ClinicAppointment $appt)
    {
        $util = new Util();

        return __('clinic.appt_whatsapp_text', [
            'name' => optional($appt->patient)->first_name ?: optional($appt->patient)->name,
            'clinic' => \App\Business::where('id', $appt->business_id)->value('name'),
            'doctor' => trim(optional($appt->doctor)->user_full_name),
            'date' => $util->format_date($appt->date->toDateString()),
            'time' => $appt->start,
        ]);
    }

    public static function whatsappFor(ClinicAppointment $appt)
    {
        return self::whatsappLink($appt->business_id, optional($appt->patient)->mobile, self::message($appt));
    }
}
