<?php

namespace App\Console\Commands;

use App\Utils\ChangePaymentRepair;
use App\Utils\TransactionUtil;
use Illuminate\Console\Command;

/**
 * Finds (and with --fix repairs) sales whose change was subtracted twice and so show
 * as "partial". Same logic as Settings > Repair payments; see App\Utils\ChangePaymentRepair.
 *
 *   php artisan pos:repair-change-payments              (shows what it would fix)
 *   php artisan pos:repair-change-payments --fix        (fixes it)
 *   php artisan pos:repair-change-payments --business=4 --fix
 */
class RepairChangePayments extends Command
{
    protected $signature = 'pos:repair-change-payments {--business= : Only this business id} {--fix : Write the corrections (without it nothing is changed)}';

    protected $description = 'Find (and with --fix repair) sales whose change was subtracted twice and show as partially paid';

    public function handle(TransactionUtil $transactionUtil)
    {
        $rows = ChangePaymentRepair::find($this->option('business') ? (int) $this->option('business') : null);
        if ($rows->isEmpty()) {
            $this->info('No affected sales found.');

            return 0;
        }

        $this->table(['id', 'business', 'invoice', 'date', 'total', 'recorded paid', 'change', 'status now'],
            $rows->map(fn ($r) => [$r->id, $r->business_id, $r->invoice_no, $r->transaction_date, (float) $r->final_total, (float) $r->paid, (float) $r->change_given, $r->payment_status])->all());

        if (! $this->option('fix')) {
            $this->warn($rows->count().' sale(s) affected. Nothing was changed; run again with --fix to repair them.');

            return 0;
        }

        $fixed = ChangePaymentRepair::repair($rows, $transactionUtil, 'artisan');
        $this->info("Repaired {$fixed} sale(s). Their payments now match what the cash register recorded.");

        return 0;
    }
}
