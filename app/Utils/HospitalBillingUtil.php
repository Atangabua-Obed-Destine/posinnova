<?php

namespace App\Utils;

use App\VisitCharge;

/**
 * Hospital billing (hospital module).
 *
 * Posts each hospital fee (consultation, lab, imaging, ward dispense, etc.) as
 * an ordinary line-less `sell` transaction on the PATIENT contact, exactly like
 * any customer invoice. The patient's "bill" is therefore just their normal
 * customer due (ContactUtil::getContactInfo), collectable through the existing
 * Pay-due flow and visible on the contact ledger — no parallel billing engine.
 *
 * A matching `visit_charge` row is written purely as a per-visit/per-admission
 * INDEX (it labels the charge-transaction and groups it for the running bill
 * and reports). The transaction — not the visit_charge — is the source of truth
 * for money owed and paid.
 *
 * Line-less sell transactions are safe: TransactionUtil::mapPurchaseSell()
 * returns early on empty lines, so no stock decrement or accounting side-effects
 * occur.
 */
class HospitalBillingUtil extends Util
{
    protected $transactionUtil;

    public function __construct()
    {
        $this->transactionUtil = new TransactionUtil();
    }

    /**
     * Charge a fee to a patient. Creates a finalized, unpaid (`due`) sell
     * transaction on the patient contact and records the visit_charge index.
     *
     * @param  array  $opts  keys: business_id, contact_id, amount, charge_type,
     *                        description, location_id, visit_id (null),
     *                        admission_id (null), created_by, reference_type
     *                        (default 'transaction').
     * @return \App\Transaction|null  null when amount <= 0 (nothing to charge)
     */
    public function chargePatientFee(array $opts)
    {
        $amount = (float) ($opts['amount'] ?? 0);
        if ($amount <= 0) {
            return null;
        }

        $business_id = $opts['business_id'];
        $contact_id = $opts['contact_id'];
        $location_id = $opts['location_id'];
        $user_id = $opts['created_by'];
        $charge_type = $opts['charge_type'] ?? 'other';
        $description = $opts['description'] ?? __('lang_v1.hospital_charge');

        $input = [
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contact_id,
            'location_id' => $location_id,
            'transaction_date' => \Carbon::now()->toDateTimeString(),
            'discount_amount' => 0,
            // The fee is the whole invoice; no tax, no lines.
            'final_total' => $amount,
            'sale_note' => $description,
        ];

        $invoice_total = [
            'total_before_tax' => $amount,
            'tax' => 0,
            'discount' => 0,
        ];

        // $uf_data = false: amounts are already in DB format (raw floats).
        $transaction = $this->transactionUtil->createSellTransaction($business_id, $input, $invoice_total, $user_id, false);

        // Stamp the hospital links (columns added in earlier phases).
        $transaction->visit_id = $opts['visit_id'] ?? null;
        $transaction->admission_id = $opts['admission_id'] ?? null;
        $transaction->save();

        // No payments yet -> payment_status becomes 'due'.
        $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

        // Insurance auto-split: if the patient has active coverage, the insurer
        // share is recorded as an advance payment so the patient only owes the
        // co-pay. Coverage applies only while not expired.
        $insurance_covered = 0;
        $pd = \App\PatientDetail::where('contact_id', $contact_id)->first();
        if ($pd && $pd->insurance_coverage_pct > 0
            && (empty($pd->insurance_expiry_date) || $pd->insurance_expiry_date->isFuture())) {
            $insurance_covered = round($amount * ($pd->insurance_coverage_pct / 100), 4);
            if ($insurance_covered > 0) {
                $this->transactionUtil->createOrUpdatePaymentLines($transaction, [[
                    'amount' => $insurance_covered,
                    'method' => 'other',
                    'note' => __('lang_v1.insurance_paid', ['provider' => $pd->insurance_provider ?: 'Insurer']),
                    'paid_on' => \Carbon::now()->toDateTimeString(),
                ]], $business_id, $user_id, false);
                $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
            }
        }

        // Index row for the per-visit/admission running bill + reports.
        VisitCharge::record([
            'business_id' => $business_id,
            'visit_id' => $opts['visit_id'] ?? null,
            'admission_id' => $opts['admission_id'] ?? null,
            'contact_id' => $contact_id,
            'charge_type' => $charge_type,
            'reference_id' => $transaction->id,
            'reference_type' => $opts['reference_type'] ?? 'transaction',
            'description' => $description,
            'amount' => $amount,
            'insurance_covered' => $insurance_covered,
            'patient_owes' => max(0, $amount - $insurance_covered),
            'is_paid' => 0,
            'created_by' => $user_id,
        ]);

        return $transaction;
    }

    /**
     * Charge a lab request's total fee to the patient. Idempotent via the
     * lab_requests.fee_charged flag.
     */
    public function chargeLabRequest($lab_request, $created_by = null)
    {
        if ($lab_request->fee_charged) {
            return;
        }

        $total = $lab_request->lines->sum('fee');
        if ($total <= 0) {
            $lab_request->fee_charged = 1;
            $lab_request->save();

            return;
        }

        $location_id = $lab_request->location_id
            ?? \App\BusinessLocation::where('business_id', $lab_request->business_id)->value('id');
        if (empty($location_id)) {
            return;
        }

        $this->chargePatientFee([
            'business_id' => $lab_request->business_id,
            'contact_id' => $lab_request->contact_id,
            'amount' => (float) $total,
            'charge_type' => 'lab',
            'description' => __('lang_v1.lab_request').' · '.$lab_request->ref_no,
            'location_id' => $location_id,
            'visit_id' => $lab_request->visit_id,
            'admission_id' => null,
            'created_by' => $created_by ?? $lab_request->requested_by,
            'reference_type' => 'lab_request',
        ]);

        $lab_request->fee_charged = 1;
        $lab_request->save();
    }

    /**
     * Charge an imaging request's fee to the patient. Idempotent via the
     * imaging_requests.fee_charged flag.
     */
    public function chargeImagingRequest($imaging_request, $created_by = null)
    {
        if ($imaging_request->fee_charged) {
            return;
        }

        $fee = (float) $imaging_request->fee;
        if ($fee <= 0) {
            $imaging_request->fee_charged = 1;
            $imaging_request->save();

            return;
        }

        $location_id = $imaging_request->location_id
            ?? \App\BusinessLocation::where('business_id', $imaging_request->business_id)->value('id');
        if (empty($location_id)) {
            return;
        }

        $this->chargePatientFee([
            'business_id' => $imaging_request->business_id,
            'contact_id' => $imaging_request->contact_id,
            'amount' => $fee,
            'charge_type' => 'imaging',
            'description' => $imaging_request->study_name.' · '.$imaging_request->ref_no,
            'location_id' => $location_id,
            'visit_id' => $imaging_request->visit_id,
            'admission_id' => null,
            'created_by' => $created_by ?? $imaging_request->requested_by,
            'reference_type' => 'imaging_request',
        ]);

        $imaging_request->fee_charged = 1;
        $imaging_request->save();
    }
}
