<?php

namespace App\Utils;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\Events\TransactionPaymentAdded;
use App\HotelReservation;
use App\PressingEvent;
use App\PressingItem;
use App\PressingOrder;
use App\PressingPiece;
use App\PressingPrice;
use App\PressingProblem;
use App\PressingService;
use App\Transaction;
use App\TransactionPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova pressing (laundry, dry cleaning, ironing): the rules.
 *
 * Money is never kept apart: an order is an ordinary final sale on the customer's contact, one
 * line per item and service (non-stock service products, PressingServices), plus the express
 * surcharge; payments are ordinary payments posted to the cashier's open register; a hotel guest's
 * order can go on the room bill (HotelRoomCharge), and is then paid at check-out. So the sales
 * reports, the cash register and the accounts see the pressing like any other sale.
 *
 * An order moves received -> washing -> ironing -> ready -> collected. "Ready" tells the customer
 * on WhatsApp (PressingWhatsApp). Collection needs every piece ticked as handed over and the balance
 * paid (or the order on the room bill).
 */
class Pressing
{
    const DEFAULTS = [
        'location_id' => null,
        'ticket_prefix' => 'P-',
        'ready_time' => '17:00',
        'express_percent' => 50,
        'express_time' => '12:00',   // express: ready the next day at this time
        'pay_rule' => 'any',         // any | half | full: what must be paid at drop-off (room bill aside)
        'guest_prices' => 0,         // 1: hotel guests pay the price list's guest column when it is filled
        'charge_to_room' => 1,       // hotel guests' orders can go on the room bill
        'left_over_days' => 30,
        'reminder_days' => 7,
        'send_received' => 1,
        'send_ready' => 1,
        'send_reminder' => 1,
        'tpl_received' => 'pressing_received',
        'tpl_ready' => 'pressing_ready',
        'tpl_reminder' => 'pressing_reminder',
    ];

    const STAGES = ['received', 'washing', 'ironing'];

    private static $settingsCache = [];

    // ------------------------------------------------------------------ module and settings

    public static function enabled($business_id = null)
    {
        $business_id = $business_id ?: session('business.id');
        $mods = session('business.id') == $business_id ? (array) session('business.enabled_modules') : (array) optional(Business::find($business_id))->enabled_modules;

        return in_array('pressing', $mods ?: [], true);
    }

    public static function settings($business_id)
    {
        if (! isset(self::$settingsCache[$business_id])) {
            $raw = DB::table('pressing_settings')->where('business_id', $business_id)->value('settings');
            $s = array_merge(self::DEFAULTS, (array) json_decode((string) $raw, true));
            if (empty($s['location_id']) || ! BusinessLocation::where('business_id', $business_id)->where('id', $s['location_id'])->exists()) {
                $s['location_id'] = BusinessLocation::where('business_id', $business_id)->orderBy('id')->value('id');
            }
            self::$settingsCache[$business_id] = $s;
        }

        return self::$settingsCache[$business_id];
    }

    public static function saveSettings($business_id, array $values)
    {
        $s = array_merge(self::settings($business_id), array_intersect_key($values, self::DEFAULTS));
        DB::table('pressing_settings')->updateOrInsert(['business_id' => $business_id], ['settings' => json_encode($s), 'updated_at' => now(), 'created_at' => now()]);
        unset(self::$settingsCache[$business_id]);

        return self::settings($business_id);
    }

    public static function forget()
    {
        self::$settingsCache = [];
    }

    // ------------------------------------------------------------------ the price list

    /** The usual services, made once (renamable later). */
    public static function ensureServices($business_id)
    {
        if (PressingService::where('business_id', $business_id)->exists()) {
            return;
        }
        foreach (['svc_wash_iron', 'svc_dry_clean', 'svc_iron_only'] as $i => $key) {
            PressingService::create(['business_id' => $business_id, 'name' => __('pressing.'.$key), 'sort_order' => $i + 1]);
        }
    }

    /** The usual items (no prices: the manager types them). Returns how many were added. */
    public static function addUsualItems($business_id)
    {
        $have = PressingItem::where('business_id', $business_id)->pluck('name')->map(fn ($n) => mb_strtolower($n))->all();
        $n = 0;
        $order = (int) PressingItem::where('business_id', $business_id)->max('sort_order');
        foreach (['shirt' => 2, 'trousers' => 2, 'suit' => 3, 'jacket' => 3, 'dress' => 2, 'kaba' => 2, 'skirt' => 2, 'tshirt' => 2, 'bedsheet' => 2, 'duvet' => 3, 'towel' => 2, 'curtain' => 3] as $key => $days) {
            $name = __('pressing.usual_'.$key);
            if (in_array(mb_strtolower($name), $have, true)) {
                continue;
            }
            PressingItem::create(['business_id' => $business_id, 'name' => $name, 'ready_days' => $days, 'sort_order' => ++$order]);
            $n++;
        }

        return $n;
    }

    /** Active items with their prices by service: [item, prices => [service_id => PressingPrice]]. */
    public static function priceList($business_id)
    {
        $prices = PressingPrice::where('business_id', $business_id)->get()->groupBy('item_id');

        return PressingItem::where('business_id', $business_id)->where('is_active', 1)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn ($i) => ['item' => $i, 'prices' => ($prices[$i->id] ?? collect())->keyBy('service_id')]);
    }

    public static function services($business_id, $activeOnly = true)
    {
        return PressingService::where('business_id', $business_id)->when($activeOnly, fn ($q) => $q->where('is_active', 1))
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    /** What one piece costs: the guest price for a hotel guest when the business uses them. */
    public static function priceOf($business_id, $item_id, $service_id, $guest = false)
    {
        $p = PressingPrice::where('business_id', $business_id)->where('item_id', $item_id)->where('service_id', $service_id)->first();
        if (! $p) {
            return null;
        }
        if ($guest && ! empty(self::settings($business_id)['guest_prices']) && $p->guest_price !== null) {
            return (float) $p->guest_price;
        }

        return (float) $p->price;
    }

    // ------------------------------------------------------------------ dates and money

    /** When an order is ready: the longest delay of its items (or express: the next day), at the shop's time. */
    public static function readyAt($business_id, array $item_ids, $express, $from = null)
    {
        $s = self::settings($business_id);
        $from = $from ? Carbon::parse($from) : now();
        if ($express) {
            [$h, $m] = array_pad(explode(':', (string) $s['express_time']), 2, 0);

            return $from->copy()->addDay()->setTime((int) $h, (int) $m);
        }
        $days = (int) PressingItem::whereIn('id', $item_ids ?: [0])->max('ready_days');
        [$h, $m] = array_pad(explode(':', (string) $s['ready_time']), 2, 0);

        return $from->copy()->addDays(max(1, $days ?: 2))->setTime((int) $h, (int) $m);
    }

    /** Paid and still due on an order's sale. Room-bill orders are due on the stay, not at the counter. */
    public static function money(PressingOrder $o)
    {
        $total = (float) $o->total;
        $paid = $o->transaction_id ? (float) DB::table('transaction_payments')->where('transaction_id', $o->transaction_id)->sum('amount') : 0.0;
        $due = max(0, round($total - $paid, 4));

        return ['total' => $total, 'paid' => round($paid, 4), 'due' => $due, 'counter_due' => $o->on_room_bill ? 0.0 : $due];
    }

    public static function format($business_id, $amount)
    {
        $sym = optional(optional(Business::find($business_id))->currency)->symbol ?: 'FCFA';

        return number_format((float) $amount, 0, '.', ' ').' '.$sym;
    }

    // ------------------------------------------------------------------ taking an order

    /**
     * A new drop-off. $data:
     *   for         'outside' | 'guest'
     *   name, mobile           (outside) or reservation_id (guest)
     *   pieces      [['item_id', 'service_id', 'note'?], …]  one entry per piece
     *   express     bool;  ready_at  optional 'Y-m-d H:i' (else readyAt())
     *   pay         'all' | 'part' | 'later' | 'room';  amount (for part);  method
     *   whatsapp    bool;  lang 'fr' | 'en';  note
     */
    public static function createOrder($business_id, array $data, $user_id)
    {
        $s = self::settings($business_id);
        $guest = ($data['for'] ?? 'outside') === 'guest';
        $pieces = array_values(array_filter((array) ($data['pieces'] ?? []), fn ($p) => ! empty($p['item_id']) && ! empty($p['service_id'])));
        if (! $pieces) {
            throw new PressingException(__('pressing.err_no_pieces'));
        }
        if (count($pieces) > 300) {
            throw new PressingException(__('pressing.err_too_many'));
        }

        $res = null;
        if ($guest) {
            if (! Hotel::enabled($business_id)) {
                throw new PressingException(__('pressing.err_no_hotel'));
            }
            $res = HotelReservation::where('business_id', $business_id)->find($data['reservation_id'] ?? 0);
            if (! $res || $res->status !== 'checked_in') {
                throw new PressingException(__('pressing.err_guest_not_in'));
            }
        } elseif (trim((string) ($data['name'] ?? '')) === '' || trim((string) ($data['mobile'] ?? '')) === '') {
            throw new PressingException(__('pressing.err_customer'));
        }

        // every piece priced from the list (never from the form)
        $priced = [];
        foreach ($pieces as $p) {
            $item = PressingItem::where('business_id', $business_id)->where('is_active', 1)->find($p['item_id']);
            $svc = PressingService::where('business_id', $business_id)->where('is_active', 1)->find($p['service_id']);
            $price = ($item && $svc) ? self::priceOf($business_id, $item->id, $svc->id, $guest) : null;
            if ($price === null) {
                throw new PressingException(__('pressing.err_no_price', ['item' => optional($item)->name ?: '?', 'service' => optional($svc)->name ?: '?']));
            }
            $priced[] = ['item' => $item, 'service' => $svc, 'price' => $price, 'note' => mb_substr(trim((string) ($p['note'] ?? '')), 0, 255)];
        }
        $subtotal = round(array_sum(array_column($priced, 'price')), 4);
        $express = ! empty($data['express']);
        $surcharge = $express ? round($subtotal * (float) $s['express_percent'] / 100, 0) : 0.0;
        $total = round($subtotal + $surcharge, 4);

        $pay = $data['pay'] ?? 'later';
        if ($pay === 'room') {
            if (! $guest || empty($s['charge_to_room']) || ! HotelRoomCharge::enabled($business_id)) {
                throw new PressingException(__('pressing.err_room'));
            }
            $amount = 0.0;
        } else {
            $amount = $pay === 'all' ? $total : ($pay === 'part' ? round((float) ($data['amount'] ?? 0), 4) : 0.0);
            if ($amount < 0 || $amount > $total + 0.004) {
                throw new PressingException(__('pressing.err_amount'));
            }
            if ($s['pay_rule'] === 'full' && $amount + 0.004 < $total) {
                throw new PressingException(__('pressing.err_pay_full'));
            }
            if ($s['pay_rule'] === 'half' && $amount + 0.004 < round($total / 2, 0)) {
                throw new PressingException(__('pressing.err_pay_half', ['amount' => self::format($business_id, round($total / 2, 0))]));
            }
        }
        $method = $data['method'] ?? 'cash';
        if ($amount > 0 && ($method === 'advance' || ! array_key_exists($method, (new Util())->payment_types(null, false, $business_id)))) {
            throw new PressingException(__('pressing.err_method'));
        }

        $order = DB::transaction(function () use ($business_id, $data, $user_id, $s, $guest, $res, $priced, $express, $surcharge, $total, $pay, $amount, $method) {
            $contact = $guest ? Contact::find($res->contact_id)
                : Hotel::guestContact($business_id, ['name' => $data['name'], 'mobile' => $data['mobile']], $user_id);
            $location_id = $guest ? ($res->location_id ?: $s['location_id']) : $s['location_id'];
            $seq = (new Util())->setAndGetReferenceCount('pressing_order', $business_id);
            $ticket = $s['ticket_prefix'].str_pad($seq, 4, '0', STR_PAD_LEFT);
            $ready = ! empty($data['ready_at']) ? Carbon::parse($data['ready_at'])
                : self::readyAt($business_id, array_map(fn ($p) => $p['item']->id, $priced), $express);

            // the sale: one line per item and service, plus the express surcharge
            $groups = [];
            foreach ($priced as $p) {
                $k = $p['item']->id.'|'.$p['service']->id.'|'.$p['price'];
                $groups[$k] = $groups[$k] ?? ['item' => $p['item'], 'service' => $p['service'], 'price' => $p['price'], 'qty' => 0];
                $groups[$k]['qty']++;
            }
            $lines = [];
            foreach ($groups as $g) {
                $v = PressingServices::variation($business_id, $g['item'], $g['service'], $user_id);
                $lines[] = ['product_id' => $v->product_id, 'variation_id' => $v->id, 'quantity' => $g['qty'],
                    'unit_price' => $g['price'], 'unit_price_inc_tax' => $g['price'], 'item_tax' => 0, 'tax_id' => null,
                    'line_discount_type' => null, 'line_discount_amount' => 0, 'sell_line_note' => $ticket];
            }
            if ($surcharge > 0) {
                $v = PressingServices::express($business_id, $user_id);
                $lines[] = ['product_id' => $v->product_id, 'variation_id' => $v->id, 'quantity' => 1,
                    'unit_price' => $surcharge, 'unit_price_inc_tax' => $surcharge, 'item_tax' => 0, 'tax_id' => null,
                    'line_discount_type' => null, 'line_discount_amount' => 0, 'sell_line_note' => __('pressing.express').' '.$s['express_percent'].'%'];
            }
            $tu = new TransactionUtil();
            $tx = $tu->createSellTransaction($business_id, [
                'type' => 'sell', 'status' => 'final', 'contact_id' => $contact->id, 'location_id' => $location_id,
                'transaction_date' => now()->toDateTimeString(), 'discount_amount' => 0, 'final_total' => $total,
                'sale_note' => __('pressing.sale_note', ['ticket' => $ticket]),
            ], ['total_before_tax' => $total, 'tax' => 0, 'discount' => 0], $user_id, false);
            $tu->createOrUpdateSellLines($tx, $lines, $location_id, false, null, [], false);
            $tu->updatePaymentStatus($tx->id, $tx->final_total);

            $order = PressingOrder::create([
                'business_id' => $business_id, 'location_id' => $location_id, 'seq' => $seq, 'ticket_no' => $ticket,
                'contact_id' => $contact->id, 'reservation_id' => $res ? $res->id : null, 'transaction_id' => $tx->id,
                'status' => 'received', 'express' => $express, 'total' => $total,
                'phone' => $guest ? ($contact->mobile ?: null) : trim((string) $data['mobile']),
                'whatsapp' => ! empty($data['whatsapp']), 'lang' => in_array($data['lang'] ?? '', ['fr', 'en'], true) ? $data['lang'] : 'fr',
                'note' => mb_substr(trim((string) ($data['note'] ?? '')), 0, 1000) ?: null,
                'received_at' => now(), 'ready_at' => $ready, 'created_by' => $user_id,
            ]);
            foreach ($priced as $i => $p) {
                PressingPiece::create(['order_id' => $order->id, 'tag' => $i + 1, 'item_id' => $p['item']->id, 'service_id' => $p['service']->id,
                    'price' => $p['price'], 'note' => $p['note'] ?: null]);
            }
            self::event($order, 'received', $user_id);

            if ($pay === 'room') {
                if (! HotelRoomCharge::attach($tx->fresh(), $res->id, $user_id)) {
                    throw new PressingException(__('pressing.err_room'));
                }
                $order->on_room_bill = true;
                $order->save();
            } elseif ($amount > 0) {
                self::recordPayment($order, $amount, $method, $user_id, __('pressing.paid_at_dropoff'));
            }

            return $order;
        });

        PressingWhatsApp::notify($order, 'received', $user_id);

        return $order->fresh();
    }

    /** A payment on the order's sale, as Add payment records it, into the cashier's open register. */
    public static function recordPayment(PressingOrder $o, $amount, $method, $user_id, $note = null)
    {
        $amount = round((float) $amount, 4);
        $m = self::money($o);
        if ($amount <= 0 || $amount > $m['due'] + 0.004) {
            throw new PressingException(__('pressing.err_amount'));
        }
        if ($method === 'advance' || ! array_key_exists($method, (new Util())->payment_types(null, false, $o->business_id))) {
            throw new PressingException(__('pressing.err_method'));
        }
        $t = Transaction::findOrFail($o->transaction_id);
        $tu = new TransactionUtil();
        $loc = BusinessLocation::find($t->location_id);
        $defaults = $loc ? (json_decode((string) $loc->default_payment_accounts, true) ?: []) : [];
        $account = ! empty($defaults[$method]['is_enabled']) && ! empty($defaults[$method]['account']) ? (int) $defaults[$method]['account'] : null;

        $inputs = ['amount' => $amount, 'method' => $method, 'note' => $note ?: $o->ticket_no, 'paid_on' => now()->toDateTimeString(),
            'transaction_id' => $t->id, 'created_by' => $user_id, 'payment_for' => $t->contact_id, 'business_id' => $o->business_id,
            'account_id' => $account,
            'payment_ref_no' => $tu->generateReferenceNumber('sell_payment', $tu->setAndGetReferenceCount('sell_payment', $o->business_id), $o->business_id)];
        $tp = TransactionPayment::create($inputs);
        $inputs['transaction_type'] = 'sell';
        event(new TransactionPaymentAdded($tp, $inputs));
        $tu->updatePaymentStatus($t->id, $t->final_total);
        ClinicCashier::postToRegister($user_id, $t->id, $method, $amount);

        return $tp;
    }

    // ------------------------------------------------------------------ progress

    public static function event(PressingOrder $o, $status, $user_id, $note = null)
    {
        return PressingEvent::create(['order_id' => $o->id, 'status' => $status, 'note' => $note ? mb_substr($note, 0, 255) : null,
            'user_id' => $user_id, 'created_at' => now()]);
    }

    /** Received, washing or ironing (forward or back, a stage may be skipped). */
    public static function move(PressingOrder $o, $status, $user_id)
    {
        if (! in_array($status, self::STAGES, true)) {
            throw new PressingException(__('pressing.err_stage'));
        }
        if (! in_array($o->status, ['received', 'washing', 'ironing', 'ready'], true)) {
            throw new PressingException(__('pressing.err_closed'));
        }
        if ($o->status === $status) {
            return $o;
        }
        $o->status = $status;
        $o->ready_marked_at = null;
        $o->save();
        self::event($o, $status, $user_id);

        return $o;
    }

    /** Ready: told on WhatsApp. Returns the logged message (or null: the counter sends the link). */
    public static function markReady(PressingOrder $o, $user_id)
    {
        if (! in_array($o->status, self::STAGES, true)) {
            throw new PressingException($o->status === 'ready' ? __('pressing.err_already_ready') : __('pressing.err_closed'));
        }
        $o->status = 'ready';
        $o->ready_marked_at = now();
        $o->reminded_at = null;
        $o->save();
        self::event($o, 'ready', $user_id);

        return PressingWhatsApp::notify($o, 'ready', $user_id);
    }

    /**
     * Handing over. $handed: the piece ids ticked (all of them, or it is refused); the balance is
     * paid now ($amount = what is due) unless the order is on the room bill.
     */
    public static function collect(PressingOrder $o, array $handed, $amount, $method, $by, $user_id)
    {
        if ($o->status !== 'ready') {
            throw new PressingException($o->status === 'collected' ? __('pressing.err_collected') : __('pressing.err_not_ready'));
        }
        $ids = $o->pieces()->pluck('id')->map(fn ($v) => (int) $v)->all();
        $handed = array_map('intval', $handed);
        if (array_diff($ids, $handed)) {
            throw new PressingException(__('pressing.err_pieces'));
        }
        $m = self::money($o);
        $amount = round((float) $amount, 4);
        if ($m['counter_due'] > 0.004 && abs($amount - $m['counter_due']) > 0.004) {
            throw new PressingException(__('pressing.err_balance', ['amount' => self::format($o->business_id, $m['counter_due'])]));
        }

        return DB::transaction(function () use ($o, $m, $amount, $method, $by, $user_id) {
            if ($m['counter_due'] > 0.004) {
                self::recordPayment($o, $amount, $method, $user_id, __('pressing.paid_at_collection'));
            }
            PressingPiece::where('order_id', $o->id)->update(['handed_over' => 1]);
            $o->status = 'collected';
            $o->collected_at = now();
            $o->collected_by = mb_substr(trim((string) $by), 0, 150) ?: optional($o->contact)->name;
            $o->collected_user_id = $user_id;
            $o->save();
            self::event($o, 'collected', $user_id, $o->collected_by);

            return $o;
        });
    }

    /** Cancel an order that has no money on it yet: its sale is removed with it. */
    public static function cancel(PressingOrder $o, $reason, $user_id)
    {
        if (! $o->isOpen()) {
            throw new PressingException(__('pressing.err_closed'));
        }
        $m = self::money($o);
        if ($m['paid'] > 0.004 || $o->on_room_bill) {
            throw new PressingException(__('pressing.err_cancel_paid'));
        }
        if (trim((string) $reason) === '') {
            throw new PressingException(__('pressing.err_reason'));
        }

        return DB::transaction(function () use ($o, $reason, $user_id) {
            if ($o->transaction_id && ($t = Transaction::find($o->transaction_id))) {
                $t->sell_lines()->delete();
                $t->delete();
            }
            $o->status = 'cancelled';
            $o->save();
            self::event($o, 'cancelled', $user_id, $reason);

            return $o;
        });
    }

    /**
     * A lost or damaged piece, or a complaint. A refund pays the agreed amount out of the cash box as
     * an expense ("Pressing compensation"), so the cash and the accounts show it.
     */
    public static function reportProblem(PressingOrder $o, array $data, $user_id)
    {
        $kind = in_array($data['kind'] ?? '', PressingProblem::KINDS, true) ? $data['kind'] : null;
        $outcome = in_array($data['outcome'] ?? '', PressingProblem::OUTCOMES, true) ? $data['outcome'] : null;
        $text = trim((string) ($data['description'] ?? ''));
        if (! $kind || ! $outcome || $text === '') {
            throw new PressingException(__('pressing.err_problem'));
        }
        $piece = ! empty($data['piece_id']) ? PressingPiece::where('order_id', $o->id)->find($data['piece_id']) : null;
        $amount = $outcome === 'refund' ? round((float) ($data['amount'] ?? 0), 4) : 0.0;
        if ($outcome === 'refund' && $amount <= 0) {
            throw new PressingException(__('pressing.err_amount'));
        }
        $method = $data['method'] ?? 'cash';

        return DB::transaction(function () use ($o, $kind, $outcome, $text, $piece, $amount, $method, $user_id) {
            $expense_id = null;
            if ($amount > 0) {
                $expense_id = self::compensation($o, $amount, $method, $text, $user_id);
            }

            return PressingProblem::create(['business_id' => $o->business_id, 'order_id' => $o->id, 'piece_id' => optional($piece)->id,
                'kind' => $kind, 'description' => mb_substr($text, 0, 2000), 'outcome' => $outcome, 'amount' => $amount,
                'expense_id' => $expense_id, 'created_by' => $user_id]);
        });
    }

    private static function compensation(PressingOrder $o, $amount, $method, $text, $user_id)
    {
        $cat = DB::table('expense_categories')->where('business_id', $o->business_id)->where('code', 'PRESSING')->whereNull('deleted_at')->value('id');
        if (! $cat) {
            $cat = DB::table('expense_categories')->insertGetId(['business_id' => $o->business_id, 'name' => __('pressing.compensation_category'),
                'code' => 'PRESSING', 'created_at' => now(), 'updated_at' => now()]);
        }
        $tu = new TransactionUtil();
        $t = Transaction::create(['business_id' => $o->business_id, 'location_id' => $o->location_id, 'type' => 'expense', 'status' => 'final',
            'payment_status' => 'due', 'ref_no' => $o->ticket_no, 'transaction_date' => now(), 'total_before_tax' => $amount, 'tax_amount' => 0,
            'final_total' => $amount, 'expense_category_id' => $cat, 'contact_id' => $o->contact_id,
            'additional_notes' => mb_substr(__('pressing.compensation_note', ['ticket' => $o->ticket_no]).' · '.$text, 0, 1000), 'created_by' => $user_id]);
        $tu->createOrUpdatePaymentLines($t, [['amount' => $amount, 'method' => $method, 'paid_on' => now()->toDateTimeString(), 'note' => $o->ticket_no]], $o->business_id, $user_id, false);
        $tu->updatePaymentStatus($t->id, $t->final_total);

        return $t->id;
    }

    // ------------------------------------------------------------------ reading

    /** The open orders for the counter board, with the figures above it. */
    public static function board($business_id, $filter = 'all')
    {
        $q = PressingOrder::where('business_id', $business_id)->whereIn('status', ['received', 'washing', 'ironing', 'ready'])
            ->with(['contact:id,name,mobile', 'reservation.room:id,number'])->withCount('pieces');
        if ($filter === 'express') {
            $q->where('express', 1);
        } elseif ($filter === 'guests') {
            $q->whereNotNull('reservation_id');
        } elseif ($filter === 'outside') {
            $q->whereNull('reservation_id');
        }
        $orders = $q->orderBy('ready_at')->get();
        $s = self::settings($business_id);
        $today = PressingOrder::where('business_id', $business_id)->where('status', '!=', 'cancelled')->whereDate('received_at', today());
        $all = PressingOrder::where('business_id', $business_id)->whereIn('status', ['received', 'washing', 'ironing', 'ready'])->get();

        return [
            'columns' => [
                'received' => $orders->where('status', 'received')->values(),
                'washing' => $orders->where('status', 'washing')->values(),
                'ironing' => $orders->where('status', 'ironing')->values(),
                'ready' => $orders->where('status', 'ready')->sortBy('ready_marked_at')->values(),
            ],
            'today_orders' => (clone $today)->count(),
            'today_pieces' => PressingPiece::whereIn('order_id', (clone $today)->pluck('id'))->count(),
            'today_total' => (float) (clone $today)->sum('total'),
            'in_work' => $all->whereIn('status', self::STAGES)->count(),
            'ready' => $all->where('status', 'ready')->count(),
            'late' => $all->filter(fn ($o) => $o->isLate())->count(),
            'left_over' => $all->where('status', 'ready')->filter(fn ($o) => $o->ready_marked_at && $o->ready_marked_at->lt(now()->subDays((int) $s['left_over_days'])))->count(),
        ];
    }

    /** Last WhatsApp message of each order (for the cards): [order_id => message]. */
    public static function lastMessages(array $order_ids)
    {
        if (! $order_ids || ! \Illuminate\Support\Facades\Schema::hasColumn('clinic_whatsapp_messages', 'pressing_order_id')) {
            return [];
        }

        return \App\ClinicWhatsappMessage::whereIn('pressing_order_id', $order_ids)->where('direction', 'out')->orderBy('id')->get()
            ->keyBy('pressing_order_id')->all();
    }

    /** Find by ticket, tag ("P-0413 #2"), name or phone. */
    public static function search($business_id, $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return collect();
        }
        $ticket = preg_replace('/\s*#\d+$/', '', $term);
        $digits = preg_replace('/\D+/', '', $term);

        return PressingOrder::where('business_id', $business_id)->with(['contact:id,name,mobile'])->withCount('pieces')
            ->where(function ($q) use ($term, $ticket, $digits) {
                $q->where('ticket_no', $ticket)->orWhere('ticket_no', 'like', '%'.$ticket)
                    ->orWhereHas('contact', fn ($c) => $c->where('name', 'like', '%'.$term.'%'));
                if (strlen($digits) >= 6) {
                    // numbers are typed with spaces or dashes: compare the digits only
                    $like = '%'.substr($digits, -8).'%';
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '.', '') LIKE ?", [$like])
                        ->orWhereHas('contact', fn ($c) => $c->whereRaw("REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '.', '') LIKE ?", [$like]));
                }
            })->orderByDesc('id')->limit(30)->get();
    }

    /** A month (or any range) of the pressing: figures, by item and service, left over, problems. */
    public static function report($business_id, $from, $to)
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();
        $orders = PressingOrder::where('business_id', $business_id)->where('status', '!=', 'cancelled')->whereBetween('received_at', [$from, $to])->get();
        $ids = $orders->pluck('id')->all();
        $pieces = PressingPiece::whereIn('order_id', $ids ?: [0])->with(['item:id,name', 'service:id,name'])->get();
        $byLine = $pieces->groupBy(fn ($p) => $p->item_id.'|'.$p->service_id)->map(fn ($g) => [
            'item' => optional($g->first()->item)->name, 'service' => optional($g->first()->service)->name,
            'pieces' => $g->count(), 'amount' => round((float) $g->sum('price'), 2),
        ])->sortByDesc('amount')->values();
        $due = 0.0;
        foreach (PressingOrder::where('business_id', $business_id)->whereNotIn('status', ['cancelled'])->where('on_room_bill', 0)->get() as $o) {
            $due += self::money($o)['due'];
        }
        $readyDone = $orders->filter(fn ($o) => $o->ready_marked_at);
        $onTime = $readyDone->count() ? round(100 * $readyDone->filter(fn ($o) => $o->ready_marked_at->lte($o->ready_at))->count() / $readyDone->count()) : null;
        $s = self::settings($business_id);

        return [
            'from' => $from, 'to' => $to,
            'orders' => $orders->count(),
            'pieces' => $pieces->count(),
            'sales' => round((float) $orders->sum('total'), 2),
            'sales_guests' => round((float) $orders->whereNotNull('reservation_id')->sum('total'), 2),
            'sales_outside' => round((float) $orders->whereNull('reservation_id')->sum('total'), 2),
            'due' => round($due, 2),
            'on_time' => $onTime,
            'by_line' => $byLine,
            'left_over' => PressingOrder::where('business_id', $business_id)->where('status', 'ready')
                ->where('ready_marked_at', '<', now()->subDays((int) $s['left_over_days']))->with('contact:id,name,mobile')->withCount('pieces')->orderBy('ready_marked_at')->get(),
            'problems' => PressingProblem::where('business_id', $business_id)->whereBetween('created_at', [$from, $to])->with(['piece.item', 'user'])
                ->orderByDesc('id')->get()->map(function ($p) {
                    $p->order = PressingOrder::find($p->order_id);

                    return $p;
                }),
        ];
    }
}
