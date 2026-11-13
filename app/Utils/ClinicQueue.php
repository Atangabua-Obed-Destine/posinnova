<?php

namespace App\Utils;

use App\Transaction;
use App\User;
use App\Visit;

/**
 * PosInnova clinic: check-in queue rules shared by reception, the doctor
 * queue and the consultation screen.
 *
 *  - doctors():   users whose role has visit.receive_patients, plus the
 *                 Admins ticked in Clinic settings (admin_doctors).
 *  - nextQueueNo: the day's running number, one sequence for the whole
 *                 waiting room so a called number is never ambiguous.
 *  - payState / payGateBlocks: when the clinic takes payment before the
 *                 doctor, an unpaid check-in invoice keeps the patient out of
 *                 the consulting room until it is paid or someone with
 *                 visit.override_pay_first lets them through.
 */
class ClinicQueue
{
    /**
     * [user_id => display name] of everyone who receives patients: users whose
     * role has visit.receive_patients, plus the Admins ticked in Clinic
     * settings. Admins pass every permission check (Gate::before), so the role
     * permission alone would list every Admin as a doctor.
     */
    public static function doctors($business_id)
    {
        $admin_doctors = (array) ClinicSettings::get($business_id, 'admin_doctors');

        return User::where('business_id', $business_id)
            ->where('status', 'active')
            ->where('user_type', 'user')
            ->orderBy('first_name')
            // roles and permissions in a few queries for everyone, not two queries per user
            ->with(['roles.permissions', 'permissions'])
            ->get()
            ->filter(function ($user) use ($business_id, $admin_doctors) {
                if ($user->hasRole('Admin#'.$business_id)) {
                    return in_array($user->id, $admin_doctors);
                }
                try {
                    return $user->hasPermissionTo('visit.receive_patients');
                } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
                    return false;
                }
            })
            ->mapWithKeys(function ($user) {
                return [$user->id => trim($user->user_full_name)];
            })
            ->toArray();
    }

    /** Admin users of the business: [user_id => display name]. */
    public static function admins($business_id)
    {
        return User::where('business_id', $business_id)
            ->where('status', 'active')
            ->where('user_type', 'user')
            ->orderBy('first_name')
            ->with('roles')
            ->get()
            ->filter(function ($user) use ($business_id) {
                return $user->hasRole('Admin#'.$business_id);
            })
            ->mapWithKeys(function ($user) {
                return [$user->id => trim($user->user_full_name)];
            })
            ->toArray();
    }

    /** Next queue number for the day. Call inside a DB transaction. */
    public static function nextQueueNo($business_id, $date)
    {
        $max = Visit::where('business_id', $business_id)
            ->whereDate('visit_date', $date)
            ->lockForUpdate()
            ->max('queue_no');

        return ((int) $max) + 1;
    }

    /** The open check-in a patient already has today, if any. */
    public static function activeCheckIn($business_id, $contact_id, $date)
    {
        return Visit::where('business_id', $business_id)
            ->where('contact_id', $contact_id)
            ->whereDate('visit_date', $date)
            ->where('status', 'open')
            ->whereIn('queue_status', ['waiting', 'with_doctor'])
            ->first();
    }

    /**
     * Payment state of the check-in invoice: 'none' (nothing to pay),
     * 'paid', 'partial' or 'due'.
     */
    public static function payState(Visit $visit)
    {
        if (empty($visit->fee_transaction_id)) {
            return 'none';
        }

        $transaction = $visit->relationLoaded('feeTransaction')
            ? $visit->feeTransaction
            : Transaction::find($visit->fee_transaction_id);

        if (! $transaction || $transaction->final_total <= 0) {
            return 'none';
        }

        return in_array($transaction->payment_status, ['paid', 'partial']) ? $transaction->payment_status : 'due';
    }

    /** True when the patient may not see the doctor yet. */
    public static function payGateBlocks(Visit $visit)
    {
        if (! ClinicSettings::get($visit->business_id, 'pay_first')) {
            return false;
        }
        if (! empty($visit->pay_override_at)) {
            return false;
        }

        return in_array(self::payState($visit), ['due', 'partial']);
    }
}
