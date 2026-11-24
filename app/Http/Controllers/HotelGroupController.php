<?php

namespace App\Http\Controllers;

use App\HotelGroup;
use App\HotelRoomType;
use App\Utils\Hotel;
use App\Utils\HotelException;
use App\Utils\HotelFolio;
use App\Utils\HotelGroups;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** PosInnova hotel: group bookings (several rooms, one payer). */
class HotelGroupController extends HotelBaseController
{
    public function create(Request $request)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $biz = $this->biz();
        $types = HotelRoomType::where('business_id', $biz)->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get();
        if ($types->isEmpty()) {
            return redirect()->action([HotelSetupController::class, 'index'])->with('status', ['success' => 0, 'msg' => __('hotel.setup_first')]);
        }
        $arrival = $request->input('arrival') ? Hotel::parseDate($request->input('arrival')) : Hotel::hotelDay($biz)->toDateString();

        return view('hotel.group_form', [
            'arrival' => $arrival, 'types' => $types, 'settings' => Hotel::settings($biz),
            'payment_types' => $this->tu->payment_types(null, false, $biz), 'can_rate' => self::can('hotel.manager'),
        ]);
    }

    public function store(Request $request)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $biz = $this->biz();
        $data = $request->only(['contact_id', 'name', 'mobile', 'pays', 'notes', 'rate_plan', 'source', 'status', 'hold_until']);
        $data['rooms'] = [];
        foreach (array_values((array) $request->input('rooms', [])) as $r) {
            if (! is_array($r)) {
                continue;
            }
            $row = array_intersect_key($r, array_flip(['room_type_id', 'room_id', 'arrival', 'departure', 'adults', 'occupant']));
            // a price other than the plan's needs a manager
            if (isset($r['rate']) && $r['rate'] !== '' && self::can('hotel.manager')) {
                $row['rate'] = $this->tu->num_uf($r['rate']);
            }
            $data['rooms'][] = $row;
        }

        try {
            if (! empty($data['hold_until'])) {
                $data['hold_until'] = Hotel::holdUntil($data['hold_until']);
            }
            $result = \DB::transaction(function () use ($biz, $data, $request) {
                $group = HotelGroups::create($biz, $data, auth()->id());
                $paid = $request->filled('deposit') ? (float) $this->tu->num_uf($request->input('deposit')) : 0;
                $r = $paid > 0 ? HotelGroups::receive($group, $paid, $request->input('method', 'cash'), __('hotel.deposit'), auth()->id()) : null;

                return [$group, $r];
            });
            [$group, $r] = $result;
        } catch (HotelException $e) {
            return back()->withInput()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::emergency('Hotel group store: '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());

            return back()->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }
        $msg = __('hotel.grp_created', ['ref' => $group->ref_no, 'n' => count($data['rooms'])]);
        if ($r) {
            $msg .= ' '.__('hotel.payment_taken', ['amount' => $this->money($r['amount']), 'ref' => $r['ref']]);
        }

        return redirect()->action([self::class, 'show'], [$group->id])->with('status', ['success' => 1, 'msg' => $msg]);
    }

    public function show($id)
    {
        $this->guard('hotel.view');
        $group = $this->group($id);
        $biz = $this->biz();

        return view('hotel.group', [
            'group' => $group, 'sum' => HotelGroups::summary($group), 'today' => Hotel::today(),
            'types' => HotelRoomType::where('business_id', $biz)->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get(),
            'payment_types' => $this->tu->payment_types(null, false, $biz), 'fmt' => fn ($v) => $this->money($v), 'num' => fn ($v) => $this->money($v, false),
            'can' => ['res' => self::can('hotel.reservations'), 'desk' => self::can('hotel.front_desk')],
        ]);
    }

    public function payment(Request $request, $id)
    {
        $this->guard('hotel.front_desk', 'hotel.reservations');
        $group = $this->group($id);

        return $this->attempt(fn () => HotelGroups::receive($group, $this->tu->num_uf($request->input('amount')), $request->input('method'), $request->input('note'), auth()->id(),
            $request->only(['card_number', 'card_transaction_number', 'cheque_number', 'bank_account_number'])),
            fn ($r) => __('hotel.payment_taken', ['amount' => $this->money($r['amount']), 'ref' => $r['ref']]));
    }

    public function addRoom(Request $request, $id)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $group = $this->group($id);
        $first = HotelGroups::members($group)->first();

        return $this->attempt(function () use ($request, $group, $first) {
            return \DB::transaction(fn () => HotelGroups::addRoom($group, [
                'room_type_id' => $request->input('room_type_id'), 'room_id' => $request->input('room_id'),
                'arrival' => $request->input('arrival'), 'departure' => $request->input('departure'), 'adults' => $request->input('adults', 1),
                'occupant' => $request->input('occupant'), 'rate_plan' => optional($first)->rate_plan ?: 'room_only',
                'source' => optional($first)->source ?: 'phone', 'status' => 'confirmed',
            ], auth()->id()));
        }, fn ($res) => __('hotel.grp_room_added', ['ref' => $res->ref_no]));
    }

    public function printBill($id)
    {
        $this->guard('hotel.view');
        $group = $this->group($id);
        $folio = new HotelFolio();
        $rooms = HotelGroups::members($group)->map(fn ($r) => ['res' => $r, 'st' => $folio->statement($r)]);

        return view('hotel.group_print', [
            'group' => $group, 'rooms' => $rooms, 'sum' => HotelGroups::summary($group),
            'business' => \App\Business::find($this->biz()), 'location' => \App\BusinessLocation::find(Hotel::settings($this->biz())['location_id']),
            'fmt' => fn ($v) => $this->money($v), 'num' => fn ($v) => $this->money($v, false),
        ]);
    }

    private function group($id)
    {
        return HotelGroup::where('business_id', $this->biz())->with('contact')->findOrFail($id);
    }
}
