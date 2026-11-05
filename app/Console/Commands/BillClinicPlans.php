<?php

namespace App\Console\Commands;

use App\ClinicSubscription;
use App\Utils\ClinicPlans;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Bills every monthly clinic plan whose next period has started (one invoice
 * per patient per month, never twice). Scheduled daily in the live
 * environment; safe to run again by hand, and it catches up days it missed.
 *
 *   php artisan clinic:bill-plans
 *   php artisan clinic:bill-plans --business=4 --date=2026-12-05
 */
class BillClinicPlans extends Command
{
    protected $signature = 'clinic:bill-plans {--business= : Only this business id} {--date= : Bill as if today were this date (Y-m-d)}';

    protected $description = 'Bill monthly clinic plans that are due';

    public function handle()
    {
        $today = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $query = ClinicSubscription::active()->whereDate('next_bill_date', '<=', $today);
        if ($this->option('business')) {
            $query->where('business_id', (int) $this->option('business'));
        }

        $total = 0;
        foreach ($query->distinct()->pluck('business_id') as $business_id) {
            $n = ClinicPlans::billDue($business_id, $today);
            $this->info("business $business_id: $n period(s) billed");
            $total += $n;
        }
        $this->info("done: $total period(s) billed for ".$today->toDateString());

        return 0;
    }
}
