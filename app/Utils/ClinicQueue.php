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

    /**
     * Does this user receive patients? The same rule as doctors(), for one user (no query over all users):
     * visit.receive_patients, and an Admin only when ticked in Clinic settings.
     */
    public static function isDoctor($user, $business_id)
    {
        if (! $user || empty($business_id)) {
            return false;
        }
        if ($user->hasRole('Admin#'.$business_id)) {
            return in_array($user->id, (array) ClinicSettings::get($business_id, 'admin_doctors'));
        }
        try {
            return $user->hasPermissionTo('visit.receive_patients');
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            return false;
        }
    }

    /**
     * The queue order of a checked-in patient: with the doctor first; then (Clinic setting) booked patients
     * by their appointment time; then walk-ins by number. Used by the doctor queue and the waiting alert.
     */
    public static function orderKey(Visit $v, $booked_first)
    {
        if ($v->queue_status == 'with_doctor') {
            return sprintf('0-%06d', $v->queue_no);
        }
        if ($booked_first && $v->appointment) {
            return '1-'.$v->appointment->start.'-'.sprintf('%06d', $v->queue_no);
        }

        return sprintf('2-%06d', $v->queue_no);
    }

    /**
     * For the waiting alert shown to a doctor on every page: today's patients checked in for them.
     *  - waiting:     ready to be called in, in queue order [{id, queue_no, name, minutes}];
     *  - unpaid:      waiting but held back until they pay (clinic takes payment first);
     *  - with_doctor: patients already marked as "with the doctor" (then the alert is quiet).
     */
    public static function waitingFor($business_id, $user_id)
    {
        $visits = Visit::forBusiness($business_id)
            ->whereDate('visit_date', \Carbon::today()->toDateString())
            ->whereNotNull('queue_no')
            ->where('status', 'open')
            ->where('doctor_id', $user_id)
            ->whereIn('queue_status', ['waiting', 'with_doctor'])
            ->with(['patient', 'appointment', 'feeTransaction'])
            ->get();
        $booked_first = (bool) ClinicSettings::get($business_id, 'appointments_booked_first');
        $visits = $visits->sortBy(fn ($v) => self::orderKey($v, $booked_first))->values();

        $waiting = [];
        $unpaid = 0;
        $with = [];
        foreach ($visits as $v) {
            if ($v->queue_status == 'with_doctor') {
                $with[] = optional($v->patient)->name;

                continue;
            }
            if (self::payGateBlocks($v)) {
                $unpaid++;

                continue;
            }
            $since = $v->created_at ? \Carbon::parse($v->created_at) : null;
            $waiting[] = ['id' => $v->id, 'queue_no' => $v->queue_no, 'name' => optional($v->patient)->name,
                'minutes' => $since ? max(0, (int) $since->diffInMinutes(now())) : 0];
        }

        return ['waiting' => $waiting, 'unpaid' => $unpaid, 'with_doctor' => $with];
    }

    /**
     * The consultations a doctor has not finished: saved to go on later (drafts of the last two weeks, visit
     * still open) and patients called in today or yesterday and still "with the doctor" with nothing saved.
     * For the "Continue" bar shown on every page: [['kind' => draft|open, 'visit_id', 'name', 'time', 'url']].
     */
    public static function openConsultations($business_id, $user_id, $limit = 4)
    {
        $out = [];
        $drafts = \App\Consultation::forBusiness($business_id)->where('status', 'draft')->where('consulted_by', $user_id)
            ->where('updated_at', '>=', now()->subDays(14))
            ->whereHas('visit', fn ($q) => $q->where('status', 'open'))
            ->with('patient')->orderByDesc('updated_at')->limit($limit)->get();
        foreach ($drafts as $c) {
            $out[$c->visit_id] = ['kind' => 'draft', 'visit_id' => $c->visit_id, 'name' => optional($c->patient)->name,
                'time' => $c->updated_at->isToday() ? $c->updated_at->format('H:i') : $c->updated_at->format('d/m H:i'),
                'url' => action([\App\Http\Controllers\ConsultationController::class, 'edit'], [$c->id])];
        }
        $with = Visit::forBusiness($business_id)->where('status', 'open')->where('queue_status', 'with_doctor')->where('doctor_id', $user_id)
            ->whereDate('visit_date', '>=', \Carbon::yesterday()->toDateString())
            ->whereNotIn('id', array_keys($out) ?: [0])
            ->with('patient')->orderByDesc('called_at')->limit($limit)->get();
        foreach ($with as $v) {
            $at = $v->called_at ? \Carbon::parse($v->called_at) : null;
            $out[$v->id] = ['kind' => 'open', 'visit_id' => $v->id, 'name' => optional($v->patient)->name,
                'time' => $at ? ($at->isToday() ? $at->format('H:i') : $at->format('d/m H:i')) : '',
                'url' => action([\App\Http\Controllers\ConsultationController::class, 'create'], ['visit_id' => $v->id])];
        }

        return array_slice(array_values($out), 0, $limit);
    }
}
