<?php

namespace App\Utils;

use App\VisitCharge;

/**
 * Hospital billing (hospital module).
 *
 * Posts each hospital fee (consultation, lab, imaging, ward dispense, etc.) as
 * an ordinary `sell` transaction on the PATIENT contact, exactly like any
 * customer invoice. Since the clinic phase 2 each invoice carries real lines on
 * non-stock service products (App\Utils\ClinicServices), so receipts list what
 * was paid for and product reports count consultations and tests. The patient's "bill" is therefore just their normal
 * customer due (ContactUtil::getContactInfo), collectable through the existing
 * Pay-due flow and visible on the contact ledger — no parallel billing engine.
 *
 * A matching `visit_charge` row is written purely as a per-visit/per-admission
 * INDEX (it labels the charge-transaction and groups it for the running bill
 * and reports). The transaction — not the visit_charge — is the source of truth
 * for money owed and paid.
 *
 * The service products have enable_stock = 0 and lines are never passed to
 * mapPurchaseSell(), so no stock decrement or purchase mapping occurs.
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
     *                        (default 'transaction'), lines (optional),
     *                        no_insurance (true: the patient pays it all),
     *                        no_index (true: no visit_charge row).
     *                        An insured patient's charge is split between
     *                        their invoice (returned) and the insurer's
     *                        (App\Utils\ClinicInsurance).
     * @return \App\Transaction|null  null when amount <= 0 (nothing to charge)
     */
    public function chargePatientFee(array $opts)
    {
        $business_id = $opts['business_id'];
        $contact_id = $opts['contact_id'];
        $location_id = $opts['location_id'];
        $user_id = $opts['created_by'];
        $charge_type = $opts['charge_type'] ?? 'other';
        $description = $opts['description'] ?? __('lang_v1.hospital_charge');

        // PosInnova clinic: invoice lines. A caller may pass its own lines
        // (variation_id, product_id, quantity, unit_price, note); otherwise the
        // whole fee becomes one line on the generic service of its charge type.
        $lines = [];
        foreach (($opts['lines'] ?? []) as $l) {
            $qty = (float) ($l['quantity'] ?? 1);
            $price = (float) ($l['unit_price'] ?? 0);
            if ($qty > 0 && $price > 0) {
                $lines[] = ['variation_id' => $l['variation_id'], 'product_id' => $l['product_id'],
                    'quantity' => $qty, 'unit_price' => $price, 'note' => $l['note'] ?? ''];
            }
        }
        if (! empty($lines)) {
            $amount = round(array_sum(array_map(function ($l) {
                return $l['quantity'] * $l['unit_price'];
            }, $lines)), 4);
        } else {
            $amount = (float) ($opts['amount'] ?? 0);
        }

        if ($amount <= 0) {
            return null;
        }

        if (empty($lines)) {
            $service = ClinicServices::generic($business_id, $charge_type, $user_id);
            $lines[] = ['variation_id' => $service->id, 'product_id' => $service->product_id,
                'quantity' => 1, 'unit_price' => $amount, 'note' => $description];
        }

        // Clinic phase 5: an insured patient's charge is split. The patient's
        // invoice carries the real lines at their part of each price; the
        // insurer's part is an invoice on the insurer's own account, owed
        // until the insurer pays (nothing is booked as paid in advance).
        $cover = empty($opts['no_insurance']) ? ClinicInsurance::coverFor($business_id, $contact_id) : null;
        $insurer_total = 0;
        $detail = [];
        $patient_lines = $lines;
        if ($cover) {
            [$patient_lines, $insurer_total, $detail] = ClinicInsurance::split($lines, $cover['pct'], ClinicInsurance::precision($business_id), $cover['insurer']->name);
            if ($insurer_total <= 0) {
                $cover = null;
                $patient_lines = $lines;
            }
        }
        $patient_amount = $cover ? round($amount - $insurer_total, 4) : $amount;
        $sale_note = $cover ? $description.' · '.__('clinic.ins_sale_note', ['insurer' => $cover['insurer']->name, 'pct' => ClinicInsurance::pct($cover['pct'])]) : $description;

        $transaction = $this->makeInvoice($business_id, $contact_id, $location_id, $user_id, $patient_lines, $patient_amount, $sale_note);

        // Stamp the hospital links (columns added in earlier phases).
        $transaction->visit_id = $opts['visit_id'] ?? null;
        $transaction->admission_id = $opts['admission_id'] ?? null;
        $transaction->save();

        if ($cover) {
            $insurer = $cover['insurer'];
            $patient_name = \App\Contact::where('id', $contact_id)->value('name');
            $insurer_note = $patient_name.' · '.$transaction->invoice_no.' · '.$description;
            $service = ClinicServices::generic($business_id, 'insurance', $user_id);
            $insurer_tx = $this->makeInvoice($business_id, $insurer->contact_id, $location_id, $user_id, [[
                'variation_id' => $service->id, 'product_id' => $service->product_id,
                'quantity' => 1, 'unit_price' => $insurer_total, 'note' => $insurer_note,
            ]], $insurer_total, $insurer_note);

            \App\ClinicInsuranceShare::create([
                'business_id' => $business_id,
                'insurer_id' => $insurer->id,
                'contact_id' => $contact_id,
                'visit_id' => $opts['visit_id'] ?? null,
                'patient_transaction_id' => $transaction->id,
                'insurer_transaction_id' => $insurer_tx->id,
                'pct' => $cover['pct'],
                'full_amount' => $amount,
                'amount' => $insurer_total,
                'lines' => $detail,
            ]);
        }

        // Index row for the per-visit/admission running bill + reports.
        if (empty($opts['no_index'])) {
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
                'insurance_covered' => $insurer_total,
                'patient_owes' => max(0, $amount - $insurer_total),
                'is_paid' => 0,
                'created_by' => $user_id,
            ]);
        }

        return $transaction;
    }

    /**
     * A finished, unpaid sell with the given lines on non-stock services. A
     * zero total (the insurer pays everything) is created as paid.
     */
    private function makeInvoice($business_id, $contact_id, $location_id, $user_id, array $lines, $amount, $note)
    {
        $input = [
            'type' => 'sell',
            'status' => 'final',
            'contact_id' => $contact_id,
            'location_id' => $location_id,
            'transaction_date' => \Carbon::now()->toDateTimeString(),
            'discount_amount' => 0,
            'final_total' => $amount,
            'sale_note' => $note,
        ];
        $invoice_total = ['total_before_tax' => $amount, 'tax' => 0, 'discount' => 0];

        // $uf_data = false: amounts are already in DB format (raw floats).
        $transaction = $this->transactionUtil->createSellTransaction($business_id, $input, $invoice_total, $user_id, false);

        // Real lines on non-stock services: no stock or purchase mapping.
        $this->transactionUtil->createOrUpdateSellLines($transaction, array_map(function ($l) {
            return [
                'product_id' => $l['product_id'],
                'variation_id' => $l['variation_id'],
                'quantity' => $l['quantity'],
                'unit_price' => $l['unit_price'],
                'unit_price_inc_tax' => $l['unit_price'],
                'item_tax' => 0,
                'tax_id' => null,
                'line_discount_type' => null,
                'line_discount_amount' => 0,
                'sell_line_note' => $l['note'],
            ];
        }, $lines), $location_id, false, null, [], false);

        $this->transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);

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

        // One invoice line per test, on the generic lab service.
        $service = ClinicServices::generic($lab_request->business_id, 'lab', $created_by ?? $lab_request->requested_by);
        $lines = [];
        foreach ($lab_request->lines as $test_line) {
            $lines[] = ['variation_id' => $service->id, 'product_id' => $service->product_id,
                'quantity' => 1, 'unit_price' => (float) $test_line->fee,
                'note' => $test_line->test_name.' · '.$lab_request->ref_no];
        }

        // the invoice is kept on the request, so its payment can be shown where the work is done
        $transaction = $this->chargePatientFee([
            'business_id' => $lab_request->business_id,
            'contact_id' => $lab_request->contact_id,
            'amount' => (float) $total,
            'lines' => $lines,
            'charge_type' => 'lab',
            'description' => __('lang_v1.lab_request').' · '.$lab_request->ref_no,
            'location_id' => $location_id,
            'visit_id' => $lab_request->visit_id,
            'admission_id' => null,
            'created_by' => $created_by ?? $lab_request->requested_by,
            'reference_type' => 'lab_request',
        ]);

        $lab_request->fee_charged = 1;
        $lab_request->transaction_id = optional($transaction)->id;
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

        $transaction = $this->chargePatientFee([
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
        $imaging_request->transaction_id = optional($transaction)->id;
        $imaging_request->save();
    }
}
