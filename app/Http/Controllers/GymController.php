<?php

namespace App\Http\Controllers;

use App\GymMembership;
use App\GymPlan;
use App\GymVisit;
use App\Utils\Gym;
use App\Utils\GymWhatsApp;
use App\Utils\Hotel;
use App\Utils\HotelRoomCharge;
use App\Utils\Util;
use Illuminate\Http\Request;

/**
 * PosInnova gym: the door (find, let in, renew, day pass, hotel guests), the members (list,
 * new member, a member's page, renew, the card with its QR code).
 */
class GymController extends GymBaseController
{
    // ---------------------------------------------------------------- the door

    public function door(Request $request)
    {
        $this->guard('gym.door', 'gym.members');
        $bid = $this->biz();
        $q = trim((string) $request->input('q'));
        $found = $q !== '' ? Gym::search($bid, $q) : null;

        return view('gym.door', [
            'q' => $q, 'found' => $found, 'today' => Gym::visitsToday($bid), 's' => Gym::settings($bid),
            'plans' => Gym::plans($bid, ['months', 'visits']), 'dayPlan' => Gym::dayPlan($bid),
            'methods' => (new Util())->payment_types(null, false, $bid), 'roomBill' => HotelRoomCharge::enabled($bid),
            'counts' => Gym::members($bid),
            'needsSetup' => ! GymPlan::where('business_id', $bid)->where('is_active', 1)->where('price', '>', 0)->exists(),
        ]);
    }

    public function checkIn($id)
    {
        $this->guard('gym.door');
        $m = $this->member($id);

        return $this->attempt(fn () => Gym::checkIn($m, auth()->id()), __('gym.let_in_ok', ['name' => optional($m->contact)->name]));
    }

    public function dayPass(Request $request)
    {
        $this->guard('gym.door');

        return $this->attempt(fn () => Gym::dayPass($this->biz(), $request->only(['member_id', 'reservation_id', 'name', 'mobile', 'pay', 'method']), auth()->id()),
            fn ($v) => __('gym.pass_ok', ['name' => $v->name]));
    }

    public function guestIn(Request $request)
    {
        $this->guard('gym.door');

        return $this->attempt(fn () => Gym::guestIn($this->biz(), (int) $request->input('reservation_id'), auth()->id()), fn ($v) => __('gym.let_in_ok', ['name' => $v->name]));
    }

    // ---------------------------------------------------------------- members

    public function members(Request $request)
    {
        $this->guard('gym.members', 'gym.door');
        $filter = in_array($request->input('f'), ['expiring', 'expired'], true) ? $request->input('f') : 'all';
        $data = Gym::members($this->biz(), $filter);
        $data['links'] = $data['list']->mapWithKeys(fn ($m) => [$m->id => in_array($m->state, ['expiring', 'expired']) ? GymWhatsApp::link($m, $m->state === 'expiring' ? 'expiring' : 'expired') : null])->all();
        $data['filter'] = $filter;

        return view('gym.members', $data);
    }

    public function create()
    {
        $this->guard('gym.members');
        $bid = $this->biz();

        return view('gym.member_form', ['plans' => Gym::plans($bid, ['months', 'visits']), 'methods' => (new Util())->payment_types(null, false, $bid)]);
    }

    public function store(Request $request)
    {
        $this->guard('gym.members');

        return $this->attempt(fn () => Gym::newMember($this->biz(), $request->only(['name', 'mobile', 'plan_id', 'method', 'start_date', 'whatsapp', 'lang', 'note']), auth()->id()),
            fn ($m) => __('gym.member_saved', ['no' => $m->member_no]),
            fn ($m) => redirect()->action([self::class, 'show'], [$m->id]));
    }

    public function show($id)
    {
        $this->guard('gym.members', 'gym.door');
        $m = $this->member($id);
        $bid = $this->biz();

        return view('gym.member', [
            'm' => $m, 'state' => Gym::status($m), 'current' => $m->current(),
            'memberships' => GymMembership::where('member_id', $m->id)->with('plan')->orderByDesc('end_date')->orderByDesc('id')->get(),
            'visits' => GymVisit::where('member_id', $m->id)->orderByDesc('visited_at')->limit(30)->get(),
            'plans' => Gym::plans($bid, ['months', 'visits']), 'methods' => (new Util())->payment_types(null, false, $bid),
            'messages' => \Illuminate\Support\Facades\Schema::hasColumn('clinic_whatsapp_messages', 'gym_member_id')
                ? \App\ClinicWhatsappMessage::where('gym_member_id', $m->id)->orderByDesc('id')->limit(10)->get() : collect(),
        ]);
    }

    public function renew(Request $request, $id)
    {
        $this->guard('gym.members', 'gym.door');
        $m = $this->member($id);

        return $this->attempt(fn () => Gym::renew($m, (int) $request->input('plan_id'), $request->input('method'), auth()->id()),
            fn ($ms) => __('gym.renewed', ['name' => optional($m->contact)->name, 'to' => $ms->end_date->format('d/m/Y')]));
    }

    public function card($id)
    {
        $this->guard('gym.members', 'gym.door');
        $m = $this->member($id);

        return view('gym.card', ['m' => $m, 'current' => $m->current() ?: $m->latest(), 'business' => \App\Business::find($this->biz())]);
    }
}
