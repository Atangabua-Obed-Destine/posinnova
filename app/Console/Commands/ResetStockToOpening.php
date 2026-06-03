<?php

namespace App\Console\Commands;

use DB;
use Illuminate\Console\Command;

/**
 * PosInnova: Reset stock so current variation_location_details.qty_available
 * becomes the ONLY backing as a single fresh opening_stock line per
 * (location, variation).
 *
 * What is wiped (scoped by --business):
 *   - transactions of type: purchase, opening_stock, purchase_transfer,
 *     production_purchase, stock_adjustment, purchase_return, sell_transfer
 *   - their purchase_lines, stock_adjustment_lines
 *   - their transaction_payments (supplier payments against those purchases)
 *   - the bridge rows transaction_sell_lines_purchase_lines pointing at the
 *     about-to-be-deleted purchase_lines / stock_adjustment_lines
 *   - their account_transactions and accounting_accounts_transactions
 *     postings (linked by transaction_id OR by transaction_payment_id of the
 *     deleted payments)
 *
 * What is NOT touched:
 *   - transactions of type sell, sell_return, expense, etc.
 *   - transaction_sell_lines and the sales' own payments / ledgers
 *   - products, variations, customers, suppliers, locations
 *   - variation_location_details.qty_available (source of truth)
 *
 * Usage:
 *   php artisan pos:resetStock --business=2                    # dry-run
 *   php artisan pos:resetStock --business=2 --apply            # commit
 *   php artisan pos:resetStock --business=2 --apply --no-backup
 */
class ResetStockToOpening extends Command
{
    protected $signature = 'pos:resetStock
                            {--business= : (required) business_id to reset}
                            {--apply : Actually write changes (default dry-run)}
                            {--no-backup : Skip mysqldump backup (not recommended)}
                            {--force : Skip interactive confirmation prompts}';

    protected $description = 'Wipe stock-producing transactions for a business and replace with a single opening_stock line per (location, variation) matching current qty_available.';

    /** @var string */
    protected $logFile;

    /** Stock-producing transaction types to be wiped. */
    protected $wipeTypes = [
        'purchase',
        'opening_stock',
        'purchase_transfer',
        'sell_transfer',
        'production_purchase',
        'stock_adjustment',
        'purchase_return',
    ];

    public function handle()
    {
        @ini_set('max_execution_time', 0);
        @ini_set('memory_limit', '1024M');

        $bizId = (int) $this->option('business');
        if ($bizId <= 0) {
            $this->error('--business is required.');
            return 1;
        }

        $apply = (bool) $this->option('apply');

        $this->logFile = storage_path('logs/stock-reset-'.date('Ymd').'.log');
        $this->log('===== run start ===== business='.$bizId.' apply='.($apply ? '1' : '0'));

        $business = DB::table('business')->where('id', $bizId)->first();
        if (! $business) {
            $this->error('Business not found.');
            return 1;
        }
        $this->info('Business: '.$business->name.' (id='.$bizId.')');

        // ---- Pre-flight counts ----
        $txnIds = DB::table('transactions')
            ->where('business_id', $bizId)
            ->whereIn('type', $this->wipeTypes)
            ->pluck('id');

        $plIds = DB::table('purchase_lines')->whereIn('transaction_id', $txnIds)->pluck('id');
        $salIds = DB::table('stock_adjustment_lines')->whereIn('transaction_id', $txnIds)->pluck('id');
        $payIds = DB::table('transaction_payments')->whereIn('transaction_id', $txnIds)->pluck('id');

        $bridgeCount = DB::table('transaction_sell_lines_purchase_lines')
            ->where(function ($q) use ($plIds, $salIds) {
                if ($plIds->isNotEmpty())  $q->orWhereIn('purchase_line_id', $plIds);
                if ($salIds->isNotEmpty()) $q->orWhereIn('stock_adjustment_line_id', $salIds);
            })
            ->count();

        $acctTxnCount = DB::table('account_transactions')
            ->where(function ($q) use ($txnIds, $payIds) {
                if ($txnIds->isNotEmpty()) $q->orWhereIn('transaction_id', $txnIds);
                if ($payIds->isNotEmpty()) $q->orWhereIn('transaction_payment_id', $payIds);
            })->count();

        $accAcctTxnCount = 0;
        if (\Schema::hasTable('accounting_accounts_transactions')) {
            $accAcctTxnCount = DB::table('accounting_accounts_transactions')
                ->where(function ($q) use ($txnIds, $payIds) {
                    if ($txnIds->isNotEmpty()) $q->orWhereIn('transaction_id', $txnIds);
                    if ($payIds->isNotEmpty()) $q->orWhereIn('transaction_payment_id', $payIds);
                })->count();
        }

        // What we'll create
        $vldRows = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->where('p.business_id', $bizId)
            ->where('vld.qty_available', '>', 0)
            ->select(
                'vld.location_id',
                'vld.variation_id',
                'v.product_id',
                'v.default_purchase_price',
                'vld.qty_available'
            )->get();

        $locCounts = $vldRows->groupBy('location_id')->map->count();

        $this->newLine();
        $this->table(['What', 'Count'], [
            ['transactions to delete (wipe types)', $txnIds->count()],
            ['  purchase_lines to delete',           $plIds->count()],
            ['  stock_adjustment_lines to delete',   $salIds->count()],
            ['  transaction_payments to delete',     $payIds->count()],
            ['  TSL<>PL bridge rows to delete',      $bridgeCount],
            ['  account_transactions to delete',     $acctTxnCount],
            ['  accounting_accounts_transactions to delete', $accAcctTxnCount],
            ['NEW: opening_stock transactions to create (one per location)', $locCounts->count()],
            ['NEW: purchase_lines to create (one per variation with qty>0)', $vldRows->count()],
        ]);

        if (! $apply) {
            $this->warn('DRY-RUN. Re-run with --apply to commit.');
            return 0;
        }

        $force = (bool) $this->option('force');

        if (! $this->option('no-backup')) {
            $this->backupOrAbort();
        } elseif (! $force) {
            if (! $this->confirm('Proceed WITHOUT a database backup?', false)) {
                $this->info('Aborted.');
                return 0;
            }
        }

        if (! $force && ! $this->confirm('Apply destructive changes for business '.$bizId.' ('.$business->name.')? This cannot be undone except via the backup.', false)) {
            $this->info('Aborted.');
            return 0;
        }

        $actorUserId = $this->resolveActorUserId($bizId);

        DB::beginTransaction();
        try {
            // ---------- DELETE PHASE ----------
            if ($bridgeCount > 0) {
                DB::table('transaction_sell_lines_purchase_lines')
                    ->where(function ($q) use ($plIds, $salIds) {
                        if ($plIds->isNotEmpty())  $q->orWhereIn('purchase_line_id', $plIds);
                        if ($salIds->isNotEmpty()) $q->orWhereIn('stock_adjustment_line_id', $salIds);
                    })->delete();
                $this->log('deleted bridge rows: '.$bridgeCount);
            }

            if ($payIds->isNotEmpty() || $txnIds->isNotEmpty()) {
                DB::table('account_transactions')
                    ->where(function ($q) use ($txnIds, $payIds) {
                        if ($txnIds->isNotEmpty()) $q->orWhereIn('transaction_id', $txnIds);
                        if ($payIds->isNotEmpty()) $q->orWhereIn('transaction_payment_id', $payIds);
                    })->delete();
                $this->log('deleted account_transactions: '.$acctTxnCount);
            }

            if (\Schema::hasTable('accounting_accounts_transactions') && ($payIds->isNotEmpty() || $txnIds->isNotEmpty())) {
                DB::table('accounting_accounts_transactions')
                    ->where(function ($q) use ($txnIds, $payIds) {
                        if ($txnIds->isNotEmpty()) $q->orWhereIn('transaction_id', $txnIds);
                        if ($payIds->isNotEmpty()) $q->orWhereIn('transaction_payment_id', $payIds);
                    })->delete();
                $this->log('deleted accounting_accounts_transactions: '.$accAcctTxnCount);
            }

            if ($payIds->isNotEmpty()) {
                DB::table('transaction_payments')->whereIn('id', $payIds)->delete();
                $this->log('deleted transaction_payments: '.$payIds->count());
            }

            if ($plIds->isNotEmpty()) {
                DB::table('purchase_lines')->whereIn('id', $plIds)->delete();
                $this->log('deleted purchase_lines: '.$plIds->count());
            }

            if ($salIds->isNotEmpty()) {
                DB::table('stock_adjustment_lines')->whereIn('id', $salIds)->delete();
                $this->log('deleted stock_adjustment_lines: '.$salIds->count());
            }

            if ($txnIds->isNotEmpty()) {
                DB::table('transactions')->whereIn('id', $txnIds)->delete();
                $this->log('deleted transactions: '.$txnIds->count());
            }

            // ---------- RECREATE PHASE ----------
            // Native UltimatePOS convention: one opening_stock transaction per
            // (location, product), with opening_stock_product_id set, so the
            // per-product Opening Stock UI can find and display it.
            $now = now();
            $stamp = $now->format('Ymd');
            $createdTxns = 0;
            $createdLines = 0;

            $grouped = $vldRows->groupBy(function ($r) {
                return $r->location_id.'|'.$r->product_id;
            });

            foreach ($grouped as $key => $rows) {
                [$locationId, $productId] = explode('|', $key);
                $locationId = (int) $locationId;
                $productId = (int) $productId;

                $totalValue = 0.0;
                foreach ($rows as $r) {
                    $totalValue += ((float) ($r->default_purchase_price ?? 0)) * (float) $r->qty_available;
                }

                $refNo = 'OS-RESET-'.$bizId.'-'.$locationId.'-'.$productId.'-'.$stamp;

                $txnId = DB::table('transactions')->insertGetId([
                    'business_id' => $bizId,
                    'location_id' => $locationId,
                    'type' => 'opening_stock',
                    'status' => 'received',
                    'payment_status' => 'paid',
                    'opening_stock_product_id' => $productId,
                    'ref_no' => $refNo,
                    'transaction_date' => $now,
                    'total_before_tax' => $totalValue,
                    'final_total' => $totalValue,
                    'created_by' => $actorUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $plBatch = [];
                foreach ($rows as $r) {
                    $cost = (float) ($r->default_purchase_price ?? 0);
                    $plBatch[] = [
                        'transaction_id' => $txnId,
                        'product_id' => $r->product_id,
                        'variation_id' => $r->variation_id,
                        'quantity' => $r->qty_available,
                        'pp_without_discount' => $cost,
                        'purchase_price' => $cost,
                        'purchase_price_inc_tax' => $cost,
                        'item_tax' => 0,
                        'quantity_sold' => 0,
                        'quantity_adjusted' => 0,
                        'quantity_returned' => 0,
                        'mfg_quantity_used' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $createdLines++;
                }
                DB::table('purchase_lines')->insert($plBatch);

                $createdTxns++;
            }

            $this->log('created opening_stock txns='.$createdTxns.' lines='.$createdLines);

            DB::commit();
            $this->info('Done. Created '.$createdTxns.' opening_stock transaction(s) (one per location/product) covering '.$createdLines.' variation row(s).');
            $this->line('Log: '.$this->logFile);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('FAILED — rolled back. '.$e->getMessage());
            $this->log('ERROR rollback: '.$e->getMessage());
            return 1;
        }

        // ---------- POST-FLIGHT VERIFY ----------
        $this->newLine();
        $this->info('Running pos:stockAudit report --business='.$bizId.' to verify...');
        $this->call('pos:stockAudit', ['action' => 'report', '--business' => $bizId]);

        return 0;
    }

    protected function backupOrAbort()
    {
        $dir = storage_path('app/stock-reset');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $file = $dir.DIRECTORY_SEPARATOR.'backup-'.date('Ymd-His').'.sql';

        $db = config('database.connections.'.config('database.default'));
        $host = $db['host'] ?? '127.0.0.1';
        $port = $db['port'] ?? '3306';
        $user = $db['username'] ?? 'root';
        $pass = $db['password'] ?? '';
        $name = $db['database'] ?? '';

        // Try common XAMPP path first, then plain "mysqldump" on PATH.
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'mysqldump',
        ];
        $bin = null;
        foreach ($candidates as $c) {
            if ($c === 'mysqldump' || is_file($c)) { $bin = $c; break; }
        }
        if (! $bin) {
            $this->error('mysqldump not found. Re-run with --no-backup to skip (not recommended) or install mysqldump.');
            throw new \RuntimeException('mysqldump missing');
        }

        $passArg = $pass !== '' ? '-p'.escapeshellarg($pass) : '';
        $cmd = sprintf(
            '"%s" -h %s -P %s -u %s %s --single-transaction --quick --routines --triggers %s > "%s"',
            $bin, escapeshellarg($host), escapeshellarg($port), escapeshellarg($user), $passArg, escapeshellarg($name), $file
        );
        $this->info('Backing up DB to '.$file.' ...');
        // Use system() so user sees progress; suppress password in log.
        $ret = 0;
        @system($cmd, $ret);
        if ($ret !== 0 || ! is_file($file) || filesize($file) < 1024) {
            $this->error('Backup failed (exit '.$ret.'). Aborting.');
            throw new \RuntimeException('mysqldump failed');
        }
        $this->info('Backup OK ('.round(filesize($file) / 1024 / 1024, 2).' MB).');
        $this->log('backup: '.$file);
    }

    protected function resolveActorUserId($bizId)
    {
        $u = DB::table('users')->where('business_id', $bizId)->orderBy('id', 'asc')->first();
        return $u ? $u->id : 1;
    }

    protected function log($msg)
    {
        @file_put_contents($this->logFile, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND);
    }
}
