<?php

namespace App\Utils;

use App\Consultation;
use App\ConsultationPrescription;
use App\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova clinic: what was given against each prescription line.
 *
 * The quantity given is never stored as a running total. It is read from the
 * POS sale lines linked to the prescription line, counting only finished
 * sales (type sell, status final) minus returned quantities, plus the
 * legacy_given of lines marked dispensed before per-line tracking. Drafts,
 * quotations, deleted sales and returns therefore need no bookkeeping.
 *
 * Line states:
 *   waiting       nothing given yet, still to dispense
 *   partly_given  some given; still waiting unless the line was closed
 *   given         given quantity reached the prescribed quantity
 *   not_given     closed by the dispensary with nothing given
 */
class Dispensing
{
    /**
     * Quantity given per prescription line id, from finished sales.
     *
     * @return array [prescription_id => float]
     */
    public static function givenFromSales(array $rx_ids)
    {
        if (empty($rx_ids)) {
            return [];
        }

        return DB::table('transaction_sell_lines as tsl')
            ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
            ->whereIn('tsl.consultation_prescription_id', $rx_ids)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereNull('tsl.parent_sell_line_id')
            ->groupBy('tsl.consultation_prescription_id')
            ->select('tsl.consultation_prescription_id as rx_id', DB::raw('SUM(tsl.quantity - tsl.quantity_returned) as given'))
            ->pluck('given', 'rx_id')
            ->map(fn ($q) => (float) $q)
            ->all();
    }

    /**
     * Sets given_qty, outstanding_qty and dispense_state on each line.
     *
     * @param  Collection|ConsultationPrescription[]  $prescriptions
     * @return Collection
     */
    public static function attach($prescriptions)
    {
        $prescriptions = collect($prescriptions);
        $given = self::givenFromSales($prescriptions->pluck('id')->all());

        foreach ($prescriptions as $rx) {
            $qty = (float) $rx->quantity;
            $got = max(0, ($given[$rx->id] ?? 0) + (float) $rx->legacy_given);
            $rx->given_qty = $got;
            $rx->outstanding_qty = $rx->closed_at ? 0 : max(0, $qty - $got);

            if ($qty > 0 && $got >= $qty) {
                $rx->dispense_state = 'given';
            } elseif ($got > 0) {
                $rx->dispense_state = 'partly_given';
            } elseif ($rx->closed_at) {
                $rx->dispense_state = 'not_given';
            } else {
                $rx->dispense_state = 'waiting';
            }
        }

        return $prescriptions;
    }

    /**
     * Links the lines of a finished sale made from a consultation to the
     * prescription lines they fill, by variation, first outstanding line
     * first. Lines already linked are kept; a line that matches nothing (a
     * substitute, an extra item) is left unlinked. Then refreshes the
     * is_dispensed flag on the consultation's lines.
     */
    public static function allocate(Transaction $transaction)
    {
        if (empty($transaction->consultation_id)) {
            return;
        }

        if ($transaction->type == 'sell' && $transaction->status == 'final') {
            $prescriptions = self::attach(
                ConsultationPrescription::where('consultation_id', $transaction->consultation_id)->orderBy('id')->get()
            );
            $remaining = $prescriptions->mapWithKeys(fn ($rx) => [$rx->id => $rx->outstanding_qty])->all();

            $sell_lines = DB::table('transaction_sell_lines')
                ->where('transaction_id', $transaction->id)
                ->whereNull('parent_sell_line_id')
                ->whereNull('consultation_prescription_id')
                ->orderBy('id')
                ->get(['id', 'variation_id', 'quantity']);

            foreach ($sell_lines as $line) {
                $match = $prescriptions->first(fn ($rx) => $rx->variation_id == $line->variation_id && ($remaining[$rx->id] ?? 0) > 0);
                if (! $match) {
                    continue;
                }
                DB::table('transaction_sell_lines')->where('id', $line->id)
                    ->update(['consultation_prescription_id' => $match->id]);
                $remaining[$match->id] = max(0, $remaining[$match->id] - (float) $line->quantity);
            }
        }

        self::refreshFlags($transaction->consultation_id, $transaction->created_by);
    }

    /**
     * Keeps the old is_dispensed / dispensed_at columns meaningful for any
     * reader that still uses them: set when nothing more is waiting.
     */
    public static function refreshFlags($consultation_id, $user_id = null)
    {
        $prescriptions = self::attach(ConsultationPrescription::where('consultation_id', $consultation_id)->get());
        foreach ($prescriptions as $rx) {
            $done = $rx->outstanding_qty <= 0 && $rx->given_qty > 0;
            if ($done && ! $rx->is_dispensed) {
                DB::table('consultation_prescriptions')->where('id', $rx->id)
                    ->update(['is_dispensed' => 1, 'dispensed_at' => now(), 'dispensed_by' => $user_id]);
            } elseif (! $done && $rx->is_dispensed) {
                DB::table('consultation_prescriptions')->where('id', $rx->id)
                    ->update(['is_dispensed' => 0, 'dispensed_at' => null, 'dispensed_by' => null]);
            }
        }
    }

    /**
     * Readable close reason: "out_of_stock: note" -> "Out of stock: note".
     */
    public static function reasonLabel($close_reason)
    {
        if (empty($close_reason)) {
            return '';
        }
        $parts = explode(': ', $close_reason, 2);
        $key = $parts[0];
        $label = in_array($key, \App\Http\Controllers\ClinicDispensaryController::CLOSE_REASONS) ? __('clinic.rx_reason_'.$key) : $key;

        return isset($parts[1]) && $parts[1] !== '' ? $label.': '.$parts[1] : $label;
    }

    /**
     * Prescription lines still waiting in a consultation.
     */
    public static function waitingLines(Consultation $consultation)
    {
        return self::attach($consultation->prescriptions)->filter(fn ($rx) => $rx->outstanding_qty > 0)->values();
    }

    /**
     * SQL fragment: number of lines of consultation c still waiting (not
     * closed and given less than prescribed). Used by the dispensary list.
     */
    public static function waitingCountSql()
    {
        return "(SELECT COUNT(*) FROM consultation_prescriptions rx
            WHERE rx.consultation_id = consultations.id AND rx.closed_at IS NULL
            AND rx.quantity > COALESCE(rx.legacy_given, 0) + COALESCE((
                SELECT SUM(tsl.quantity - tsl.quantity_returned) FROM transaction_sell_lines tsl
                JOIN transactions t ON t.id = tsl.transaction_id
                WHERE tsl.consultation_prescription_id = rx.id AND tsl.parent_sell_line_id IS NULL
                AND t.type = 'sell' AND t.status = 'final'), 0))";
    }
}
