<?php

namespace App\Utils;

/**
 * PosInnova clinic: what needs the signed-in user, in one list, for the floating
 * activity widget shown on every screen (layouts/partials/activity_widget).
 *
 * Sources (each under its own rule, the same as the bars it replaced):
 *  - booking requests to approve       ClinicAppointments::pendingFor (reception / doctor / both)
 *  - patients waiting for this doctor   ClinicQueue::waitingFor        (ClinicQueue::isDoctor)
 *  - consultations not finished         ClinicQueue::openConsultations (consultation.create)
 *
 * Groups, most urgent first: now (requests; patients waiting while nobody is with
 * the doctor), unfinished (consultations), progress (patients waiting while one is
 * with the doctor; patients who must pay first). Texts are in the user's language.
 */
class ClinicActivity
{
    /** Which sources this user has, or null when the widget is not for him. */
    public static function sources($user, $business_id)
    {
        if (! $user || empty($business_id)) {
            return null;
        }
        $mods = session('business.enabled_modules', []);
        if (! is_array($mods) || ! in_array('hospital', $mods)) {
            return null;
        }
        $pending = ClinicAppointments::pendingFor($business_id, $user);
        $src = [
            'requests' => ! empty($pending['can']),
            'waiting' => ClinicQueue::isDoctor($user, $business_id),
            'resume' => $user->can('consultation.create'),
        ];

        return ($src['requests'] || $src['waiting'] || $src['resume']) ? $src + ['_pending' => $pending] : null;
    }

    /** The list for the widget: {items, needs, total}. */
    public static function build($user, $business_id, $sources = null)
    {
        $src = $sources ?? self::sources($user, $business_id);
        if ($src === null) {
            return null;
        }
        $items = [];

        if ($src['requests']) {
            $pending = $src['_pending'] ?? ClinicAppointments::pendingFor($business_id, $user);
            foreach (collect($pending['requests'] ?? [])->take(30)->all() as $r) {
                $ago = (int) ($r['minutes_ago'] ?? 0);
                $items[] = [
                    'key' => 'r'.$r['id'], 'kind' => 'request', 'group' => 'now', 'id' => $r['id'],
                    'icon' => 'fa-calendar-check', 'color' => '#7c3aed',
                    'title' => __('clinic.pr_one', ['name' => $r['patient']]),
                    'sub' => __('clinic.pr_sub', ['doctor' => $r['doctor'], 'date' => $r['date_label'] ?? $r['date'], 'time' => $r['start']])
                        .' · '.($ago < 1 ? __('clinic.pr_just_now') : __('clinic.pr_ago', ['n' => $ago])),
                    'tag' => ! empty($r['guest']) ? __('clinic.pr_guest') : null,
                    'action' => 'review', 'label' => __('clinic.pr_review'),
                    'peek' => __('clinic.act_new_request'),
                ];
            }
        }

        if ($src['waiting']) {
            $w = ClinicQueue::waitingFor($business_id, $user->id);
            $loud = empty($w['with_doctor']);
            foreach ($w['waiting'] as $i => $p) {
                $items[] = [
                    'key' => 'w'.$p['id'], 'kind' => 'waiting', 'group' => $loud ? 'now' : 'progress', 'id' => $p['id'],
                    'icon' => 'fa-user-clock', 'color' => '#d92d20',
                    'title' => __('clinic.wq_one', ['name' => $p['name']]),
                    'sub' => __('clinic.act_wait_sub', ['no' => $p['queue_no'], 'n' => $p['minutes']])
                        .(! $loud ? ' · '.__('clinic.wq_with_you', ['name' => implode(', ', $w['with_doctor'])]) : ''),
                    // the queue order is kept: the first one is called in from here, the others from the queue
                    'action' => $i === 0 ? 'call' : 'link',
                    'label' => $i === 0 ? __('clinic.act_call_in') : __('clinic.wq_open_queue'),
                    'url' => $i === 0 ? url('clinic/queue/'.$p['id'].'/start') : url('clinic/queue'),
                    'peek' => __('clinic.act_new_patient'),
                ];
            }
            if (! empty($w['unpaid'])) {
                $items[] = [
                    'key' => 'u', 'kind' => 'unpaid', 'group' => 'progress', 'icon' => 'fa-cash-register', 'color' => '#b54708',
                    'title' => __('clinic.act_unpaid', ['n' => $w['unpaid']]), 'sub' => __('clinic.act_unpaid_sub'),
                    'action' => 'link', 'label' => __('clinic.wq_open_queue'), 'url' => url('clinic/queue'),
                ];
            }
        }

        if ($src['resume']) {
            foreach (ClinicQueue::openConsultations($business_id, $user->id, 6) as $c) {
                $draft = $c['kind'] === 'draft';
                $items[] = [
                    'key' => 'c'.$c['visit_id'], 'kind' => 'resume', 'group' => 'unfinished', 'id' => $c['visit_id'],
                    'icon' => 'fa-stethoscope', 'color' => '#b54708',
                    'title' => __($draft ? 'clinic.rs_draft' : 'clinic.rs_open').' '.$c['name'],
                    'sub' => __($draft ? 'clinic.rs_saved_at' : 'clinic.rs_since', ['time' => $c['time']]),
                    'action' => 'link', 'label' => __('clinic.continue'), 'url' => $c['url'],
                ];
            }
        }

        $needs = count(array_filter($items, fn ($i) => $i['group'] === 'now'));

        return ['items' => $items, 'needs' => $needs, 'total' => count($items)];
    }
}
