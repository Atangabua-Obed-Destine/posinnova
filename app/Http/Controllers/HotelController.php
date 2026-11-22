<?php

namespace App\Http\Controllers;

use App\Contact;
use App\HotelGuest;
use App\HotelNightAudit;
use App\HotelReservation;
use App\HotelRoom;
use App\HotelRoomType;
use App\Utils\Hotel;
use App\Utils\HotelAudit;
use App\Utils\HotelFolio;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** PosInnova hotel: the day at a glance, the room board, the calendar and housekeeping. */
class HotelController extends HotelBaseController
{
    public function home()
    {
        $this->guard('hotel.view');
        $biz = $this->biz();
        $today = Hotel::today();
        $rooms = HotelRoom::where('business_id', $biz)->where('is_active', 1)->get();
        if ($rooms->isEmpty() && self::can('hotel.setup')) {
            return redirect()->action([HotelSetupController::class, 'index'])->with('status', ['success' => 1, 'msg' => __('hotel.setup_first')]);
        }
        $board = $this->roomStates($biz, $rooms);
        $arrivals = Hotel::arrivals($biz);
        $departures = Hotel::departures($biz);
        $in_house = Hotel::inHouse($biz);
        $folio = new HotelFolio();
        $owed = $in_house->sum(fn ($r) => max(0, $folio->balance($r)));
        $sellable = $rooms->filter(fn ($r) => ! $r->outOfOrderOn($today))->count();
        $expected = $in_house->count() + $arrivals->where('arrival', '<=', $today)->count() - $departures->where('status', 'checked_in')->filter(fn ($r) => $r->departure->lte($today))->count();
        $next_audit = HotelAudit::nextDate($biz);
        $summary = HotelAudit::summary($biz, $today->toDateString());

        $attention = [];
        // guests pay before they sleep: who is in the hotel and not paid up to tonight
        $unpaid = collect();
        if (Hotel::settings($biz)['pay_first']) {
            foreach ($in_house as $r) {
                $c = $folio->coverage($r);
                if ($c && $c['to_collect'] > 0.004) {
                    $unpaid->push(['res' => $r, 'amount' => $c['to_collect']]);
                }
            }
        }
        foreach ($arrivals as $a) {
            if ($a->room && ! Hotel::sellable($a->room)) {
                $attention[] = ['tone' => 'amber', 'text' => __('hotel.att_dirty_arrival', ['room' => $a->room->number, 'name' => optional($a->contact)->name]), 'url' => action([self::class, 'housekeeping'])];
            }
        }
        if ($next_audit->lt($today)) {
            $attention[] = ['tone' => 'red', 'text' => __('hotel.att_audit_late', ['date' => $next_audit->format('d/m/Y')]), 'url' => action([HotelAuditController::class, 'index'])];
        }
        $tentative = HotelReservation::where('business_id', $biz)->where('status', 'tentative')->where('arrival', '<=', $today->copy()->addDay())->count();
        if ($tentative) {
            $attention[] = ['tone' => 'purple', 'text' => trans_choice('hotel.att_tentative', $tentative, ['n' => $tentative]), 'url' => action([HotelReservationController::class, 'index'], ['status' => 'tentative'])];
        }

        return view('hotel.home', [
            'today' => $today, 'rooms' => $rooms, 'board' => $board, 'arrivals' => $arrivals, 'departures' => $departures,
            'in_house' => $in_house, 'owed' => $owed, 'unpaid' => $unpaid, 'sellable' => $sellable, 'expected' => max(0, $expected),
            'next_audit' => $next_audit, 'last_audit' => HotelNightAudit::where('business_id', $biz)->orderByDesc('audit_date')->first(),
            'summary' => $summary, 'attention' => $attention, 'folio' => $folio, 'fmt' => fn ($v) => $this->money($v), 'num' => fn ($v) => $this->money($v, false),
        ]);
    }

    /** Each room's state now: occ | dep | arr | dirty | free | ooo, with the stay or booking behind it. */
    private function roomStates($biz, $rooms)
    {
        $today = Hotel::today();
        $in = HotelReservation::where('business_id', $biz)->where('status', 'checked_in')->whereNotNull('room_id')->with('contact')->get()->keyBy('room_id');
        $arr = HotelReservation::where('business_id', $biz)->whereIn('status', ['tentative', 'confirmed'])->whereNotNull('room_id')
            ->where('arrival', '<=', $today->toDateString())->with('contact')->get()->keyBy('room_id');
        $out = [];
        foreach ($rooms as $room) {
            $res = $in->get($room->id);
            if ($res) {
                $state = $res->departure->lte($today) ? 'dep' : 'occ';
            } elseif ($room->outOfOrderOn($today)) {
                $state = 'ooo';
            } elseif ($arr->get($room->id)) {
                $res = $arr->get($room->id);
                $state = 'arr';
            } else {
                $state = Hotel::sellable($room) ? 'free' : 'dirty';
            }
            $out[$room->id] = ['room' => $room, 'state' => $state, 'res' => $res];
        }

        return $out;
    }

    public function board()
    {
        $this->guard('hotel.view');
        $biz = $this->biz();
        $rooms = HotelRoom::where('business_id', $biz)->where('is_active', 1)->with('type')->orderBy('floor')->orderBy('number')->get();
        $states = $this->roomStates($biz, $rooms);
        $counts = collect($states)->countBy('state')->all();

        return view('hotel.board', [
            'floors' => collect($states)->groupBy(fn ($s) => (string) ($s['room']->floor ?? '')),
            'counts' => $counts, 'total' => count($states), 'folio' => new HotelFolio(), 'fmt' => fn ($v) => $this->money($v),
        ]);
    }

    public function calendar(Request $request)
    {
        $this->guard('hotel.view');
        $biz = $this->biz();
        $days = 14;
        $start = $request->filled('start') ? Carbon::parse(Hotel::parseDate($request->input('start'))) : Hotel::today()->subDays(2);
        $end = $start->copy()->addDays($days);
        $rooms = HotelRoom::where('business_id', $biz)->where('is_active', 1)->with('type')->orderBy('floor')->orderBy('number')->get();
        $bookings = HotelReservation::where('business_id', $biz)
            ->whereIn('status', ['tentative', 'confirmed', 'checked_in', 'checked_out'])
            ->where('arrival', '<', $end->toDateString())->where('departure', '>', $start->toDateString())
            ->with('contact', 'roomType')->get();

        $bars = [];
        foreach ($bookings->whereNotNull('room_id') as $b) {
            $from = $b->arrival->max($start);
            $to = $b->departure->min($end);
            $col = $start->diffInDays($from) + 1;
            $span = max(1, $from->diffInDays($to));
            $bars[$b->room_id][] = ['res' => $b, 'col' => $col, 'span' => min($span, $days - $col + 1), 'kind' => [
                'checked_in' => 'in', 'confirmed' => 'conf', 'tentative' => 'tent', 'checked_out' => 'out'][$b->status]];
        }
        $ooo = [];
        foreach ($rooms as $r) {
            if ($r->ooo_from && $r->ooo_from->lt($end) && (! $r->ooo_to || $r->ooo_to->gte($start))) {
                $from = $r->ooo_from->max($start);
                $to = $r->ooo_to ? $r->ooo_to->copy()->addDay()->min($end) : $end;
                $ooo[$r->id] = ['col' => $start->diffInDays($from) + 1, 'span' => max(1, $from->diffInDays($to)), 'reason' => $r->ooo_reason];
            }
        }
        $avail = Hotel::availability($biz, $start, $end);

        return view('hotel.calendar', [
            'start' => $start, 'days' => $days, 'rooms' => $rooms, 'bars' => $bars, 'ooo' => $ooo, 'avail' => $avail,
            'unassigned' => $bookings->whereNull('room_id')->whereIn('status', ['tentative', 'confirmed'])->sortBy('arrival'),
            'today' => Hotel::today(),
        ]);
    }

    public function housekeeping()
    {
        $this->guard('hotel.housekeeping', 'hotel.front_desk');
        $biz = $this->biz();
        $today = Hotel::today();
        $rooms = HotelRoom::where('business_id', $biz)->where('is_active', 1)->with('type')->orderBy('floor')->orderBy('number')->get();
        $states = $this->roomStates($biz, $rooms);
        $arrival_rooms = HotelReservation::where('business_id', $biz)->whereIn('status', ['tentative', 'confirmed'])->where('arrival', $today->toDateString())
            ->whereNotNull('room_id')->pluck('room_id')->all();
        $s = Hotel::settings($biz);
        $list = collect($states)->map(function ($st) use ($arrival_rooms, $s) {
            $room = $st['room'];
            if ($st['state'] === 'ooo') {
                $group = 'ooo';
            } elseif ($room->hk_status === 'dirty') {
                $group = in_array($st['state'], ['occ', 'dep'], true) ? ($st['state'] === 'dep' ? 'leaving' : 'stayover') : 'departure';
            } elseif ($room->hk_status === 'clean' && ! empty($s['require_inspection'])) {
                $group = 'inspect';
            } else {
                $group = 'ready';
            }

            return $st + ['group' => $group, 'urgent' => in_array($room->id, $arrival_rooms) && $room->hk_status === 'dirty'];
        })->sortBy(fn ($st) => [array_search($st['group'], ['departure', 'stayover', 'leaving', 'inspect', 'ooo', 'ready']), $st['urgent'] ? 0 : 1, $st['room']->number])->values();

        return view('hotel.housekeeping', ['list' => $list, 'require_inspection' => ! empty($s['require_inspection']),
            'done' => $list->whereIn('group', ['ready', 'inspect'])->count(), 'todo' => $list->whereIn('group', ['departure', 'stayover'])->count()]);
    }

    public function markRoom(Request $request, $id)
    {
        $this->guard('hotel.housekeeping', 'hotel.front_desk');
        $room = HotelRoom::where('business_id', $this->biz())->findOrFail($id);
        $status = $request->input('hk_status');
        if ($status === 'inspected' && ! self::can('hotel.manager') && ! self::can('hotel.housekeeping')) {
            abort(403);
        }

        return $this->attempt(fn () => Hotel::setHousekeeping($room, $status, auth()->id()), __('hotel.hk_saved', ['room' => $room->number, 'status' => __('hotel.hk_'.$status)]));
    }

    /** Guests for the reservation form: name, phone, ID number. */
    public function guestSearch(Request $request)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $biz = $this->biz();
        $by_id = HotelGuest::where('business_id', $biz)->where('id_number', 'like', '%'.$q.'%')->whereNotNull('contact_id')->pluck('contact_id')->all();
        $contacts = Contact::where('business_id', $biz)->whereIn('type', ['customer', 'both'])->where('contact_status', 'active')
            ->where(fn ($w) => $w->where('name', 'like', '%'.$q.'%')->orWhere('mobile', 'like', '%'.$q.'%')->orWhereIn('id', $by_id ?: [0]))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'mobile']);
        $stays = HotelReservation::where('business_id', $biz)->whereIn('contact_id', $contacts->pluck('id'))->where('status', 'checked_out')
            ->selectRaw('contact_id, COUNT(*) n')->groupBy('contact_id')->pluck('n', 'contact_id');

        return response()->json($contacts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'mobile' => $c->mobile, 'stays' => (int) ($stays[$c->id] ?? 0)]));
    }

    /** Free rooms per type for the reservation form. */
    public function availability(Request $request)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        try {
            $arr = Hotel::parseDate($request->input('arrival'));
            $dep = Hotel::parseDate($request->input('departure'));
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        if (! $arr || ! $dep || $dep <= $arr) {
            return response()->json(['error' => __('hotel.err_dates')], 422);
        }
        $nights = count(Hotel::nights($arr, $dep));
        $biz = $this->biz();
        $out = [];
        foreach (Hotel::availability($biz, $arr, $dep, $request->input('except')) as $tid => $a) {
            $t = $a['type'];
            $plans = [];
            foreach ($t->plans() as $p) {
                $plans[$p] = ['label' => __('hotel.plan_'.$p), 'rate' => $t->rateFor($p), 'rate_f' => $this->money($t->rateFor($p), false), 'total_f' => $this->money($t->rateFor($p) * $nights, false)];
            }
            $out[] = ['id' => $tid, 'name' => $t->name, 'description' => $t->description, 'capacity' => $t->capacity, 'free' => max(0, $a['free']),
                'rooms' => $a['rooms'], 'plans' => $plans, 'tax' => (float) $t->tourist_tax,
                'free_rooms' => Hotel::freeRooms($biz, $tid, $arr, $dep, $request->input('except'))->map(fn ($r) => ['id' => $r->id, 'number' => $r->number, 'clean' => Hotel::sellable($r)])->values()];
        }

        return response()->json(['nights' => $nights, 'types' => $out]);
    }
}
