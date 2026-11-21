<?php

namespace App\Http\Controllers;

use App\HotelGuest;
use App\HotelReservation;
use App\HotelRoom;
use App\HotelRoomType;
use App\Utils\Hotel;
use App\Utils\HotelException;
use App\Utils\HotelFolio;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** PosInnova hotel: reservations, and the stay they become (check-in, the bill, check-out). */
class HotelReservationController extends HotelBaseController
{
    public function index(Request $request)
    {
        $this->guard('hotel.view');
        $biz = $this->biz();
        $status = $request->input('status', 'upcoming');
        $q = trim((string) $request->input('q'));
        $query = HotelReservation::where('business_id', $biz)->with(['contact', 'roomType', 'room']);
        if ($status === 'upcoming') {
            $query->whereIn('status', ['tentative', 'confirmed'])->orderBy('arrival');
        } elseif ($status === 'in_house') {
            $query->where('status', 'checked_in')->orderBy('departure');
        } elseif (in_array($status, ['tentative', 'confirmed', 'checked_out', 'cancelled', 'no_show'], true)) {
            $query->where('status', $status)->orderByDesc('arrival');
        } else {
            $query->orderByDesc('id');
        }
        if ($q !== '') {
            $query->where(fn ($w) => $w->where('ref_no', 'like', '%'.$q.'%')->orWhereHas('contact', fn ($c) => $c->where('name', 'like', '%'.$q.'%')->orWhere('mobile', 'like', '%'.$q.'%')));
        }

        return view('hotel.reservations', ['list' => $query->paginate(30)->withQueryString(), 'status' => $status, 'q' => $q, 'folio' => new HotelFolio(), 'fmt' => fn ($v) => $this->money($v)]);
    }

    public function create(Request $request)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $biz = $this->biz();
        $walk_in = (bool) $request->input('walk_in');
        $arrival = $walk_in ? Hotel::hotelDay($biz)->toDateString()
            : ($request->input('arrival') ? Hotel::parseDate($request->input('arrival')) : Hotel::today()->toDateString());
        $departure = $request->input('departure') ? Hotel::parseDate($request->input('departure')) : Carbon::parse($arrival)->addDay()->toDateString();
        $types = HotelRoomType::where('business_id', $biz)->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get();
        if ($types->isEmpty()) {
            return redirect()->action([HotelSetupController::class, 'index'])->with('status', ['success' => 0, 'msg' => __('hotel.setup_first')]);
        }

        return view('hotel.reservation_form', [
            'walk_in' => $walk_in, 'arrival' => $arrival, 'departure' => $departure, 'types' => $types,
            'room_id' => $request->input('room_id'), 'type_id' => $request->input('room_type_id') ?: optional(HotelRoom::find($request->input('room_id')))->room_type_id,
            'payment_types' => $this->tu->payment_types(null, false, $biz), 'settings' => Hotel::settings($biz),
            'can_rate' => self::can('hotel.manager'),
        ]);
    }

    public function store(Request $request)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $biz = $this->biz();
        $data = $request->only(['contact_id', 'name', 'mobile', 'email', 'room_type_id', 'room_id', 'arrival', 'departure', 'adults', 'children', 'rate_plan', 'source', 'status', 'hold_until', 'notes']);
        $walk_in = (bool) $request->input('walk_in');

        try {
            $data['arrival'] = Hotel::parseDate($data['arrival'] ?? '');
            $data['departure'] = Hotel::parseDate($data['departure'] ?? '');
            if (! empty($data['hold_until'])) {
                $data['hold_until'] = Hotel::parseDate($data['hold_until']);
            }
            // a price other than the plan's needs a manager
            if ($request->filled('rate') && self::can('hotel.manager')) {
                $data['rate'] = $this->tu->num_uf($request->input('rate'));
            }
            if ($walk_in) {
                $data['arrival'] = Hotel::hotelDay($biz)->toDateString();
                $data['source'] = 'walk_in';
                $data['status'] = 'confirmed';
            }
            $res = Hotel::createReservation($biz, $data, auth()->id());

            $deposit = $request->filled('deposit') ? (float) $this->tu->num_uf($request->input('deposit')) : 0;
            $msg = __('hotel.res_created', ['ref' => $res->ref_no]);
            if ($deposit > 0) {
                $r = (new HotelFolio())->receive($res, $deposit, $request->input('method', 'cash'), __('hotel.deposit'), auth()->id());
                $msg .= ' '.__('hotel.deposit_taken', ['amount' => $this->money($deposit), 'ref' => $r['ref']]);
            }
        } catch (HotelException $e) {
            return back()->withInput()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::emergency('Hotel store: '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());

            return back()->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }

        $to = $walk_in ? action([self::class, 'checkInForm'], [$res->id]) : action([self::class, 'show'], [$res->id]);

        return redirect($to)->with('status', ['success' => 1, 'msg' => $msg]);
    }

    /** The reservation, or the stay with its bill. */
    public function show($id)
    {
        $this->guard('hotel.view');
        $res = $this->reservation($id);
        $folio = new HotelFolio();
        $biz = $this->biz();

        return view('hotel.stay', [
            'res' => $res, 'statement' => $folio->statement($res), 'balance' => $folio->balance($res),
            'guests' => $res->guests()->get(), 'moves' => \DB::table('hotel_room_moves as m')->leftJoin('hotel_rooms as f', 'f.id', '=', 'm.from_room_id')
                ->leftJoin('hotel_rooms as t', 't.id', '=', 'm.to_room_id')->where('m.reservation_id', $res->id)
                ->select('m.*', 'f.number as from_no', 't.number as to_no')->orderBy('m.id')->get(),
            'types' => HotelRoomType::where('business_id', $biz)->where('is_active', 1)->get(),
            'free_rooms' => $res->isOpen() ? Hotel::freeRooms($biz, $res->room_type_id, $res->status === 'checked_in' ? Hotel::today() : $res->arrival, $res->departure->max(Hotel::today()->addDay()), $res->id) : collect(),
            'move_rooms' => $res->status === 'checked_in' ? HotelRoom::where('business_id', $biz)->where('is_active', 1)->where('id', '!=', $res->room_id)->orderBy('number')->get()
                ->filter(fn ($r) => Hotel::roomFree($r, Hotel::today(), $res->departure->max(Hotel::today()->addDay()), $res->id))->values() : collect(),
            'payment_types' => $this->tu->payment_types(null, false, $biz), 'late_fee' => Hotel::lateCheckoutFee($res),
            'fmt' => fn ($v) => $this->money($v), 'num' => fn ($v) => $this->money($v, false),
            'can' => ['res' => self::can('hotel.reservations'), 'desk' => self::can('hotel.front_desk'), 'manager' => self::can('hotel.manager')],
        ]);
    }

    public function update(Request $request, $id)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $res = $this->reservation($id);

        return $this->attempt(function () use ($request, $res) {
            $arr = Hotel::parseDate($request->input('arrival', $res->arrival->toDateString()));
            $dep = Hotel::parseDate($request->input('departure', $res->departure->toDateString()));
            \DB::transaction(function () use ($request, $res, $arr, $dep) {
                if ($res->status !== 'checked_in' && $request->filled('room_type_id') && (int) $request->input('room_type_id') !== (int) $res->room_type_id) {
                    $type = HotelRoomType::where('business_id', $res->business_id)->findOrFail($request->input('room_type_id'));
                    $res->room_type_id = $type->id;
                    $res->room_id = null;
                    $res->rate = $type->rateFor($res->rate_plan);
                    $res->save();
                    \DB::table('hotel_room_nights')->where('reservation_id', $res->id)->delete();
                }
                if ($arr !== $res->arrival->toDateString() || $dep !== $res->departure->toDateString()) {
                    $res = Hotel::changeDates($res, $arr, $dep);
                }
                if ($res->status !== 'checked_in' && $request->has('room_id') && (string) $request->input('room_id') !== (string) $res->room_id) {
                    Hotel::assignRoom($res, $request->input('room_id') ?: null);
                }
                $res->refresh();
                if ($request->filled('rate_plan') && in_array($request->input('rate_plan'), HotelRoomType::PLANS, true) && $request->input('rate_plan') !== $res->rate_plan) {
                    $res->rate_plan = $request->input('rate_plan');
                    $res->rate = HotelRoomType::find($res->room_type_id)->rateFor($res->rate_plan);
                }
                if ($request->filled('rate') && self::can('hotel.manager')) {
                    $res->rate = max(0, (float) $this->tu->num_uf($request->input('rate')));
                }
                foreach (['adults', 'children'] as $f) {
                    if ($request->filled($f)) {
                        $res->{$f} = max($f === 'adults' ? 1 : 0, (int) $request->input($f));
                    }
                }
                if ($request->has('notes')) {
                    $res->notes = mb_substr(trim((string) $request->input('notes')), 0, 500) ?: null;
                }
                $res->save();
            });
        }, __('hotel.res_saved'));
    }

    public function confirm($id)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $res = $this->reservation($id);

        return $this->attempt(fn () => Hotel::confirm($res), __('hotel.res_confirmed', ['ref' => $res->ref_no]));
    }

    public function cancel(Request $request, $id)
    {
        $this->guard('hotel.reservations', 'hotel.front_desk');
        $res = $this->reservation($id);

        return $this->attempt(function () use ($request, $res) {
            if (trim((string) $request->input('reason')) === '') {
                throw new HotelException(__('hotel.err_reason'));
            }
            Hotel::cancel($res, auth()->id(), $request->input('reason'), $request->input('as') === 'no_show' ? 'no_show' : 'cancelled');
        }, fn () => __('hotel.res_cancelled', ['ref' => $res->ref_no]).((float) $res->fresh()->credit > 0 ? ' '.__('hotel.credit_kept', ['amount' => $this->money($res->fresh()->credit)]) : ''));
    }

    public function checkInForm($id)
    {
        $this->guard('hotel.front_desk');
        $res = $this->reservation($id);
        if ($res->status === 'checked_in') {
            return redirect()->action([self::class, 'show'], [$res->id]);
        }
        $biz = $this->biz();
        $from = $res->arrival->max(Hotel::today());
        $to = $res->departure->gt($from) ? $res->departure : $from->copy()->addDay();
        $rooms = Hotel::freeRooms($biz, $res->room_type_id, $from, $to, $res->id);
        if ($res->room && ! $rooms->contains('id', $res->room_id)) {
            $rooms->prepend($res->room);
        }
        $last = HotelGuest::where('business_id', $biz)->where('contact_id', $res->contact_id)->where('is_primary', 1)->orderByDesc('id')->first();
        $folio = new HotelFolio();

        return view('hotel.checkin', [
            'res' => $res, 'rooms' => $rooms, 'last' => $last, 'settings' => Hotel::settings($biz),
            'balance' => $folio->balance($res), 'nights' => count(Hotel::nights($from, $to)),
            'payment_types' => $this->tu->payment_types(null, false, $biz), 'fmt' => fn ($v) => $this->money($v),
            'sellable' => fn ($r) => Hotel::sellable($r),
        ]);
    }

    public function checkIn(Request $request, $id)
    {
        $this->guard('hotel.front_desk');
        $res = $this->reservation($id);
        $guests = array_values((array) $request->input('guests', []));

        try {
            $res = Hotel::checkIn($res, $request->input('room_id'), $guests, $request->input('keys'), auth()->id());
            $msg = __('hotel.checked_in', ['name' => optional($res->contact)->name, 'room' => optional($res->room)->number]);
            $pay = $request->filled('amount') ? (float) $this->tu->num_uf($request->input('amount')) : 0;
            if ($pay > 0) {
                $r = (new HotelFolio())->receive($res, $pay, $request->input('method', 'cash'), null, auth()->id());
                $msg .= ' '.__('hotel.payment_taken', ['amount' => $this->money($pay), 'ref' => $r['ref']]);
            }
        } catch (HotelException $e) {
            return back()->withInput()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::emergency('Hotel check-in: '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());

            return back()->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'show'], [$res->id])->with('status', ['success' => 1, 'msg' => $msg]);
    }

    /** The register card of the guest's last stay, to fill a returning guest's card. */
    public function lastCard($id)
    {
        $this->guard('hotel.front_desk');
        $res = $this->reservation($id);
        $g = HotelGuest::where('business_id', $this->biz())->where('contact_id', $res->contact_id)->where('is_primary', 1)->orderByDesc('id')->first();
        if (! $g) {
            return response()->json(null);
        }
        $out = [];
        foreach (HotelGuest::FIELDS as $f) {
            $out[$f] = $g->{$f} instanceof Carbon ? $g->{$f}->toDateString() : $g->{$f};
        }

        return response()->json($out);
    }

    public function payment(Request $request, $id)
    {
        $this->guard('hotel.front_desk', 'hotel.reservations');
        $res = $this->reservation($id);

        return $this->attempt(fn () => (new HotelFolio())->receive($res, $this->tu->num_uf($request->input('amount')), $request->input('method'), $request->input('note'), auth()->id(),
            $request->only(['card_number', 'card_transaction_number', 'cheque_number', 'bank_account_number'])),
            fn ($r) => __('hotel.payment_taken', ['amount' => $this->money($r['paid'] + $r['credit']), 'ref' => $r['ref']]));
    }

    public function charge(Request $request, $id)
    {
        $this->guard('hotel.front_desk');
        $res = $this->reservation($id);

        return $this->attempt(fn () => (new HotelFolio())->postCharge($res, $request->input('item'), $this->tu->num_uf($request->input('amount')), trim((string) $request->input('note')) ?: null, auth()->id()),
            fn ($l) => __('hotel.charge_posted', ['what' => $l->description, 'amount' => $this->money($l->amount)]));
    }

    public function discount(Request $request, $id)
    {
        $this->guard('hotel.manager');
        $res = $this->reservation($id);

        return $this->attempt(fn () => (new HotelFolio())->discount($res, $request->input('line_id'), $this->tu->num_uf($request->input('amount')), $request->input('reason'), auth()->id()),
            __('hotel.discount_given'));
    }

    public function void(Request $request, $id)
    {
        $this->guard('hotel.manager');
        $res = $this->reservation($id);

        return $this->attempt(fn () => (new HotelFolio())->void($res, $request->input('line_id'), $request->input('reason'), auth()->id()), __('hotel.charge_voided'));
    }

    public function move(Request $request, $id)
    {
        $this->guard('hotel.front_desk');
        $res = $this->reservation($id);
        $rate = $request->filled('rate') && self::can('hotel.manager') ? $this->tu->num_uf($request->input('rate')) : null;

        return $this->attempt(fn () => Hotel::moveRoom($res, $request->input('room_id'), $rate, $request->input('reason'), auth()->id()),
            fn ($r) => __('hotel.moved', ['room' => optional($r->room)->number]));
    }

    public function checkOutForm($id)
    {
        $this->guard('hotel.front_desk');
        $res = $this->reservation($id);
        if ($res->status !== 'checked_in') {
            return redirect()->action([self::class, 'show'], [$res->id]);
        }
        $folio = new HotelFolio();
        // every night slept is on the bill before it is shown (a missed audit, an arrival after midnight)
        try {
            $folio->catchUp($res, auth()->id());
        } catch (\Exception $e) {
            \Log::error('Hotel catch-up: '.$e->getMessage());
        }
        $res = $this->reservation($id);

        return view('hotel.checkout', [
            'res' => $res, 'statement' => $folio->statement($res), 'balance' => $folio->balance($res),
            'late_fee' => Hotel::lateCheckoutFee($res), 'late_posted' => $res->folioLines()->where('kind', 'late_checkout')->whereNull('voided_at')->exists(),
            'early' => $res->departure->gt(Hotel::today()), 'payment_types' => $this->tu->payment_types(null, false, $this->biz()),
            'fmt' => fn ($v) => $this->money($v), 'num' => fn ($v) => $this->money($v, false), 'can_leave_balance' => self::can('hotel.manager'),
        ]);
    }

    public function checkOut(Request $request, $id)
    {
        $this->guard('hotel.front_desk');
        $res = $this->reservation($id);
        $folio = new HotelFolio();

        try {
            if ($request->input('late') === 'add') {
                $fee = Hotel::lateCheckoutFee($res);
                if ($fee > 0 && ! $res->folioLines()->where('kind', 'late_checkout')->whereNull('voided_at')->exists()) {
                    $folio->postCharge($res, 'late_checkout', $fee, null, auth()->id());
                }
            }
            $pay = $request->filled('amount') ? (float) $this->tu->num_uf($request->input('amount')) : 0;
            if ($pay > 0) {
                $folio->receive($res->fresh(), $pay, $request->input('method', 'cash'), null, auth()->id());
            }
            $leave = $request->input('leave_balance') && self::can('hotel.manager');
            $res = Hotel::checkOut($res->fresh(), auth()->id(), $leave);
            $msg = __('hotel.checked_out', ['name' => optional($res->contact)->name, 'room' => optional($res->room)->number]);
            $balance = $folio->balance($res);
            if ($balance > 0.004) {
                $msg .= ' '.__('hotel.left_on_account', ['amount' => $this->money($balance)]);
            } elseif ((float) $res->credit > 0.004) {
                $msg .= ' '.__('hotel.credit_kept', ['amount' => $this->money($res->credit)]);
            }
        } catch (HotelException $e) {
            return back()->withInput()->with('status', ['success' => 0, 'msg' => $e->getMessage()]);
        } catch (\Exception $e) {
            \Log::emergency('Hotel check-out: '.$e->getFile().':'.$e->getLine().' '.$e->getMessage());

            return back()->withInput()->with('status', ['success' => 0, 'msg' => __('messages.something_went_wrong')]);
        }

        return redirect()->action([self::class, 'show'], [$res->id])->with('status', ['success' => 1, 'msg' => $msg])->with('hotel_print', 1);
    }

    public function printFolio($id)
    {
        $this->guard('hotel.view');
        $res = $this->reservation($id);
        $folio = new HotelFolio();
        $business = \App\Business::find($this->biz());
        $location = \App\BusinessLocation::find($res->location_id);

        return view('hotel.print_folio', [
            'res' => $res, 'statement' => $folio->statement($res), 'balance' => $folio->balance($res), 'business' => $business, 'location' => $location,
            'guest' => $res->guests()->first(), 'fmt' => fn ($v) => $this->money($v), 'num' => fn ($v) => $this->money($v, false),
        ]);
    }
}
