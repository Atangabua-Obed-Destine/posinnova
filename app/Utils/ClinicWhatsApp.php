<?php

namespace App\Utils;

use App\Business;
use App\ClinicAppointment;
use App\ClinicWhatsappAccount;
use App\ClinicWhatsappMessage;
use App\Contact;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PosInnova clinic: automatic WhatsApp messages through Meta's WhatsApp
 * Business Platform (Cloud API, https://graph.facebook.com).
 *
 * What is sent (each can be switched off, and nothing goes to a patient who has
 * not agreed - patient_details.whatsapp_opt_in):
 *   confirmation  when reception books, or accepts a portal request
 *   reminder      the day before, from the clinic's reminder time (artisan
 *                 clinic:whatsapp-reminders, every 10 minutes), with two answer
 *                 buttons: "I will come" / "I cannot come"
 *   moved         when a booking is moved to another time
 * Each is a template the clinic has had approved by Meta, in French and English
 * (the texts are in lang/xx/clinic.php: wa_tpl_*), so the guide can show them.
 *
 * Meta tells us what happened through the webhook (/webhook/whatsapp): delivery
 * (sent, delivered, read, failed) and the patient's replies. A button answer is
 * written on the appointment; "I cannot come" and any text wait in the replies
 * inbox for reception; STOP (or ARRET) ends the messages for that patient.
 */
class ClinicWhatsApp
{
    const KINDS = ['confirmation', 'reminder', 'moved'];

    const STOP_WORDS = ['STOP', 'ARRET', 'ARRÊT', 'UNSUBSCRIBE', 'STOPP'];

    /* ------------------------------------------------------------------ helpers */

    public static function account($business_id)
    {
        return ClinicWhatsappAccount::where('business_id', $business_id)->first();
    }

    /**
     * A phone number as WhatsApp wants it: digits with the country code
     * ("677 11 10 01" -> "237677111001"), or null when it cannot be one.
     */
    public static function waNumber($business_id, $phone)
    {
        $phone = trim((string) $phone);
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($phone, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) < 8) {
            return null;
        }
        $cc = preg_replace('/\D+/', '', (string) ClinicSettings::get($business_id, 'whatsapp_country_code'));
        if ($cc !== '' && ! str_starts_with($phone, '+') && ! str_starts_with($digits, $cc) && strlen($digits) <= 10) {
            $digits = $cc.ltrim($digits, '0');
        }

        return $digits;
    }

    /** A template's text in a language, with {{1}}... filled in (for the log, the inbox and the guide). */
    public static function render($kind, $lang, array $params = [])
    {
        $text = __('clinic.wa_tpl_'.$kind, [], $lang);
        foreach (array_values($params) as $i => $p) {
            $text = str_replace('{{'.($i + 1).'}}', $p, $text);
        }

        return $text;
    }

    /** Meta refuses empty values, line breaks, tabs and long runs of spaces in a variable. */
    private static function clean($text)
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

        return $text === '' ? '-' : mb_substr($text, 0, 200);
    }

    /** The five values of an appointment message: name, clinic, doctor, day, time. */
    public static function params(ClinicAppointment $appt, $lang)
    {
        $patient = $appt->patient;
        // "Friday 13 November 2026" / "vendredi 13 novembre 2026" (French keeps the day in lower case)
        $day = Carbon::parse($appt->date->toDateString())->locale($lang)->translatedFormat('l j F Y');

        return array_map([self::class, 'clean'], [
            optional($patient)->first_name ?: optional($patient)->name,
            Business::where('id', $appt->business_id)->value('name'),
            trim((string) optional($appt->doctor)->user_full_name),
            $lang == 'en' ? \Illuminate\Support\Str::ucfirst($day) : $day,
            $appt->start,
        ]);
    }

    /** A plain-language explanation of a Meta error code, or Meta's own text. */
    public static function explain($code, $fallback = null)
    {
        $key = 'clinic.wa_err_'.$code;
        $text = __($key);

        return $text !== $key ? $text : ($fallback ?: __('clinic.wa_err_unknown'));
    }

    /* ------------------------------------------------------------------ calling Meta */

    /**
     * One call to the Graph API: [ok, json body, error code, error text].
     */
    public static function call(ClinicWhatsappAccount $acc, $method, $path, array $data = [])
    {
        $url = 'https://graph.facebook.com/'.($acc->api_version ?: 'v25.0').'/'.ltrim($path, '/');
        try {
            $http = Http::withToken((string) $acc->access_token)->acceptJson()->timeout(15);
            $res = $method == 'GET' ? $http->get($url, $data) : $http->post($url, $data);
        } catch (\Throwable $e) {
            return [false, null, 'network', __('clinic.wa_err_network')];
        }
        $json = $res->json() ?: [];
        if ($res->successful() && empty($json['error'])) {
            return [true, $json, null, null];
        }
        $err = $json['error'] ?? [];
        $code = (string) ($err['code'] ?? $res->status());
        $detail = trim(($err['message'] ?? '').' '.($err['error_data']['details'] ?? ''));

        return [false, $json, $code, self::explain($code, $detail ?: ('HTTP '.$res->status()))];
    }

    /**
     * Sends a template message and logs it. $buttons: payloads of the template's
     * quick-reply buttons, in order (null when it has none).
     */
    public static function sendTemplate(ClinicWhatsappAccount $acc, $to, $template, $lang, array $params, array $meta = [], ?array $buttons = null)
    {
        $components = [];
        if (! empty($params)) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn ($t) => ['type' => 'text', 'text' => (string) $t], array_values($params))];
        }
        foreach ((array) $buttons as $i => $payload) {
            $components[] = ['type' => 'button', 'sub_type' => 'quick_reply', 'index' => (string) $i, 'parameters' => [['type' => 'payload', 'payload' => $payload]]];
        }
        $tpl = ['name' => $template, 'language' => ['code' => $lang]];
        if ($components) {
            $tpl['components'] = $components;
        }

        return self::send($acc, $to, ['type' => 'template', 'template' => $tpl], $meta + ['template' => $template, 'language' => $lang]);
    }

    /** A free text (only allowed within 24 hours of the patient's last message). */
    public static function sendText(ClinicWhatsappAccount $acc, $to, $text, array $meta = [])
    {
        return self::send($acc, $to, ['type' => 'text', 'text' => ['preview_url' => false, 'body' => mb_substr((string) $text, 0, 4000)]], $meta + ['body' => $text]);
    }

    private static function send(ClinicWhatsappAccount $acc, $to, array $message, array $meta)
    {
        $msg = ClinicWhatsappMessage::create([
            'business_id' => $acc->business_id,
            'direction' => 'out',
            'kind' => $meta['kind'] ?? 'text',
            'contact_id' => $meta['contact_id'] ?? null,
            'appointment_id' => $meta['appointment_id'] ?? null,
            'wa_number' => $to,
            'template' => $meta['template'] ?? null,
            'language' => $meta['language'] ?? null,
            'body' => $meta['body'] ?? null,
            'status' => 'queued',
            'created_by' => $meta['created_by'] ?? null,
        ]);
        [$ok, $json, $code, $error] = self::call($acc, 'POST', $acc->phone_number_id.'/messages',
            ['messaging_product' => 'whatsapp', 'recipient_type' => 'individual', 'to' => $to] + $message);
        if ($ok && ! empty($json['messages'][0]['id'])) {
            $msg->update(['status' => 'sent', 'wamid' => $json['messages'][0]['id'], 'sent_at' => now()]);
        } else {
            $msg->update(['status' => 'failed', 'failed_at' => now(), 'error_code' => $code, 'error_message' => mb_substr((string) $error, 0, 500)]);
            Log::warning('WhatsApp send failed', ['business_id' => $acc->business_id, 'kind' => $msg->kind, 'code' => $code]);
        }

        return $msg;
    }

    /* ------------------------------------------------------------------ appointments */

    /**
     * Sends the automatic message of an appointment event, when the clinic has
     * WhatsApp on, sends that kind, and the patient agreed. Returns the logged
     * message, or null when nothing was attempted (the manual WhatsApp link stays).
     */
    public static function notify(ClinicAppointment $appt, $kind, $user_id = null)
    {
        $acc = self::account($appt->business_id);
        if (! $acc || ! $acc->ready() || ! $acc->sends($kind)) {
            return null;
        }
        $appt->loadMissing(['patient.patientDetails', 'doctor']);
        $pd = optional($appt->patient)->patientDetails;
        if (! $pd || ! $pd->whatsapp_opt_in) {
            return null;
        }
        $to = self::waNumber($appt->business_id, optional($appt->patient)->mobile);
        if (! $to) {
            return null;
        }
        $lang = in_array($pd->whatsapp_language, ['en', 'fr']) ? $pd->whatsapp_language : $acc->language;
        $params = self::params($appt, $lang);
        $buttons = $kind == 'reminder' && $acc->reminder_buttons ? ['APPT:'.$appt->id.':YES', 'APPT:'.$appt->id.':NO'] : null;

        $msg = self::sendTemplate($acc, $to, $acc->templateFor($kind), $lang, $params, [
            'kind' => $kind, 'contact_id' => $appt->contact_id, 'appointment_id' => $appt->id,
            'created_by' => $user_id, 'body' => self::render($kind, $lang, $params),
        ], $buttons);
        if ($kind == 'reminder' && $msg->status != 'failed') {
            $appt->reminded_at = now();
            $appt->save();
        }

        return $msg;
    }

    /**
     * The day-before reminders: for every clinic past its reminder time, each
     * confirmed appointment of tomorrow not reminded yet (by hand or
     * automatically) and never tried. Run every 10 minutes by the scheduler.
     *
     * @return array [business_id => number sent]
     */
    public static function runReminders(?Carbon $now = null)
    {
        $done = [];
        $accounts = ClinicWhatsappAccount::where('enabled', 1)->where('send_reminder', 1)->get();
        foreach ($accounts as $acc) {
            if (! $acc->ready() || ! ClinicAppointments::enabled($acc->business_id)) {
                continue;
            }
            $tz = Business::where('id', $acc->business_id)->value('time_zone') ?: config('app.timezone');
            $local = ($now ?: Carbon::now())->copy()->setTimezone($tz);
            if ($local->format('H:i') < ($acc->reminder_time ?: '18:00')) {
                continue;
            }
            $tomorrow = $local->copy()->addDay()->toDateString();
            $appts = ClinicAppointment::forBusiness($acc->business_id)->where('status', 'confirmed')
                ->whereDate('date', $tomorrow)->whereNull('reminded_at')
                ->whereDoesntHave('whatsappMessages', fn ($q) => $q->where('direction', 'out')->where('kind', 'reminder'))
                ->with(['patient.patientDetails', 'doctor'])->orderBy('start_time')->get();
            $n = 0;
            foreach ($appts as $appt) {
                $msg = self::notify($appt, 'reminder');
                if ($msg && $msg->status != 'failed') {
                    $n++;
                }
            }
            $done[$acc->business_id] = $n;
        }

        return $done;
    }

    /* ------------------------------------------------------------------ webhook */

    /** The account a webhook event is for (by Meta's phone number id). */
    public static function accountForPayload(array $payload)
    {
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $pnid = $change['value']['metadata']['phone_number_id'] ?? null;
                if ($pnid) {
                    return ClinicWhatsappAccount::where('phone_number_id', $pnid)->first();
                }
                // template events carry the WhatsApp Business Account id instead
                if (! empty($entry['id'])) {
                    $acc = ClinicWhatsappAccount::where('waba_id', $entry['id'])->first();
                    if ($acc) {
                        return $acc;
                    }
                }
            }
        }

        return null;
    }

    /** Meta signs each event with the app secret (header X-Hub-Signature-256). */
    public static function signatureValid(ClinicWhatsappAccount $acc, $raw, $header)
    {
        if (empty($acc->app_secret) || ! is_string($header) || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $raw, (string) $acc->app_secret), substr($header, 7));
    }

    public static function handleWebhook(ClinicWhatsappAccount $acc, array $payload)
    {
        $acc->last_webhook_at = now();
        $acc->save();
        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                if (($change['field'] ?? '') == 'message_template_status_update') {
                    self::templateEvent($acc, $value);
                    continue;
                }
                foreach ((array) ($value['statuses'] ?? []) as $st) {
                    self::statusEvent($acc, $st);
                }
                foreach ((array) ($value['messages'] ?? []) as $m) {
                    self::incoming($acc, $m);
                }
            }
        }
    }

    private static function statusEvent(ClinicWhatsappAccount $acc, array $st)
    {
        $msg = ClinicWhatsappMessage::forBusiness($acc->business_id)->where('wamid', $st['id'] ?? '-')->first();
        $new = $st['status'] ?? null;
        if (! $msg || ! isset(ClinicWhatsappMessage::RANK[$new])) {
            return;
        }
        $at = ! empty($st['timestamp']) ? Carbon::createFromTimestamp((int) $st['timestamp']) : now();
        $stamp = ['sent' => 'sent_at', 'delivered' => 'delivered_at', 'read' => 'read_at', 'failed' => 'failed_at'][$new];
        $msg->{$stamp} = $msg->{$stamp} ?: $at;
        if ($new == 'failed' || ClinicWhatsappMessage::RANK[$new] > (ClinicWhatsappMessage::RANK[$msg->status] ?? 0)) {
            $msg->status = $new;
        }
        if ($new == 'failed' && ! empty($st['errors'][0])) {
            $e = $st['errors'][0];
            $msg->error_code = (string) ($e['code'] ?? '');
            $msg->error_message = mb_substr(self::explain($e['code'] ?? '', trim(($e['title'] ?? '').' '.($e['error_data']['details'] ?? ''))), 0, 500);
            // a reminder that never arrived: let reception remind by hand
            if ($msg->kind == 'reminder' && $msg->appointment_id) {
                ClinicAppointment::where('id', $msg->appointment_id)->update(['reminded_at' => null]);
            }
        }
        $msg->save();
    }

    private static function incoming(ClinicWhatsappAccount $acc, array $m)
    {
        if (empty($m['id']) || ClinicWhatsappMessage::where('wamid', $m['id'])->exists()) {
            return; // Meta may deliver an event twice
        }
        $type = $m['type'] ?? 'other';
        $text = $m['text']['body'] ?? $m['button']['text'] ?? $m['interactive']['button_reply']['title'] ?? null;
        $payload = $m['button']['payload'] ?? $m['interactive']['button_reply']['id'] ?? null;
        $from = preg_replace('/\D+/', '', (string) ($m['from'] ?? ''));
        $context = $m['context']['id'] ?? null;

        $patient = self::patientByNumber($acc->business_id, $from);
        $answered = $context ? ClinicWhatsappMessage::forBusiness($acc->business_id)->where('wamid', $context)->first() : null;
        $appointment_id = optional($answered)->appointment_id;
        if (! $appointment_id && $payload && preg_match('/^APPT:(\d+):(YES|NO)$/', $payload, $pm)) {
            $appointment_id = (int) $pm[1];
        }
        $appt = $appointment_id ? ClinicAppointment::forBusiness($acc->business_id)->find($appointment_id) : null;
        // an answer only counts for the sender's own appointment (a payload can be typed by anyone)
        if ($appt && $patient && (int) $appt->contact_id !== (int) $patient->id) {
            $appt = null;
        }

        $in = ClinicWhatsappMessage::create([
            'business_id' => $acc->business_id,
            'direction' => 'in',
            'kind' => in_array($type, ['text', 'button', 'interactive']) ? ($type == 'text' ? 'text' : 'button') : 'other',
            'contact_id' => $patient ? $patient->id : optional($appt)->contact_id,
            'appointment_id' => optional($appt)->id,
            'wa_number' => $from,
            'body' => $text !== null ? mb_substr($text, 0, 4000) : '['.$type.']',
            'button_payload' => $payload ? mb_substr($payload, 0, 100) : null,
            'status' => 'received',
            'wamid' => $m['id'],
            'context_wamid' => $context,
            'sent_at' => ! empty($m['timestamp']) ? Carbon::createFromTimestamp((int) $m['timestamp']) : now(),
        ]);

        // the answer buttons of the reminder
        if ($appt && $payload && preg_match('/^APPT:'.$appt->id.':(YES|NO)$/', $payload, $pm)) {
            $appt->patient_reply = $pm[1] == 'YES' ? 'coming' : 'not_coming';
            $appt->patient_reply_at = now();
            $appt->save();
            if ($pm[1] == 'YES') {
                $in->update(['status' => 'handled', 'handled_at' => now()]); // nothing to do
            }

            return;
        }

        // STOP: no more messages to this patient
        $word = mb_strtoupper(trim((string) $text, " \t\n\r\0\x0B.!"));
        if ($type == 'text' && in_array($word, self::STOP_WORDS, true)) {
            foreach (self::patientsByNumber($acc->business_id, $from) as $p) {
                $pd = $p->patientDetails;
                if ($pd) {
                    $pd->setWhatsappConsent(false);
                    $pd->save();
                    $lang = in_array($pd->whatsapp_language, ['en', 'fr']) ? $pd->whatsapp_language : $acc->language;
                }
            }
            $in->update(['status' => 'handled', 'handled_at' => now()]);
            self::sendText($acc, $from, __('clinic.wa_stop_ack', [], $lang ?? $acc->language), ['kind' => 'optout', 'contact_id' => $in->contact_id]);
        }
    }

    private static function templateEvent(ClinicWhatsappAccount $acc, array $v)
    {
        $name = $v['message_template_name'] ?? null;
        $lang = $v['message_template_language'] ?? null;
        $event = $v['event'] ?? null;
        if ($name && $lang && $event) {
            $all = (array) $acc->templates_status;
            $all[$name][$lang] = strtoupper($event);
            $acc->templates_status = $all;
            $acc->save();
        }
    }

    /** Patients of the clinic whose mobile is this WhatsApp number. */
    public static function patientsByNumber($business_id, $wa_number)
    {
        $tail = substr($wa_number, -9);
        if (strlen($tail) < 8) {
            return collect();
        }

        return Contact::where('business_id', $business_id)->onlyPatients()
            ->where(function ($q) use ($tail) {
                // stored with spaces or dashes too: compare the digits only
                $q->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '.', ''), '+', '') LIKE ?", ['%'.$tail]);
            })->with('patientDetails')->get()
            ->filter(fn ($c) => self::waNumber($business_id, $c->mobile) === $wa_number)->values();
    }

    public static function patientByNumber($business_id, $wa_number)
    {
        return self::patientsByNumber($business_id, $wa_number)->first();
    }

    /** May staff still answer this number with free text (Meta: within 24 hours of the patient's last message)? */
    public static function canReply($business_id, $wa_number)
    {
        $last = ClinicWhatsappMessage::forBusiness($business_id)->where('direction', 'in')->where('wa_number', $wa_number)->max('sent_at');

        return $last && Carbon::parse($last)->gt(now()->subHours(24));
    }

    /* ------------------------------------------------------------------ set-up checks */

    /** Reads the phone number from Meta: proves the details and the token work. */
    public static function testConnection(ClinicWhatsappAccount $acc)
    {
        [$ok, $json, $code, $error] = self::call($acc, 'GET', $acc->phone_number_id,
            ['fields' => 'display_phone_number,verified_name,code_verification_status,quality_rating,platform_type,name_status']);
        if ($ok) {
            $acc->display_phone = $json['display_phone_number'] ?? null;
            $acc->verified_name = $json['verified_name'] ?? null;
            $acc->connection_ok_at = now();
            $acc->connection_error = null;
            if (($json['platform_type'] ?? null) == 'CLOUD_API' && ! $acc->registered_at) {
                $acc->registered_at = now();
            }
        } else {
            $acc->connection_ok_at = null;
            $acc->connection_error = mb_substr((string) $error, 0, 500);
        }
        $acc->save();

        return [$ok, $ok ? $json : $error];
    }

    /** Registers the number for sending through the Cloud API (a 6-digit PIN the clinic chooses). */
    public static function registerNumber(ClinicWhatsappAccount $acc, $pin)
    {
        [$ok, $json, $code, $error] = self::call($acc, 'POST', $acc->phone_number_id.'/register', ['messaging_product' => 'whatsapp', 'pin' => (string) $pin]);
        if ($ok) {
            $acc->registered_at = now();
            $acc->save();
        }

        return [$ok, $error];
    }

    /** Subscribes the app to the WhatsApp account so Meta sends its events to the webhook. */
    public static function subscribeWebhook(ClinicWhatsappAccount $acc)
    {
        [$ok, $json, $code, $error] = self::call($acc, 'POST', $acc->waba_id.'/subscribed_apps');
        if ($ok) {
            $acc->webhook_subscribed_at = now();
            $acc->save();
        }

        return [$ok, $error];
    }

    /** Fetches the approval status of the clinic's three templates, in each language. */
    public static function checkTemplates(ClinicWhatsappAccount $acc)
    {
        [$ok, $json, $code, $error] = self::call($acc, 'GET', $acc->waba_id.'/message_templates', ['fields' => 'name,language,status,category', 'limit' => 250]);
        if (! $ok) {
            return [false, $error];
        }
        $mine = [$acc->tpl_confirmation, $acc->tpl_reminder, $acc->tpl_moved];
        $status = [];
        foreach ((array) ($json['data'] ?? []) as $t) {
            if (in_array($t['name'] ?? null, $mine, true)) {
                $status[$t['name']][$t['language']] = $t['status'] ?? null;
            }
        }
        $acc->templates_status = $status;
        $acc->templates_checked_at = now();
        $acc->save();

        return [true, $status];
    }

    /**
     * Are the templates of the messages switched on approved in the clinic's
     * default language? (The other language is needed only for patients who
     * chose it; the set-up page shows both.)
     */
    public static function templatesReady(ClinicWhatsappAccount $acc)
    {
        foreach (self::KINDS as $kind) {
            if ($acc->sends($kind) && $acc->templateStatus($acc->templateFor($kind), $acc->language) != 'APPROVED') {
                return false;
            }
        }

        return true;
    }
}
