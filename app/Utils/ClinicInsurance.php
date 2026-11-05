<?php

namespace App\Utils;

use App\Business;
use App\ClinicClaim;
use App\ClinicInsurer;
use App\ClinicInsuranceShare;
use App\Contact;
use App\PatientDetail;
use App\Transaction;
use App\VisitCharge;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic phase 5: insurance as money the insurer owes.
 *
 * An insured patient's charge becomes two invoices (see
 * HospitalBillingUtil::chargePatientFee):
 *
 *  - the patient's invoice, with the real service lines at the patient's part
 *    of each price (it can be 0 when the insurer pays everything);
 *  - the insurer's invoice, on the insurer's own customer account, with one
 *    "Insurer share" line for the rest.
 *
 * So the patient's balance is their part only, the insurer's balance is what
 * it owes, nothing is booked as paid before money arrives, and sales and
 * product reports count each service once. A ClinicInsuranceShare row links
 * the two and carries the detail the claim form prints.
 *
 * What the insurer still owes is read live from its invoice (total minus
 * payments). Claims (ClinicClaim) batch shares for an insurer; its payment is
 * an ordinary customer payment on its invoices; whatever it refuses is moved
 * to a new invoice on the patient (rebill()).
 */
class ClinicInsurance
{
    /**
     * Currency decimals of the business (shares are rounded to this).
     */
    public static function precision($business_id)
    {
        $p = Business::where('id', $business_id)->value('currency_precision');

        return is_null($p) ? 2 : (int) $p;
    }

    /**
     * New insurer with its own customer account.
     *
     * @param  array  $data  name, default_pct, phone, email, address, notes, is_active
     */
    public static function createInsurer($business_id, array $data, $user_id)
    {
        $contact = (new ContactUtil())->createNewContact([
            'business_id' => $business_id,
            'type' => 'customer',
            'name' => $data['name'],
            'mobile' => $data['phone'] ?? '',
            'email' => $data['email'] ?? null,
            'address_line_1' => $data['address'] ?? null,
            'created_by' => $user_id,
            'contact_status' => 'active',
        ]);
        $contact = $contact['data'] ?? $contact;

        return ClinicInsurer::create([
            'business_id' => $business_id,
            'contact_id' => $contact->id,
            'name' => $data['name'],
            'default_pct' => $data['default_pct'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'created_by' => $user_id,
        ]);
    }

    /**
     * Save insurer changes; its account and its patients' insurer name follow.
     */
    public static function updateInsurer(ClinicInsurer $insurer, array $data)
    {
        $insurer->fill($data);
        $insurer->save();

        Contact::where('id', $insurer->contact_id)->update([
            'name' => $insurer->name,
            'mobile' => $insurer->phone ?? '',
            'email' => $insurer->email,
            'address_line_1' => $insurer->address,
        ]);
        PatientDetail::where('business_id', $insurer->business_id)->where('insurer_id', $insurer->id)
            ->update(['insurance_provider' => $insurer->name]);

        return $insurer;
    }

    /**
     * The patient's cover on a date: the insurer and the % it pays, or null.
     * The patient's own % wins; otherwise the insurer's default. An expiry
     * date is the last covered day.
     */
    public static function coverFor($business_id, $contact_id, $date = null)
    {
        $pd = PatientDetail::where('business_id', $business_id)->where('contact_id', $contact_id)->first();
        if (! $pd) {
            return null;
        }
        // an insurer only typed as text (older records, imports) becomes a real one, as the phase 5 migration does
        if (empty($pd->insurer_id) && trim((string) $pd->insurance_provider) !== '') {
            $name = trim($pd->insurance_provider);
            $insurer = ClinicInsurer::forBusiness($business_id)->where('name', $name)->first()
                ?? self::createInsurer($business_id, ['name' => $name], Business::where('id', $business_id)->value('owner_id'));
            $pd->insurer_id = $insurer->id;
            $pd->save();
        }
        if (empty($pd->insurer_id)) {
            return null;
        }
        $insurer = ClinicInsurer::forBusiness($business_id)->active()->find($pd->insurer_id);
        if (! $insurer) {
            return null;
        }
        $date = ($date ? Carbon::parse($date) : Carbon::today())->startOfDay();
        if ($pd->insurance_expiry_date && $pd->insurance_expiry_date->copy()->startOfDay()->lt($date)) {
            return null;
        }
        $pct = ! is_null($pd->insurance_coverage_pct) ? (float) $pd->insurance_coverage_pct : (float) $insurer->default_pct;
        if ($pct <= 0) {
            return null;
        }

        return ['insurer' => $insurer, 'pct' => min(100.0, $pct), 'number' => $pd->insurance_number];
    }

    /**
     * Splits invoice lines between the patient and the insurer.
     *
     * @param  array  $lines  variation_id, product_id, quantity, unit_price, note
     * @return array [patient_lines, insurer_total, detail]
     */
    public static function split(array $lines, $pct, $precision, $insurer_name)
    {
        $products = DB::table('products')->whereIn('id', array_column($lines, 'product_id'))->get(['id', 'name', 'sku'])->keyBy('id');
        // a package line is named by its product; a generic service (lab, imaging...) by its note (the test)
        $names = [];
        foreach ($lines as $l) {
            $p = $products[$l['product_id']] ?? null;
            $generic = $p && in_array($p->sku, ClinicServices::GENERIC_SKU);
            $names[$l['product_id']] = $p && ! $generic ? $p->name : null;
        }
        $patient_lines = [];
        $detail = [];
        $insurer_total = 0;
        foreach ($lines as $l) {
            $share_unit = round($l['unit_price'] * $pct / 100, $precision);
            $own_unit = round($l['unit_price'] - $share_unit, 4);
            $tag = __('clinic.ins_line_tag', ['insurer' => $insurer_name, 'pct' => self::pct($pct)]);
            $patient_lines[] = array_merge($l, [
                'unit_price' => $own_unit,
                'note' => trim(($l['note'] ? $l['note'].' · ' : '').$tag),
            ]);
            $insurer_total += $share_unit * $l['quantity'];
            $detail[] = [
                'name' => ($names[$l['product_id']] ?? null) ?: ($l['note'] ?: ($products[$l['product_id']]->name ?? '')),
                'qty' => (float) $l['quantity'],
                'price' => (float) $l['unit_price'],
                'share' => round($share_unit * $l['quantity'], 4),
            ];
        }

        return [$patient_lines, round($insurer_total, 4), $detail];
    }

    public static function pct($pct)
    {
        return rtrim(rtrim(number_format((float) $pct, 2, '.', ''), '0'), '.');
    }

    /**
     * What is still unpaid on an invoice (0 when it no longer exists).
     */
    public static function invoiceDue($transaction_id)
    {
        if (empty($transaction_id)) {
            return 0.0;
        }
        $total = Transaction::where('id', $transaction_id)->value('final_total');
        if (is_null($total)) {
            return 0.0;
        }

        return max(0, round((float) $total - self::invoicePaid($transaction_id), 4));
    }

    public static function invoicePaid($transaction_id)
    {
        if (empty($transaction_id)) {
            return 0.0;
        }

        return round((float) DB::table('transaction_payments')->where('transaction_id', $transaction_id)
            ->sum(DB::raw('IF(is_return = 1, -1 * amount, amount)')), 4);
    }

    /**
     * Fills claimed / paid / due / state on each share (live, from the
     * insurer's invoice).
     */
    public static function attach($shares)
    {
        foreach ($shares as $s) {
            $s->paid = self::invoicePaid($s->insurer_transaction_id);
            $s->due = self::invoiceDue($s->insurer_transaction_id);
            $s->claimed = round((float) $s->amount - (float) $s->rebilled_amount, 4);
            if ($s->due <= 0.0001) {
                $s->state = $s->paid > 0 ? 'paid' : 'rebilled';
            } elseif ($s->paid > 0) {
                $s->state = 'partial';
            } else {
                $s->state = 'due';
            }
        }

        return $shares;
    }

    /**
     * Totals of a claim and its state: draft, submitted, partial, paid.
     */
    public static function claimSummary(ClinicClaim $claim, $items = null)
    {
        $items = $items ?? self::attach($claim->items()->get());
        $sum = [
            'count' => $items->count(),
            'full' => round($items->sum('full_amount'), 4),
            'claimed' => round($items->sum('amount'), 4),
            'paid' => round($items->sum('paid'), 4),
            'rebilled' => round($items->sum('rebilled_amount'), 4),
            'due' => round($items->sum('due'), 4),
        ];
        if ($claim->status == 'draft') {
            $sum['state'] = 'draft';
        } elseif ($sum['due'] <= 0.0001) {
            $sum['state'] = 'settled';
        } elseif ($sum['paid'] > 0 || $sum['rebilled'] > 0) {
            $sum['state'] = 'partial';
        } else {
            $sum['state'] = 'submitted';
        }

        return $sum;
    }

    /**
     * What each insurer owes, split into not yet claimed, in a draft claim and
     * claimed (submitted).
     *
     * @return array [insurer_id => [unclaimed, draft, claimed, total]]
     */
    public static function balances($business_id)
    {
        $rows = DB::table('clinic_insurance_shares as s')
            ->leftJoin('clinic_claims as c', 'c.id', '=', 's.claim_id')
            ->join('transactions as t', 't.id', '=', 's.insurer_transaction_id')
            ->where('s.business_id', $business_id)
            ->select('s.insurer_id', 'c.status as claim_status',
                DB::raw('SUM(t.final_total - (SELECT COALESCE(SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)), 0) FROM transaction_payments tp WHERE tp.transaction_id = t.id)) as due'))
            ->groupBy('s.insurer_id', 'c.status')
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $b = $out[$r->insurer_id] ?? ['unclaimed' => 0, 'draft' => 0, 'claimed' => 0, 'total' => 0];
            $key = $r->claim_status == 'submitted' ? 'claimed' : ($r->claim_status == 'draft' ? 'draft' : 'unclaimed');
            $b[$key] += max(0, round((float) $r->due, 4));
            $b['total'] += max(0, round((float) $r->due, 4));
            $out[$r->insurer_id] = $b;
        }

        return $out;
    }

    /**
     * The patient's charges the insurer has not settled yet.
     */
    public static function openSharesFor($business_id, $contact_id)
    {
        return self::attach(ClinicInsuranceShare::forBusiness($business_id)
            ->where('contact_id', $contact_id)
            ->whereNotNull('insurer_transaction_id')
            ->with(['insurer', 'claim', 'patientInvoice'])
            ->orderBy('id')
            ->get())
            ->filter(fn ($s) => $s->due > 0.0001)
            ->values();
    }

    /**
     * Why a patient invoice cannot be cancelled together with its insurer
     * part, or null when it can.
     */
    public static function voidBlocker($patient_transaction_id)
    {
        foreach (ClinicInsuranceShare::where('patient_transaction_id', $patient_transaction_id)->with('claim')->get() as $s) {
            if ($s->claim && $s->claim->status == 'submitted') {
                return __('clinic.ins_void_claimed', ['ref' => $s->claim->ref_no]);
            }
            if (self::invoicePaid($s->insurer_transaction_id) > 0) {
                return __('clinic.ins_void_paid');
            }
        }

        return null;
    }

    /**
     * Cancels the insurer's part of a patient invoice being cancelled.
     *
     * @return array|true  true, or a failed deleteSale() result
     */
    public static function voidFor($business_id, $patient_transaction_id)
    {
        foreach (ClinicInsuranceShare::where('patient_transaction_id', $patient_transaction_id)->get() as $s) {
            if ($s->insurer_transaction_id) {
                $result = (new TransactionUtil())->deleteSale($business_id, $s->insurer_transaction_id);
                if (isset($result['success']) && ! $result['success']) {
                    return $result;
                }
            }
            $s->delete();
        }

        return true;
    }

    /**
     * Payments on a patient invoice that were really the insurer's share, from
     * before phase 5 (it was booked as a payment). 0 for split invoices.
     */
    public static function legacyInsurerPaid($transaction_id)
    {
        if (ClinicInsuranceShare::where('patient_transaction_id', $transaction_id)->exists()) {
            return 0.0;
        }

        return (float) VisitCharge::where('reference_id', $transaction_id)->sum('insurance_covered');
    }

    /**
     * The insurer refuses what it still owes on a charge: its invoice is
     * reduced to what it paid (or cancelled when it paid nothing) and the rest
     * goes on a new invoice on the patient's account.
     *
     * @return \App\Transaction|null  the patient's new invoice
     */
    public static function rebill(ClinicInsuranceShare $share, $reason, $user_id)
    {
        $due = self::invoiceDue($share->insurer_transaction_id);
        if ($due <= 0.0001) {
            return null;
        }
        $business_id = $share->business_id;
        $tu = new TransactionUtil();
        $paid = self::invoicePaid($share->insurer_transaction_id);
        $insurer_tx = Transaction::where('business_id', $business_id)->findOrFail($share->insurer_transaction_id);

        if ($paid <= 0.0001) {
            $result = $tu->deleteSale($business_id, $insurer_tx->id);
            if (isset($result['success']) && ! $result['success']) {
                throw new \Exception($result['msg'] ?? 'insurer invoice could not be cancelled');
            }
            $share->insurer_transaction_id = null;
        } else {
            // its one "Insurer share" line comes down to what was paid
            DB::table('transaction_sell_lines')->where('transaction_id', $insurer_tx->id)->update([
                'unit_price_before_discount' => $paid, 'unit_price' => $paid, 'unit_price_inc_tax' => $paid, 'quantity' => 1,
            ]);
            $insurer_tx->total_before_tax = $paid;
            $insurer_tx->final_total = $paid;
            $insurer_tx->save();
            $tu->updatePaymentStatus($insurer_tx->id, $paid);
        }

        $patient_tx = Transaction::find($share->patient_transaction_id);
        $insurer_name = optional($share->insurer)->name;
        $claim_ref = optional($share->claim)->ref_no;
        $service = ClinicServices::generic($business_id, 'insurance', $user_id);
        $note = __('clinic.ins_rebill_note', ['insurer' => $insurer_name, 'invoice' => optional($patient_tx)->invoice_no])
            .($claim_ref ? ' · '.$claim_ref : '').($reason ? ' · '.$reason : '');

        $invoice = (new HospitalBillingUtil())->chargePatientFee([
            'business_id' => $business_id,
            'contact_id' => $share->contact_id,
            'location_id' => optional($patient_tx)->location_id ?? \App\BusinessLocation::where('business_id', $business_id)->value('id'),
            'created_by' => $user_id,
            'charge_type' => 'other',
            'description' => $note,
            'lines' => [['variation_id' => $service->id, 'product_id' => $service->product_id, 'quantity' => 1, 'unit_price' => $due, 'note' => $note]],
            'visit_id' => $share->visit_id,
            'no_insurance' => true,
            'no_index' => true,
        ]);

        // the visit's running bill keeps the same total; the patient owes more of it
        VisitCharge::where('reference_id', $share->patient_transaction_id)->orderBy('id')->limit(1)->get()->each(function ($vc) use ($due) {
            $vc->insurance_covered = max(0, round((float) $vc->insurance_covered - $due, 4));
            $vc->patient_owes = round((float) $vc->patient_owes + $due, 4);
            $vc->save();
        });

        $share->rebilled_amount = round((float) $share->rebilled_amount + $due, 4);
        $share->rebill_transaction_id = optional($invoice)->id;
        $share->rebill_reason = $reason ? mb_substr($reason, 0, 191) : null;
        $share->rebilled_at = now();
        $share->rebilled_by = $user_id;
        $share->save();

        return $invoice;
    }

    /**
     * Next claim number for the business: CLM-<year>-<0001>.
     */
    public static function nextClaimRef($business_id)
    {
        $year = Carbon::today()->format('Y');
        $n = ClinicClaim::forBusiness($business_id)->where('ref_no', 'like', 'CLM-'.$year.'-%')->count() + 1;
        do {
            $ref = 'CLM-'.$year.'-'.str_pad($n, 4, '0', STR_PAD_LEFT);
            $n++;
        } while (ClinicClaim::forBusiness($business_id)->where('ref_no', $ref)->exists());

        return $ref;
    }

    /**
     * The insurer's charges in a date range that are owed and not in a claim.
     */
    public static function unclaimed($business_id, $insurer_id, $from, $to)
    {
        return ClinicInsuranceShare::forBusiness($business_id)
            ->where('clinic_insurance_shares.insurer_id', $insurer_id)
            ->whereNull('clinic_insurance_shares.claim_id')
            ->whereNotNull('clinic_insurance_shares.insurer_transaction_id')
            ->join('transactions as pt', 'pt.id', '=', 'clinic_insurance_shares.patient_transaction_id')
            ->whereDate('pt.transaction_date', '>=', $from)
            ->whereDate('pt.transaction_date', '<=', $to)
            ->select('clinic_insurance_shares.*')
            ->get()
            ->filter(fn ($s) => self::invoiceDue($s->insurer_transaction_id) > 0.0001)
            ->values();
    }
}
