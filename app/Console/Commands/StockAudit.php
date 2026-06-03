<?php

namespace App\Console\Commands;

use App\PurchaseLine;
use App\Transaction;
use DB;
use Illuminate\Console\Command;

/**
 * PosInnova: Stock integrity audit & repair command.
 *
 * UltimatePOS tracks stock in two parallel places:
 *   1. variation_location_details.qty_available  (what the UI shows)
 *   2. purchase_lines                            (what POS checkout maps against)
 *
 * When (1) and (2) disagree, sales fail with:
 *   "ERROR: NOT ALLOWED: Mismatch between sold and purchase quantity..."
 *
 * This command finds and (optionally) repairs those mismatches WITHOUT
 * ever changing the displayed qty_available — so what cashiers see on
 * screen stays exactly the same after the fix.
 *
 * Usage:
 *   php artisan pos:stockAudit                          # dry-run report only
 *   php artisan pos:stockAudit report                   # same as above
 *   php artisan pos:stockAudit fix-ghost --apply        # create missing opening-stock purchase lines
 *   php artisan pos:stockAudit fix-overcount --apply    # consume phantom surplus on existing purchase lines
 *   php artisan pos:stockAudit export-zero-cost         # export CSV of variations with cost = 0
 *
 * Scope filters (all actions):
 *   --sku=SB119325B          only this SKU
 *   --location=8             only this location id
 *   --business=1             only this business id
 *   --default-cost=1000      cost to use when variation has no default_purchase_price
 *   --no-backup              skip mysqldump (NOT recommended)
 *
 * Safety:
 *   - Default mode is dry-run. Nothing is written without --apply.
 *   - --apply triggers a mysqldump backup of affected tables first.
 *   - All writes are wrapped in a DB transaction (full rollback on error).
 *   - A per-row log is written to storage/logs/stock-audit-YYYYMMDD.log
 */
class StockAudit extends Command
{
    protected $signature = 'pos:stockAudit
                            {action=report : report|fix-ghost|fix-overcount|export-zero-cost}
                            {--apply : Actually write changes (default is dry-run)}
                            {--sku= : Limit to a single variation sub_sku}
                            {--location= : Limit to a single business_location id}
                            {--business= : Limit to a single business id}
                            {--default-cost=0 : Fallback purchase price for variations with cost = 0}
                            {--no-backup : Skip the mysqldump backup (not recommended)}';

    protected $description = 'Audit and repair stock integrity (ghost stock / over-counted stock / zero-cost variations).';

    /** @var string */
    protected $logFile;

    public function handle()
    {
        @ini_set('max_execution_time', 0);
        @ini_set('memory_limit', '512M');

        $this->logFile = storage_path('logs/stock-audit-'.date('Ymd').'.log');
        $this->log('===== run start ===== action='.$this->argument('action').' apply='.($this->option('apply') ? '1' : '0'));

        $action = $this->argument('action');

        switch ($action) {
            case 'report':
                return $this->actionReport();
            case 'fix-ghost':
                return $this->actionFixGhost();
            case 'fix-overcount':
                return $this->actionFixOvercount();
            case 'export-zero-cost':
                return $this->actionExportZeroCost();
            default:
                $this->error("Unknown action: {$action}");
                $this->line('Valid actions: report | fix-ghost | fix-overcount | export-zero-cost');
                return 1;
        }
    }

    // -------------------------------------------------------------------------
    // ACTION: report (read-only)
    // -------------------------------------------------------------------------
    protected function actionReport()
    {
        $this->info('PosInnova Stock Audit Report');
        $this->line(str_repeat('=', 80));

        // Headline counts
        $totalVariations = DB::table('variations')->count();
        $zeroCost = DB::table('variations')
            ->where(function ($q) {
                $q->where('default_purchase_price', 0)->orWhereNull('default_purchase_price');
            })->count();
        $rowsWithStock = DB::table('variation_location_details')->where('qty_available', '>', 0)->count();

        $ghostRows = $this->ghostStockQuery()->get();
        $overcountRows = $this->overcountQuery()->get();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total variations', $totalVariations],
                ['Variations with zero/null cost price', $zeroCost],
                ['Location rows with stock > 0', $rowsWithStock],
                ['GHOST stock rows (display > real, blocks POS)', $ghostRows->count()],
                ['OVER-COUNTED purchase rows (display < real, reports skewed)', $overcountRows->count()],
            ]
        );

        // Ghost stock detail
        if ($ghostRows->count() > 0) {
            $this->newLine();
            $this->warn('GHOST STOCK — these will cause "Mismatch between sold and purchase quantity" at POS:');
            $rows = [];
            foreach ($ghostRows as $r) {
                $rows[] = [
                    $r->business_id,
                    $r->location_id,
                    $r->location_name,
                    $r->product_id,
                    $r->variation_id,
                    $r->sub_sku,
                    $r->product_name,
                    (float) $r->displayed_stock,
                    (float) $r->real_remaining,
                    (float) ($r->displayed_stock - $r->real_remaining),
                    (float) $r->default_purchase_price,
                ];
            }
            $this->table(
                ['Biz', 'Loc#', 'Location', 'Prod#', 'Var#', 'SKU', 'Product', 'Display', 'Real', 'Gap', 'Cost'],
                $rows
            );
        } else {
            $this->info('No ghost-stock rows found. POS sales should not be blocked by stock mismatch.');
        }

        // Over-count detail
        if ($overcountRows->count() > 0) {
            $this->newLine();
            $this->warn('OVER-COUNTED stock — display < real. Sales still work but stock-value reports are inflated:');
            $rows = [];
            foreach ($overcountRows as $r) {
                $rows[] = [
                    $r->business_id,
                    $r->location_id,
                    $r->location_name,
                    $r->sub_sku,
                    $r->product_name,
                    (float) $r->displayed_stock,
                    (float) $r->real_remaining,
                    (float) ($r->real_remaining - $r->displayed_stock),
                ];
            }
            $this->table(
                ['Biz', 'Loc#', 'Location', 'SKU', 'Product', 'Display', 'Real', 'Surplus'],
                $rows
            );
        }

        $this->newLine();
        $this->info('This was a dry-run report — nothing was changed.');
        $this->line('Next steps:');
        $this->line('  php artisan pos:stockAudit fix-ghost --apply       # heal ghost stock');
        $this->line('  php artisan pos:stockAudit fix-overcount --apply   # heal over-counted stock');
        $this->line('  php artisan pos:stockAudit export-zero-cost        # export CSV of zero-cost variations');

        return 0;
    }

    // -------------------------------------------------------------------------
    // ACTION: fix-ghost
    // Creates an opening_stock Transaction + PurchaseLine for each row where
    // displayed_stock > real_remaining. qty_available is NOT changed.
    // -------------------------------------------------------------------------
    protected function actionFixGhost()
    {
        $rows = $this->ghostStockQuery()->get();
        if ($rows->isEmpty()) {
            $this->info('No ghost-stock rows to fix.');
            return 0;
        }

        $this->info('Found '.$rows->count().' ghost-stock row(s).');

        if (! $this->option('apply')) {
            $this->warn('DRY-RUN. Re-run with --apply to write changes.');
            foreach ($rows as $r) {
                $gap = (float) ($r->displayed_stock - $r->real_remaining);
                $cost = $this->resolveCost($r);
                $this->line(sprintf(
                    'WOULD create opening_stock: biz=%d loc=%d (%s) sku=%s qty=%s cost=%s',
                    $r->business_id, $r->location_id, $r->location_name, $r->sub_sku, $gap, $cost
                ));
            }
            return 0;
        }

        $this->backupOrAbort();

        DB::beginTransaction();
        try {
            $created = 0;
            foreach ($rows as $r) {
                $gap = round((float) ($r->displayed_stock - $r->real_remaining), 4);
                if ($gap <= 0) {
                    continue;
                }
                $cost = $this->resolveCost($r);

                // Reuse one daily opening_stock transaction per (business,location,product)
                // to keep the transactions table tidy.
                $refNo = 'OS-FIX-'.$r->business_id.'-'.$r->location_id.'-'.$r->product_id.'-'.date('Ymd');

                $txn = Transaction::firstOrCreate(
                    [
                        'business_id' => $r->business_id,
                        'location_id' => $r->location_id,
                        'type' => 'opening_stock',
                        'ref_no' => $refNo,
                    ],
                    [
                        'opening_stock_product_id' => $r->product_id,
                        'status' => 'received',
                        'transaction_date' => now(),
                        'total_before_tax' => 0,
                        'final_total' => 0,
                        'payment_status' => 'paid',
                        'created_by' => $this->resolveActorUserId($r->business_id),
                    ]
                );

                $line = new PurchaseLine();
                $line->transaction_id = $txn->id;
                $line->product_id = $r->product_id;
                $line->variation_id = $r->variation_id;
                $line->quantity = $gap;
                $line->pp_without_discount = $cost;
                $line->purchase_price = $cost;
                $line->purchase_price_inc_tax = $cost;
                $line->item_tax = 0;
                $line->quantity_sold = 0;
                $line->quantity_adjusted = 0;
                $line->quantity_returned = 0;
                $line->save();

                // Update txn totals
                $txn->total_before_tax = (float) $txn->total_before_tax + ($cost * $gap);
                $txn->final_total = (float) $txn->final_total + ($cost * $gap);
                $txn->save();

                $this->log(sprintf(
                    'FIX-GHOST: txn=%d pl=%d biz=%d loc=%d sku=%s qty=%s cost=%s',
                    $txn->id, $line->id, $r->business_id, $r->location_id, $r->sub_sku, $gap, $cost
                ));
                $created++;
            }
            DB::commit();
            $this->info("Done. Created opening-stock purchase lines for {$created} row(s).");
            $this->line('Log: '.$this->logFile);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('FAILED — rolled back. '.$e->getMessage());
            $this->log('ERROR fix-ghost rollback: '.$e->getMessage());
            return 1;
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // ACTION: fix-overcount
    // For rows where real_remaining > displayed_stock, consume the surplus by
    // incrementing quantity_sold on the OLDEST purchase lines (FIFO) until
    // real_remaining == displayed_stock. qty_available is NOT changed.
    // -------------------------------------------------------------------------
    protected function actionFixOvercount()
    {
        $rows = $this->overcountQuery()->get();
        if ($rows->isEmpty()) {
            $this->info('No over-counted rows to fix.');
            return 0;
        }

        $this->info('Found '.$rows->count().' over-counted row(s).');

        if (! $this->option('apply')) {
            $this->warn('DRY-RUN. Re-run with --apply to write changes.');
            foreach ($rows as $r) {
                $surplus = (float) ($r->real_remaining - $r->displayed_stock);
                $this->line(sprintf(
                    'WOULD consume %s surplus units on purchase_lines: biz=%d loc=%d (%s) sku=%s',
                    $surplus, $r->business_id, $r->location_id, $r->location_name, $r->sub_sku
                ));
            }
            return 0;
        }

        $this->backupOrAbort();

        DB::beginTransaction();
        try {
            $touched = 0;
            foreach ($rows as $r) {
                $surplus = round((float) ($r->real_remaining - $r->displayed_stock), 4);
                if ($surplus <= 0) {
                    continue;
                }

                // Mirror the POS stock-validation rule (TransactionUtil::mapPurchaseSell):
                // valid sources of stock are purchase, opening_stock, purchase_transfer
                // and production_purchase. Excluding any of these causes the audit to
                // mis-classify legitimate stock-transfer / production rows as anomalies.
                $lines = DB::table('purchase_lines as pl')
                    ->join('transactions as t', 't.id', '=', 'pl.transaction_id')
                    ->select('pl.id', 'pl.quantity', 'pl.quantity_sold', 'pl.quantity_adjusted', 'pl.quantity_returned')
                    ->where('pl.variation_id', $r->variation_id)
                    ->whereRaw('COALESCE(pl.location_id, t.location_id) = ?', [$r->location_id])
                    ->whereIn('t.type', ['purchase', 'opening_stock', 'purchase_transfer', 'production_purchase'])
                    ->where('t.status', 'received')
                    ->orderBy('pl.id', 'asc')
                    ->get();

                $remaining = $surplus;
                foreach ($lines as $pl) {
                    if ($remaining <= 0) break;
                    $available = (float) ($pl->quantity - $pl->quantity_sold - $pl->quantity_adjusted - $pl->quantity_returned);
                    if ($available <= 0) continue;
                    $take = min($available, $remaining);
                    DB::table('purchase_lines')
                        ->where('id', $pl->id)
                        ->update(['quantity_sold' => DB::raw('quantity_sold + '.$take)]);
                    $this->log(sprintf(
                        'FIX-OVERCOUNT: pl=%d biz=%d loc=%d sku=%s consumed=%s',
                        $pl->id, $r->business_id, $r->location_id, $r->sub_sku, $take
                    ));
                    $remaining -= $take;
                    $touched++;
                }
            }
            DB::commit();
            $this->info("Done. Adjusted {$touched} purchase line(s).");
            $this->line('Log: '.$this->logFile);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('FAILED — rolled back. '.$e->getMessage());
            $this->log('ERROR fix-overcount rollback: '.$e->getMessage());
            return 1;
        }

        return 0;
    }

    // -------------------------------------------------------------------------
    // ACTION: export-zero-cost
    // Writes CSV of variations with cost=0 so the owner can fill in real costs.
    // -------------------------------------------------------------------------
    protected function actionExportZeroCost()
    {
        $q = DB::table('variations as v')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->select('v.id as variation_id', 'v.sub_sku', 'p.id as product_id', 'p.name as product_name', 'v.default_purchase_price', 'v.default_sell_price', 'p.business_id')
            ->where(function ($q2) {
                $q2->where('v.default_purchase_price', 0)->orWhereNull('v.default_purchase_price');
            });

        if ($this->option('business')) {
            $q->where('p.business_id', $this->option('business'));
        }
        if ($this->option('sku')) {
            $q->where('v.sub_sku', $this->option('sku'));
        }

        $rows = $q->orderBy('p.name')->get();

        if ($rows->isEmpty()) {
            $this->info('No zero-cost variations found for the given filters.');
            return 0;
        }

        $dir = storage_path('app/stock-audit');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.'zero-cost-variations-'.date('Ymd-His').'.csv';
        $fp = fopen($path, 'w');
        fputcsv($fp, ['variation_id', 'sub_sku', 'product_id', 'product_name', 'current_purchase_price', 'default_sell_price', 'business_id', 'new_purchase_price']);
        foreach ($rows as $r) {
            fputcsv($fp, [
                $r->variation_id, $r->sub_sku, $r->product_id, $r->product_name,
                $r->default_purchase_price, $r->default_sell_price, $r->business_id, '',
            ]);
        }
        fclose($fp);

        $this->info('Exported '.$rows->count().' zero-cost variation(s).');
        $this->line('File: '.$path);
        $this->line('Fill in the "new_purchase_price" column then ask for the import command (not yet built).');
        return 0;
    }

    // -------------------------------------------------------------------------
    // QUERIES
    // -------------------------------------------------------------------------

    /**
     * Rows where displayed_stock > real_remaining (these BLOCK POS sales).
     */
    protected function ghostStockQuery()
    {
        return $this->baseStockQuery()->whereRaw('vld.qty_available > IFNULL(stock_calc.real_remaining, 0)');
    }

    /**
     * Rows where real_remaining > displayed_stock (reports inflated, sales OK).
     */
    protected function overcountQuery()
    {
        return $this->baseStockQuery()->whereRaw('IFNULL(stock_calc.real_remaining, 0) > vld.qty_available');
    }

    protected function baseStockQuery()
    {
        // Mirror the POS stock-validation rule: stock can enter via purchase,
        // opening_stock, purchase_transfer (the destination side of a stock
        // transfer) or production_purchase. Any narrower set causes the audit
        // to flag legitimate transfers / production receipts as ghost stock.
        // Per-line location override: when pl.location_id is set, the line lives
        // at that location even if the parent transaction.location_id differs.
        // (Multi-location purchase form populates pl.location_id per row.)
        $subSql = '(SELECT pl.variation_id, COALESCE(pl.location_id, t.location_id) AS location_id, '
            .'SUM(pl.quantity - pl.quantity_sold - pl.quantity_adjusted - pl.quantity_returned) AS real_remaining '
            .'FROM purchase_lines pl '
            .'JOIN transactions t ON t.id = pl.transaction_id '
            .'WHERE t.type IN ("purchase","opening_stock","purchase_transfer","production_purchase") AND t.status = "received" '
            .'GROUP BY pl.variation_id, COALESCE(pl.location_id, t.location_id))';

        $q = DB::table('variation_location_details as vld')
            ->join('variations as v', 'v.id', '=', 'vld.variation_id')
            ->join('products as p', 'p.id', '=', 'v.product_id')
            ->join('business_locations as bl', 'bl.id', '=', 'vld.location_id')
            ->leftJoin(DB::raw($subSql.' as stock_calc'), function ($j) {
                $j->on('stock_calc.variation_id', '=', 'vld.variation_id')
                  ->on('stock_calc.location_id', '=', 'vld.location_id');
            })
            ->select(
                'p.business_id',
                'vld.location_id',
                'bl.name as location_name',
                'p.id as product_id',
                'p.name as product_name',
                'v.id as variation_id',
                'v.sub_sku',
                'v.default_purchase_price',
                'vld.qty_available as displayed_stock',
                DB::raw('IFNULL(stock_calc.real_remaining, 0) as real_remaining')
            )
            ->where('vld.qty_available', '>', 0);

        if ($this->option('business')) {
            $q->where('p.business_id', $this->option('business'));
        }
        if ($this->option('location')) {
            $q->where('vld.location_id', $this->option('location'));
        }
        if ($this->option('sku')) {
            $q->where('v.sub_sku', $this->option('sku'));
        }

        return $q->orderBy('p.business_id')->orderBy('vld.location_id')->orderBy('v.sub_sku');
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    protected function resolveCost($row)
    {
        $cost = (float) $row->default_purchase_price;
        if ($cost <= 0) {
            $cost = (float) $this->option('default-cost');
        }
        return $cost;
    }

    protected function resolveActorUserId($businessId)
    {
        // Use the first admin/user of the business; fall back to any user id 1
        $uid = DB::table('users')->where('business_id', $businessId)->orderBy('id')->value('id');
        return $uid ?: 1;
    }

    protected function log($msg)
    {
        @file_put_contents($this->logFile, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL, FILE_APPEND);
    }

    /**
     * Try to mysqldump the affected tables. Aborts the command if backup fails
     * (unless --no-backup was passed).
     */
    protected function backupOrAbort()
    {
        if ($this->option('no-backup')) {
            $this->warn('--no-backup passed: skipping mysqldump.');
            return;
        }

        $tables = ['variation_location_details', 'purchase_lines', 'transactions', 'transaction_sell_lines_purchase_lines'];
        $dir = storage_path('app/stock-audit-backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir.DIRECTORY_SEPARATOR.'backup-'.date('Ymd-His').'.sql';

        $host = config('database.connections.mysql.host');
        $port = config('database.connections.mysql.port');
        $db = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        // Locate mysqldump (Windows XAMPP path first, then PATH)
        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'mysqldump',
        ];
        $bin = null;
        foreach ($candidates as $c) {
            if ($c === 'mysqldump' || file_exists($c)) {
                $bin = $c;
                break;
            }
        }
        if (! $bin) {
            $this->error('mysqldump not found. Re-run with --no-backup if you have your own backup.');
            exit(1);
        }

        $passArg = $pass !== '' ? ' -p'.escapeshellarg($pass) : '';
        $tablesArg = implode(' ', array_map('escapeshellarg', $tables));
        $cmd = sprintf(
            '%s -h %s -P %s -u %s%s --single-transaction --quick %s %s > %s 2>&1',
            escapeshellcmd($bin),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            $passArg,
            escapeshellarg($db),
            $tablesArg,
            escapeshellarg($path)
        );

        $this->line('Backing up affected tables to: '.$path);
        $exit = 0;
        $out = [];
        exec($cmd, $out, $exit);
        if ($exit !== 0 || ! file_exists($path) || filesize($path) < 100) {
            $this->error('Backup FAILED. Aborting. Re-run with --no-backup only if you have an external backup.');
            $this->log('Backup failed: '.implode("\n", $out));
            exit(1);
        }
        $this->info('Backup OK ('.number_format(filesize($path) / 1024, 1).' KB).');
        $this->log('Backup written: '.$path);
    }
}
