<?php

namespace App\Utils;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\HotelGuest;
use App\HotelReservation;
use App\HotelRoom;
use App\HotelRoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova hotel module: settings, availability, reservations, check-in,
 * check-out, room moves and housekeeping. Money is in App\Utils\HotelFolio,
 * the close of each hotel day in App\Utils\HotelAudit.
 *
 * Two locks keep the hotel from selling what it does not have:
 * - hotel_room_nights (unique room + night): an assigned room cannot be given twice for a night;
 * - the room type row is locked while a booking is counted, so two clerks cannot both take
 *   the last free room of a type.
 */
class Hotel
{
    const DEFAULTS = [
        'location_id' => null,
        'checkin_time' => '14:00',
        'checkout_time' => '12:00',
        'audit_time' => '02:00',
        'charge_mode' => 'nightly',   // nightly | checkin
        'late_checkout' => 'half',    // half: half a night until 18:00, a full night after | none
        'deposit_rule' => 'first_night', // first_night | none | full
        'police_card' => 1,
        'require_inspection' => 0,
        'pay_first' => 1,             // guests pay before they sleep: the screens show who is not paid up to tonight
    ];

    const SOURCES = ['walk_in', 'phone', 'whatsapp', 'booking_com', 'agency', 'company', 'other'];

    const EXTRAS = ['laundry', 'minibar', 'extra_bed', 'phone', 'damage', 'other'];

    public static function enabled($business_id = null)
    {
        $business_id = $business_id ?: session('business.id');
        $mods = session('business.id') == $business_id ? (array) session('business.enabled_modules') : (array) optional(Business::find($business_id))->enabled_modules;

        return in_array('hotel', $mods ?: [], true);
    }

    public static function settings($business_id)
    {
        if (! isset(self::$settingsCache[$business_id])) {
            $raw = DB::table('hotel_settings')->where('business_id', $business_id)->value('settings');
            $s = array_merge(self::DEFAULTS, (array) json_decode((string) $raw, true));
            if (empty($s['location_id'])) {
                $s['location_id'] = BusinessLocation::where('business_id', $business_id)->orderBy('id')->value('id');
            }
            self::$settingsCache[$business_id] = $s;
        }

        return self::$settingsCache[$business_id];
    }

    public static function saveSettings($business_id, array $values)
    {
        $s = array_merge(self::settings($business_id), $values);
        DB::table('hotel_settings')->updateOrInsert(['business_id' => $business_id], ['settings' => json_encode($s), 'updated_at' => now(), 'created_at' => now()]);
        self::forget();

        return $s;
    }

    /** Clears the per-request settings cache (after saving, tests, the scheduled run). */
    public static function forget()
    {
        self::$settingsCache = [];
    }

    private static $settingsCache = [];

    public static function today()
    {
        return Carbon::today();
    }

    /**
     * The hotel day a guest arriving now belongs to. After midnight, until the night audit time, while
     * yesterday is not closed yet, it is still yesterday: a guest arriving at 00:30 sleeps "last night",
     * and the audit that closes it puts that night on the bill.
     */
    public static function hotelDay($business_id)
    {
        $today = self::today();
        $s = self::settings($business_id);
        [$h, $m] = array_map('intval', explode(':', $s['audit_time'] ?: '02:00') + [0, 0]);
        if ($h >= 12 || now()->gte(now()->setTime($h, $m))) {
            return $today;
        }
        $yesterday = $today->copy()->subDay();
        $closed = DB::table('hotel_night_audits')->where('business_id', $business_id)->where('audit_date', $yesterday->toDateString())->exists();

        return $closed ? $today : $yesterday;
    }

    /** The nights of a stay: [arrival, departure) as Y-m-d strings. */
    public static function nights($from, $to)
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        if ($to->lte($from)) {
            return [];
        }

        return collect(CarbonPeriod::create($from, $to->copy()->subDay()))->map(fn ($d) => $d->toDateString())->all();
    }

    /**
     * Free rooms per type for every night of [from, to).
     *
     * @return array type_id => ['type' => HotelRoomType, 'rooms' => n, 'free' => least free on any night, 'by_night' => [date => free]]
     */
    public static function availability($business_id, $from, $to, $except_reservation_id = null)
    {
        $nights = self::nights($from, $to);
        $types = HotelRoomType::where('business_id', $business_id)->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get();
        $rooms = HotelRoom::where('business_id', $business_id)->where('is_active', 1)->get()->groupBy('room_type_id');

        $today = self::today()->toDateString();
        $bookings = HotelReservation::where('business_id', $business_id)
            ->whereIn('status', HotelReservation::HOLDING)
            ->where('arrival', '<', Carbon::parse($to)->toDateString())
            ->when($except_reservation_id, fn ($q) => $q->where('id', '!=', $except_reservation_id))
            ->get(['id', 'room_type_id', 'arrival', 'departure', 'status']);

        $out = [];
        foreach ($types as $type) {
            $type_rooms = $rooms->get($type->id, collect());
            $by_night = [];
            foreach ($nights as $n) {
                $sellable = $type_rooms->filter(fn ($r) => ! $r->outOfOrderOn($n))->count();
                $booked = $bookings->filter(function ($b) use ($type, $n, $today) {
                    if ($b->room_type_id != $type->id) {
                        return false;
                    }
                    $dep = $b->departure->toDateString();
                    // a guest still in the room after the planned departure keeps it
                    if ($b->status === 'checked_in' && $dep <= $today) {
                        $dep = Carbon::parse($today)->addDay()->toDateString();
                    }

                    return $b->arrival->toDateString() <= $n && $n < $dep;
                })->count();
                $by_night[$n] = $sellable - $booked;
            }
            $out[$type->id] = [
                'type' => $type,
                'rooms' => $type_rooms->count(),
                'free' => empty($by_night) ? 0 : min($by_night),
                'by_night' => $by_night,
            ];
        }

        return $out;
    }

    /** Is that room free for [from, to)? (not out of order, not given to another stay) */
    public static function roomFree(HotelRoom $room, $from, $to, $except_reservation_id = null)
    {
        if (! $room->is_active || $room->outOfOrderBetween($from, $to)) {
            return false;
        }

        return ! DB::table('hotel_room_nights')->where('room_id', $room->id)
            ->where('night', '>=', Carbon::parse($from)->toDateString())
            ->where('night', '<', Carbon::parse($to)->toDateString())
            ->when($except_reservation_id, fn ($q) => $q->where('reservation_id', '!=', $except_reservation_id))
            ->exists();
    }

    /** Rooms of a type free for [from, to), clean ones first. */
    public static function freeRooms($business_id, $type_id, $from, $to, $except_reservation_id = null)
    {
        return HotelRoom::where('business_id', $business_id)->where('room_type_id', $type_id)->where('is_active', 1)
            ->orderBy('number')->get()
            ->filter(fn ($r) => self::roomFree($r, $from, $to, $except_reservation_id))
            ->sortBy(fn ($r) => $r->isTidy() ? 0 : 1)->values();
    }

    /**
     * Holds the room's nights for the reservation (replacing what it held). Throws when a night is taken.
     * from: only nights from that date are replaced (a room move keeps the nights already slept).
     */
    public static function holdRoom(HotelReservation $res, $from = null)
    {
        $from = $from ? Carbon::parse($from)->toDateString() : $res->arrival->toDateString();
        DB::table('hotel_room_nights')->where('reservation_id', $res->id)->where('night', '>=', $from)->delete();
        if (empty($res->room_id)) {
            return;
        }
        $start = max($from, $res->arrival->toDateString());
        foreach (self::nights($start, $res->departure) as $n) {
            try {
                DB::table('hotel_room_nights')->insert(['business_id' => $res->business_id, 'room_id' => $res->room_id, 'night' => $n, 'reservation_id' => $res->id]);
            } catch (\Illuminate\Database\QueryException $e) {
                throw new HotelException(__('hotel.err_room_taken', ['room' => optional($res->room)->number, 'date' => Carbon::parse($n)->format('d/m')]));
            }
        }
    }

    private static function checkDates($arrival, $departure)
    {
        $a = Carbon::parse($arrival)->startOfDay();
        $d = Carbon::parse($departure)->startOfDay();
        if ($d->lte($a)) {
            throw new HotelException(__('hotel.err_dates'));
        }
        if ($a->diffInDays($d) > 365) {
            throw new HotelException(__('hotel.err_too_long'));
        }
    }

    /** One more booking of the type fits on every night? (call inside a transaction, the type row locked) */
    private static function ensureTypeFree($business_id, $type_id, $from, $to, $except = null)
    {
        $avail = self::availability($business_id, $from, $to, $except)[$type_id] ?? null;
        if (! $avail) {
            throw new HotelException(__('hotel.err_type'));
        }
        foreach ($avail['by_night'] as $n => $free) {
            if ($free < 1) {
                throw new HotelException(__('hotel.err_full', ['type' => $avail['type']->name, 'date' => Carbon::parse($n)->format('d/m')]));
            }
        }
    }

    /** The guest's contact: an existing customer, or a new one. */
    public static function guestContact($business_id, $data, $user_id)
    {
        if (! empty($data['contact_id'])) {
            return Contact::where('business_id', $business_id)->whereIn('type', ['customer', 'both'])->findOrFail($data['contact_id']);
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new HotelException(__('hotel.err_guest'));
        }
        $mobile = trim((string) ($data['mobile'] ?? ''));
        if ($mobile !== '') {
            $found = Contact::where('business_id', $business_id)->whereIn('type', ['customer', 'both'])->where('mobile', $mobile)->first();
            if ($found) {
                return $found;
            }
        }
        $parts = preg_split('/\s+/', $name, 2);

        return (new ContactUtil())->createNewContact([
            'business_id' => $business_id, 'type' => 'customer', 'name' => $name,
            'first_name' => $parts[0], 'last_name' => $parts[1] ?? null,
            'mobile' => $mobile, 'email' => $data['email'] ?? null, 'created_by' => $user_id,
        ])['data'];
    }

    /**
     * New reservation. $data: contact_id | name+mobile, room_type_id, room_id?, arrival, departure, adults,
     * children, rate_plan, rate?, source, status (tentative|confirmed), hold_until?, notes
     */
    public static function createReservation($business_id, array $data, $user_id)
    {
        self::checkDates($data['arrival'], $data['departure']);
        $s = self::settings($business_id);

        return DB::transaction(function () use ($business_id, $data, $user_id, $s) {
            $type = HotelRoomType::where('business_id', $business_id)->lockForUpdate()->findOrFail($data['room_type_id']);
            self::ensureTypeFree($business_id, $type->id, $data['arrival'], $data['departure']);
            $contact = self::guestContact($business_id, $data, $user_id);

            $plan = in_array($data['rate_plan'] ?? '', HotelRoomType::PLANS, true) ? $data['rate_plan'] : 'room_only';
            $rate = isset($data['rate']) && $data['rate'] !== '' && $data['rate'] !== null ? (float) $data['rate'] : $type->rateFor($plan);
            $count = (new Util())->setAndGetReferenceCount('hotel_reservation', $business_id);

            $res = HotelReservation::create([
                'business_id' => $business_id,
                'location_id' => $s['location_id'],
                'ref_no' => 'RES-'.str_pad($count, 4, '0', STR_PAD_LEFT),
                'contact_id' => $contact->id,
                'room_type_id' => $type->id,
                'room_id' => null,
                'arrival' => Carbon::parse($data['arrival'])->toDateString(),
                'departure' => Carbon::parse($data['departure'])->toDateString(),
                'adults' => max(1, (int) ($data['adults'] ?? 1)),
                'children' => max(0, (int) ($data['children'] ?? 0)),
                'rate_plan' => $plan,
                'rate' => max(0, $rate),
                'source' => in_array($data['source'] ?? '', self::SOURCES, true) ? $data['source'] : 'walk_in',
                'status' => ($data['status'] ?? 'confirmed') === 'tentative' ? 'tentative' : 'confirmed',
                'hold_until' => ! empty($data['hold_until']) ? Carbon::parse($data['hold_until'])->toDateString() : null,
                'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500) ?: null,
                'created_by' => $user_id,
            ]);

            if (! empty($data['room_id'])) {
                self::assignRoom($res, $data['room_id']);
            }

            return $res->fresh();
        });
    }

    /** Gives the reservation a room (or none: null) for its nights. */
    public static function assignRoom(HotelReservation $res, $room_id)
    {
        if (empty($room_id)) {
            $res->room_id = null;
            $res->save();
            self::holdRoom($res);

            return $res;
        }
        $room = HotelRoom::where('business_id', $res->business_id)->where('room_type_id', $res->room_type_id)->findOrFail($room_id);
        if (! self::roomFree($room, $res->arrival, $res->departure, $res->id)) {
            throw new HotelException(__('hotel.err_room_not_free', ['room' => $room->number]));
        }
        $res->room_id = $room->id;
        $res->save();
        $res->setRelation('room', $room);
        self::holdRoom($res);

        return $res;
    }

    /** New dates (and, before arrival, type / plan / rate). A stay in the hotel only changes its departure. */
    public static function changeDates(HotelReservation $res, $arrival, $departure)
    {
        if (! $res->isOpen()) {
            throw new HotelException(__('hotel.err_closed'));
        }
        if ($res->status === 'checked_in') {
            $arrival = $res->arrival->toDateString();
            if (Carbon::parse($departure)->lt(self::today())) {
                throw new HotelException(__('hotel.err_departure_past'));
            }
        }
        self::checkDates($arrival, $departure);

        return DB::transaction(function () use ($res, $arrival, $departure) {
            HotelRoomType::where('id', $res->room_type_id)->lockForUpdate()->first();
            // a guest in the hotel: only tonight onwards is checked and held again (the nights slept stay as they were,
            // in the rooms they were slept in)
            $in = $res->status === 'checked_in';
            $from = $in ? max(Carbon::parse($arrival)->toDateString(), self::today()->toDateString()) : $arrival;
            self::ensureTypeFree($res->business_id, $res->room_type_id, $from, $departure, $res->id);
            if ($res->room_id) {
                $room = HotelRoom::find($res->room_id);
                if (! self::roomFree($room, $from, $departure, $res->id)) {
                    throw new HotelException(__('hotel.err_room_not_free', ['room' => $room->number]));
                }
            }
            $res->arrival = $arrival;
            $res->departure = $departure;
            $res->save();
            self::holdRoom($res, $in ? $from : null);

            return $res->fresh();
        });
    }

    public static function confirm(HotelReservation $res)
    {
        if ($res->status !== 'tentative') {
            throw new HotelException(__('hotel.err_closed'));
        }
        $res->status = 'confirmed';
        $res->hold_until = null;
        $res->save();
    }

    public static function cancel(HotelReservation $res, $user_id, $reason, $status = 'cancelled')
    {
        if (! in_array($res->status, ['tentative', 'confirmed'], true)) {
            throw new HotelException(__('hotel.err_closed'));
        }
        DB::transaction(function () use ($res, $user_id, $reason, $status) {
            $res->status = $status;
            $res->cancelled_at = now();
            $res->cancelled_by = $user_id;
            $res->cancel_reason = mb_substr(trim((string) $reason), 0, 191) ?: null;
            $res->save();
            DB::table('hotel_room_nights')->where('reservation_id', $res->id)->delete();
        });
    }

    /** The arrivals still to come today (and late ones), the stays leaving today, the guests in. */
    public static function arrivals($business_id, $date = null)
    {
        $date = Carbon::parse($date ?: self::today())->toDateString();

        return HotelReservation::where('business_id', $business_id)->whereIn('status', ['tentative', 'confirmed'])
            ->where('arrival', '<=', $date)->with(['contact', 'roomType', 'room'])->orderBy('arrival')->orderBy('id')->get();
    }

    public static function departures($business_id, $date = null)
    {
        $date = Carbon::parse($date ?: self::today())->toDateString();

        return HotelReservation::where('business_id', $business_id)
            ->where(function ($q) use ($date) {
                $q->where(fn ($q) => $q->where('status', 'checked_in')->where('departure', '<=', $date))
                    ->orWhere(fn ($q) => $q->where('status', 'checked_out')->whereDate('checked_out_at', $date));
            })
            ->with(['contact', 'roomType', 'room'])->orderBy('status')->orderBy('departure')->get();
    }

    public static function inHouse($business_id)
    {
        return HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')
            ->with(['contact', 'roomType', 'room'])->get()->sortBy(fn ($r) => optional($r->room)->number)->values();
    }

    /**
     * Check-in. $guests: list of police cards (the first is the main guest). $room_id: the room given.
     * Returns the reservation, checked in.
     */
    public static function checkIn(HotelReservation $res, $room_id, array $guests, $keys, $user_id)
    {
        if (! in_array($res->status, ['tentative', 'confirmed'], true)) {
            throw new HotelException(__('hotel.err_closed'));
        }
        $today = self::today();
        if ($res->arrival->gt($today)) {
            throw new HotelException(__('hotel.err_arrival_future', ['date' => $res->arrival->format('d/m/Y')]));
        }
        $s = self::settings($res->business_id);
        $guests = array_values(array_filter($guests, fn ($g) => trim((string) ($g['surname'] ?? '')) !== ''));
        if (empty($guests)) {
            throw new HotelException(__('hotel.err_card'));
        }
        if (! empty($s['police_card']) && trim((string) ($guests[0]['id_number'] ?? '')) === '') {
            throw new HotelException(__('hotel.err_card_id'));
        }

        $day = self::hotelDay($res->business_id);

        return DB::transaction(function () use ($res, $room_id, $guests, $keys, $user_id, $day, $s) {
            // a late guest: the nights already gone are not theirs (after midnight, "today" is still the hotel day not yet closed)
            if ($res->arrival->lt($day)) {
                $res->arrival = $day->toDateString();
                if ($res->departure->lte($day)) {
                    $res->departure = $day->copy()->addDay()->toDateString();
                }
                $res->save();
            }
            if ((int) $room_id !== (int) $res->room_id) {
                self::assignRoom($res, $room_id);
            } else {
                self::holdRoom($res);
            }
            if (empty($res->room_id)) {
                throw new HotelException(__('hotel.err_room_needed'));
            }
            // the room's last guest has not been checked out
            $still = HotelReservation::where('business_id', $res->business_id)->where('status', 'checked_in')
                ->where('room_id', $res->room_id)->where('id', '!=', $res->id)->with('contact')->first();
            if ($still) {
                throw new HotelException(__('hotel.err_room_occupied', ['room' => optional($res->room)->number, 'name' => optional($still->contact)->name]));
            }

            foreach ($guests as $i => $g) {
                $row = ['business_id' => $res->business_id, 'reservation_id' => $res->id, 'is_primary' => $i === 0 ? 1 : 0,
                    'contact_id' => $i === 0 ? $res->contact_id : null];
                foreach (HotelGuest::FIELDS as $f) {
                    $v = isset($g[$f]) ? trim((string) $g[$f]) : '';
                    if (in_array($f, ['dob', 'id_issued_on'], true)) {
                        $v = $v === '' ? null : self::parseDate($v);
                    }
                    $row[$f] = $v === '' ? null : (is_string($v) ? mb_substr($v, 0, 191) : $v);
                }
                HotelGuest::create($row);
            }

            $res->status = 'checked_in';
            $res->checked_in_at = now();
            $res->checked_in_by = $user_id;
            $res->keys_given = $keys !== null && $keys !== '' ? max(0, (int) $keys) : null;
            $res->save();

            if ($s['charge_mode'] === 'checkin') {
                $folio = new HotelFolio();
                foreach (self::nights($res->arrival, $res->departure) as $n) {
                    $folio->postNight($res, $n, $user_id);
                }
            }

            return $res->fresh(['room', 'roomType', 'contact']);
        });
    }

    /** Accepts dd/mm/yyyy (business format) or Y-m-d. */
    public static function parseDate($v)
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        foreach ([session('business.date_format') ?: 'd/m/Y', 'd/m/Y', 'Y-m-d', 'm/d/Y', 'd-m-Y'] as $f) {
            try {
                $d = Carbon::createFromFormat('!'.$f, $v);
                if ($d && $d->format($f) === $v) {
                    return $d->toDateString();
                }
            } catch (\Exception $e) {
            }
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Exception $e) {
            throw new HotelException(__('hotel.err_date', ['value' => $v]));
        }
    }

    /** Moves a guest in the hotel to another free room from tonight; the old room goes to housekeeping. */
    public static function moveRoom(HotelReservation $res, $to_room_id, $new_rate, $reason, $user_id)
    {
        if ($res->status !== 'checked_in') {
            throw new HotelException(__('hotel.err_closed'));
        }
        $today = self::today()->toDateString();

        return DB::transaction(function () use ($res, $to_room_id, $new_rate, $reason, $user_id, $today) {
            $to = HotelRoom::where('business_id', $res->business_id)->where('is_active', 1)->findOrFail($to_room_id);
            if ($to->id == $res->room_id) {
                throw new HotelException(__('hotel.err_same_room'));
            }
            $until = $res->departure->toDateString() > $today ? $res->departure : Carbon::parse($today)->addDay();
            if (! self::roomFree($to, $today, $until, $res->id)) {
                throw new HotelException(__('hotel.err_room_not_free', ['room' => $to->number]));
            }
            if ($to->room_type_id != $res->room_type_id) {
                HotelRoomType::where('id', $to->room_type_id)->lockForUpdate()->first();
                // another type: one of its rooms is used from tonight
                self::ensureTypeFree($res->business_id, $to->room_type_id, $today, $until, $res->id);
            }
            $from_room = $res->room_id;
            $old_rate = $res->rate;
            DB::table('hotel_room_moves')->insert([
                'business_id' => $res->business_id, 'reservation_id' => $res->id, 'from_room_id' => $from_room, 'to_room_id' => $to->id,
                'old_rate' => $old_rate, 'new_rate' => $new_rate !== null && $new_rate !== '' ? $new_rate : $old_rate,
                'reason' => mb_substr(trim((string) $reason), 0, 191) ?: null, 'moved_by' => $user_id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $res->room_id = $to->id;
            $res->room_type_id = $to->room_type_id;
            if ($new_rate !== null && $new_rate !== '') {
                $res->rate = max(0, (float) $new_rate);
            }
            $res->save();
            $res->setRelation('room', $to);
            self::holdRoom($res, $today);
            if ($from_room) {
                self::setHousekeeping(HotelRoom::find($from_room), 'dirty', $user_id);
            }

            return $res->fresh(['room', 'roomType']);
        });
    }

    public static function setHousekeeping(HotelRoom $room, $status, $user_id)
    {
        if (! in_array($status, ['dirty', 'clean', 'inspected'], true)) {
            throw new HotelException(__('messages.something_went_wrong'));
        }
        $room->hk_status = $status;
        $room->hk_by = $user_id;
        $room->hk_at = now();
        $room->save();

        return $room;
    }

    /** Can a clean room be sold, or must a supervisor inspect it first? */
    public static function sellable(HotelRoom $room)
    {
        $s = self::settings($room->business_id);

        return ! empty($s['require_inspection']) ? $room->hk_status === 'inspected' : $room->isTidy();
    }

    /**
     * Check-out. The bill must be settled unless a manager leaves the balance on the guest's account.
     * The departure becomes today; nights after today held by the stay are released.
     */
    public static function checkOut(HotelReservation $res, $user_id, $allow_balance = false)
    {
        if ($res->status !== 'checked_in') {
            throw new HotelException(__('hotel.err_closed'));
        }
        $folio = new HotelFolio();
        $today = self::today()->toDateString();

        return DB::transaction(function () use ($res, $user_id, $allow_balance, $folio, $today) {
            // every night slept is on the bill, even if a night audit was missed (the arrival night always)
            $folio->catchUp($res, $user_id);
            // nights charged in advance but not slept: their unpaid invoices are cancelled
            $first_not_slept = max($today, $res->arrival->copy()->addDay()->toDateString());
            $folio->releaseNightsFrom($res, $first_not_slept, $user_id);
            $res->refresh(); // the bill work above changed the stay's credit in the database
            $balance = $folio->balance($res);
            if ($balance > 0.004 && ! $allow_balance) {
                throw new HotelException(__('hotel.err_balance', ['amount' => (new Util())->num_f($balance)]));
            }
            $res->departure = $today > $res->arrival->toDateString() ? $today : $res->arrival->copy()->addDay()->toDateString();
            $res->status = 'checked_out';
            $res->checked_out_at = now();
            $res->checked_out_by = $user_id;
            $res->save();
            DB::table('hotel_room_nights')->where('reservation_id', $res->id)->where('night', '>=', $today)->delete();
            if ($res->room_id) {
                self::setHousekeeping(HotelRoom::find($res->room_id), 'dirty', $user_id);
            }

            return $res->fresh();
        });
    }

    /** The late checkout fee due if the guest leaves now (0 when none). */
    public static function lateCheckoutFee(HotelReservation $res, Carbon $now = null)
    {
        $s = self::settings($res->business_id);
        $now = $now ?: now();
        if ($s['late_checkout'] === 'none' || $res->status !== 'checked_in' || $res->departure->toDateString() !== $now->toDateString()) {
            return 0;
        }
        [$h, $m] = array_map('intval', explode(':', $s['checkout_time'] ?: '12:00') + [0, 0]);
        $limit = $now->copy()->setTime($h, $m);
        if ($now->lte($limit)) {
            return 0;
        }
        $after6 = $now->gt($now->copy()->setTime(18, 0));

        return round((float) $res->rate * ($after6 ? 1 : 0.5), 4);
    }
}
