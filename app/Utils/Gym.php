<?php

namespace App\Utils;

use App\Business;
use App\BusinessLocation;
use App\Contact;
use App\GymMember;
use App\GymMembership;
use App\GymPlan;
use App\GymVisit;
use App\HotelReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova gym: plans and passes, members, the door.
 *
 * Money is never kept apart: a membership or a day pass is an ordinary final sale (a non-stock
 * service product per plan, ServiceSale) paid at once into the cashier's open register, or, for a
 * hotel guest, put on the room bill (HotelRoomCharge). A membership runs from its start date to the
 * day before the same date N months later; renewing starts the day after the current one ends, or
 * today when it has lapsed (the clinic packages' rule). A card of visits counts down at the door.
 * Hotel guests: free while they stay, a day pass on the room, or pay like anyone (setting).
 */
class Gym
{
    const DEFAULTS = [
        'location_id' => null,
        'member_prefix' => 'G-',
        'guest_rule' => 'free',      // free | room | same
        'remind_before' => 3,
        'remind_after' => 5,
        'send_expiring' => 1,
        'send_expired' => 1,
        'tpl_expiring' => 'gym_expiring',
        'tpl_expired' => 'gym_expired',
    ];

    private static $settingsCache = [];

    public static function enabled($business_id = null)
    {
        $business_id = $business_id ?: session('business.id');
        $mods = session('business.id') == $business_id ? (array) session('business.enabled_modules') : (array) optional(Business::find($business_id))->enabled_modules;

        return in_array('gym', $mods ?: [], true);
    }

    public static function settings($business_id)
    {
        if (! isset(self::$settingsCache[$business_id])) {
            $raw = DB::table('gym_settings')->where('business_id', $business_id)->value('settings');
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
        DB::table('gym_settings')->updateOrInsert(['business_id' => $business_id], ['settings' => json_encode($s), 'updated_at' => now(), 'created_at' => now()]);
        unset(self::$settingsCache[$business_id]);

        return self::settings($business_id);
    }

    public static function forget()
    {
        self::$settingsCache = [];
    }

    // ------------------------------------------------------------------ plans

    /** The usual plans, made once without prices (switched on when a price is typed). */
    public static function addUsualPlans($business_id)
    {
        $n = 0;
        foreach ([['day', 0, 0], ['months', 1, 0], ['months', 3, 0], ['months', 12, 0], ['visits', 3, 10]] as $i => [$kind, $months, $visits]) {
            $name = __('gym.usual_'.$kind.($kind === 'day' ? '' : '_'.($kind === 'visits' ? $visits : $months)));
            if (GymPlan::where('business_id', $business_id)->where('name', $name)->exists()) {
                continue;
            }
            GymPlan::create(['business_id' => $business_id, 'name' => $name, 'kind' => $kind, 'months' => $months, 'visits' => $visits,
                'price' => 0, 'is_active' => 0, 'sort_order' => $i + 1]);
            $n++;
        }

        return $n;
    }

    public static function plans($business_id, $kinds = null)
    {
        return GymPlan::where('business_id', $business_id)->where('is_active', 1)->where('price', '>', 0)
            ->when($kinds, fn ($q) => $q->whereIn('kind', (array) $kinds))->orderBy('sort_order')->orderBy('id')->get();
    }

    public static function dayPlan($business_id)
    {
        return self::plans($business_id, 'day')->first();
    }

    private static function variation(GymPlan $plan, $user_id)
    {
        $v = ServiceSale::product($plan->business_id, 'GYM-'.$plan->id, __('gym.product', ['plan' => $plan->name]), (float) $plan->price, $user_id);
        if ((int) $plan->variation_id !== (int) $v->id) {
            $plan->variation_id = $v->id;
            $plan->save();
        }

        return $v;
    }

    // ------------------------------------------------------------------ members

    /** A new member: their contact (found by number), member number, and a first plan paid now. */
    public static function newMember($business_id, array $data, $user_id)
    {
        $name = trim((string) ($data['name'] ?? ''));
        $mobile = trim((string) ($data['mobile'] ?? ''));
        if ($name === '' || $mobile === '') {
            throw new GymException(__('gym.err_member'));
        }
        $plan = GymPlan::where('business_id', $business_id)->whereIn('kind', ['months', 'visits'])->where('is_active', 1)->find($data['plan_id'] ?? 0);
        if (! $plan || (float) $plan->price <= 0) {
            throw new GymException(__('gym.err_plan'));
        }
        if (! ServiceSale::validMethod($business_id, $data['method'] ?? '')) {
            throw new GymException(__('gym.err_method'));
        }
        $contact = Hotel::guestContact($business_id, ['name' => $name, 'mobile' => $mobile], $user_id);
        if (GymMember::where('business_id', $business_id)->where('contact_id', $contact->id)->exists()) {
            throw new GymException(__('gym.err_already_member', ['name' => $contact->name]));
        }

        return DB::transaction(function () use ($business_id, $data, $user_id, $contact, $mobile, $plan) {
            $seq = (new Util())->setAndGetReferenceCount('gym_member', $business_id);
            $member = GymMember::create([
                'business_id' => $business_id, 'seq' => $seq, 'member_no' => self::settings($business_id)['member_prefix'].str_pad($seq, 4, '0', STR_PAD_LEFT),
                'contact_id' => $contact->id, 'phone' => $mobile, 'whatsapp' => ! empty($data['whatsapp']),
                'lang' => in_array($data['lang'] ?? '', ['fr', 'en'], true) ? $data['lang'] : 'fr',
                'note' => mb_substr(trim((string) ($data['note'] ?? '')), 0, 1000) ?: null, 'created_by' => $user_id,
            ]);
            self::renew($member, $plan->id, $data['method'], $user_id, $data['start_date'] ?? null);

            return $member->fresh();
        });
    }

    /**
     * A new period (or card) for a member, paid now. Starts the day after the latest one ends, or
     * today if it has lapsed ($start overrides, never before today's lapsed rule would allow).
     */
    public static function renew(GymMember $member, $plan_id, $method, $user_id, $start = null)
    {
        $plan = GymPlan::where('business_id', $member->business_id)->whereIn('kind', ['months', 'visits'])->where('is_active', 1)->find($plan_id);
        if (! $plan || (float) $plan->price <= 0) {
            throw new GymException(__('gym.err_plan'));
        }
        if (! ServiceSale::validMethod($member->business_id, $method)) {
            throw new GymException(__('gym.err_method'));
        }
        $latest = $member->latest();
        $from = $latest && $latest->end_date->gte(today()) && $latest->visits_left === null ? $latest->end_date->copy()->addDay() : today();
        if ($start) {
            $from = Carbon::parse($start)->startOfDay();
        }
        $months = $plan->kind === 'months' ? max(1, (int) $plan->months) : max(1, (int) $plan->months ?: 6);
        $end = $from->copy()->addMonthsNoOverflow($months)->subDay();
        $s = self::settings($member->business_id);

        return DB::transaction(function () use ($member, $plan, $method, $user_id, $from, $end, $s) {
            $v = self::variation($plan, $user_id);
            $note = __('gym.sale_note', ['no' => $member->member_no, 'plan' => $plan->name, 'from' => $from->format('d/m/Y'), 'to' => $end->format('d/m/Y')]);
            $t = ServiceSale::sell($member->business_id, $s['location_id'], $member->contact_id, $v, (float) $plan->price, $note, $user_id);
            ServiceSale::pay($t, (float) $plan->price, $method, $user_id, $member->member_no);

            return GymMembership::create([
                'business_id' => $member->business_id, 'member_id' => $member->id, 'plan_id' => $plan->id,
                'start_date' => $from->toDateString(), 'end_date' => $end->toDateString(),
                'visits_left' => $plan->kind === 'visits' ? max(1, (int) $plan->visits) : null,
                'amount' => $plan->price, 'transaction_id' => $t->id, 'created_by' => $user_id,
            ]);
        });
    }

    /** valid | expiring (ends within the reminder days) | future | expired | none */
    public static function status(GymMember $m)
    {
        $cur = $m->current();
        if ($cur) {
            if ($cur->visits_left !== null) {
                return $cur->visits_left <= 2 ? 'expiring' : 'valid';
            }

            return $cur->end_date->lte(today()->addDays((int) self::settings($m->business_id)['remind_before'])) && ! self::renewedAfter($cur) ? 'expiring' : 'valid';
        }
        $latest = $m->latest();
        if (! $latest) {
            return 'none';
        }

        return $latest->start_date->gt(today()) ? 'future' : 'expired';
    }

    private static function renewedAfter(GymMembership $ms)
    {
        return GymMembership::where('member_id', $ms->member_id)->where('start_date', '>', $ms->end_date)->exists();
    }

    // ------------------------------------------------------------------ the door

    /** A member comes in: counted, and a visit taken off a card of visits. */
    public static function checkIn(GymMember $member, $user_id)
    {
        $cur = $member->current();
        if (! $cur) {
            throw new GymException(__('gym.err_not_valid', ['name' => optional($member->contact)->name]));
        }

        return DB::transaction(function () use ($member, $cur, $user_id) {
            if ($cur->visits_left !== null) {
                $cur->visits_left = max(0, $cur->visits_left - 1);
                $cur->save();
            }

            return GymVisit::create(['business_id' => $member->business_id, 'kind' => 'member', 'member_id' => $member->id, 'membership_id' => $cur->id,
                'contact_id' => $member->contact_id, 'name' => optional($member->contact)->name, 'user_id' => $user_id, 'visited_at' => now()]);
        });
    }

    /**
     * A day pass, sold at the door: to a member whose plan lapsed, to anyone (name optional), or to a
     * hotel guest (paid, or on the room bill). $data: member_id | reservation_id | name, mobile; pay now|room; method.
     */
    public static function dayPass($business_id, array $data, $user_id)
    {
        $plan = self::dayPlan($business_id);
        if (! $plan) {
            throw new GymException(__('gym.err_no_day_plan'));
        }
        $s = self::settings($business_id);
        $member = ! empty($data['member_id']) ? GymMember::where('business_id', $business_id)->find($data['member_id']) : null;
        $res = ! empty($data['reservation_id']) ? HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->find($data['reservation_id']) : null;
        if (! empty($data['reservation_id']) && ! $res) {
            throw new GymException(__('gym.err_guest_not_in'));
        }
        $room = ($data['pay'] ?? 'now') === 'room';
        if ($room && (! $res || ! HotelRoomCharge::enabled($business_id))) {
            throw new GymException(__('gym.err_room'));
        }
        if (! $room && ! ServiceSale::validMethod($business_id, $data['method'] ?? '')) {
            throw new GymException(__('gym.err_method'));
        }
        if ($member) {
            $contact_id = $member->contact_id;
        } elseif ($res) {
            $contact_id = $res->contact_id;
        } elseif (trim((string) ($data['mobile'] ?? '')) !== '' && trim((string) ($data['name'] ?? '')) !== '') {
            $contact_id = Hotel::guestContact($business_id, ['name' => $data['name'], 'mobile' => $data['mobile']], $user_id)->id;
        } else {
            $contact_id = Contact::where('business_id', $business_id)->where('is_default', 1)->value('id');
        }
        $name = $member ? optional($member->contact)->name : ($res ? optional(Contact::find($res->contact_id))->name : (trim((string) ($data['name'] ?? '')) ?: __('gym.walk_in')));

        return DB::transaction(function () use ($business_id, $plan, $s, $member, $res, $room, $data, $contact_id, $name, $user_id) {
            $v = self::variation($plan, $user_id);
            $t = ServiceSale::sell($business_id, $res ? ($res->location_id ?: $s['location_id']) : $s['location_id'], $contact_id, $v, (float) $plan->price,
                __('gym.pass_note', ['name' => $name]).($res ? ' · '.$res->ref_no : ''), $user_id);
            if ($room) {
                if (! HotelRoomCharge::attach($t, $res->id, $user_id)) {
                    throw new GymException(__('gym.err_room'));
                }
            } else {
                ServiceSale::pay($t, (float) $plan->price, $data['method'], $user_id, __('gym.day_pass'));
            }

            return GymVisit::create(['business_id' => $business_id, 'kind' => 'day_pass', 'member_id' => optional($member)->id, 'contact_id' => $contact_id,
                'reservation_id' => optional($res)->id, 'transaction_id' => $t->id, 'name' => mb_substr((string) $name, 0, 150), 'user_id' => $user_id, 'visited_at' => now()]);
        });
    }

    /** A hotel guest comes in under the "free while they stay" rule. */
    public static function guestIn($business_id, $reservation_id, $user_id)
    {
        if (self::settings($business_id)['guest_rule'] !== 'free') {
            throw new GymException(__('gym.err_guest_rule'));
        }
        $res = HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->with(['contact', 'room'])->find($reservation_id);
        if (! $res) {
            throw new GymException(__('gym.err_guest_not_in'));
        }

        return GymVisit::create(['business_id' => $business_id, 'kind' => 'guest', 'contact_id' => $res->contact_id, 'reservation_id' => $res->id,
            'name' => mb_substr(optional($res->contact)->name.' · '.__('gym.room', ['n' => optional($res->room)->number]), 0, 150), 'user_id' => $user_id, 'visited_at' => now()]);
    }

    /** Find at the door: members by name, phone or number (a scanned card types its number), hotel guests by room. */
    public static function search($business_id, $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return ['members' => collect(), 'guests' => collect()];
        }
        $digits = preg_replace('/\D+/', '', $term);
        $exact = GymMember::where('business_id', $business_id)->where('member_no', strtoupper($term))->with('contact')->get();
        $members = $exact->isNotEmpty() ? $exact : GymMember::where('business_id', $business_id)->with('contact')
            ->where(function ($q) use ($term, $digits) {
                $q->whereHas('contact', fn ($c) => $c->where('name', 'like', '%'.$term.'%'))->orWhere('member_no', 'like', '%'.$term);
                if (strlen($digits) >= 6) {
                    // numbers are typed with spaces or dashes: compare the digits only
                    $like = '%'.substr($digits, -8).'%';
                    $q->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '.', '') LIKE ?", [$like])
                        ->orWhereHas('contact', fn ($c) => $c->whereRaw("REPLACE(REPLACE(REPLACE(mobile, ' ', ''), '-', ''), '.', '') LIKE ?", [$like]));
                }
            })->orderBy('seq')->limit(12)->get();
        $guests = collect();
        if (Hotel::enabled($business_id)) {
            $guests = HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->with(['contact', 'room'])
                ->where(function ($q) use ($term) {
                    $q->whereHas('room', fn ($r) => $r->where('number', $term))->orWhereHas('contact', fn ($c) => $c->where('name', 'like', '%'.$term.'%'));
                })->limit(6)->get();
        }

        return ['members' => $members, 'guests' => $guests];
    }

    /** The members list with each one's state, and the counts above it. */
    public static function members($business_id, $filter = 'all')
    {
        $all = GymMember::where('business_id', $business_id)->with('contact')->orderBy('seq')->get()->map(function ($m) {
            $m->state = self::status($m);
            $m->last = $m->latest();
            $m->visits30 = GymVisit::where('member_id', $m->id)->where('visited_at', '>=', now()->subDays(30))->count();

            return $m;
        });
        $list = $filter === 'expiring' ? $all->where('state', 'expiring') : ($filter === 'expired' ? $all->where('state', 'expired') : $all);

        return [
            'list' => $list->sortBy(fn ($m) => [$m->state === 'expiring' ? 0 : ($m->state === 'expired' ? 1 : 2), optional(optional($m->last)->end_date)->timestamp])->values(),
            'active' => $all->whereIn('state', ['valid', 'expiring'])->count(),
            'expiring' => $all->where('state', 'expiring')->count(),
            'expired_month' => $all->where('state', 'expired')->filter(fn ($m) => $m->last && $m->last->end_date->gte(today()->startOfMonth()))->count(),
            'passes_month' => GymVisit::where('business_id', $business_id)->where('kind', 'day_pass')->where('visited_at', '>=', today()->startOfMonth())->count(),
        ];
    }

    public static function visitsToday($business_id)
    {
        return GymVisit::where('business_id', $business_id)->where('visited_at', '>=', today())->orderByDesc('visited_at')->get();
    }

    public static function format($business_id, $amount)
    {
        return Pressing::format($business_id, $amount);
    }
}
