<?php

namespace App\Utils;

use App\Business;

/**
 * The Clinic settings page (hospital module), read in one place.
 *
 * Stored as JSON in business.hospital_settings; anything missing falls back to
 * DEFAULTS, so a business that never opened the page behaves exactly as before
 * (every hospital section on, pay after the doctor, portal on).
 *
 * The file number prefix and the default consultation / morgue fees keep their
 * own existing business columns and are read from there.
 */
class ClinicSettings
{
    const DEFAULTS = [
        // Reception
        'pay_first' => false,              // true: a visit reaches the doctor only once its fee is paid
        'admin_doctors' => [],             // Admin users who also see patients (Admins pass every permission check, so they are not doctors by default)
        // Monthly plans
        'plan_billing_anchor' => 'start',  // 'start' = each patient's start date, 'fixed' = one day for everyone
        'plan_fixed_day' => 1,             // 1-28, used when anchor = fixed
        'plan_grace_days' => 0,            // an unpaid period still covers visits this many days
        'plan_unpaid_rule' => 'charge',    // after grace: 'charge' per-visit fee | 'block' check-in | 'cover' anyway
        // Appointments
        'appointments_enabled' => true,
        'appointments_portal' => true,     // patients may ask for a slot from the portal (reception confirms)
        'appointments_days_ahead' => 30,   // how far ahead the portal offers slots
        'appointments_min_notice_hours' => 2, // the portal offers no slot starting sooner than this
        'appointments_booked_first' => true, // booked patients are seen before walk-ins in the doctor queue
        'appointments_approval' => 'reception', // who is loudly alerted to approve a booking request: 'reception' | 'doctor' | 'both'
        'appointments_daily_cap' => 0,     // most appointments a doctor takes in a day (0 = no limit); blocks online booking only, reception may override
        'appointments_doctor_caps' => [],  // per-doctor override of the daily cap, [user_id => n]
        'appointments_whatsapp_bot' => false, // patients may book over the clinic WhatsApp number (the chat bot)
        'whatsapp_country_code' => '237',  // added to local numbers in WhatsApp reminder links
        // Insurance
        'insurance_covers_plans' => false, // true: an insured patient's monthly plan invoice is split with the insurer too
        // Hospital sections shown in the menu (a consultation centre turns these off)
        'sections' => ['wards' => true, 'theatre' => true, 'maternity' => true, 'morgue' => true, 'imaging' => true, 'departments' => true],
        // Patient portal
        'portal_enabled' => true,
        'portal_idle_minutes' => 30,       // signed out after this long without activity
        'portal_consultation_detail' => 'diagnosis', // 'diagnosis' only, or 'full' notes
        // Printing: the prescription is free; the full consultation report costs this (0 = free)
        'report_fee' => 0,
    ];

    const SECTIONS = ['wards', 'theatre', 'maternity', 'morgue', 'imaging', 'departments'];

    private static $cache = [];

    /**
     * All settings for a business, defaults filled in.
     */
    public static function all($business_id)
    {
        if (empty($business_id)) {
            return self::DEFAULTS;
        }
        if (! isset(self::$cache[$business_id])) {
            $raw = Business::where('id', $business_id)->value('hospital_settings');
            self::$cache[$business_id] = self::merge(json_decode((string) $raw, true) ?: []);
        }

        return self::$cache[$business_id];
    }

    public static function get($business_id, $key)
    {
        $all = self::all($business_id);

        return $all[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    /**
     * Is a hospital section (wards, theatre, maternity, morgue) switched on?
     */
    public static function sectionOn($business_id, $section)
    {
        $sections = self::get($business_id, 'sections');

        return ! empty($sections[$section]);
    }

    /**
     * Validate and save the settings page. Unknown keys are dropped, values
     * are coerced to their type and clamped to their allowed range.
     */
    public static function save($business_id, array $input)
    {
        $s = self::DEFAULTS;
        $s['pay_first'] = ! empty($input['pay_first']);
        $s['admin_doctors'] = array_values(array_unique(array_filter(array_map('intval', (array) ($input['admin_doctors'] ?? [])))));
        $s['plan_billing_anchor'] = in_array($input['plan_billing_anchor'] ?? '', ['start', 'fixed']) ? $input['plan_billing_anchor'] : 'start';
        $s['plan_fixed_day'] = max(1, min(28, (int) ($input['plan_fixed_day'] ?? 1)));
        $s['plan_grace_days'] = max(0, min(60, (int) ($input['plan_grace_days'] ?? 0)));
        $s['plan_unpaid_rule'] = in_array($input['plan_unpaid_rule'] ?? '', ['charge', 'block', 'cover']) ? $input['plan_unpaid_rule'] : 'charge';
        $s['insurance_covers_plans'] = ! empty($input['insurance_covers_plans']);
        $s['appointments_enabled'] = ! empty($input['appointments_enabled']);
        $s['appointments_portal'] = ! empty($input['appointments_portal']);
        $s['appointments_days_ahead'] = max(1, min(180, (int) ($input['appointments_days_ahead'] ?? 30)));
        $s['appointments_min_notice_hours'] = max(0, min(72, (int) ($input['appointments_min_notice_hours'] ?? 2)));
        $s['appointments_booked_first'] = ! empty($input['appointments_booked_first']);
        $s['appointments_approval'] = in_array($input['appointments_approval'] ?? '', ['reception', 'doctor', 'both']) ? $input['appointments_approval'] : 'reception';
        $s['appointments_daily_cap'] = max(0, min(100, (int) ($input['appointments_daily_cap'] ?? 0)));
        // the per-doctor cap map is edited on the hours page, not this form: keep what is
        // stored unless this save explicitly carries the map (array_key_exists, so [] can clear it)
        $s['appointments_doctor_caps'] = array_key_exists('appointments_doctor_caps', $input)
            ? self::cleanCaps($input['appointments_doctor_caps'])
            : self::get($business_id, 'appointments_doctor_caps');
        $s['appointments_whatsapp_bot'] = ! empty($input['appointments_whatsapp_bot']);
        $cc = preg_replace('/\D+/', '', (string) ($input['whatsapp_country_code'] ?? ''));
        $s['whatsapp_country_code'] = substr($cc, 0, 4);
        foreach (self::SECTIONS as $sec) {
            $s['sections'][$sec] = ! empty($input['sections'][$sec]);
        }
        $s['portal_enabled'] = ! empty($input['portal_enabled']);
        $s['portal_idle_minutes'] = max(5, min(240, (int) ($input['portal_idle_minutes'] ?? 30)));
        $s['portal_consultation_detail'] = ($input['portal_consultation_detail'] ?? '') === 'full' ? 'full' : 'diagnosis';
        $s['report_fee'] = max(0, round((float) ($input['report_fee'] ?? 0), 4));

        Business::where('id', $business_id)->update(['hospital_settings' => json_encode($s)]);
        unset(self::$cache[$business_id]);

        return $s;
    }

    /** Normalise a [user_id => max-per-day] map: positive ints only, 0/blank dropped. */
    private static function cleanCaps($caps)
    {
        $out = [];
        foreach ((array) $caps as $uid => $n) {
            $uid = (int) $uid;
            $n = max(0, min(100, (int) $n));
            if ($uid > 0 && $n > 0) {
                $out[$uid] = $n;
            }
        }

        return $out;
    }

    /**
     * Set (or clear, with 0) one doctor's daily-cap override without disturbing
     * any other setting. Used by the per-doctor field on the hours page.
     */
    public static function setDoctorCap($business_id, $doctor_id, $n)
    {
        $caps = self::cleanCaps(self::get($business_id, 'appointments_doctor_caps'));
        $doctor_id = (int) $doctor_id;
        $n = max(0, min(100, (int) $n));
        if ($n > 0) {
            $caps[$doctor_id] = $n;
        } else {
            unset($caps[$doctor_id]);
        }
        $all = self::all($business_id);
        $all['appointments_doctor_caps'] = $caps;
        Business::where('id', $business_id)->update(['hospital_settings' => json_encode($all)]);
        unset(self::$cache[$business_id]);

        return $caps;
    }

    public static function forget($business_id = null)
    {
        if ($business_id === null) {
            self::$cache = [];
        } else {
            unset(self::$cache[$business_id]);
        }
    }

    private static function merge(array $stored)
    {
        $s = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
        $s['sections'] = array_merge(self::DEFAULTS['sections'], is_array($stored['sections'] ?? null) ? $stored['sections'] : []);

        return $s;
    }
}
