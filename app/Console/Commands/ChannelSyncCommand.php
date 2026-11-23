<?php

namespace App\Console\Commands;

use App\Business;
use App\ChannelLink;
use App\Utils\ChannelSync;
use Illuminate\Console\Command;

/**
 * PosInnova online channels: every minute, for each linked location of a business with the
 * E-Ride module on, send what waits (rung-up, images), the catalogue changes and a heartbeat.
 * POS only calls out, so this works the same on a shop PC behind a router.
 */
class ChannelSyncCommand extends Command
{
    protected $signature = 'channel:sync {--link= : only this link id} {--full : send the whole catalogue now} {--hourly : compare every published item now}';

    protected $description = 'Push catalogue changes, queued order messages and the heartbeat to E-Ride';

    public function handle(): int
    {
        $default_tz = config('app.timezone');
        $q = ChannelLink::live();
        if ($this->option('link')) {
            $q->where('id', (int) $this->option('link'));
        }
        foreach ($q->get() as $link) {
            $business = Business::find($link->business_id, ['id', 'name', 'time_zone', 'enabled_modules']);
            $hotel = $link->channel === \App\Utils\ChannelHotel::CHANNEL;
            if (! $business || ! in_array($hotel ? 'hotel' : 'eride', (array) $business->enabled_modules, true)) {
                continue;
            }
            date_default_timezone_set($business->time_zone ?: $default_tz);
            config(['app.timezone' => $business->time_zone ?: $default_tz]);
            $done = $hotel
                ? \App\Utils\ChannelHotel::run($link, ['full' => (bool) $this->option('full')])
                : ChannelSync::run($link, ['full' => (bool) $this->option('full'), 'hourly' => (bool) $this->option('hourly')]);
            $line = $business->name.' / link '.$link->id.': '.json_encode($done);
            $done['error'] ? $this->error($line) : $this->line($line);
        }
        date_default_timezone_set($default_tz);
        config(['app.timezone' => $default_tz]);

        return self::SUCCESS;
    }
}
