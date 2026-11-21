<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\HotelReservation;
use App\HotelRoom;
use App\HotelRoomType;
use App\Utils\Hotel;
use App\Utils\HotelException;
use App\Utils\HotelServices;
use Illuminate\Http\Request;

/** PosInnova hotel: room types and rates, rooms, settings. */
class HotelSetupController extends HotelBaseController
{
    public function index(Request $request)
    {
        $this->guard('hotel.setup');
        $biz = $this->biz();
        $tab = in_array($request->input('tab'), ['types', 'rooms', 'settings'], true) ? $request->input('tab') : 'types';
        $types = HotelRoomType::where('business_id', $biz)->withCount(['rooms' => fn ($q) => $q->where('is_active', 1)])->orderBy('sort_order')->orderBy('id')->get();
        if ($types->isEmpty() && ! $request->has('tab')) {
            $tab = 'types';
        }

        return view('hotel.setup', [
            'tab' => $tab, 'types' => $types,
            'rooms' => HotelRoom::where('business_id', $biz)->with('type')->orderBy('floor')->orderBy('number')->get(),
            'settings' => Hotel::settings($biz), 'locations' => BusinessLocation::where('business_id', $biz)->pluck('name', 'id'),
            'num' => fn ($v) => $v === null ? '' : $this->money($v, false), 'today' => Hotel::today(),
        ]);
    }

    private function typeData(Request $request)
    {
        $n = fn ($k) => $request->filled($k) ? max(0, (float) $this->tu->num_uf($request->input($k))) : null;
        $name = trim((string) $request->input('name'));
        if ($name === '' || $n('rate_room_only') === null) {
            throw new HotelException(__('hotel.err_type_fields'));
        }

        return [
            'name' => mb_substr($name, 0, 100), 'description' => mb_substr(trim((string) $request->input('description')), 0, 255) ?: null,
            'capacity' => max(1, min(20, (int) $request->input('capacity', 2))),
            'rate_room_only' => $n('rate_room_only'), 'rate_bb' => $n('rate_bb'), 'rate_corporate' => $n('rate_corporate'),
            'tourist_tax' => $n('tourist_tax') ?? 0, 'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }

    public function storeType(Request $request)
    {
        $this->guard('hotel.setup');

        return $this->attempt(function () use ($request) {
            $type = HotelRoomType::create(['business_id' => $this->biz()] + $this->typeData($request));
            HotelServices::roomNight($type, auth()->id());
        }, __('hotel.type_saved'), redirect()->action([self::class, 'index'], ['tab' => 'types']));
    }

    public function updateType(Request $request, $id)
    {
        $this->guard('hotel.setup');
        $type = HotelRoomType::where('business_id', $this->biz())->findOrFail($id);

        return $this->attempt(function () use ($request, $type) {
            $type->fill($this->typeData($request));
            if ($request->has('is_active')) {
                $type->is_active = (bool) $request->input('is_active');
            }
            $type->save();
            HotelServices::roomNight($type, auth()->id());
        }, __('hotel.type_saved'), redirect()->action([self::class, 'index'], ['tab' => 'types']));
    }

    /** One room, or several at once: "101-112" (or "101, 102, 105"). */
    public function storeRooms(Request $request)
    {
        $this->guard('hotel.setup');
        $biz = $this->biz();

        return $this->attempt(function () use ($request, $biz) {
            $type = HotelRoomType::where('business_id', $biz)->findOrFail($request->input('room_type_id'));
            $numbers = $this->parseNumbers((string) $request->input('numbers'));
            if (empty($numbers)) {
                throw new HotelException(__('hotel.err_room_numbers'));
            }
            $taken = HotelRoom::where('business_id', $biz)->whereIn('number', $numbers)->pluck('number')->all();
            if ($taken) {
                throw new HotelException(__('hotel.err_room_exists', ['rooms' => implode(', ', $taken)]));
            }
            foreach ($numbers as $no) {
                HotelRoom::create(['business_id' => $biz, 'room_type_id' => $type->id, 'number' => $no,
                    'floor' => trim((string) $request->input('floor')) ?: null, 'hk_status' => 'clean']);
            }

            return count($numbers);
        }, fn ($n) => trans_choice('hotel.rooms_added', $n, ['n' => $n]), redirect()->action([self::class, 'index'], ['tab' => 'rooms']));
    }

    private function parseNumbers($s)
    {
        $out = [];
        foreach (preg_split('/[,;\s]+/', trim($s)) as $part) {
            if ($part === '') {
                continue;
            }
            if (preg_match('/^(\d+)-(\d+)$/', $part, $m) && (int) $m[2] >= (int) $m[1] && (int) $m[2] - (int) $m[1] < 200) {
                foreach (range((int) $m[1], (int) $m[2]) as $n) {
                    $out[] = (string) $n;
                }
            } else {
                $out[] = mb_substr($part, 0, 20);
            }
        }

        return array_values(array_unique($out));
    }

    public function updateRoom(Request $request, $id)
    {
        $this->guard('hotel.setup');
        $biz = $this->biz();
        $room = HotelRoom::where('business_id', $biz)->findOrFail($id);

        return $this->attempt(function () use ($request, $room, $biz) {
            $number = trim((string) $request->input('number', $room->number));
            if ($number === '' || HotelRoom::where('business_id', $biz)->where('number', $number)->where('id', '!=', $room->id)->exists()) {
                throw new HotelException(__('hotel.err_room_exists', ['rooms' => $number]));
            }
            $busy = HotelReservation::where('business_id', $biz)->whereIn('status', HotelReservation::HOLDING)->where('room_id', $room->id)->exists();
            if ($request->filled('room_type_id') && (int) $request->input('room_type_id') !== (int) $room->room_type_id) {
                if ($busy) {
                    throw new HotelException(__('hotel.err_room_busy'));
                }
                $room->room_type_id = HotelRoomType::where('business_id', $biz)->findOrFail($request->input('room_type_id'))->id;
            }
            if ($request->has('is_active') && ! $request->input('is_active') && $busy) {
                throw new HotelException(__('hotel.err_room_busy'));
            }
            $room->number = mb_substr($number, 0, 20);
            if ($request->has('floor')) {
                $room->floor = trim((string) $request->input('floor')) ?: null;
            }
            if ($request->has('is_active')) {
                $room->is_active = (bool) $request->input('is_active');
            }
            $room->save();
        }, __('hotel.room_saved'), redirect()->action([self::class, 'index'], ['tab' => 'rooms']));
    }

    /** Out of order from … to (both included), or back in service (clear=1). */
    public function outOfOrder(Request $request, $id)
    {
        $this->guard('hotel.setup', 'hotel.front_desk');
        $biz = $this->biz();
        $room = HotelRoom::where('business_id', $biz)->findOrFail($id);

        return $this->attempt(function () use ($request, $room, $biz) {
            if ($request->input('clear')) {
                $room->update(['ooo_from' => null, 'ooo_to' => null, 'ooo_reason' => null]);

                return;
            }
            $from = Hotel::parseDate($request->input('from')) ?: Hotel::today()->toDateString();
            $to = Hotel::parseDate($request->input('to'));
            if ($to && $to < $from) {
                throw new HotelException(__('hotel.err_dates'));
            }
            $end = $to ? \Carbon\Carbon::parse($to)->addDay()->toDateString() : '2999-12-31';
            $clash = \DB::table('hotel_room_nights')->where('room_id', $room->id)->where('night', '>=', $from)->where('night', '<', $end)->exists();
            if ($clash) {
                throw new HotelException(__('hotel.err_ooo_booked', ['room' => $room->number]));
            }
            $room->update(['ooo_from' => $from, 'ooo_to' => $to, 'ooo_reason' => mb_substr(trim((string) $request->input('reason')), 0, 191) ?: null]);
        }, __('hotel.room_saved'));
    }

    public function saveSettings(Request $request)
    {
        $this->guard('hotel.setup');
        $biz = $this->biz();
        $time = fn ($k, $d) => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) $request->input($k)) ? $request->input($k) : $d;

        return $this->attempt(fn () => Hotel::saveSettings($biz, [
            'location_id' => BusinessLocation::where('business_id', $biz)->where('id', $request->input('location_id'))->value('id') ?: Hotel::settings($biz)['location_id'],
            'checkin_time' => $time('checkin_time', '14:00'), 'checkout_time' => $time('checkout_time', '12:00'), 'audit_time' => $time('audit_time', '02:00'),
            'charge_mode' => $request->input('charge_mode') === 'checkin' ? 'checkin' : 'nightly',
            'late_checkout' => $request->input('late_checkout') === 'none' ? 'none' : 'half',
            'deposit_rule' => in_array($request->input('deposit_rule'), ['first_night', 'none', 'full'], true) ? $request->input('deposit_rule') : 'first_night',
            'police_card' => $request->boolean('police_card') ? 1 : 0,
            'require_inspection' => $request->boolean('require_inspection') ? 1 : 0,
        ]), __('hotel.settings_saved'), redirect()->action([self::class, 'index'], ['tab' => 'settings']));
    }
}
