<?php

namespace App\Http\Controllers;

use App\HotelFolioLine;
use App\HotelGuest;
use App\HotelReservation;
use App\HotelRoom;
use App\Utils\Hotel;
use App\Utils\HotelFolio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** PosInnova hotel: occupancy, the police register, revenue and money still owed. */
class HotelReportController extends HotelBaseController
{
    public function index(Request $request)
    {
        $this->guard('hotel.reports');
        $biz = $this->biz();
        $tab = in_array($request->input('tab'), ['occupancy', 'police', 'revenue'], true) ? $request->input('tab') : 'occupancy';
        $from = $request->filled('from') ? Carbon::parse(Hotel::parseDate($request->input('from'))) : Hotel::today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse(Hotel::parseDate($request->input('to'))) : Hotel::today();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }
        $data = ['tab' => $tab, 'from' => $from, 'to' => $to, 'fmt' => fn ($v) => $this->money($v), 'num' => fn ($v) => $this->money($v, false)];

        if ($tab === 'occupancy') {
            $data += $this->occupancy($biz, $from, $to);
        } elseif ($tab === 'police') {
            $day = $request->filled('day') ? Carbon::parse(Hotel::parseDate($request->input('day'))) : Hotel::today();
            $data += ['day' => $day, 'cards' => $this->police($biz, $day)];
            if ($request->boolean('print')) {
                return view('hotel.police_print', $data + ['business' => \App\Business::find($biz)]);
            }
        } else {
            $data += $this->revenue($biz, $from, $to);
        }

        return view('hotel.reports', $data);
    }

    private function occupancy($biz, Carbon $from, Carbon $to)
    {
        $rooms = HotelRoom::where('business_id', $biz)->where('is_active', 1)->with('type')->get();
        $nights = HotelFolioLine::where('hotel_folio_lines.business_id', $biz)->where('kind', 'night')->whereNull('voided_at')
            ->whereBetween('night_key', [$from->toDateString(), $to->toDateString()])
            ->join('hotel_reservations as r', 'r.id', '=', 'hotel_folio_lines.reservation_id')
            ->select('hotel_folio_lines.night_key', 'hotel_folio_lines.transaction_id', 'r.room_type_id', 'r.source')->get();
        $room_rev = DB::table('transaction_sell_lines as sl')->join('products as p', 'p.id', '=', 'sl.product_id')
            ->whereIn('sl.transaction_id', $nights->pluck('transaction_id')->filter()->all() ?: [0])->where('p.sku', 'like', 'HTL-ROOM-%')
            ->groupBy('sl.transaction_id')->select('sl.transaction_id', DB::raw('SUM(sl.quantity * sl.unit_price_inc_tax) as amount'))->pluck('amount', 'transaction_id');
        // a discount on a night lowers what the night brought in
        $finals = DB::table('transactions')->whereIn('id', $nights->pluck('transaction_id')->filter()->all() ?: [0])->select('id', 'final_total', 'total_before_tax')->get()->keyBy('id');
        $value = function ($tid) use ($room_rev, $finals) {
            $gross = (float) ($room_rev[$tid] ?? 0);
            $t = $finals->get($tid);
            if (! $t || (float) $t->total_before_tax <= 0) {
                return $gross;
            }

            return $gross * min(1, (float) $t->final_total / (float) $t->total_before_tax);
        };

        $days = [];
        $tot_sold = 0;
        $tot_sellable = 0;
        $tot_rev = 0;
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $ds = $d->toDateString();
            $sellable = $rooms->filter(fn ($r) => ! $r->outOfOrderOn($ds))->count();
            $sold_lines = $nights->filter(fn ($n) => $n->night_key->toDateString() === $ds);
            $rev = $sold_lines->sum(fn ($n) => $value($n->transaction_id));
            $days[] = ['date' => $d->copy(), 'sold' => $sold_lines->count(), 'sellable' => $sellable, 'pct' => $sellable ? round($sold_lines->count() / $sellable * 100) : 0, 'revenue' => $rev];
            $tot_sold += $sold_lines->count();
            $tot_sellable += $sellable;
            $tot_rev += $rev;
        }
        $by_type = $rooms->groupBy('room_type_id')->map(function ($rs, $tid) use ($nights, $value, $tot_sellable, $rooms) {
            $lines = $nights->where('room_type_id', $tid);
            $share = $rooms->count() ? $rs->count() / $rooms->count() : 0;

            return ['name' => optional($rs->first()->type)->name, 'rooms' => $rs->count(), 'nights' => $lines->count(),
                'pct' => $tot_sellable && $share ? round($lines->count() / ($tot_sellable * $share) * 100) : 0,
                'revenue' => $lines->sum(fn ($n) => $value($n->transaction_id))];
        })->values();
        $sources = $nights->groupBy('source')->map(fn ($g, $s) => ['source' => $s, 'nights' => $g->count()])->sortByDesc('nights')->values();

        return ['days' => $days, 'by_type' => $by_type, 'sources' => $sources, 'kpi' => [
            'occupancy' => $tot_sellable ? round($tot_sold / $tot_sellable * 100, 1) : 0, 'sold' => $tot_sold,
            'adr' => $tot_sold ? $tot_rev / $tot_sold : 0, 'revpar' => $tot_sellable ? $tot_rev / $tot_sellable : 0, 'revenue' => $tot_rev,
        ]];
    }

    /** Guests in the hotel on $day: arrived that day or before, not gone before it. */
    private function police($biz, Carbon $day)
    {
        $d = $day->toDateString();
        $res_ids = HotelReservation::where('business_id', $biz)->whereIn('status', ['checked_in', 'checked_out'])
            ->whereDate('checked_in_at', '<=', $d)
            ->where(fn ($q) => $q->whereNull('checked_out_at')->orWhereDate('checked_out_at', '>=', $d))->pluck('id');

        return HotelGuest::whereIn('reservation_id', $res_ids)->get()->map(function ($g) {
            $g->res = HotelReservation::with('room')->find($g->reservation_id);

            return $g;
        })->sortBy(fn ($g) => [optional($g->res->room)->number, $g->is_primary ? 0 : 1])->values();
    }

    private function revenue($biz, Carbon $from, Carbon $to)
    {
        $lines = DB::table('transaction_sell_lines as sl')
            ->join('transactions as t', 't.id', '=', 'sl.transaction_id')
            ->join('products as p', 'p.id', '=', 'sl.product_id')
            ->where('t.business_id', $biz)->where('t.type', 'sell')->where('t.status', 'final')
            ->whereBetween(DB::raw('DATE(t.transaction_date)'), [$from->toDateString(), $to->toDateString()])
            ->whereNull('sl.parent_sell_line_id')
            ->select('p.sku', DB::raw('sl.quantity * sl.unit_price_inc_tax as amount'))->get();
        $dept = ['rooms' => 0, 'hotel_extras' => 0, 'tourist_tax' => 0, 'other_sales' => 0];
        foreach ($lines as $l) {
            $k = str_starts_with((string) $l->sku, 'HTL-ROOM-') ? 'rooms' : ($l->sku === 'HTL-TOURIST-TAX' ? 'tourist_tax' : (str_starts_with((string) $l->sku, 'HTL-') ? 'hotel_extras' : 'other_sales'));
            $dept[$k] += (float) $l->amount;
        }
        $discounts = (float) DB::table('transactions')->whereIn('id', HotelFolioLine::where('business_id', $biz)->whereNotNull('transaction_id')->pluck('transaction_id')->all() ?: [0])
            ->whereBetween(DB::raw('DATE(transaction_date)'), [$from->toDateString(), $to->toDateString()])->sum('discount_amount');

        $folio = new HotelFolio();
        $owed = HotelReservation::where('business_id', $biz)->whereIn('status', ['checked_in', 'checked_out'])
            ->where(fn ($q) => $q->where('status', 'checked_in')->orWhere('checked_out_at', '>=', Hotel::today()->subMonths(12)))
            ->with('contact', 'room')->get()
            ->map(fn ($r) => ['res' => $r, 'balance' => $folio->balance($r)])->filter(fn ($x) => $x['balance'] > 0.004)->sortByDesc('balance')->values();

        return ['dept' => $dept, 'discounts' => $discounts, 'owed' => $owed];
    }
}
