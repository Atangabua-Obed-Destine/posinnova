<?php

namespace App\Console\Commands;

use App\Business;
use App\Utils\Hotel;
use App\Utils\HotelAudit;
use Illuminate\Console\Command;

/**
 * PosInnova hotel: closes yesterday for every hotel whose audit time has passed and
 * nobody ran the night audit by hand. Scheduled every 15 minutes; safe to run often.
 */
class HotelNightAuditCommand extends Command
{
    protected $signature = 'hotel:night-audit';

    protected $description = 'Run the hotel night audit for hotels that have not closed yesterday';

    public function handle(): int
    {
        $default_tz = config('app.timezone');
        foreach (Business::all(['id', 'name', 'time_zone', 'enabled_modules']) as $business) {
            if (! in_array('hotel', (array) $business->enabled_modules, true)) {
                continue;
            }
            date_default_timezone_set($business->time_zone ?: $default_tz);
            config(['app.timezone' => $business->time_zone ?: $default_tz]);
            Hotel::forget();
            try {
                // holds whose time has passed are let go (a linked hotel also does it every minute)
                Hotel::lapseHolds($business->id);
                $done = HotelAudit::scheduled($business->id);
                if ($done) {
                    $this->info($business->name.': closed '.implode(', ', $done));
                }
            } catch (\Throwable $e) {
                \Log::error('hotel:night-audit business '.$business->id.': '.$e->getMessage());
                $this->error($business->name.': '.$e->getMessage());
            }
        }
        date_default_timezone_set($default_tz);
        config(['app.timezone' => $default_tz]);

        return self::SUCCESS;
    }
}
