<?php

namespace App\Console\Commands;

use App\Ledger\Ledger;
use App\Ledger\Services\LedgerSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Brings every business's books up to date with its documents (anything the
 * observers missed, failed postings, deleted documents). Runs every few
 * minutes from the scheduler; safe to run by hand at any time.
 */
class LedgerSyncCommand extends Command
{
    protected $signature = 'ledger:sync {--business= : only this business}';

    protected $description = 'Post to the ledger any sale, purchase, payment or expense not posted yet, and correct changed ones';

    public function handle()
    {
        $ids = $this->option('business') ? [(int) $this->option('business')]
            : DB::table('business')->where('enabled_modules', 'like', '%"accounting"%')->pluck('id')->all();
        foreach ($ids as $id) {
            if (! Ledger::enabledFor($id) || ! Ledger::isSetUp($id)) {
                continue;
            }
            $stats = Ledger::forBusiness($id, fn () => (new LedgerSync())->sweep());
            $this->line('business '.$id.': '.json_encode($stats));
        }

        return 0;
    }
}
