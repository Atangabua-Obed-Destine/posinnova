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
        // Insurance
        'insurance_covers_plans' => false, // true: an insured patient's monthly plan invoice is split with the insurer too
        // Hospital sections shown in the menu (a consultation centre turns these off)
        'sections' => ['wards' => true, 'theatre' => true, 'maternity' => true, 'morgue' => true],
        // Patient portal
        'portal_enabled' => true,
        'portal_idle_minutes' => 30,       // signed out after this long without activity
        'portal_consultation_detail' => 'diagnosis', // 'diagnosis' only, or 'full' notes
    ];

    const SECTIONS = ['wards', 'theatre', 'maternity', 'morgue'];

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
        foreach (self::SECTIONS as $sec) {
            $s['sections'][$sec] = ! empty($input['sections'][$sec]);
        }
        $s['portal_enabled'] = ! empty($input['portal_enabled']);
        $s['portal_idle_minutes'] = max(5, min(240, (int) ($input['portal_idle_minutes'] ?? 30)));
        $s['portal_consultation_detail'] = ($input['portal_consultation_detail'] ?? '') === 'full' ? 'full' : 'diagnosis';

        Business::where('id', $business_id)->update(['hospital_settings' => json_encode($s)]);
        unset(self::$cache[$business_id]);

        return $s;
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
