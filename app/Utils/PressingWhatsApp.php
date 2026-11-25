<?php

namespace App\Utils;

use App\Business;
use App\PressingOrder;
use Carbon\Carbon;

/**
 * PosInnova pressing: the WhatsApp messages to the customer, through the business's WhatsApp
 * account (the one the clinic reminders use, App\Utils\ClinicWhatsApp: Meta's Cloud API, templates
 * approved by Meta). Three messages: received (at drop-off), ready, reminder (not collected after
 * N days). Without a connected account, or when a template is not approved yet, nothing is sent
 * and the order page offers "Send on WhatsApp" (a wa.me link with the same text) instead.
 */
class PressingWhatsApp
{
    const KINDS = ['received', 'ready', 'reminder'];

    /** The values of a message, in the order of the template's {{1}}, {{2}}… */
    public static function params(PressingOrder $o, $kind, $lang)
    {
        $name = optional($o->contact)->first_name ?: optional($o->contact)->name;
        $biz = Business::where('id', $o->business_id)->value('name');
        $when = Carbon::parse($o->ready_at)->locale($lang)->translatedFormat($lang === 'fr' ? 'l j F \à H\hi' : 'l j F \a\t H:i');
        $m = Pressing::money($o);
        switch ($kind) {
            case 'received':
                return [$name, $biz, $o->ticket_no, (string) $o->pieces()->count(), $when];
            case 'ready':
                return [$name, $biz, $o->ticket_no, $o->on_room_bill ? __('pressing.wa_on_room', [], $lang) : Pressing::format($o->business_id, $m['due'])];
            default:
                return [$name, $biz, $o->ticket_no, Carbon::parse($o->ready_marked_at ?: $o->ready_at)->format('d/m')];
        }
    }

    public static function render($kind, $lang, array $params)
    {
        $text = __('pressing.wa_tpl_'.$kind, [], $lang);
        foreach (array_values($params) as $i => $p) {
            $text = str_replace('{{'.($i + 1).'}}', (string) $p, $text);
        }

        return $text;
    }

    /** Can this business send the message on its own (account ready, kind switched on, template approved)? */
    public static function canSend($business_id, $kind, $lang)
    {
        $s = Pressing::settings($business_id);
        $acc = ClinicWhatsApp::account($business_id);
        $tpl = $s['tpl_'.$kind] ?? null;
        if (! $acc || ! $acc->ready() || empty($s['send_'.$kind]) || ! $tpl) {
            return false;
        }
        $status = $acc->templateStatus($tpl, $lang);

        return $status === null || strtoupper((string) $status) === 'APPROVED';
    }

    /** Sends the message when it can; returns the logged message, or null (the link stays). */
    public static function notify(PressingOrder $o, $kind, $user_id = null)
    {
        if (! $o->whatsapp || ! in_array($kind, self::KINDS, true)) {
            return null;
        }
        $to = ClinicWhatsApp::waNumber($o->business_id, $o->phone);
        if (! $to || ! self::canSend($o->business_id, $kind, $o->lang)) {
            return null;
        }
        try {
            $acc = ClinicWhatsApp::account($o->business_id);
            $params = array_map(fn ($p) => trim(preg_replace('/\s+/u', ' ', (string) $p)) ?: '-', self::params($o, $kind, $o->lang));
            $msg = ClinicWhatsApp::sendTemplate($acc, $to, Pressing::settings($o->business_id)['tpl_'.$kind], $o->lang, $params, [
                'kind' => 'pressing_'.$kind, 'contact_id' => $o->contact_id, 'created_by' => $user_id, 'body' => self::render($kind, $o->lang, $params),
            ]);
            $msg->pressing_order_id = $o->id;
            $msg->save();

            return $msg;
        } catch (\Throwable $e) {
            \Log::warning('Pressing WhatsApp: '.$e->getMessage());

            return null;
        }
    }

    /** "Send on WhatsApp": a wa.me link opening the message ready to send from the counter phone. */
    public static function link(PressingOrder $o, $kind)
    {
        $to = ClinicWhatsApp::waNumber($o->business_id, $o->phone);
        if (! $to) {
            return null;
        }

        return 'https://wa.me/'.$to.'?text='.rawurlencode(self::render($kind, $o->lang, self::params($o, $kind, $o->lang)));
    }

    /** Orders ready for N days and not collected get one reminder (pressing:reminders, hourly). */
    public static function sendReminders($business_id)
    {
        $s = Pressing::settings($business_id);
        $sent = 0;
        $due = PressingOrder::where('business_id', $business_id)->where('status', 'ready')->whereNull('reminded_at')->where('whatsapp', 1)
            ->where('ready_marked_at', '<=', now()->subDays(max(1, (int) $s['reminder_days'])))->get();
        foreach ($due as $o) {
            $msg = self::notify($o, 'reminder');
            if ($msg && $msg->status !== 'failed') {
                $o->reminded_at = now();
                $o->save();
                $sent++;
            }
        }

        return $sent;
    }
}
