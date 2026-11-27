<?php

namespace App\Utils;

use App\ClinicAppointment;
use App\ClinicWaSession;
use App\ClinicWhatsappAccount;
use App\ClinicWhatsappMessage;
use App\Contact;
use App\PatientDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The clinic WhatsApp booking bot: a guided, button/list-driven chat that lets a
 * patient book an appointment over the clinic's WhatsApp number. It never books
 * silently — every booking is a *request* (source 'whatsapp') that reception or
 * the doctor approves, exactly like a portal request. The sender's number is
 * Meta-verified, so a first-time patient can book with no login. State lives in
 * clinic_wa_sessions for 30 minutes; nothing of value is kept there.
 *
 * It engages only when switched on (appointments_whatsapp_bot) and only when a
 * patient clearly wants it (a booking keyword, or a chat already in progress) —
 * anything else falls through to the replies inbox for reception, unchanged.
 */
class ClinicWhatsAppBot
{
    const TTL_MINUTES = 30;

    const MAX_REPLIES = 40; // a crude per-conversation throttle against abuse

    /** Is the bot switched on for this account's business? */
    public static function enabled(ClinicWhatsappAccount $acc)
    {
        if (! $acc->enabled) {
            return false;
        }
        $bid = $acc->business_id;
        // enabled_modules may come back already cast to an array, or as JSON text
        $raw = \App\Business::where('id', $bid)->value('enabled_modules');
        $mods = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        return in_array('hospital', $mods, true)
            && ClinicAppointments::enabled($bid)
            && (bool) ClinicSettings::get($bid, 'appointments_whatsapp_bot');
    }

    /**
     * Take one inbound message. Returns true if the bot handled it (so the
     * webhook marks it handled), false to leave it in the inbox for reception.
     */
    public static function handle(ClinicWhatsappAccount $acc, $from, $patient, $text, $payload, $type, ClinicWhatsappMessage $in)
    {
        try {
            $bid = $acc->business_id;
            $session = ClinicWaSession::current($bid, $from);

            // nothing in progress: only a clear booking intent starts the bot
            if (! $session) {
                if (! self::isTrigger($text, $payload)) {
                    return false;
                }
                $session = self::fresh($bid, $from, $patient);

                return self::showMenu($acc, $session);
            }

            if ($session->replies >= self::MAX_REPLIES) {
                return true; // stay quiet; the session will expire
            }

            return self::route($acc, $session, $text, $payload);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp bot error', ['business_id' => $acc->business_id, 'error' => $e->getMessage()]);

            return false; // on any error, leave the message for a human
        }
    }

    /* ------------------------------------------------------------------ routing */

    private static function route(ClinicWhatsappAccount $acc, ClinicWaSession $s, $text, $payload)
    {
        $id = (string) $payload;
        $lang = self::lang($acc, $s);

        // a global "menu"/greeting resets to the main menu at any point
        if (self::isGreeting($text)) {
            return self::showMenu($acc, $s);
        }

        switch ($s->state) {
            case 'menu':
                if ($id === 'BOT:MENU:BOOK') {
                    return self::startBooking($acc, $s);
                }
                if ($id === 'BOT:MENU:APPTS') {
                    return self::showAppointments($acc, $s);
                }
                if ($id === 'BOT:MENU:RECEPTION') {
                    return self::handOff($acc, $s);
                }

                return self::showMenu($acc, $s);

            case 'ask_name':
                $name = trim((string) $text);
                if (mb_strlen($name) < 2) {
                    return self::say($acc, $s, __('clinic.wa_bot_ask_name', [], $lang));
                }
                $contact = self::makePatient($s->business_id, $name, $s->wa_number, $lang);
                $s->contact_id = $contact->id;
                $s->save();

                return self::pickDoctor($acc, $s);

            case 'pick_doctor':
                if (preg_match('/^BOT:DOC:(\d+)$/', $id, $m)) {
                    $doc = (int) $m[1];
                    if (isset(ClinicAppointments::doctorsWithHours($s->business_id)[$doc])) {
                        return self::pickDate($acc, $s, $doc);
                    }
                }

                return self::pickDoctor($acc, $s);

            case 'pick_date':
                if (preg_match('/^BOT:DATE:(\d{8})$/', $id, $m)) {
                    return self::pickSlot($acc, $s, Carbon::createFromFormat('Ymd', $m[1])->toDateString());
                }

                return self::pickDate($acc, $s, (int) ($s->data['doctor_id'] ?? 0));

            case 'pick_slot':
                if ($id === 'BOT:SLOT:OTHER') {
                    return self::pickDate($acc, $s, (int) ($s->data['doctor_id'] ?? 0));
                }
                if (preg_match('/^BOT:SLOT:(\d{2}:\d{2})$/', $id, $m)) {
                    return self::confirm($acc, $s, $m[1]);
                }

                return self::pickSlot($acc, $s, (string) ($s->data['date'] ?? ''));

            case 'confirm':
                if ($id === 'BOT:OK') {
                    return self::book($acc, $s);
                }
                if ($id === 'BOT:CANCEL') {
                    return self::showMenu($acc, $s);
                }

                return self::confirm($acc, $s, (string) ($s->data['start'] ?? ''));

            case 'appts':
                if (preg_match('/^BOT:APPT:(\d+)$/', $id, $m)) {
                    return self::askCancel($acc, $s, (int) $m[1]);
                }

                return self::showMenu($acc, $s);

            case 'cancel_appt':
                if ($id === 'BOT:CANCEL_YES') {
                    return self::doCancel($acc, $s);
                }

                return self::showMenu($acc, $s);
        }

        return self::showMenu($acc, $s);
    }

    /* ------------------------------------------------------------------ steps */

    private static function showMenu(ClinicWhatsappAccount $acc, ClinicWaSession $s)
    {
        $lang = self::lang($acc, $s);
        self::to($s, 'menu');

        return self::buttons($acc, $s, __('clinic.wa_bot_welcome', [], $lang), [
            ['id' => 'BOT:MENU:BOOK', 'title' => __('clinic.wa_bot_menu_book', [], $lang)],
            ['id' => 'BOT:MENU:APPTS', 'title' => __('clinic.wa_bot_menu_appts', [], $lang)],
            ['id' => 'BOT:MENU:RECEPTION', 'title' => __('clinic.wa_bot_menu_reception', [], $lang)],
        ]);
    }

    private static function startBooking(ClinicWhatsappAccount $acc, ClinicWaSession $s)
    {
        $lang = self::lang($acc, $s);
        if (empty(ClinicAppointments::doctorsWithHours($s->business_id))) {
            self::clear($s);

            return self::say($acc, $s, __('clinic.wa_bot_no_doctors', [], $lang), true);
        }
        if (! $s->contact_id) {
            self::to($s, 'ask_name');

            return self::say($acc, $s, __('clinic.wa_bot_ask_name', [], $lang));
        }

        return self::pickDoctor($acc, $s);
    }

    private static function pickDoctor(ClinicWhatsappAccount $acc, ClinicWaSession $s, $note = null)
    {
        $lang = self::lang($acc, $s);
        $doctors = ClinicAppointments::doctorsWithHours($s->business_id);
        if (empty($doctors)) {
            self::clear($s);

            return self::say($acc, $s, __('clinic.wa_bot_no_doctors', [], $lang), true);
        }
        self::note($acc, $s, $note);
        self::to($s, 'pick_doctor');
        $rows = [];
        foreach ($doctors as $id => $name) {
            $rows[] = ['id' => 'BOT:DOC:'.$id, 'title' => $name];
        }

        return self::list($acc, $s, __('clinic.wa_bot_pick_doctor', [], $lang), __('clinic.wa_bot_doctors_btn', [], $lang), $rows);
    }

    private static function pickDate(ClinicWhatsappAccount $acc, ClinicWaSession $s, $doctor_id, $note = null)
    {
        $lang = self::lang($acc, $s);
        $doctors = ClinicAppointments::doctorsWithHours($s->business_id);
        if (! isset($doctors[$doctor_id])) {
            return self::pickDoctor($acc, $s);
        }
        self::note($acc, $s, $note);
        $bid = $s->business_id;
        $days_ahead = max(1, (int) ClinicSettings::get($bid, 'appointments_days_ahead'));
        $notice = (int) ClinicSettings::get($bid, 'appointments_min_notice_hours');
        $rows = [];
        for ($i = 0; $i <= $days_ahead && count($rows) < 10; $i++) {
            $d = Carbon::today()->addDays($i);
            if (ClinicAppointments::isDayOff($bid, $doctor_id, $d) || ! ClinicAppointments::dayHasRoom($bid, $doctor_id, $d)) {
                continue;
            }
            $free = collect(ClinicAppointments::slots($bid, $doctor_id, $d, $notice))->where('state', 'free');
            if ($free->isEmpty()) {
                continue;
            }
            $rows[] = ['id' => 'BOT:DATE:'.$d->format('Ymd'),
                'title' => $d->locale($lang)->isoFormat('ddd D MMM'),
                'description' => __('clinic.wa_bot_slots_free', ['n' => $free->count()], $lang)];
        }
        if (empty($rows)) {
            return self::pickDoctor($acc, $s, __('clinic.wa_bot_no_dates', ['doctor' => $doctors[$doctor_id]], $lang));
        }
        self::to($s, 'pick_date', ['doctor_id' => (int) $doctor_id]);

        return self::list($acc, $s, __('clinic.wa_bot_pick_date', ['doctor' => $doctors[$doctor_id]], $lang),
            __('clinic.wa_bot_dates_btn', [], $lang), $rows);
    }

    private static function pickSlot(ClinicWhatsappAccount $acc, ClinicWaSession $s, $date, $note = null)
    {
        $lang = self::lang($acc, $s);
        $doctor_id = (int) ($s->data['doctor_id'] ?? 0);
        $bid = $s->business_id;
        self::note($acc, $s, $note);
        try {
            $d = Carbon::parse($date)->startOfDay();
        } catch (\Throwable $e) {
            return self::pickDate($acc, $s, $doctor_id);
        }
        $notice = (int) ClinicSettings::get($bid, 'appointments_min_notice_hours');
        $free = collect(ClinicAppointments::slots($bid, $doctor_id, $d, $notice))->where('state', 'free')->take(9);
        if ($free->isEmpty() || ! ClinicAppointments::dayHasRoom($bid, $doctor_id, $d)) {
            return self::pickDate($acc, $s, $doctor_id, __('clinic.wa_bot_no_slots', [], $lang));
        }
        $rows = [];
        foreach ($free as $slot) {
            $rows[] = ['id' => 'BOT:SLOT:'.$slot['start'], 'title' => $slot['start']];
        }
        $rows[] = ['id' => 'BOT:SLOT:OTHER', 'title' => __('clinic.wa_bot_other_day', [], $lang)];
        self::to($s, 'pick_slot', ['doctor_id' => $doctor_id, 'date' => $d->toDateString()]);

        return self::list($acc, $s, __('clinic.wa_bot_pick_slot', ['date' => $d->locale($lang)->isoFormat('ddd D MMM')], $lang),
            __('clinic.wa_bot_slots_btn', [], $lang), $rows);
    }

    private static function confirm(ClinicWhatsappAccount $acc, ClinicWaSession $s, $start)
    {
        $lang = self::lang($acc, $s);
        $doctor_id = (int) ($s->data['doctor_id'] ?? 0);
        $date = (string) ($s->data['date'] ?? '');
        if (! preg_match('/^\d{2}:\d{2}$/', (string) $start) || ! $date) {
            return self::pickSlot($acc, $s, $date);
        }
        $doctors = ClinicAppointments::doctorsWithHours($s->business_id);
        self::to($s, 'confirm', ['doctor_id' => $doctor_id, 'date' => $date, 'start' => $start]);
        $msg = __('clinic.wa_bot_confirm', [
            'doctor' => $doctors[$doctor_id] ?? '',
            'date' => Carbon::parse($date)->locale($lang)->isoFormat('ddd D MMM'),
            'time' => $start,
        ], $lang);

        return self::buttons($acc, $s, $msg, [
            ['id' => 'BOT:OK', 'title' => __('clinic.wa_bot_confirm_yes', [], $lang)],
            ['id' => 'BOT:CANCEL', 'title' => __('clinic.wa_bot_confirm_no', [], $lang)],
        ]);
    }

    private static function book(ClinicWhatsappAccount $acc, ClinicWaSession $s)
    {
        $lang = self::lang($acc, $s);
        $doctor_id = (int) ($s->data['doctor_id'] ?? 0);
        $date = (string) ($s->data['date'] ?? '');
        $start = (string) ($s->data['start'] ?? '');
        if (! $s->contact_id) {
            return self::startBooking($acc, $s);
        }
        [$appt, $error] = ClinicAppointments::book($s->business_id, $s->contact_id, $doctor_id, $date, $start, 'whatsapp');
        if ($error) {
            // could not book (slot just taken, cap reached…): explain and offer the days again
            return self::pickDate($acc, $s, $doctor_id, __('clinic.wa_bot_book_failed', ['msg' => $error], $lang));
        }
        ClinicWhatsApp::notifyDoctorRequest($appt);
        self::clear($s);
        $when = Carbon::parse($date)->locale($lang)->isoFormat('ddd D MMM');

        return self::out($acc, $s, __('clinic.wa_bot_booked', ['date' => $when, 'time' => $start], $lang));
    }

    private static function showAppointments(ClinicWhatsappAccount $acc, ClinicWaSession $s)
    {
        $lang = self::lang($acc, $s);
        if (! $s->contact_id) {
            return self::say($acc, $s, __('clinic.wa_bot_appts_none', [], $lang), true);
        }
        $appts = ClinicAppointment::forBusiness($s->business_id)->where('contact_id', $s->contact_id)
            ->whereIn('status', ClinicAppointment::OPEN)->whereDate('date', '>=', Carbon::today()->toDateString())
            ->with('doctor')->orderBy('date')->orderBy('start_time')->limit(10)->get();
        if ($appts->isEmpty()) {
            self::clear($s);

            return self::say($acc, $s, __('clinic.wa_bot_appts_none', [], $lang), true);
        }
        $doctors = ClinicAppointments::doctorsWithHours($s->business_id);
        $rows = $appts->map(fn ($a) => [
            'id' => 'BOT:APPT:'.$a->id,
            'title' => Carbon::parse($a->date)->locale($lang)->isoFormat('ddd D MMM').' · '.$a->start,
            'description' => $doctors[$a->doctor_id] ?? '',
        ])->all();
        self::to($s, 'appts');

        return self::list($acc, $s, __('clinic.wa_bot_appts_list', [], $lang), __('clinic.wa_bot_appts_btn', [], $lang), $rows);
    }

    private static function askCancel(ClinicWhatsappAccount $acc, ClinicWaSession $s, $appt_id)
    {
        $lang = self::lang($acc, $s);
        $appt = ClinicAppointment::forBusiness($s->business_id)->where('contact_id', $s->contact_id)
            ->whereIn('status', ClinicAppointment::OPEN)->find($appt_id);
        if (! $appt) {
            return self::showMenu($acc, $s);
        }
        self::to($s, 'cancel_appt', ['appt_id' => $appt->id]);

        return self::buttons($acc, $s, __('clinic.wa_bot_cancel_confirm', [
            'date' => Carbon::parse($appt->date)->locale($lang)->isoFormat('ddd D MMM'), 'time' => $appt->start,
        ], $lang), [
            ['id' => 'BOT:CANCEL_YES', 'title' => __('clinic.wa_bot_cancel_yes', [], $lang)],
            ['id' => 'BOT:MENU:BACK', 'title' => __('clinic.wa_bot_confirm_no', [], $lang)],
        ]);
    }

    private static function doCancel(ClinicWhatsappAccount $acc, ClinicWaSession $s)
    {
        $lang = self::lang($acc, $s);
        $appt = ClinicAppointment::forBusiness($s->business_id)->where('contact_id', $s->contact_id)
            ->whereIn('status', ClinicAppointment::OPEN)->find((int) ($s->data['appt_id'] ?? 0));
        if (! $appt) {
            return self::showMenu($acc, $s);
        }
        $when = Carbon::parse($appt->date)->locale($lang)->isoFormat('ddd D MMM');
        $time = $appt->start;
        ClinicAppointments::release($appt, 'cancelled', null, __('clinic.appt_cancelled_by_patient'));
        self::clear($s);

        return self::out($acc, $s, __('clinic.wa_bot_cancel_done', ['date' => $when, 'time' => $time], $lang));
    }

    private static function handOff(ClinicWhatsappAccount $acc, ClinicWaSession $s)
    {
        $lang = self::lang($acc, $s);
        self::clear($s);

        return self::out($acc, $s, __('clinic.wa_bot_reception', [], $lang));
    }

    /* ------------------------------------------------------------------ patient */

    private static function makePatient($business_id, $name, $mobile, $lang)
    {
        // contacts.created_by is required; attribute a bot-made patient to the business owner
        $by = \App\Business::where('id', $business_id)->value('owner_id');

        return DB::transaction(function () use ($business_id, $name, $mobile, $lang, $by) {
            $contact = (new ContactUtil())->createNewContact([
                'business_id' => $business_id,
                'type' => 'customer',
                'is_patient' => 1,
                'contact_type' => 'individual',
                'first_name' => mb_substr($name, 0, 150),
                'name' => mb_substr($name, 0, 255),
                'mobile' => mb_substr((string) $mobile, 0, 40),
                'created_by' => $by,
            ])['data'];
            $details = PatientDetail::create([
                'contact_id' => $contact->id,
                'business_id' => $business_id,
                'file_number' => PatientDetail::generateFileNumber($business_id),
            ]);
            $details->setWhatsappConsent(true); // they messaged us first: consent to replies
            $details->whatsapp_language = in_array($lang, ['en', 'fr'], true) ? $lang : null;
            $details->save();

            return $contact;
        });
    }

    /* ------------------------------------------------------------------ helpers */

    private static function isTrigger($text, $payload)
    {
        if (is_string($payload) && str_starts_with($payload, 'BOT:')) {
            return true;
        }
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') {
            return false;
        }
        foreach (['book', 'appointment', 'rdv', 'rendez', 'rendezvous'] as $kw) {
            if (str_contains($t, $kw)) {
                return true;
            }
        }

        return in_array($t, ['hi', 'hello', 'hey', 'bonjour', 'salut', 'menu', 'start', 'commencer', '1'], true);
    }

    private static function isGreeting($text)
    {
        $t = mb_strtolower(trim((string) $text));

        return in_array($t, ['menu', 'hi', 'hello', 'bonjour', 'start'], true);
    }

    private static function lang(ClinicWhatsappAccount $acc, ClinicWaSession $s)
    {
        $l = $s->data['lang'] ?? null;
        if (in_array($l, ['en', 'fr'], true)) {
            return $l;
        }

        return in_array($acc->language, ['en', 'fr'], true) ? $acc->language : 'fr';
    }

    private static function fresh($business_id, $from, $patient)
    {
        $s = ClinicWaSession::updateOrCreate(
            ['business_id' => $business_id, 'wa_number' => $from],
            ['contact_id' => $patient ? $patient->id : null, 'state' => 'menu', 'data' => [], 'replies' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES)]
        );

        return $s;
    }

    /** Move to a new state, merging into the stored data, and extend the life. */
    private static function to(ClinicWaSession $s, $state, array $data = [])
    {
        $s->state = $state;
        $s->data = array_merge((array) $s->data, $data);
        $s->expires_at = now()->addMinutes(self::TTL_MINUTES);
        $s->save();
    }

    private static function clear(ClinicWaSession $s)
    {
        $s->delete();
    }

    private static function replyUsed(ClinicWaSession $s)
    {
        $s->replies = (int) $s->replies + 1;
        $s->save();
    }

    /* --- the four send wrappers: each counts one reply against the throttle --- */

    private static function buttons(ClinicWhatsappAccount $acc, ClinicWaSession $s, $body, array $buttons)
    {
        self::replyUsed($s);
        ClinicWhatsApp::sendButtons($acc, $s->wa_number, $body, $buttons, ['kind' => 'bot', 'contact_id' => $s->contact_id]);

        return true;
    }

    private static function list(ClinicWhatsappAccount $acc, ClinicWaSession $s, $body, $label, array $rows)
    {
        self::replyUsed($s);
        ClinicWhatsApp::sendList($acc, $s->wa_number, $body, $label, $rows, ['kind' => 'bot', 'contact_id' => $s->contact_id]);

        return true;
    }

    /** A plain text reply that keeps the session; pass $end to clear it. */
    private static function say(ClinicWhatsappAccount $acc, ClinicWaSession $s, $text, $end = false)
    {
        self::replyUsed($s);
        ClinicWhatsApp::sendText($acc, $s->wa_number, $text, ['kind' => 'bot', 'contact_id' => $s->contact_id]);
        if ($end) {
            self::clear($s);
        }

        return true;
    }

    /** An optional leading text before a list/buttons prompt (e.g. an error). */
    private static function note(ClinicWhatsappAccount $acc, ClinicWaSession $s, $note)
    {
        if ($note) {
            self::replyUsed($s);
            ClinicWhatsApp::sendText($acc, $s->wa_number, $note, ['kind' => 'bot', 'contact_id' => $s->contact_id]);
        }
    }

    /** A final text reply (session already cleared by the caller). */
    private static function out(ClinicWhatsappAccount $acc, ClinicWaSession $s, $text)
    {
        ClinicWhatsApp::sendText($acc, $s->wa_number, $text, ['kind' => 'bot', 'contact_id' => $s->contact_id]);

        return true;
    }

    /** Sweep expired conversations. Returns how many were removed. */
    public static function sweepExpired()
    {
        return ClinicWaSession::where('expires_at', '<', now())->delete();
    }
}
