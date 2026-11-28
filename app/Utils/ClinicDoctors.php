<?php

namespace App\Utils;

use App\User;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: a doctor's specialty (users.clinic_specialty).
 *
 * Empty = a general doctor. A value is one of the clinic's specialty list
 * (ClinicOptions 'specialty'); the "general medicine" entries of that list
 * mean "general" too, so a doctor who picks them is a general doctor.
 * Used on the public booking page, at check-in, in the diary, by the
 * WhatsApp bot and as the default of a new consultation.
 */
class ClinicDoctors
{
    /** Lowercased, accent-free names that mean "a general doctor". */
    const GENERAL = ['general', 'general medicine', 'general practice', 'general practitioner', 'gp',
        'medecine generale', 'generaliste', 'medecin generaliste'];

    public static function fold($value)
    {
        $v = mb_strtolower(trim((string) $value));
        $v = strtr($v, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c']);

        return trim(preg_replace('/\s+/u', ' ', $v));
    }

    public static function isGeneral($value)
    {
        $v = self::fold($value);

        return $v === '' || in_array($v, self::GENERAL, true);
    }

    /** The specialty of one doctor, null for a general doctor. */
    public static function specialty($user_id)
    {
        if (empty($user_id)) {
            return null;
        }
        $v = DB::table('users')->where('id', $user_id)->value('clinic_specialty');

        return self::isGeneral($v) ? null : $v;
    }

    /** [user_id => specialty or null] for the given doctor ids. */
    public static function specialties($user_ids)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $user_ids)));
        if (empty($ids)) {
            return [];
        }
        $rows = DB::table('users')->whereIn('id', $ids)->pluck('clinic_specialty', 'id')->all();
        $out = [];
        foreach ($ids as $id) {
            $v = $rows[$id] ?? null;
            $out[$id] = self::isGeneral($v) ? null : $v;
        }

        return $out;
    }

    /** The specialties a doctor can pick (the clinic's list without the "general" entries). */
    public static function choices($business_id)
    {
        return array_values(array_filter(ClinicOptions::values($business_id, 'specialty'), function ($v) {
            return ! self::isGeneral($v);
        }));
    }

    /** "General doctor" or the specialty. */
    public static function label($specialty)
    {
        return self::isGeneral($specialty) ? __('clinic.sp_general') : $specialty;
    }

    /**
     * Sets a doctor's specialty. An empty or "general" value makes him a general
     * doctor; a value not in the clinic's list is added to it (as typed).
     *
     * @return string|null the value stored
     */
    public static function set($business_id, $user_id, $value, $actor_id = null)
    {
        $value = mb_substr(ClinicOptions::clean($value), 0, 100);
        if (self::isGeneral($value)) {
            $value = null;
        } else {
            $known = null;
            foreach (self::choices($business_id) as $c) {
                if (self::fold($c) === self::fold($value)) {
                    $known = $c;
                    break;
                }
            }
            $value = $known ?? mb_substr((string) ClinicOptions::add($business_id, 'specialty', $value, $actor_id), 0, 100);
        }
        User::where('business_id', $business_id)->where('id', $user_id)->update(['clinic_specialty' => $value]);

        return $value;
    }
}
