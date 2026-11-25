<?php

namespace App\Utils;

use App\Business;
use App\GymMember;
use App\GymMembership;

/**
 * PosInnova gym: the WhatsApp reminders to members, through the business's WhatsApp account (the
 * clinic's: Meta's Cloud API, templates approved by Meta): "expiring" N days before a membership
 * ends, "expired" M days after when it was not renewed. Once each. Without an account, the members
 * screen offers "Send on WhatsApp" (a wa.me link with the same text).
 */
class GymWhatsApp
{
    const KINDS = ['expiring', 'expired'];

    public static function params(GymMember $m, GymMembership $ms, $lang)
    {
        return [
            optional($m->contact)->first_name ?: optional($m->contact)->name,
            Business::where('id', $m->business_id)->value('name'),
            optional($ms->plan)->name,
            $ms->end_date->format('d/m/Y'),
        ];
    }

    public static function render($kind, $lang, array $params)
    {
        $text = __('gym.wa_tpl_'.$kind, [], $lang);
        foreach (array_values($params) as $i => $p) {
            $text = str_replace('{{'.($i + 1).'}}', (string) $p, $text);
        }

        return $text;
    }

    public static function canSend($business_id, $kind, $lang)
    {
        $s = Gym::settings($business_id);
        $acc = ClinicWhatsApp::account($business_id);
        $tpl = $s['tpl_'.$kind] ?? null;
        if (! $acc || ! $acc->ready() || empty($s['send_'.$kind]) || ! $tpl) {
            return false;
        }
        $status = $acc->templateStatus($tpl, $lang);

        return $status === null || strtoupper((string) $status) === 'APPROVED';
    }

    public static function notify(GymMember $m, GymMembership $ms, $kind, $user_id = null)
    {
        if (! $m->whatsapp) {
            return null;
        }
        $to = ClinicWhatsApp::waNumber($m->business_id, $m->phone ?: optional($m->contact)->mobile);
        if (! $to || ! self::canSend($m->business_id, $kind, $m->lang)) {
            return null;
        }
        try {
            $params = array_map(fn ($p) => trim(preg_replace('/\s+/u', ' ', (string) $p)) ?: '-', self::params($m, $ms, $m->lang));
            $msg = ClinicWhatsApp::sendTemplate(ClinicWhatsApp::account($m->business_id), $to, Gym::settings($m->business_id)['tpl_'.$kind], $m->lang, $params, [
                'kind' => 'gym_'.$kind, 'contact_id' => $m->contact_id, 'created_by' => $user_id, 'body' => self::render($kind, $m->lang, $params),
            ]);
            $msg->gym_member_id = $m->id;
            $msg->save();

            return $msg;
        } catch (\Throwable $e) {
            \Log::warning('Gym WhatsApp: '.$e->getMessage());

            return null;
        }
    }

    public static function link(GymMember $m, $kind)
    {
        $ms = $m->latest();
        $to = ClinicWhatsApp::waNumber($m->business_id, $m->phone ?: optional($m->contact)->mobile);
        if (! $ms || ! $to) {
            return null;
        }

        return 'https://wa.me/'.$to.'?text='.rawurlencode(self::render($kind, $m->lang, self::params($m, $ms, $m->lang)));
    }

    /** Before the end and after it (not renewed): one message each (gym:reminders, hourly). */
    public static function sendReminders($business_id)
    {
        $s = Gym::settings($business_id);
        $sent = 0;
        // ending within the reminder days, the member's latest period (not renewed yet)
        $soon = GymMembership::where('business_id', $business_id)->whereNull('visits_left')->whereNull('reminded_before_at')
            ->whereBetween('end_date', [today()->toDateString(), today()->addDays(max(0, (int) $s['remind_before']))->toDateString()])->with(['member.contact', 'plan'])->get();
        foreach ($soon as $ms) {
            if (! $ms->member || GymMembership::where('member_id', $ms->member_id)->where('end_date', '>', $ms->end_date)->exists()) {
                continue;
            }
            $msg = self::notify($ms->member, $ms, 'expiring');
            if ($msg && $msg->status !== 'failed') {
                $ms->reminded_before_at = now();
                $ms->save();
                $sent++;
            }
        }
        $lapsed = GymMembership::where('business_id', $business_id)->whereNull('reminded_after_at')
            ->where('end_date', '<=', today()->subDays(max(1, (int) $s['remind_after']))->toDateString())
            ->where('end_date', '>=', today()->subDays(max(1, (int) $s['remind_after']) + 30)->toDateString())->with(['member.contact', 'plan'])->get();
        foreach ($lapsed as $ms) {
            if (! $ms->member || GymMembership::where('member_id', $ms->member_id)->where('end_date', '>', $ms->end_date)->exists()) {
                continue;
            }
            $msg = self::notify($ms->member, $ms, 'expired');
            if ($msg && $msg->status !== 'failed') {
                $ms->reminded_after_at = now();
                $ms->save();
                $sent++;
            }
        }

        return $sent;
    }
}
