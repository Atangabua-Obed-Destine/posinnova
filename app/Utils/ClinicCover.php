<?php

namespace App\Utils;

use App\ClinicPackage;
use App\Visit;
use Carbon\Carbon;

/**
 * PosInnova clinic: return visits covered by a paid consultation.
 *
 * A package can say its fee also covers the patient's return visits
 * (clinic_packages.cover_mode):
 *   none            every visit is paid
 *   days            for cover_days days, the paid visit's day included
 *   month           one month from the paid visit (12 Nov -> 11 Dec)
 *   calendar_month  until the end of the paid visit's month
 * with an optional number of return visits (cover_max_visits, null = no limit)
 * and optionally only with the doctor of the paid visit (cover_same_doctor).
 *
 * When such a package is billed at check-in, the visit keeps its own copy of
 * the rules (visits.cover_until / cover_max_visits / cover_same_doctor), so a
 * later change to the package does not change what was paid for. A return
 * visit bills nothing and points at the paid one (covered_by_visit_id).
 * Nothing is ever billed automatically: once the cover has run out, the next
 * check-in charges the fee again and starts a new cover.
 */
class ClinicCover
{
    /** The last day a fee paid on $from covers, or null when the package covers nothing. */
    public static function until(ClinicPackage $package, $from)
    {
        $from = Carbon::parse($from)->startOfDay();
        switch ($package->cover_mode) {
            case 'days':
                $days = max(1, (int) $package->cover_days);

                return $from->copy()->addDays($days - 1);
            case 'month':
                return $from->copy()->addMonthNoOverflow()->subDay();
            case 'calendar_month':
                return $from->copy()->endOfMonth()->startOfDay();
        }

        return null;
    }

    /** Does the package cover return visits at all? */
    public static function covers(ClinicPackage $package)
    {
        return in_array($package->cover_mode, ['days', 'month', 'calendar_month'])
            && ($package->cover_mode != 'days' || (int) $package->cover_days >= 1);
    }

    /** Copies the package's rules onto a visit that has just been billed for it. */
    public static function stamp(Visit $visit, ClinicPackage $package)
    {
        if (! self::covers($package)) {
            return;
        }
        $visit->cover_until = self::until($package, $visit->visit_date)->toDateString();
        $visit->cover_max_visits = $package->cover_max_visits ?: null;
        $visit->cover_same_doctor = (bool) $package->cover_same_doctor;
    }

    /**
     * What covers a check-in of the patient on $date with $doctor_id:
     *   state   none | covers | used_up | other_doctor
     *   paying  the paid visit (when there is one)
     *   until   Carbon, used, max, message
     * The most recent paid visit whose cover reaches $date counts.
     */
    public static function find($business_id, $contact_id, $date, $doctor_id = null)
    {
        $date = Carbon::parse($date)->startOfDay();
        $paying = Visit::forBusiness($business_id)->where('contact_id', $contact_id)
            ->whereNotNull('fee_transaction_id')        // a cancelled check-in loses its invoice
            ->whereHas('feeTransaction')                // ... and a deleted invoice covers nothing
            ->whereNotNull('cover_until')
            ->where('status', '!=', 'cancelled')
            ->whereDate('visit_date', '<=', $date->toDateString())
            ->whereDate('cover_until', '>=', $date->toDateString())
            ->with(['package', 'doctor', 'feeTransaction'])
            ->orderByDesc('visit_date')->orderByDesc('id')
            ->first();
        if (! $paying) {
            return ['state' => 'none', 'message' => null];
        }

        $used = Visit::forBusiness($business_id)->where('covered_by_visit_id', $paying->id)
            ->where('status', '!=', 'cancelled')->count();
        $util = new Util();
        $out = [
            'paying' => $paying,
            'until' => Carbon::parse($paying->cover_until),
            'used' => $used,
            'max' => $paying->cover_max_visits,
            'unpaid' => ClinicQueue::payState($paying) == 'due',
        ];
        $vars = [
            'date' => $util->format_date($paying->visit_date->toDateString()),
            'until' => $util->format_date($out['until']->toDateString()),
            'package' => optional($paying->package)->name,
            'doctor' => trim(optional($paying->doctor)->user_full_name),
            'n' => $used + 1,
            'max' => $paying->cover_max_visits,
        ];

        if ($paying->cover_max_visits !== null && $used >= (int) $paying->cover_max_visits) {
            return $out + ['state' => 'used_up', 'message' => trans_choice('clinic.cover_used_up', (int) $paying->cover_max_visits, $vars)];
        }
        if ($paying->cover_same_doctor && $paying->doctor_id && $doctor_id && (int) $doctor_id !== (int) $paying->doctor_id) {
            return $out + ['state' => 'other_doctor', 'message' => __('clinic.cover_other_doctor', $vars)];
        }

        $msg = __('clinic.cover_message', $vars);
        if ($paying->cover_max_visits !== null) {
            $msg .= ' '.__('clinic.cover_visit_n_of', $vars);
        }
        if ($paying->cover_same_doctor && $vars['doctor']) {
            $msg .= ' '.__('clinic.cover_with_doctor', $vars);
        }
        if ($out['unpaid']) {
            $msg .= ' '.__('clinic.cover_fee_unpaid');
        }

        return $out + ['state' => 'covers', 'message' => $msg];
    }

    /** A sentence for the package list / form: what a package's fee covers. */
    public static function describe(ClinicPackage $package)
    {
        if (! self::covers($package)) {
            return __('clinic.cover_desc_none');
        }
        $parts = [__('clinic.cover_desc_'.$package->cover_mode, ['n' => (int) $package->cover_days])];
        $parts[] = $package->cover_max_visits
            ? trans_choice('clinic.cover_desc_max', (int) $package->cover_max_visits, ['n' => (int) $package->cover_max_visits])
            : __('clinic.cover_desc_unlimited');
        if ($package->cover_same_doctor) {
            $parts[] = __('clinic.cover_desc_same_doctor');
        }

        return implode(', ', $parts);
    }
}
