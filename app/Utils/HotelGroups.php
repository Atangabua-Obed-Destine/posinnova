<?php

namespace App\Utils;

use App\HotelFolioLine;
use App\HotelGroup;
use App\HotelReservation;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova hotel: group bookings.
 *
 * A group is several ordinary reservations (one per room, each with its own dates, check-in, moves and
 * bill) whose guest is the same payer. The bills stay per room, on the payer's contact; the group adds:
 *  - one payment shared over its rooms (what is already billed first, then the nights to come, rooms
 *    that leave first coming first; anything left over waits as credit on the room that leaves last);
 *  - pays "payer": a room may check out still owing (it stays on the group); the last room to leave
 *    settles the whole group (or a manager leaves it on the payer's account);
 *  - pays "each": every room settles its own bill when it leaves, as a normal stay.
 */
class HotelGroups
{
    const MAX_ROOMS = 40;

    /**
     * New group. $data: contact_id | name+mobile, pays, notes, rate_plan, source, status, hold_until,
     * rooms: [{room_type_id, room_id?, arrival, departure, adults?, children?, occupant?, rate?}]
     */
    public static function create($business_id, array $data, $user_id)
    {
        $rooms = array_values(array_filter((array) ($data['rooms'] ?? []), fn ($r) => is_array($r) && ! empty($r['room_type_id'])));
        if (! $rooms) {
            throw new HotelException(__('hotel.grp_err_no_rooms'));
        }
        if (count($rooms) > self::MAX_ROOMS) {
            throw new HotelException(__('hotel.grp_err_too_many', ['n' => self::MAX_ROOMS]));
        }
        $picked = array_filter(array_column($rooms, 'room_id'));
        if (count($picked) !== count(array_unique($picked))) {
            throw new HotelException(__('hotel.grp_err_same_room'));
        }

        return DB::transaction(function () use ($business_id, $data, $user_id, $rooms) {
            $contact = Hotel::guestContact($business_id, $data, $user_id);
            $count = (new Util())->setAndGetReferenceCount('hotel_group', $business_id);
            $group = HotelGroup::create([
                'business_id' => $business_id,
                'ref_no' => 'GRP-'.str_pad($count, 4, '0', STR_PAD_LEFT),
                'contact_id' => $contact->id,
                'pays' => in_array($data['pays'] ?? '', HotelGroup::PAYS, true) ? $data['pays'] : 'payer',
                'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500) ?: null,
                'created_by' => $user_id,
            ]);
            foreach ($rooms as $i => $r) {
                self::addRoom($group, array_merge($r, array_intersect_key($data, array_flip(['rate_plan', 'source', 'status', 'hold_until']))), $user_id, $i + 1);
            }

            return $group->fresh();
        });
    }

    /** One more room in the group (its own reservation, on the payer). $n numbers the room in error messages. */
    public static function addRoom(HotelGroup $group, array $r, $user_id, $n = null)
    {
        try {
            $res = Hotel::createReservation($group->business_id, [
                'contact_id' => $group->contact_id, 'room_type_id' => $r['room_type_id'], 'room_id' => $r['room_id'] ?? null,
                'arrival' => Hotel::parseDate($r['arrival'] ?? ''), 'departure' => Hotel::parseDate($r['departure'] ?? ''),
                'adults' => $r['adults'] ?? 1, 'children' => $r['children'] ?? 0, 'rate_plan' => $r['rate_plan'] ?? 'room_only',
                'rate' => $r['rate'] ?? null, 'source' => $r['source'] ?? 'phone', 'status' => $r['status'] ?? 'confirmed',
                'hold_until' => $r['hold_until'] ?? null,
            ], $user_id);
        } catch (HotelException $e) {
            throw new HotelException($n ? __('hotel.grp_err_room', ['n' => $n, 'msg' => $e->getMessage()]) : $e->getMessage());
        }
        $res->group_id = $group->id;
        $res->occupant = mb_substr(trim((string) ($r['occupant'] ?? '')), 0, 150) ?: null;
        $res->save();

        return $res;
    }

    /** The group's rooms: the ones leaving first first. */
    public static function members(HotelGroup $group)
    {
        return HotelReservation::where('group_id', $group->id)->with(['contact', 'roomType', 'room'])
            ->orderBy('departure')->orderBy('id')->get();
    }

    /** What the nights of the stay not on its bill yet will cost (0 for a closed stay). */
    public static function toCome(HotelReservation $res, HotelFolio $folio = null)
    {
        if (! in_array($res->status, HotelReservation::HOLDING, true)) {
            return 0.0;
        }
        $folio = $folio ?: new HotelFolio();
        $on_bill = HotelFolioLine::where('reservation_id', $res->id)->whereNotNull('night_key')->pluck('night_key')
            ->map(fn ($d) => \Carbon\Carbon::parse($d)->toDateString())->all();
        $missing = count(array_diff(Hotel::nights($res->arrival, $res->departure), $on_bill));

        return round($missing * $folio->nightCost($res), 4);
    }

    /**
     * The group's figures: a row per room (statement totals, balance, nights to come) and the totals.
     *
     * @return array ['rows' => [...], 'charges', 'payments', 'owed' (sum of balances), 'to_come', 'total_left']
     */
    public static function summary(HotelGroup $group)
    {
        $folio = new HotelFolio();
        $rows = [];
        foreach (self::members($group) as $res) {
            $st = $folio->statement($res);
            if (self::gone($res)) {
                $st['balance'] = max(0, $st['balance']); // its credit was kept for the payer, not used by the group
            }
            $rows[] = ['res' => $res, 'charges' => $st['charges'], 'payments' => $st['payments'], 'balance' => $st['balance'], 'to_come' => self::toCome($res, $folio)];
        }
        $sum = fn ($k) => round(array_sum(array_column($rows, $k)), 4);
        $owed = $sum('balance');
        $to_come = $sum('to_come');

        return ['rows' => $rows, 'charges' => $sum('charges'), 'payments' => $sum('payments'), 'owed' => $owed, 'to_come' => $to_come,
            'total_left' => round($owed + $to_come, 4)];
    }

    /** What the whole group owes now (its rooms' balances: a room in credit counts against the others). */
    public static function due(HotelGroup $group)
    {
        $folio = new HotelFolio();

        return round(self::members($group)->reject(fn ($r) => self::gone($r))->sum(fn ($r) => $folio->balance($r)), 4);
    }

    /**
     * How a payment is shared over the rooms: what is already billed first, then the nights to come
     * (rooms that leave first coming first); the rest waits as credit on the room that leaves last.
     *
     * @return array reservation_id => amount
     */
    public static function share(HotelGroup $group, $amount)
    {
        $folio = new HotelFolio();
        $left = round((float) $amount, 4);
        $members = self::members($group);
        $out = [];
        $give = function ($res, $x) use (&$out, &$left) {
            $x = round(min($left, $x), 4);
            if ($x > 0.004) {
                $out[$res->id] = round(($out[$res->id] ?? 0) + $x, 4);
                $left = round($left - $x, 4);
            }
        };
        $bal = [];
        foreach ($members as $res) {
            $bal[$res->id] = self::gone($res) ? 0 : $folio->balance($res);
            if ($bal[$res->id] > 0) {
                $give($res, $bal[$res->id]);
            }
        }
        foreach ($members as $res) {
            if ($left <= 0.004) {
                break;
            }
            $need = self::toCome($res, $folio) - max(0, -$bal[$res->id]); // credit the room holds already counts
            if ($need > 0) {
                $give($res, $need);
            }
        }
        if ($left > 0.004) {
            $open = $members->filter(fn ($r) => in_array($r->status, HotelReservation::HOLDING, true));
            $last = ($open->isNotEmpty() ? $open : $members)->last();
            if ($last) {
                $give($last, $left);
            }
        }

        return $out;
    }

    /**
     * Money received for the group: shared over its rooms (see share()), each part taken on that room's
     * bill the usual way. Returns ['amount' => x, 'shares' => [ref_no => amount], 'ref' => payment refs].
     */
    public static function receive(HotelGroup $group, $amount, $method, $note, $user_id, array $extra = [])
    {
        $amount = round((float) $amount, 4);
        if ($amount <= 0) {
            throw new HotelException(__('hotel.err_amount'));
        }

        return DB::transaction(function () use ($group, $amount, $method, $note, $user_id, $extra) {
            HotelGroup::lockForUpdate()->find($group->id);
            $folio = new HotelFolio();
            $shares = self::share($group, $amount);
            if (! $shares) {
                throw new HotelException(__('hotel.grp_err_nothing_open'));
            }
            $refs = [];
            $by_ref = [];
            foreach ($shares as $res_id => $x) {
                $res = HotelReservation::find($res_id);
                $r = $folio->receive($res, $x, $method, $note ?: $group->ref_no, $user_id, $extra);
                $refs[] = $r['ref'];
                $by_ref[$res->ref_no] = $x;
            }

            return ['amount' => $amount, 'shares' => $by_ref, 'ref' => implode(', ', array_filter($refs))];
        });
    }

    /** A room that was cancelled or never came: it takes no payment and its credit is not the group's. */
    public static function gone(HotelReservation $res)
    {
        return in_array($res->status, ['cancelled', 'no_show'], true);
    }

    /** Is this the group's last room still booked or in the hotel (the others left, or never came)? */
    public static function isLastOpen(HotelReservation $res)
    {
        return ! HotelReservation::where('group_id', $res->group_id)->where('id', '!=', $res->id)
            ->whereIn('status', HotelReservation::HOLDING)->exists();
    }

    /** May this room leave with its bill not settled? (a "payer settles everything" group with rooms still to come or in) */
    public static function leavesOwing(HotelReservation $res)
    {
        if (! $res->group_id) {
            return false;
        }
        $group = HotelGroup::find($res->group_id);

        return $group && $group->pays === 'payer' && ! self::isLastOpen($res);
    }

    /** Must the whole group be settled when this room leaves? (the last room of a "payer settles everything" group) */
    public static function settlesGroup(HotelReservation $res)
    {
        if (! $res->group_id) {
            return false;
        }
        $group = HotelGroup::find($res->group_id);

        return $group && $group->pays === 'payer' && self::isLastOpen($res);
    }
}
