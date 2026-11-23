<?php

namespace App\Utils;

use App\Business;
use App\ChannelLink;
use App\ChannelOutbox;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PosInnova online channels: the scheduled work for one linked location (every minute,
 * `channel:sync`). Everything goes out from POS:
 *  - messages waiting in the outbox (rung-up, unrung, cancel-seen, images), retried until they arrive;
 *  - the full catalogue at first link, every night just after midnight, and when the owner asks;
 *  - otherwise the changes: every minute for what moved, every hour for everything (hash compare);
 *  - a heartbeat every 5 minutes.
 */
class ChannelSync
{
    const PAGE = 500;

    const IMAGES_PER_RUN = 40;

    public static function now(ChannelLink $link)
    {
        $tz = Business::where('id', $link->business_id)->value('time_zone') ?: config('app.timezone');

        return Carbon::now($tz);
    }

    /** One pass for one link. Returns what was done, for the command's output and the tests. */
    public static function run(ChannelLink $link, array $opt = [])
    {
        $done = ['outbox' => 0, 'full' => false, 'changed' => 0, 'removed' => 0, 'heartbeat' => false, 'error' => null];
        if (! $link->isLive()) {
            return $done;
        }
        $now = self::now($link);
        $client = new ChannelClient($link);

        try {
            $done['outbox'] = self::flushOutbox($link, $client);

            $full_due = $link->full_requested || empty($link->last_full_on) || $link->last_full_on->toDateString() < $now->toDateString();
            if ($full_due || ! empty($opt['full'])) {
                self::pushFull($link, $client, $now);
                $done['full'] = true;
            } else {
                $hourly = empty($link->last_hourly_at) || $link->last_hourly_at->lte($now->copy()->subMinutes(59)) || ! empty($opt['hourly']);
                [$c, $r] = self::pushChanges($link, $client, $now, $hourly);
                $done['changed'] = $c;
                $done['removed'] = $r;
            }

            if (empty($link->last_heartbeat_at) || $link->last_heartbeat_at->lte($now->copy()->subSeconds(280)) || ! empty($opt['heartbeat'])) {
                self::heartbeat($link, $client, $now);
                $done['heartbeat'] = true;
            }
        } catch (ChannelException $e) {
            self::fail($link, $e);
            $done['error'] = $e->errorCode.': '.$e->getMessage();
        } catch (\Throwable $e) {
            Log::error('channel sync link '.$link->id.': '.$e->getMessage(), ['trace' => Str::limit($e->getTraceAsString(), 2000)]);
            $link->forceFill(['last_error' => Str::limit($e->getMessage(), 500), 'last_error_at' => now()])->save();
            $done['error'] = $e->getMessage();
        }

        return $done;
    }

    public static function ok(ChannelLink $link)
    {
        $link->forceFill(['last_ok_at' => now(), 'last_error' => null, 'last_error_at' => null])->save();
    }

    public static function fail(ChannelLink $link, ChannelException $e)
    {
        $f = ['last_error' => Str::limit($e->errorCode.($e->getMessage() !== $e->errorCode ? ': '.$e->getMessage() : ''), 500), 'last_error_at' => now()];
        if ($e->isRevoked()) {
            $f['revoked_at'] = now();
        }
        $link->forceFill($f)->save();
    }

    /** GET /hello: checks the key, fills in the branch and the clock difference. */
    public static function hello(ChannelLink $link)
    {
        $r = (new ChannelClient($link))->get('hello');
        $b = (array) ($r['branch'] ?? []);
        $label = trim(implode(' · ', array_unique(array_filter([$b['store'] ?? null, $b['branch'] ?? null]))));
        if ($label === '' && ! empty($r['hotel']['name'])) {
            $label = (string) $r['hotel']['name']; // the hotel channel names the hotel
        }
        $f = ['branch_label' => $label !== '' ? Str::limit($label, 180, '') : $link->branch_label, 'revoked_at' => null];
        if (! empty($r['server_time'])) {
            $f['clock_skew'] = Carbon::parse($r['server_time'])->getTimestamp() - time();
        }
        $link->forceFill($f)->save();
        self::ok($link);

        return $r;
    }

    public static function heartbeat(ChannelLink $link, ChannelClient $client, Carbon $now)
    {
        $published = ChannelCatalog::publishedCount($link);
        $r = $client->post('heartbeat', ['pos_time' => $now->toIso8601String(), 'published' => $published]);
        $f = ['last_heartbeat_at' => now(), 'published' => $published];
        if (! empty($r['server_time'])) {
            $f['clock_skew'] = Carbon::parse($r['server_time'])->getTimestamp() - time();
        }
        $link->forceFill($f)->save();
        self::ok($link);
    }

    private static function locationBlock(ChannelLink $link, $with_name = false)
    {
        $b = ['id' => (int) $link->location_id];
        if ($with_name) {
            $b['name'] = (string) DB::table('business_locations')->where('id', $link->location_id)->value('name');
        }
        $b['qty_display'] = $link->qty_display === 'exact' ? 'exact' : 'available';

        return $b;
    }

    /** POST /catalog/full, in pages of 500. Removals happen on E-Ride only after the last page. */
    public static function pushFull(ChannelLink $link, ChannelClient $client, Carbon $now)
    {
        $items = ChannelCatalog::items($link);
        $pages = array_chunk(array_values($items), self::PAGE);
        if (! $pages) {
            $pages = [[]];
        }
        $snapshot = (string) Str::uuid();
        $need = [];
        foreach ($pages as $i => $page) {
            $r = $client->post('catalog/full', [
                'snapshot_id' => $snapshot,
                'page' => $i + 1,
                'pages' => count($pages),
                'generated_at' => $now->toIso8601String(),
                'location' => self::locationBlock($link, true),
                'items' => $page,
            ], $snapshot.'-'.($i + 1), 120);
            $need = array_merge($need, (array) ($r['need_images'] ?? []));
        }

        DB::transaction(function () use ($link, $items) {
            DB::table('channel_sent')->where('link_id', $link->id)->delete();
            foreach (array_chunk($items, 500) as $chunk) {
                DB::table('channel_sent')->insert(array_map(fn ($it) => [
                    'link_id' => $link->id, 'variation_id' => $it['variation_id'], 'product_id' => $it['product_id'],
                    'hash' => ChannelCatalog::hash($it), 'image_sha1' => $it['image_sha1'], 'sent_at' => now(),
                ], $chunk));
            }
        });
        self::queueImages($link, $items, $need);

        $link->forceFill([
            'full_requested' => false, 'last_full_on' => $now->toDateString(), 'last_full_at' => now(),
            'last_push_at' => now(), 'last_check_at' => $now->copy()->subMinutes(1), 'last_hourly_at' => $now,
            'published' => count($items),
        ])->save();
        self::ok($link);

        return count($items);
    }

    /**
     * POST /catalog/changes with what differs from what E-Ride was last sent.
     * $all = compare every published item (hourly); otherwise only what moved since the last look.
     *
     * @return array{0:int,1:int} changed, removed
     */
    public static function pushChanges(ChannelLink $link, ChannelClient $client, Carbon $now, $all = false)
    {
        $sent = DB::table('channel_sent')->where('link_id', $link->id)->pluck('hash', 'variation_id')->all();

        if ($all) {
            $items = ChannelCatalog::items($link);
            $removed = array_values(array_diff(array_keys($sent), array_keys($items)));
        } else {
            // A few minutes of overlap: a row saved during the previous look is seen again (the hash stops a resend).
            $since = ($link->last_check_at ? $link->last_check_at->copy() : $now->copy()->subHour())->subMinutes(5)->format('Y-m-d H:i:s');
            $candidates = DB::table('variations as v')
                ->join('products as p', 'p.id', '=', 'v.product_id')
                ->leftJoin('channel_products as cp', function ($j) {
                    $j->on('cp.product_id', '=', 'p.id')->on('cp.business_id', '=', 'p.business_id');
                })
                ->leftJoin('variation_location_details as vld', function ($j) use ($link) {
                    $j->on('vld.variation_id', '=', 'v.id')->where('vld.location_id', '=', $link->location_id);
                })
                ->where('p.business_id', $link->business_id)
                ->where(function ($w) use ($since) {
                    $w->where('p.updated_at', '>=', $since)->orWhere('v.updated_at', '>=', $since)
                        ->orWhere('cp.updated_at', '>=', $since)->orWhere('vld.updated_at', '>=', $since);
                })
                ->distinct()->pluck('v.id')->map(fn ($x) => (int) $x)->all();
            $items = $candidates ? ChannelCatalog::items($link, $candidates) : [];
            $removed = array_values(array_filter($candidates, fn ($id) => isset($sent[$id]) && ! isset($items[$id])));
        }

        $changed = array_values(array_filter($items, fn ($it) => ($sent[$it['variation_id']] ?? null) !== ChannelCatalog::hash($it)));

        if ($changed || $removed) {
            $chunks = array_chunk($changed, self::PAGE) ?: [[]];
            $need = [];
            foreach ($chunks as $i => $chunk) {
                $r = $client->post('catalog/changes', [
                    'cursor' => ($link->last_check_at ?: $now)->toIso8601String(),
                    'location' => self::locationBlock($link),
                    'items' => $chunk,
                    'removed' => $i === 0 ? $removed : [],
                ]);
                $need = array_merge($need, (array) ($r['need_images'] ?? []));
                foreach ($chunk as $it) {
                    DB::table('channel_sent')->updateOrInsert(
                        ['link_id' => $link->id, 'variation_id' => $it['variation_id']],
                        ['product_id' => $it['product_id'], 'hash' => ChannelCatalog::hash($it), 'image_sha1' => $it['image_sha1'], 'sent_at' => now()]
                    );
                }
                if ($i === 0 && $removed) {
                    DB::table('channel_sent')->where('link_id', $link->id)->whereIn('variation_id', $removed)->delete();
                }
            }
            self::queueImages($link, $changed, $need);
            $link->forceFill(['last_push_at' => now()]);
            self::ok($link);
        }

        $f = ['last_check_at' => $now];
        if ($all) {
            $f['last_hourly_at'] = $now;
            $f['published'] = count($items);
        }
        $link->forceFill($f)->save();

        return [count($changed), count($removed)];
    }

    /** Queue the images E-Ride asked for (one per sha1). */
    private static function queueImages(ChannelLink $link, array $items, array $need)
    {
        $need = array_values(array_unique(array_filter(array_map('strval', $need))));
        if (! $need) {
            return;
        }
        $by_sha = [];
        foreach ($items as $it) {
            if (! empty($it['image_sha1'])) {
                $by_sha[$it['image_sha1']] = $it;
            }
        }
        foreach ($need as $sha) {
            if (! isset($by_sha[$sha])) {
                continue;
            }
            $exists = ChannelOutbox::where('link_id', $link->id)->where('kind', 'image')->where('ref', $sha)->whereNull('done_at')->exists();
            if (! $exists) {
                self::queue($link, 'image', $sha, 'catalog/images', ['sha1' => $sha, 'product_id' => $by_sha[$sha]['product_id'], 'variation_id' => $by_sha[$sha]['variation_id']]);
            }
        }
    }

    public static function queue(ChannelLink $link, $kind, $ref, $path, array $body)
    {
        return ChannelOutbox::create([
            'link_id' => $link->id, 'kind' => $kind, 'ref' => $ref, 'path' => $path, 'body' => $body,
            'idem_key' => (string) Str::uuid(), 'next_try_at' => now(),
        ]);
    }

    /**
     * Send what is waiting. Order messages first (oldest first), then a few images.
     * Returns how many arrived.
     */
    public static function flushOutbox(ChannelLink $link, ChannelClient $client = null, $only_id = null)
    {
        $client = $client ?: new ChannelClient($link);
        $q = ChannelOutbox::where('link_id', $link->id)->whereNull('done_at')
            ->where(function ($w) {
                $w->whereNull('next_try_at')->orWhere('next_try_at', '<=', now());
            });
        if ($only_id) {
            $q->where('id', $only_id);
        }
        $msgs = (clone $q)->where('kind', '!=', 'image')->orderBy('id')->limit(100)->get()
            ->concat((clone $q)->where('kind', 'image')->orderBy('id')->limit(self::IMAGES_PER_RUN)->get());

        $sent = 0;
        foreach ($msgs as $m) {
            try {
                $answer = self::deliver($link, $client, $m);
                $m->forceFill(['done_at' => now(), 'attempts' => $m->attempts + 1, 'last_error' => null])->save();
                ChannelOrders::delivered($m, $answer);
                $sent++;
            } catch (ChannelException $e) {
                $m->attempts++;
                $m->last_error = Str::limit($e->errorCode.': '.$e->getMessage(), 500);
                if ($e->isRevoked()) {
                    $m->next_try_at = now()->addMinutes(10);
                    $m->save();
                    throw $e;
                }
                if ($e->isTemporary()) {
                    $wait = $e->retryAfter > 0 ? $e->retryAfter : min(3600, 60 * (2 ** min($m->attempts - 1, 6)));
                    $m->next_try_at = now()->addSeconds($wait);
                    $m->save();
                    if ($e->status === 429 || $e->status === 0) {
                        break; // the limit or the line: try the rest next time
                    }
                    continue;
                }
                // A definite answer (409 conflict, 404, 422): it will not change by retrying.
                $m->done_at = now();
                $m->save();
                ChannelOrders::refused($m, $e);
                ChannelHotel::refused($m, $e);
            }
        }

        return $sent;
    }

    private static function deliver(ChannelLink $link, ChannelClient $client, ChannelOutbox $m)
    {
        if ($m->kind === 'image') {
            $b = (array) $m->body;
            $file = DB::table('products')->where('id', $b['product_id'] ?? 0)->where('business_id', $link->business_id)->value('image');
            $img = ChannelCatalog::image($file);
            if (! $img || $img['sha1'] !== ($b['sha1'] ?? '')) {
                return ['skipped' => true]; // the picture changed since: the next push asks for the new one
            }

            return $client->upload('catalog/images', ['sha1' => $img['sha1'], 'product_id' => (string) $b['product_id'], 'variation_id' => (string) ($b['variation_id'] ?? '')], $img['path'], $m->idem_key);
        }

        return $client->post($m->path, (array) $m->body, $m->idem_key);
    }
}
