<?php

namespace App\Utils;

use App\Contact;
use App\Events\TransactionPaymentAdded;
use App\Events\TransactionPaymentDeleted;
use App\HotelFolioLine;
use App\HotelReservation;
use App\HotelRoomType;
use App\Transaction;
use App\TransactionPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova hotel: the guest's bill (folio).
 *
 * Every charge (a room night, laundry, a late checkout…) is an ordinary final
 * `sell` invoice on the guest's contact, with a line on a non-stock hotel
 * service product; every payment an ordinary transaction_payment. Reports,
 * the cash register, the contact ledger and the Financial summary therefore
 * see hotel money without anything special.
 *
 * A payment first settles the stay's unpaid invoices (oldest first). What is
 * left (a deposit before any night is charged) becomes an advance on the
 * guest's contact, counted on the stay as `credit`, and is used up by the
 * next invoices of the stay as they are posted (payment method "advance").
 *
 *   balance due = what the stay's invoices still owe − the stay's credit
 */
class HotelFolio
{
    protected $tu;

    public function __construct()
    {
        $this->tu = new TransactionUtil();
    }

    // ---------------------------------------------------------------- charges

    /** Posts the room night of $night (once: a night already on the bill is skipped). Returns the folio line or null. */
    public function postNight(HotelReservation $res, $night, $user_id)
    {
        $night = Carbon::parse($night)->toDateString();
        if (HotelFolioLine::where('reservation_id', $res->id)->where('night_key', $night)->exists()) {
            return null;
        }
        $type = HotelRoomType::find($res->room_type_id);
        $room = optional($res->room)->number;
        $label = __('hotel.line_night', ['room' => $room ?: '—', 'date' => Carbon::parse($night)->format('d/m'), 'plan' => __('hotel.plan_'.$res->rate_plan)]);
        $lines = [];
        if ((float) $res->rate > 0) {
            $v = HotelServices::roomNight($type, $user_id);
            $lines[] = ['variation' => $v, 'price' => (float) $res->rate, 'note' => $label];
        }
        if ((float) $type->tourist_tax > 0) {
            $v = HotelServices::extra($res->business_id, 'tourist_tax', $user_id);
            $lines[] = ['variation' => $v, 'price' => (float) $type->tourist_tax, 'note' => __('hotel.line_tax', ['date' => Carbon::parse($night)->format('d/m')])];
        }

        try {
            return $this->invoice($res, $lines, 'night', 'room', $label, $user_id, $night);
        } catch (\Illuminate\Database\QueryException $e) {
            // posted at the same moment by another run: the unique night_key kept it single
            if (HotelFolioLine::where('reservation_id', $res->id)->where('night_key', $night)->exists()) {
                return null;
            }
            throw $e;
        }
    }

    /** An extra on the bill: laundry, minibar, extra_bed, phone, damage, other, late_checkout. */
    public function postCharge(HotelReservation $res, $item, $amount, $note, $user_id)
    {
        if ($res->status !== 'checked_in') {
            throw new HotelException(__('hotel.err_not_in'));
        }
        $amount = round((float) $amount, 4);
        if ($amount <= 0) {
            throw new HotelException(__('hotel.err_amount'));
        }
        $kind = $item === 'late_checkout' ? 'late_checkout' : 'charge';
        if ($kind === 'charge' && ! in_array($item, Hotel::EXTRAS, true)) {
            throw new HotelException(__('hotel.err_item'));
        }
        $v = HotelServices::extra($res->business_id, $item, $user_id);
        $label = __('hotel.item_'.$item).($note ? ' · '.$note : '');

        return $this->invoice($res, [['variation' => $v, 'price' => $amount, 'note' => mb_substr($label, 0, 180)]], $kind, $item, $label, $user_id);
    }

    /** Creates the invoice, links it to the stay, then uses the stay's credit on it. */
    private function invoice(HotelReservation $res, array $lines, $kind, $item, $description, $user_id, $night = null)
    {
        return DB::transaction(function () use ($res, $lines, $kind, $item, $description, $user_id, $night) {
            $total = round(array_sum(array_column($lines, 'price')), 4);
            $tx = null;
            if ($total > 0) {
                $input = [
                    'type' => 'sell', 'status' => 'final', 'contact_id' => $res->contact_id, 'location_id' => $res->location_id,
                    'transaction_date' => now()->toDateTimeString(), 'discount_amount' => 0, 'final_total' => $total,
                    'sale_note' => $res->ref_no.' · '.$description,
                ];
                $tx = $this->tu->createSellTransaction($res->business_id, $input, ['total_before_tax' => $total, 'tax' => 0, 'discount' => 0], $user_id, false);
                $this->tu->createOrUpdateSellLines($tx, array_map(fn ($l) => [
                    'product_id' => $l['variation']->product_id, 'variation_id' => $l['variation']->id, 'quantity' => 1,
                    'unit_price' => $l['price'], 'unit_price_inc_tax' => $l['price'], 'item_tax' => 0, 'tax_id' => null,
                    'line_discount_type' => null, 'line_discount_amount' => 0, 'sell_line_note' => $l['note'],
                ], $lines), $res->location_id, false, null, [], false);
                $this->tu->updatePaymentStatus($tx->id, $tx->final_total);
            }

            $line = HotelFolioLine::create([
                'business_id' => $res->business_id, 'reservation_id' => $res->id, 'kind' => $kind, 'item' => $item,
                'night_key' => $night, 'transaction_id' => optional($tx)->id, 'amount' => $total,
                'description' => mb_substr($description, 0, 255), 'created_by' => $user_id,
            ]);

            if ($tx) {
                $this->applyCredit($res->fresh(), $user_id);
            }

            return $line;
        });
    }

    // ---------------------------------------------------------------- reading the bill

    /** The stay's invoices (not voided) with what each still owes, oldest first. */
    public function invoices(HotelReservation $res)
    {
        $ids = HotelFolioLine::where('reservation_id', $res->id)->whereNull('voided_at')->whereNotNull('transaction_id')->pluck('transaction_id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return Transaction::whereIn('id', $ids)->where('type', 'sell')->orderBy('transaction_date')->orderBy('id')->get()
            ->map(function ($t) {
                $t->paid_amount = round((float) $this->tu->getTotalPaid($t->id), 4);
                $t->due = round((float) $t->final_total - $t->paid_amount, 4);

                return $t;
            });
    }

    public function balance(HotelReservation $res)
    {
        return round($this->invoices($res)->sum('due') - (float) $res->credit, 4);
    }

    /**
     * The bill as the guest reads it: charges and money received, in order, with a running balance.
     *
     * @return array ['lines' => [...], 'charges' => x, 'payments' => y, 'credit' => c, 'balance' => b]
     */
    public function statement(HotelReservation $res)
    {
        $invoices = $this->invoices($res)->keyBy('id');
        $rows = [];
        foreach (HotelFolioLine::where('reservation_id', $res->id)->with('payment')->orderBy('created_at')->orderBy('id')->get() as $l) {
            if (in_array($l->kind, ['night', 'charge', 'late_checkout'], true)) {
                $tx = $l->transaction_id ? $invoices->get($l->transaction_id) : null;
                $rows[] = [
                    'id' => $l->id, 'at' => $l->created_at, 'kind' => $l->kind, 'item' => $l->item, 'night' => $l->night_key,
                    'what' => $l->description, 'charge' => $l->voided_at ? 0 : (float) optional($tx)->final_total,
                    'original' => (float) $l->amount, 'discount' => $tx ? round((float) $l->amount - (float) $tx->final_total, 4) : 0,
                    'pay' => 0, 'voided' => (bool) $l->voided_at, 'void_reason' => $l->void_reason,
                    'invoice_no' => optional($tx)->invoice_no, 'transaction_id' => $l->transaction_id, 'due' => $tx ? $tx->due : 0,
                    'paid' => $tx ? $tx->paid_amount : 0,
                ];
            } elseif ($l->kind === 'payment') {
                $rows[] = [
                    'id' => $l->id, 'at' => $l->created_at, 'kind' => 'payment', 'item' => optional($l->payment)->method, 'night' => null,
                    'what' => $l->description, 'charge' => 0, 'pay' => (float) $l->amount, 'voided' => false,
                    'payment_ref' => optional($l->payment)->payment_ref_no, 'transaction_id' => null,
                ];
            }
        }
        $charges = round(array_sum(array_column($rows, 'charge')), 4);
        $payments = round(array_sum(array_column($rows, 'pay')), 4);
        $balance = $this->balance($res);
        // money taken on these invoices at another desk (Pay due, POS): shown so the sums agree
        $elsewhere = round($charges - $payments - $balance, 4);
        if (abs($elsewhere) > 0.004) {
            $rows[] = ['id' => 0, 'at' => now(), 'kind' => 'elsewhere', 'item' => null, 'night' => null, 'what' => __('hotel.paid_elsewhere'),
                'charge' => 0, 'pay' => $elsewhere, 'voided' => false, 'transaction_id' => null];
            $payments = round($payments + $elsewhere, 4);
        }
        $run = 0;
        foreach ($rows as &$r) {
            $run = round($run + $r['charge'] - $r['pay'], 4);
            $r['balance'] = $run;
        }

        return ['lines' => $rows, 'charges' => $charges, 'payments' => $payments, 'credit' => (float) $res->credit, 'balance' => $balance];
    }

    // ---------------------------------------------------------------- money in

    /**
     * Money received for the stay. Settles its unpaid invoices oldest first; the rest is kept as credit
     * for the next charges. Posts to the user's open cash register. Returns ['paid' => x, 'credit' => y, 'ref' => …].
     */
    public function receive(HotelReservation $res, $amount, $method, $note, $user_id, array $extra = [])
    {
        $amount = round((float) $amount, 4);
        if ($amount <= 0) {
            throw new HotelException(__('hotel.err_amount'));
        }
        if (in_array($res->status, ['cancelled', 'no_show'], true)) {
            throw new HotelException(__('hotel.err_closed'));
        }
        $types = $this->tu->payment_types(null, false, $res->business_id);
        if ($method === 'advance' || ! array_key_exists($method, $types)) {
            throw new HotelException(__('hotel.err_method'));
        }
        $label = __('hotel.line_payment', ['method' => $types[$method]]).($note ? ' · '.$note : '');

        return DB::transaction(function () use ($res, $amount, $method, $note, $user_id, $extra, $label) {
            $res = HotelReservation::lockForUpdate()->find($res->id);
            $due = $this->invoices($res)->filter(fn ($t) => $t->due > 0.004)->values();
            $alloc = ClinicCashier::allocateOldestFirst($due, $amount);
            $paid = round(array_sum($alloc), 4);
            $refs = [];

            if ($paid > 0) {
                $req = new Request(array_merge(['contact_id' => $res->contact_id, 'method' => $method, 'note' => $note ? mb_substr($note, 0, 180) : $res->ref_no,
                    'paid_on' => now()->toDateTimeString()], $extra));
                $parent = $this->tu->payContactByInvoices($req, $alloc, false);
                foreach (TransactionPayment::where('parent_id', $parent->id)->get() as $child) {
                    ClinicCashier::postToRegister($user_id, $child->transaction_id, $child->method, $child->amount);
                }
                HotelFolioLine::create(['business_id' => $res->business_id, 'reservation_id' => $res->id, 'kind' => 'payment', 'item' => $method,
                    'transaction_payment_id' => $parent->id, 'amount' => $paid, 'description' => mb_substr($label, 0, 255), 'created_by' => $user_id]);
                $refs[] = $parent->payment_ref_no;
            }

            $left = round($amount - $paid, 4);
            if ($left > 0) {
                $p = $this->recordAdvance($res, $left, $method, $note, $user_id, $extra);
                ClinicCashier::postToRegister($user_id, null, $method, $left);
                $res->credit = round((float) $res->credit + $left, 4);
                $res->save();
                HotelFolioLine::create(['business_id' => $res->business_id, 'reservation_id' => $res->id, 'kind' => 'payment', 'item' => $method,
                    'transaction_payment_id' => $p->id, 'amount' => $left, 'description' => mb_substr($label, 0, 255), 'created_by' => $user_id]);
                $refs[] = $p->payment_ref_no;
            }

            return ['paid' => $paid, 'credit' => $left, 'ref' => implode(', ', $refs)];
        });
    }

    /** Money received before it can pay an invoice: an advance on the guest's contact (as Contacts › Pay does). */
    private function recordAdvance(HotelReservation $res, $amount, $method, $note, $user_id, array $extra)
    {
        $contact = Contact::findOrFail($res->contact_id);
        $ref_count = $this->tu->setAndGetReferenceCount('sell_payment', $res->business_id);
        $inputs = array_merge(array_intersect_key($extra, array_flip(['card_number', 'card_transaction_number', 'cheque_number', 'bank_account_number', 'account_id'])), [
            'amount' => $amount, 'method' => $method, 'note' => mb_substr(trim($res->ref_no.' '.($note ?: '')), 0, 180),
            'paid_on' => now()->toDateTimeString(), 'created_by' => $user_id, 'payment_for' => $contact->id,
            'business_id' => $res->business_id, 'is_advance' => 1, 'payment_type' => 'credit',
            'payment_ref_no' => $this->tu->generateReferenceNumber('sell_payment', $ref_count, $res->business_id),
        ]);
        $p = TransactionPayment::create($inputs);
        event(new TransactionPaymentAdded($p, array_merge($inputs, ['transaction_type' => 'sell'])));
        $this->tu->updateContactBalance($contact, $amount, 'add');

        return $p;
    }

    /** Uses the stay's credit on its unpaid invoices, oldest first (payment method "advance"). */
    public function applyCredit(HotelReservation $res, $user_id)
    {
        if ((float) $res->credit <= 0.004) {
            return 0;
        }
        $used = 0;
        foreach ($this->invoices($res)->filter(fn ($t) => $t->due > 0.004) as $tx) {
            $credit = round((float) $res->credit - $used, 4);
            $balance = (float) Contact::where('id', $res->contact_id)->value('balance');
            $take = round(min($credit, $tx->due, $balance), 4);
            if ($take <= 0.004) {
                break;
            }
            $ref_count = $this->tu->setAndGetReferenceCount('sell_payment', $res->business_id);
            $p = TransactionPayment::create([
                'transaction_id' => $tx->id, 'business_id' => $res->business_id, 'amount' => $take, 'method' => 'advance',
                'paid_on' => now()->toDateTimeString(), 'created_by' => $user_id, 'payment_for' => $res->contact_id,
                'note' => $res->ref_no, 'payment_ref_no' => $this->tu->generateReferenceNumber('sell_payment', $ref_count, $res->business_id),
            ]);
            event(new TransactionPaymentAdded($p, ['transaction_type' => 'sell', 'amount' => $take]));
            $this->tu->updatePaymentStatus($tx->id, $tx->final_total);
            $used = round($used + $take, 4);
        }
        if ($used > 0) {
            HotelReservation::where('id', $res->id)->update(['credit' => DB::raw('GREATEST(0, credit - '.(float) $used.')')]);
            $res->credit = round((float) $res->credit - $used, 4);
            $res->syncOriginalAttribute('credit'); // already written above: a later save() of this model must not write it again
        }

        return $used;
    }

    // ---------------------------------------------------------------- corrections (manager)

    /** Takes $amount off one charge (not more than it still owes). */
    public function discount(HotelReservation $res, $line_id, $amount, $reason, $user_id)
    {
        $line = HotelFolioLine::where('reservation_id', $res->id)->whereNull('voided_at')->whereNotNull('transaction_id')->findOrFail($line_id);
        $amount = round((float) $amount, 4);
        if ($amount <= 0 || trim((string) $reason) === '') {
            throw new HotelException(__('hotel.err_discount'));
        }

        return DB::transaction(function () use ($res, $line, $amount, $reason, $user_id) {
            $tx = Transaction::lockForUpdate()->findOrFail($line->transaction_id);
            $due = round((float) $tx->final_total - (float) $this->tu->getTotalPaid($tx->id), 4);
            if ($amount > $due + 0.004) {
                throw new HotelException(__('hotel.err_discount_due', ['due' => $this->tu->num_f($due)]));
            }
            $tx->discount_type = 'fixed';
            $tx->discount_amount = round((float) $tx->discount_amount + $amount, 4);
            $tx->final_total = round((float) $tx->final_total - $amount, 4);
            $tx->additional_notes = trim(($tx->additional_notes ? $tx->additional_notes."\n" : '').__('hotel.discount_note', ['amount' => $this->tu->num_f($amount), 'reason' => $reason]));
            $tx->save();
            $this->tu->updatePaymentStatus($tx->id, $tx->final_total);
            $line->description = mb_substr($line->description, 0, 255);
            $line->save();

            return $line;
        });
    }

    /** Cancels a charge that nothing was paid on: its invoice is deleted, the line stays crossed out. */
    public function void(HotelReservation $res, $line_id, $reason, $user_id)
    {
        $line = HotelFolioLine::where('reservation_id', $res->id)->whereNull('voided_at')->whereIn('kind', ['night', 'charge', 'late_checkout'])->findOrFail($line_id);
        if (trim((string) $reason) === '') {
            throw new HotelException(__('hotel.err_reason'));
        }

        return DB::transaction(function () use ($res, $line, $reason, $user_id) {
            if ($line->transaction_id) {
                $this->unwindCredit($res, $line->transaction_id);
                if ((float) $this->tu->getTotalPaid($line->transaction_id) > 0.004) {
                    throw new HotelException(__('hotel.err_void_paid'));
                }
                $tx = Transaction::find($line->transaction_id);
                if ($tx) {
                    $tx->sell_lines()->delete();
                    $tx->delete();
                }
            }
            $line->voided_at = now();
            $line->voided_by = $user_id;
            $line->void_reason = mb_substr(trim($reason), 0, 191);
            // night_key stays: a cancelled night is not charged again by the night audit or at check-out
            $line->save();

            return $line;
        });
    }

    /** Payments made from the stay's credit on an invoice go back to the credit. */
    private function unwindCredit(HotelReservation $res, $transaction_id)
    {
        $back = 0;
        foreach (TransactionPayment::where('transaction_id', $transaction_id)->where('method', 'advance')->get() as $p) {
            $back += (float) $p->amount;
            $p->delete();
            event(new TransactionPaymentDeleted($p));
        }
        if ($back > 0) {
            $this->tu->updatePaymentStatus($transaction_id);
            HotelReservation::where('id', $res->id)->update(['credit' => DB::raw('credit + '.round($back, 4))]);
            $res->credit = round((float) $res->credit + $back, 4);
            $res->syncOriginalAttribute('credit'); // already written above: a later save() of this model must not write it again
        }
    }

    /**
     * Puts on the bill every night slept that is not there yet: from the arrival to last night, and always
     * the arrival night (a guest who leaves the day they came pays one night). The night audit normally did
     * it; this covers a missed audit, a day closed before the guest arrived, an arrival after midnight.
     *
     * @return int nights added
     */
    public function catchUp(HotelReservation $res, $user_id)
    {
        if ($res->status !== 'checked_in') {
            return 0;
        }
        $today = Hotel::today();
        $end = $today->gt($res->arrival) ? $today : $res->arrival->copy()->addDay();
        $n = 0;
        foreach (Hotel::nights($res->arrival, $end) as $night) {
            if ($this->postNight($res, $night, $user_id)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * At check-out: room nights from $date on are not slept. Their invoices are cancelled when nothing but
     * the stay's credit paid them (the credit comes back); a night paid in money stays, and is counted.
     *
     * @return int nights kept because they were paid
     */
    public function releaseNightsFrom(HotelReservation $res, $date, $user_id, $forget = false)
    {
        $kept = 0;
        $lines = HotelFolioLine::where('reservation_id', $res->id)->where('kind', 'night')->whereNull('voided_at')
            ->where('night_key', '>=', Carbon::parse($date)->toDateString())->get();
        foreach ($lines as $line) {
            if ($line->transaction_id) {
                $this->unwindCredit($res, $line->transaction_id);
                if ((float) $this->tu->getTotalPaid($line->transaction_id) > 0.004) {
                    $kept++;

                    continue;
                }
            }
            $this->void($res, $line->id, __('hotel.void_not_slept'), $user_id);
            if ($forget) {
                // a stay shortened while the guest is in the hotel: if it is extended again, the night is charged again
                HotelFolioLine::where('id', $line->id)->update(['night_key' => null]);
            }
        }
        // credit given back by cancelled nights goes on what the stay still owes
        $this->applyCredit($res->fresh(), $user_id);

        return $kept;
    }

    /** What one night costs the guest: the room, and the tourist tax of its type. */
    public function nightCost(HotelReservation $res)
    {
        return round((float) $res->rate + (float) optional(HotelRoomType::find($res->room_type_id))->tourist_tax, 4);
    }

    /**
     * A guest in the hotel, seen the pay-first way: is the stay paid up to tonight? The nights up to tonight
     * that are not on the bill yet count as owed, at the price of the stay; money held as credit counts as paid.
     *
     * @return array|null null when the guest is not in the hotel, else
     *                    ['to_collect' => what is missing to be paid up to tonight (0 when covered),
     *                     'paid_until' => the last night covered, Y-m-d (null when something is missing),
     *                     'whole_stay' => every night of the stay is covered, 'night_cost' => x,
     *                     'counted_until' => the last night already inside these figures (to price an extension)]
     */
    public function coverage(HotelReservation $res)
    {
        if ($res->status !== 'checked_in') {
            return null;
        }
        $cost = $this->nightCost($res);
        $last = $res->departure->copy()->subDay();       // the last night of the stay
        $tonight = Hotel::hotelDay($res->business_id)->copy();
        if ($tonight->gt($last)) {
            $tonight = $last->copy();                    // leaving today (or late to leave): nothing new tonight
        }
        if ($tonight->lt($res->arrival)) {
            $tonight = $res->arrival->copy();
        }
        // a cancelled night keeps its date: it is not charged again, so it is not owed either
        $on_bill = HotelFolioLine::where('reservation_id', $res->id)->whereNotNull('night_key')->pluck('night_key')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $missing = count(array_diff(Hotel::nights($res->arrival, $tonight->copy()->addDay()), $on_bill));
        $need = round($this->balance($res) + $missing * $cost, 2);
        // nights charged ahead ("all nights at check-in") are inside the balance already
        $counted = fn ($d) => max($d->toDateString(), $on_bill ? max($on_bill) : '');
        if ($need > 0.004) {
            return ['to_collect' => $need, 'paid_until' => null, 'whole_stay' => false, 'night_cost' => $cost, 'counted_until' => $counted($tonight)];
        }
        // the nights after tonight: one already on the bill is inside the balance; the others take what credit is left
        $spare = -$need;
        $until = $tonight->copy();
        foreach (Hotel::nights($tonight->copy()->addDay(), $res->departure) as $night) {
            if (! in_array($night, $on_bill, true)) {
                if ($cost > 0 && $spare + 0.004 < $cost) {
                    break;
                }
                $spare -= $cost;
            }
            $until = Carbon::parse($night);
        }

        return ['to_collect' => 0.0, 'paid_until' => $until->toDateString(), 'whole_stay' => $until->gte($last), 'night_cost' => $cost, 'counted_until' => $counted($until)];
    }

    /**
     * "All nights at check-in": when the dates of a guest in the hotel change, the bill follows at once.
     * Nights added go on the bill; nights given up come off it when no money was paid on them (a night
     * paid in money stays, and is sorted out at check-out).
     *
     * @return int nights added to the bill
     */
    public function followDates(HotelReservation $res, $user_id)
    {
        if ($res->status !== 'checked_in' || Hotel::settings($res->business_id)['charge_mode'] !== 'checkin') {
            return 0;
        }
        $this->releaseNightsFrom($res, $res->departure->toDateString(), $user_id, true);
        $res->refresh();
        $n = 0;
        foreach (Hotel::nights($res->arrival, $res->departure) as $night) {
            if ($this->postNight($res, $night, $user_id)) {
                $n++;
            }
        }

        return $n;
    }
}
