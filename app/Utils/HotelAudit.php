<?php

namespace App\Utils;

use App\Business;
use App\HotelFolioLine;
use App\HotelNightAudit;
use App\HotelReservation;
use App\HotelRoom;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova hotel: the night audit closes one hotel day.
 *
 *  1. every guest in the hotel gets that night on the bill (once: hotel_folio_lines.night_key is unique per stay);
 *  2. confirmed arrivals that never came become no-shows (or are kept / cancelled, as the auditor chose),
 *     tentative bookings past their hold date are cancelled;
 *  3. occupied rooms go on tomorrow's housekeeping list;
 *  4. the daily report is stored with the audit.
 *
 * Days are closed in order, each once (unique business + date). The scheduled command
 * (hotel:night-audit) closes yesterday at the audit time when nobody did it by hand.
 */
class HotelAudit
{
    /** Today cannot be closed by hand before this hour. */
    const EARLIEST_HOUR = 18;

    /** The next hotel day to close. */
    public static function nextDate($business_id)
    {
        $last = HotelNightAudit::where('business_id', $business_id)->max('audit_date');
        if ($last) {
            return Carbon::parse($last)->addDay()->startOfDay();
        }
        $first = HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->min('arrival');

        return $first ? Carbon::parse($first)->startOfDay()->min(Hotel::today()) : Hotel::today();
    }

    /** What the auditor must look at before closing $date. */
    public static function checklist($business_id, $date)
    {
        $date = Carbon::parse($date)->toDateString();
        $in = HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')
            ->where('arrival', '<=', $date)->with(['room', 'contact', 'roomType'])->get();
        $posted = HotelFolioLine::where('business_id', $business_id)->where('night_key', $date)->whereNull('voided_at')->pluck('reservation_id')->all();

        return [
            'in_house' => $in,
            'to_post' => $in->reject(fn ($r) => in_array($r->id, $posted)),
            'to_post_amount' => $in->reject(fn ($r) => in_array($r->id, $posted))->sum(fn ($r) => (float) $r->rate + (float) optional($r->roomType)->tourist_tax),
            'not_arrived' => HotelReservation::where('business_id', $business_id)->where('status', 'confirmed')->where('arrival', '<=', $date)->with(['contact', 'roomType'])->get(),
            'lapsed' => HotelReservation::where('business_id', $business_id)->where('status', 'tentative')
                ->where(fn ($q) => $q->where('arrival', '<=', $date)->orWhere('hold_until', '<', Carbon::parse($date)->addDay()->toDateString()))->with('contact')->get(),
            'not_left' => $in->filter(fn ($r) => $r->departure->toDateString() <= $date),
            'open_registers' => DB::table('cash_registers as cr')->join('users as u', 'u.id', '=', 'cr.user_id')
                ->where('cr.business_id', $business_id)->where('cr.status', 'open')
                ->select(DB::raw("TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) as name"))->pluck('name'),
        ];
    }

    /**
     * Closes $date. $decisions: [reservation_id => 'no_show'|'keep'|'cancel'] for arrivals that did not come.
     * $user_id null = the scheduled run.
     */
    public static function run($business_id, $date, $user_id = null, array $decisions = [])
    {
        $date = Carbon::parse($date)->startOfDay();
        if ($date->gt(Hotel::today())) {
            throw new HotelException(__('hotel.err_audit_future'));
        }
        $next = self::nextDate($business_id);
        if (! $date->equalTo($next)) {
            throw new HotelException(__('hotel.err_audit_order', ['date' => $next->format('d/m/Y')]));
        }
        // closing today by hand in the afternoon would turn this evening's arrivals into no-shows
        if ($user_id && $date->equalTo(Hotel::today()) && (int) now()->format('H') < self::EARLIEST_HOUR) {
            throw new HotelException(__('hotel.err_audit_early', ['hour' => self::EARLIEST_HOUR.':00']));
        }
        $d = $date->toDateString();
        $poster = $user_id ?: Business::where('id', $business_id)->value('owner_id');
        $folio = new HotelFolio();

        return DB::transaction(function () use ($business_id, $d, $date, $user_id, $poster, $decisions, $folio) {
            // one auditor at a time
            DB::table('hotel_settings')->where('business_id', $business_id)->lockForUpdate()->first();
            if (HotelNightAudit::where('business_id', $business_id)->where('audit_date', $d)->exists()) {
                throw new HotelException(__('hotel.err_audit_done'));
            }

            $nights = 0;
            $revenue = 0;
            $stays = HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')
                ->where('arrival', '<=', $d)->with('room')->get();
            foreach ($stays as $res) {
                // a guest staying past the planned departure keeps the room tonight too
                if ($res->room_id) {
                    DB::table('hotel_room_nights')->insertOrIgnore(['business_id' => $business_id, 'room_id' => $res->room_id, 'night' => $d, 'reservation_id' => $res->id]);
                }
                $line = $folio->postNight($res, $d, $poster);
                if ($line) {
                    $nights++;
                    $revenue += (float) $line->amount;
                }
            }

            $no_shows = 0;
            foreach (HotelReservation::where('business_id', $business_id)->where('status', 'confirmed')->where('arrival', '<=', $d)->get() as $res) {
                $choice = $decisions[$res->id] ?? 'no_show';
                if ($choice === 'keep') {
                    try {
                        $arr = $date->copy()->addDay();
                        $dep = $res->departure->gt($arr) ? $res->departure : $arr->copy()->addDay();
                        Hotel::changeDates($res, $arr->toDateString(), $dep->toDateString());

                        continue;
                    } catch (HotelException $e) {
                        $choice = 'no_show';
                    }
                }
                Hotel::cancel($res, $poster, __('hotel.audit_reason_'.($choice === 'cancel' ? 'cancel' : 'no_show')), $choice === 'cancel' ? 'cancelled' : 'no_show');
                if ($choice !== 'cancel') {
                    $no_shows++;
                }
            }
            foreach (HotelReservation::where('business_id', $business_id)->where('status', 'tentative')
                ->where(fn ($q) => $q->where('arrival', '<=', $d)->orWhere('hold_until', '<', Carbon::parse($d)->addDay()->toDateString()))->get() as $res) {
                Hotel::cancel($res, $poster, __('hotel.audit_reason_lapsed'), 'cancelled', 'lapsed');
            }

            // rooms slept in need a tidy tomorrow
            $occupied = HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->whereNotNull('room_id')->pluck('room_id');
            HotelRoom::whereIn('id', $occupied)->update(['hk_status' => 'dirty', 'hk_at' => now()]);

            $summary = self::summary($business_id, $d);
            $summary['no_shows'] = $no_shows;

            return HotelNightAudit::create([
                'business_id' => $business_id, 'audit_date' => $d, 'ran_at' => now(), 'ran_by' => $user_id,
                'nights_posted' => $nights, 'room_revenue' => round($revenue, 4), 'no_shows' => $no_shows, 'summary' => $summary,
            ]);
        });
    }

    /** The daily report of $date. */
    public static function summary($business_id, $date)
    {
        $d = Carbon::parse($date)->toDateString();
        $rooms = HotelRoom::where('business_id', $business_id)->where('is_active', 1)->get();
        $ooo = $rooms->filter(fn ($r) => $r->outOfOrderOn($d))->count();
        $sellable = max(0, $rooms->count() - $ooo);
        $night_lines = HotelFolioLine::where('business_id', $business_id)->where('night_key', $d)->whereNull('voided_at')->get();
        $sold = $night_lines->count();
        $room_rev = round((float) DB::table('transaction_sell_lines as sl')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->whereIn('sl.transaction_id', $night_lines->pluck('transaction_id')->filter()->all() ?: [0])
            ->where('p.sku', 'like', 'HTL-ROOM-%')
            ->sum(DB::raw('sl.quantity * sl.unit_price_inc_tax')), 4);

        // revenue of the day by department, from every sale of the day
        $s = Hotel::settings($business_id);
        $lines = DB::table('transaction_sell_lines as sl')
            ->join('transactions as t', 't.id', '=', 'sl.transaction_id')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->where('t.business_id', $business_id)->where('t.type', 'sell')->where('t.status', 'final')
            ->whereDate('t.transaction_date', $d)->whereNull('sl.parent_sell_line_id')
            ->select('p.sku', DB::raw('sl.quantity * sl.unit_price_inc_tax as amount'))->get();
        $dept = ['rooms' => 0, 'hotel_extras' => 0, 'tourist_tax' => 0, 'other_sales' => 0];
        foreach ($lines as $l) {
            $k = str_starts_with((string) $l->sku, 'HTL-ROOM-') ? 'rooms' : ($l->sku === 'HTL-TOURIST-TAX' ? 'tourist_tax' : (str_starts_with((string) $l->sku, 'HTL-') ? 'hotel_extras' : 'other_sales'));
            $dept[$k] += (float) $l->amount;
        }
        $dept = array_map(fn ($v) => round($v, 4), $dept);

        $payments = DB::table('transaction_payments as tp')
            ->leftJoin('transactions as t', 't.id', '=', 'tp.transaction_id')
            ->where('tp.business_id', $business_id)->whereDate('tp.paid_on', $d)
            ->whereNull('tp.parent_id')->where('tp.method', '!=', 'advance')
            ->where(fn ($q) => $q->where('t.type', 'sell')->orWhere(fn ($q) => $q->whereNull('tp.transaction_id')->where('tp.payment_type', 'credit')))
            ->groupBy('tp.method')
            ->select('tp.method', DB::raw('SUM(IF(tp.is_return = 1, -1 * tp.amount, tp.amount)) as total'))
            ->pluck('total', 'method')->map(fn ($v) => round((float) $v, 4))->all();

        $tomorrow = Carbon::parse($d)->addDay()->toDateString();
        $folio = new HotelFolio();
        $owed = HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->get()->sum(fn ($r) => max(0, $folio->balance($r)));

        return [
            'rooms' => $rooms->count(), 'out_of_order' => $ooo, 'sellable' => $sellable, 'sold' => $sold,
            'occupancy' => $sellable ? round($sold / $sellable * 100, 1) : 0,
            'room_revenue' => $room_rev,
            'adr' => $sold ? round($room_rev / $sold, 0) : 0,
            'revpar' => $sellable ? round($room_rev / $sellable, 0) : 0,
            'departments' => $dept, 'revenue_total' => round(array_sum($dept), 4),
            'payments' => $payments,
            'arrivals_tomorrow' => HotelReservation::where('business_id', $business_id)->whereIn('status', ['tentative', 'confirmed'])->where('arrival', $tomorrow)->count(),
            'departures_tomorrow' => HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->where('departure', $tomorrow)->count(),
            'in_house' => HotelReservation::where('business_id', $business_id)->where('status', 'checked_in')->count(),
            'owed_in_house' => round($owed, 4),
            'location_id' => $s['location_id'],
        ];
    }

    /** The scheduled close: yesterday (and any day missed before it), once the audit time has passed. */
    public static function scheduled($business_id, Carbon $now = null)
    {
        $now = $now ?: now();
        $s = Hotel::settings($business_id);
        [$h, $m] = array_map('intval', explode(':', $s['audit_time'] ?: '02:00') + [0, 0]);
        $passed = $now->gte($now->copy()->setTime($h, $m));
        $today = $now->copy()->startOfDay();
        // an audit time in the evening closes today; one after midnight closes yesterday
        $target = $h >= 12
            ? ($passed ? $today : $today->copy()->subDay())
            : ($passed ? $today->copy()->subDay() : $today->copy()->subDays(2));
        $done = [];
        for ($i = 0; $i < 31; $i++) {
            $next = self::nextDate($business_id);
            if ($next->gt($target)) {
                break;
            }
            $done[] = self::run($business_id, $next, null)->audit_date->toDateString();
        }

        return $done;
    }
}
