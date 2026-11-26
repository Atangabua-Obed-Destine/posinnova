<?php

namespace App\Utils;

use App\Consultation;
use App\Transaction;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: the full consultation report as a paid extra.
 *
 * Patients go home with the prescription only (free). A patient who wants the whole clinical
 * assessment pays the clinic's report fee (Clinic settings), once per consultation:
 *   - fee 0 (the default)     -> state 'free': the report prints as before;
 *   - not charged yet         -> 'not_charged': staff add the fee to the patient's bill;
 *   - charged, not fully paid -> 'due': collected like any other charge, printing stays closed;
 *   - paid                    -> 'paid': the report prints (and reprints).
 * Once charged, the invoice decides, even if the fee is later set back to 0.
 */
class ConsultationReportFee
{
    public static function fee($business_id)
    {
        return max(0, (float) ClinicSettings::get($business_id, 'report_fee'));
    }

    /** The invoice charged for this consultation's report, if it still exists. */
    public static function invoice(Consultation $c)
    {
        if (empty($c->report_fee_transaction_id)) {
            return null;
        }

        return Transaction::where('business_id', $c->business_id)->where('type', 'sell')
            ->find($c->report_fee_transaction_id);
    }

    public static function state(Consultation $c, $invoice = false)
    {
        $invoice = $invoice === false ? self::invoice($c) : $invoice;
        if ($invoice) {
            return $invoice->payment_status == 'paid' ? 'paid' : 'due';
        }

        return self::fee($c->business_id) > 0 ? 'not_charged' : 'free';
    }

    public static function mayPrint(Consultation $c)
    {
        return in_array(self::state($c), ['free', 'paid']);
    }

    /**
     * The patient portal shows the full report of a finished consultation once its fee is paid. Where the
     * clinic charges nothing, the Clinic settings choice decides ("full" notes or the diagnosis only).
     */
    public static function portalMayView(Consultation $c)
    {
        if ($c->status != 'final') {
            return false;
        }
        $state = self::state($c);

        return $state === 'paid'
            || ($state === 'free' && ClinicSettings::get($c->business_id, 'portal_consultation_detail') === 'full');
    }

    /** activity_log description of a full report printed by staff without charge. */
    const STAFF_COPY = 'report_staff_copy';

    /** Clinic admins may print a staff copy without charge (referral, transfer), with a reason. */
    public static function mayPrintStaffCopy($user = null)
    {
        $user = $user ?: auth()->user();

        return $user && $user->can('clinic_settings.manage');
    }

    public static function recordStaffCopy(Consultation $c, $reason)
    {
        (new Util())->activityLog($c, self::STAFF_COPY, null, ['reason' => $reason], false, $c->business_id);
    }

    /** Staff copies printed without charge, oldest first. */
    public static function staffCopies(Consultation $c)
    {
        return \Spatie\Activitylog\Models\Activity::where('subject_type', $c->getMorphClass())->where('subject_id', $c->id)
            ->where('description', self::STAFF_COPY)->with('causer')->orderBy('id')->get();
    }

    /**
     * Adds the report fee to the patient's bill (an unpaid sale on the visit). Charged once: a second
     * call returns the invoice already made. Null when there is nothing to charge.
     */
    public static function charge(Consultation $c, $user_id)
    {
        return DB::transaction(function () use ($c, $user_id) {
            $c = Consultation::where('id', $c->id)->lockForUpdate()->first();
            if ($invoice = self::invoice($c)) {
                return $invoice;
            }
            $fee = self::fee($c->business_id);
            if ($fee <= 0) {
                return null;
            }
            $visit = $c->visit;
            $location_id = optional($visit)->location_id
                ?? \App\BusinessLocation::where('business_id', $c->business_id)->value('id');
            if (empty($location_id)) {
                return null;
            }

            $invoice = (new HospitalBillingUtil())->chargePatientFee([
                'business_id' => $c->business_id,
                'contact_id' => $c->contact_id,
                'amount' => $fee,
                'charge_type' => 'report',
                'description' => __('clinic.service_report').' · '.$c->ref_no,
                'location_id' => $location_id,
                'visit_id' => $c->visit_id,
                'admission_id' => null,
                'created_by' => $user_id,
                'reference_type' => 'consultation_report',
                'no_insurance' => true,
            ]);
            if ($invoice) {
                $c->report_fee_transaction_id = $invoice->id;
                $c->save();
            }

            return $invoice;
        });
    }
}
