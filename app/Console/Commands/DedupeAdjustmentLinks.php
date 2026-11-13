<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova: remove the duplicate stock-adjustment links left by the old mapPurchaseSell bug.
 *
 * Until it was fixed, posting a stock adjustment (Inventory Count posts large ones) re-inserted every
 * earlier line's rows into transaction_sell_lines_purchase_lines after each line, so a line of an
 * N-line adjustment could be written up to N times. The copies are exact: same adjustment line,
 * purchase line, quantity and created_at. This keeps one of each and deletes the rest.
 *
 * Nothing else changes: purchase_lines.quantity_adjusted was only ever incremented once per real
 * allocation, so stock levels are already right; only the link rows are wrong (they slow the stock
 * reports, inflate the lot report and would over-return stock if such an adjustment were deleted).
 *
 * Adjustment lines whose links still add up to more than the line after this (linked again later
 * without the old links being removed) are listed for a person to look at; they are not guessed at.
 *
 *   php artisan posinnova:dedupe-adjustment-links            # dry run: what would be removed
 *   php artisan posinnova:dedupe-adjustment-links --apply    # remove the duplicates
 */
class DedupeAdjustmentLinks extends Command
{
    protected $signature = 'posinnova:dedupe-adjustment-links {--apply : Delete the duplicates (without it nothing is changed)}';

    protected $description = 'Remove exact duplicate stock-adjustment links (transaction_sell_lines_purchase_lines) left by an old bug';

    public function handle()
    {
        $total = DB::table('transaction_sell_lines_purchase_lines')->whereNotNull('stock_adjustment_line_id')->count();
        $keep = DB::table('transaction_sell_lines_purchase_lines')->whereNotNull('stock_adjustment_line_id')
            ->groupBy('stock_adjustment_line_id', 'purchase_line_id', 'quantity', 'created_at')
            ->select(DB::raw('MIN(id) as id'))->get()->count();
        $extra = $total - $keep;
        $this->info("Stock-adjustment links: {$total}; duplicates: {$extra}; would remain: {$keep}.");

        if ($extra > 0 && $this->option('apply')) {
            $deleted = 0;
            // the groups that still have copies, the first 500 each time (a cleaned group drops out of the
            // list, so paging with an offset would skip groups) until none are left
            do {
                $groups = DB::table('transaction_sell_lines_purchase_lines')->whereNotNull('stock_adjustment_line_id')
                    ->groupBy('stock_adjustment_line_id', 'purchase_line_id', 'quantity', 'created_at')
                    ->havingRaw('COUNT(*) > 1')
                    ->select('stock_adjustment_line_id', 'purchase_line_id', 'quantity', 'created_at', DB::raw('MIN(id) as keep_id'))
                    ->limit(500)->get();
                DB::transaction(function () use ($groups, &$deleted) {
                    foreach ($groups as $g) {
                        $deleted += DB::table('transaction_sell_lines_purchase_lines')
                            ->where('stock_adjustment_line_id', $g->stock_adjustment_line_id)
                            ->where('purchase_line_id', $g->purchase_line_id)
                            ->where('quantity', $g->quantity)
                            ->where('created_at', $g->created_at)
                            ->where('id', '!=', $g->keep_id)
                            ->delete();
                    }
                });
            } while ($groups->count() > 0);
            $this->info("Deleted {$deleted} duplicate links.");
        } elseif ($extra > 0) {
            $this->line('Dry run: nothing changed. Run again with --apply to delete the duplicates.');
        }

        // what is still more than the adjustment line itself, after removing exact copies
        $over = DB::table('stock_adjustment_lines as sal')
            ->join(DB::raw('(SELECT stock_adjustment_line_id sid, SUM(quantity) q FROM (SELECT DISTINCT stock_adjustment_line_id, purchase_line_id, quantity, created_at FROM transaction_sell_lines_purchase_lines WHERE stock_adjustment_line_id IS NOT NULL) u GROUP BY stock_adjustment_line_id) d'), 'd.sid', '=', 'sal.id')
            ->whereRaw('d.q > sal.quantity + 0.0001')
            ->select('sal.id', 'sal.transaction_id', 'sal.quantity', 'd.q as linked')
            ->get();
        if ($over->count()) {
            $this->warn($over->count().' adjustment line(s) are still linked to more than their quantity (linked again later); please review:');
            $this->table(['adjustment line', 'adjustment (transaction id)', 'line quantity', 'linked quantity'],
                $over->map(function ($o) { return [$o->id, $o->transaction_id, (float) $o->quantity, (float) $o->linked]; })->all());
        }

        return 0;
    }
}
