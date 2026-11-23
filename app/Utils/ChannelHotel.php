<?php

namespace App\Utils;

use App\ChannelLink;
use App\ChannelOutbox;
use App\HotelReservation;
use App\HotelRoomType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PosInnova hotel <-> E-Ride Hotels (hotel contract v1). POS is the source of truth and only calls out:
 *  - free rooms per type and night (120 nights from the hotel day), full nightly and on first link,
 *    then the changes, each minute;
 *  - E-Ride inquiries on the hotel home: keep a room (a tentative or firm reservation, source "eride"),
 *    decline, a short reply; the conversation itself stays in E-Ride;
 *  - what happens to those reservations: confirmed, arrived, left, no_show, released, cancelled.
 * Never sent: guest IDs, the folio, payments, agreed rates, guests who did not come through E-Ride.
 */
class ChannelHotel
{
    const CHANNEL = 'eride_hotel';

    const NIGHTS = 120;

    /** The live E-Ride link of the hotel (its hotel location), if any. */
    public static function link($business_id)
    {
        return ChannelLink::where('business_id', $business_id)->where('channel', self::CHANNEL)->live()->first();
    }

    // ------------------------------------------------------------ reservation events

    /** A reservation that came from E-Ride changed: tell E-Ride (queued; the scheduler and the hotel home send it). */
    public static function event(HotelReservation $res, $event)
    {
        if (empty($res->channel_ref)) {
            return;
        }
        $link = ChannelLink::where('business_id', $res->business_id)->where('channel', self::CHANNEL)->whereNull('revoked_at')->first();
        if (! $link) {
            return;
        }
        $now = ChannelSync::now($link);
        ChannelSync::queue($link, 'hotel_event', $res->ref_no, 'inquiries/'.rawurlencode($res->channel_ref).'/event', [
            'event' => $event, 'pos_ref' => $res->ref_no, 'at' => $now->toIso8601String(),
        ]);
    }

    // ------------------------------------------------------------ availability

    /**
     * Per active room type: name, capacity, E-Ride price, rooms, and free rooms for each night from the hotel day.
     *
     * @return array{0:string, 1:array<int,array>} hotel day, types keyed by type id
     */
    public static function availability($business_id)
    {
        Hotel::forget();
        $day = Hotel::hotelDay($business_id);
        $to = $day->copy()->addDays(self::NIGHTS);
        $types = [];
        foreach (Hotel::availability($business_id, $day, $to) as $type_id => $a) {
            $t = $a['type'];
            $price = $t->eride_price !== null && (float) $t->eride_price > 0 ? (float) $t->eride_price : (float) $t->rate_room_only;
            $types[(int) $type_id] = [
                'pos_type_id' => (int) $type_id,
                'name' => (string) $t->name,
                'capacity' => (int) $t->capacity,
                'eride_price' => (int) round($price),
                'rooms_total' => (int) $a['rooms'],
                'free' => array_map(fn ($n) => max(0, (int) $n), array_values($a['by_night'])),
            ];
        }

        return [$day->toDateString(), $types];
    }

    private static function metaHash(array $t)
    {
        return sha1(json_encode([$t['name'], $t['capacity'], $t['eride_price'], $t['rooms_total']]));
    }

    public static function run(ChannelLink $link, array $opt = [])
    {
        $done = ['lapsed' => 0, 'outbox' => 0, 'full' => false, 'changed' => 0, 'removed' => 0, 'heartbeat' => false, 'error' => null];
        if (! $link->isLive()) {
            return $done;
        }
        $client = new ChannelClient($link);
        try {
            $done['lapsed'] = Hotel::lapseHolds($link->business_id);
            $done['outbox'] = ChannelSync::flushOutbox($link, $client);
            self::releases($link, $client);
            $now = ChannelSync::now($link);
            $full_due = $link->full_requested || empty($link->last_full_on) || $link->last_full_on->toDateString() < $now->toDateString();
            if ($full_due || ! empty($opt['full'])) {
                self::pushFull($link, $client, $now);
                $done['full'] = true;
            } else {
                [$c, $r] = self::pushChanges($link, $client);
                $done['changed'] = $c;
                $done['removed'] = $r;
            }
            if (empty($link->last_heartbeat_at) || $link->last_heartbeat_at->lte(now()->subSeconds(280)) || ! empty($opt['heartbeat'])) {
                $queue = ChannelOutbox::where('link_id', $link->id)->whereNull('done_at')->count();
                $r = $client->post('heartbeat', ['queue' => $queue, 'pos_time' => $now->toIso8601String()]);
                $f = ['last_heartbeat_at' => now()];
                if (! empty($r['server_time'])) {
                    $f['clock_skew'] = Carbon::parse($r['server_time'])->getTimestamp() - time();
                }
                $link->forceFill($f)->save();
                ChannelSync::ok($link);
                $done['heartbeat'] = true;
            }
        } catch (ChannelException $e) {
            ChannelSync::fail($link, $e);
            $done['error'] = $e->errorCode.': '.$e->getMessage();
        } catch (\Throwable $e) {
            Log::error('channel hotel link '.$link->id.': '.$e->getMessage());
            $link->forceFill(['last_error' => Str::limit($e->getMessage(), 500), 'last_error_at' => now()])->save();
            $done['error'] = $e->getMessage();
        }

        return $done;
    }

    public static function pushFull(ChannelLink $link, ChannelClient $client, Carbon $now)
    {
        [$day, $types] = self::availability($link->business_id);
        $client->post('availability/full', ['snapshot_id' => (string) Str::uuid(), 'hotel_day' => $day, 'types' => array_values($types)], null, 60);
        DB::transaction(function () use ($link, $day, $types) {
            DB::table('channel_hotel_sent')->where('link_id', $link->id)->delete();
            foreach ($types as $t) {
                DB::table('channel_hotel_sent')->insert(['link_id' => $link->id, 'room_type_id' => $t['pos_type_id'], 'hotel_day' => $day,
                    'meta_hash' => self::metaHash($t), 'free' => json_encode($t['free']), 'sent_at' => now()]);
            }
        });
        $link->forceFill(['full_requested' => false, 'last_full_on' => $now->toDateString(), 'last_full_at' => now(), 'last_push_at' => now(),
            'published' => count($types)])->save();
        ChannelSync::ok($link);
    }

    /**
     * What changed since the last push: per type, the run of nights from the first to the last that differ
     * (or the whole picture of a type whose name, capacity, price or rooms changed). Types gone: removed_types.
     *
     * @return array{0:int,1:int}
     */
    public static function pushChanges(ChannelLink $link, ChannelClient $client)
    {
        [$day, $types] = self::availability($link->business_id);
        $sent = DB::table('channel_hotel_sent')->where('link_id', $link->id)->get()->keyBy('room_type_id');
        $out = [];
        $rows = [];
        foreach ($types as $id => $t) {
            $old = $sent[$id] ?? null;
            $prev = [];
            if ($old) {
                // shift what was sent to today's hotel day
                $shift = Carbon::parse($old->hotel_day)->diffInDays(Carbon::parse($day), false);
                $prev = array_slice((array) json_decode($old->free, true), max(0, $shift));
            }
            $meta_changed = ! $old || $old->meta_hash !== self::metaHash($t);
            $first = null;
            $last = null;
            foreach ($t['free'] as $i => $n) {
                if (! array_key_exists($i, $prev) || (int) $prev[$i] !== (int) $n) {
                    $first = $first ?? $i;
                    $last = $i;
                }
            }
            if ($meta_changed || $first !== null) {
                $entry = ['pos_type_id' => $id];
                if ($meta_changed) {
                    $entry += ['name' => $t['name'], 'capacity' => $t['capacity'], 'eride_price' => $t['eride_price'], 'rooms_total' => $t['rooms_total']];
                }
                $from = $first ?? 0;
                $to = $last ?? count($t['free']) - 1;
                if ($meta_changed && $first === null) {
                    $from = 0;
                    $to = -1; // nothing but the details
                }
                $entry['from'] = Carbon::parse($day)->addDays($from)->toDateString();
                $entry['free'] = $to >= $from ? array_slice($t['free'], $from, $to - $from + 1) : [];
                $out[] = $entry;
                $rows[$id] = $t;
            }
        }
        $removed = array_values(array_diff(array_map('intval', $sent->keys()->all()), array_keys($types)));
        if ($out || $removed) {
            $client->post('availability/changes', ['hotel_day' => $day, 'types' => $out, 'removed_types' => $removed]);
            foreach ($rows as $id => $t) {
                DB::table('channel_hotel_sent')->updateOrInsert(['link_id' => $link->id, 'room_type_id' => $id],
                    ['hotel_day' => $day, 'meta_hash' => self::metaHash($t), 'free' => json_encode($t['free']), 'sent_at' => now()]);
            }
            if ($removed) {
                DB::table('channel_hotel_sent')->where('link_id', $link->id)->whereIn('room_type_id', $removed)->delete();
            }
            $link->forceFill(['last_push_at' => now()])->save();
            ChannelSync::ok($link);
        } elseif ($sent->isNotEmpty() && Carbon::parse($sent->first()->hotel_day)->toDateString() !== $day) {
            // a new hotel day with nothing else changed: remember the new start (the shift is implied)
            foreach ($types as $id => $t) {
                DB::table('channel_hotel_sent')->where('link_id', $link->id)->where('room_type_id', $id)->update(['hotel_day' => $day, 'free' => json_encode($t['free'])]);
            }
        }
        $link->forceFill(['last_check_at' => now()])->save();

        return [count($out), count($removed)];
    }

    // ------------------------------------------------------------ inquiries

    /** The inquiries for the hotel home, asked at most every 15 seconds. The to_release list is acted on here. */
    public static function pending(ChannelLink $link)
    {
        $key = 'channel_hotel_pending_'.$link->id;
        $fresh = Cache::get($key);
        $error = null;
        if ($fresh === null) {
            try {
                $client = new ChannelClient($link);
                ChannelSync::flushOutbox($link, $client);
                $fresh = $client->get('inquiries/pending', [], 10);
                Cache::put($key, $fresh, 15);
                self::releases($link, $client, $fresh);
                ChannelSync::ok($link);
            } catch (ChannelException $e) {
                ChannelSync::fail($link, $e);
                $error = $e->isRevoked() ? 'revoked' : 'offline';
                $fresh = ['inquiries' => [], 'to_release' => []];
                Cache::put($key, $fresh, 15);
            }
        }
        $types = HotelRoomType::where('business_id', $link->business_id)->where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get(['id', 'name', 'capacity']);
        $rows = [];
        foreach ((array) ($fresh['inquiries'] ?? []) as $q) {
            $refs = HotelReservation::where('business_id', $link->business_id)->where('channel_ref', (string) ($q['inquiry_id'] ?? ''))
                ->whereIn('status', ['tentative', 'confirmed', 'checked_in'])->get(['id', 'ref_no', 'status', 'hold_until']);
            $q['pos'] = $refs->map(fn ($r) => ['id' => $r->id, 'ref_no' => $r->ref_no, 'status' => $r->status,
                'hold_until' => $r->hold_until ? $r->hold_until->format('Y-m-d H:i') : null])->values()->all();
            $rows[] = $q;
        }

        return ['inquiries' => $rows, 'error' => $error, 'types' => $types];
    }

    /** E-Ride's to_release list: tentatives whose guest let go (or closed in the E-Ride portal) are cancelled here. */
    public static function releases(ChannelLink $link, ChannelClient $client = null, array $fresh = null)
    {
        if ($fresh === null) {
            // the scheduled run: look once a minute, so a guest who let go frees the room even if nobody has the hotel home open
            $fresh = ($client ?: new ChannelClient($link))->get('inquiries/pending', [], 10);
        }
        // one row per pos_ref; release-seen goes once per inquiry with the refs let go
        $seen = [];
        foreach ((array) ($fresh['to_release'] ?? []) as $rel) {
            $id = (string) ($rel['inquiry_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $q = HotelReservation::where('business_id', $link->business_id)->where('channel_ref', $id)->where('status', 'tentative');
            if (! empty($rel['pos_ref'])) {
                $q->where('ref_no', $rel['pos_ref']);
            }
            foreach ($q->get() as $res) {
                Hotel::cancel($res, null, __('hotel.eride_let_go'), 'cancelled', 'released_by_eride');
            }
            $seen[$id] = $seen[$id] ?? [];
            if (! empty($rel['pos_ref'])) {
                $seen[$id][] = (string) $rel['pos_ref'];
            }
        }
        foreach ($seen as $id => $refs) {
            $already = ChannelOutbox::where('link_id', $link->id)->where('kind', 'release_seen')->where('ref', $id)->whereNull('done_at')->exists();
            if (! $already) {
                $body = ['seen_at' => ChannelSync::now($link)->toIso8601String()];
                if ($refs) {
                    $body['pos_refs'] = array_values(array_unique($refs));
                }
                ChannelSync::queue($link, 'release_seen', $id, 'inquiries/'.rawurlencode($id).'/release-seen', $body);
            }
        }
        if (! empty($fresh['to_release'])) {
            ChannelSync::flushOutbox($link, $client ?: new ChannelClient($link));
        }
    }

    /**
     * "Keep a room": a fresh look at the inquiry, then $rooms reservations of the type (tentative until $until,
     * or firm), source "eride", for the E-Ride guest. E-Ride is told at once (queued if the line is down).
     *
     * @return array{ok:bool, message?:string, refs?:array}
     */
    public static function keep(ChannelLink $link, $inquiry_id, array $in, $user_id)
    {
        $client = new ChannelClient($link);
        $q = $client->get('inquiries/'.rawurlencode($inquiry_id), [], 10);
        $status = (string) ($q['status'] ?? 'open');
        if (! in_array($status, ['open', 'answered', 'kept'], true)) {
            return ['ok' => false, 'message' => __('hotel.eride_closed')];
        }
        $type_id = (int) ($in['room_type_id'] ?? data_get($q, 'room_type.pos_type_id'));
        $rooms = max(1, min(10, (int) ($in['rooms'] ?? ($q['rooms'] ?? 1))));
        $firm = ($in['status'] ?? 'tentative') === 'confirmed';
        $arrival = (string) ($in['arrival'] ?? $q['check_in']);
        $departure = (string) ($in['departure'] ?? $q['check_out']);
        $until = $firm ? null : Hotel::holdUntil($in['until'] ?? null);
        if (! $firm && ! $until) {
            $s = Hotel::settings($link->business_id);
            $until = Carbon::parse($arrival)->setTimeFromTimeString($s['eride_hold_time'] ?: '18:00')->format('Y-m-d H:i:s');
        }
        $guests = max(1, (int) ($q['guests'] ?? 1));
        $refs = [];
        $made = [];
        try {
            DB::transaction(function () use ($link, $q, $inquiry_id, $type_id, $rooms, $firm, $arrival, $departure, $until, $guests, $user_id, &$refs, &$made) {
                for ($i = 0; $i < $rooms; $i++) {
                    $res = Hotel::createReservation($link->business_id, [
                        'name' => trim((string) data_get($q, 'guest.name', '')) ?: 'E-Ride guest',
                        'mobile' => trim((string) data_get($q, 'guest.phone', '')),
                        'room_type_id' => $type_id, 'arrival' => $arrival, 'departure' => $departure,
                        'adults' => max(1, (int) ceil($guests / $rooms)), 'children' => 0,
                        'source' => 'eride', 'status' => $firm ? 'confirmed' : 'tentative', 'hold_until' => $until,
                        'channel_ref' => $inquiry_id,
                        'notes' => trim('E-Ride '.($q['number'] ?? '').($q['question'] ?? '' ? ' · '.$q['question'] : '')),
                    ], $user_id);
                    $refs[] = $res->ref_no;
                    $made[] = $res;
                }
            });
        } catch (HotelException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $tz = ChannelSync::now($link)->getTimezone();
        $all_refs = HotelReservation::where('business_id', $link->business_id)->where('channel_ref', $inquiry_id)
            ->whereIn('status', ['tentative', 'confirmed', 'checked_in'])->pluck('ref_no')->all();
        $m = ChannelSync::queue($link, 'hotel_kept', $inquiry_id, 'inquiries/'.rawurlencode($inquiry_id).'/kept', [
            'pos_refs' => $all_refs, 'pos_type_id' => $type_id, 'rooms' => count($all_refs),
            'status' => $firm ? 'confirmed' : 'tentative',
            'until' => $until ? Carbon::parse($until, $tz)->toIso8601String() : null,
            'by' => self::by($user_id),
        ]);
        $client->cap = 8;
        try {
            ChannelSync::flushOutbox($link, $client, $m->id);
        } catch (\Throwable $e) {
            // kept for the scheduler
        }
        Cache::forget('channel_hotel_pending_'.$link->id);
        $m->refresh();
        if ($m->done_at && $m->last_error && str_contains($m->last_error, 'inquiry_closed')) {
            return ['ok' => false, 'message' => __('hotel.eride_closed_after'), 'refs' => $refs];
        }

        return ['ok' => true, 'refs' => $refs, 'delivered' => (bool) $m->done_at];
    }

    /** The receptionist's first name, as E-Ride shows it in the chat. */
    private static function by($user_id)
    {
        $n = trim((string) \App\User::where('id', $user_id)->value('first_name'));

        return $n !== '' ? $n : null;
    }

    public static function decline(ChannelLink $link, $inquiry_id, $reason, $text, $user_id = null)
    {
        $m = ChannelSync::queue($link, 'hotel_declined', $inquiry_id, 'inquiries/'.rawurlencode($inquiry_id).'/declined',
            ['reason' => $reason === 'full' ? 'full' : 'other', 'text' => mb_substr(trim((string) $text), 0, 500), 'by' => self::by($user_id ?: auth()->id())]);

        return self::sendNow($link, $m);
    }

    public static function reply(ChannelLink $link, $inquiry_id, $text, $by)
    {
        $m = ChannelSync::queue($link, 'hotel_reply', $inquiry_id, 'inquiries/'.rawurlencode($inquiry_id).'/reply',
            ['text' => mb_substr(trim((string) $text), 0, 500), 'by' => $by ?: null]);

        return self::sendNow($link, $m);
    }

    private static function sendNow(ChannelLink $link, ChannelOutbox $m)
    {
        $client = new ChannelClient($link);
        $client->cap = 8;
        try {
            ChannelSync::flushOutbox($link, $client, $m->id);
        } catch (\Throwable $e) {
            // kept for the scheduler
        }
        Cache::forget('channel_hotel_pending_'.$link->id);
        $m->refresh();

        return ['ok' => true, 'delivered' => (bool) $m->done_at && ! $m->last_error, 'error' => $m->done_at && $m->last_error ? $m->last_error : null];
    }

    /** E-Ride refused a message for good. A keep for an inquiry already closed: the reservations made are let go. */
    public static function refused(ChannelOutbox $m, ChannelException $e)
    {
        if ($m->kind === 'hotel_kept' && $e->errorCode === 'inquiry_closed') {
            $link = ChannelLink::find($m->link_id);
            foreach (HotelReservation::where('business_id', optional($link)->business_id)->where('channel_ref', $m->ref)->where('status', 'tentative')->get() as $res) {
                Hotel::cancel($res, null, __('hotel.eride_let_go'), 'cancelled', 'released_by_eride');
            }
        }
    }
}
