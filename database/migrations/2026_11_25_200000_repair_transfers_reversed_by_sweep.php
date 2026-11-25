<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair for a ledger:sync fault fixed on 2026-11-25: the sweep's "document gone" pass looked
 * up every non-payment mapping in the transactions table, so a Payment Accounts movement
 * (transfer, deposit, opening balance) whose id matched no sale or purchase was taken for a
 * deleted document and its entry reversed, its mapping left at "none".
 *
 * The mappings of movements that still exist and are left at "none" are removed here, so the
 * next ledger:sync looks at each again: the ones that post (the credit half of a transfer, a
 * deposit, an opening balance) are posted again; the debit half of a transfer stays "none".
 * Nothing is posted by this migration itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_mappings') || ! Schema::hasTable('account_transactions')) {
            return;
        }
        $ids = DB::table('transaction_mappings as m')
            ->join('account_transactions as at', 'at.id', '=', 'm.transaction_id')
            ->where('m.transaction_type', 'account_transaction')
            ->where('m.status', 'none')
            ->whereNull('at.deleted_at')
            ->whereNull('at.transaction_id')
            ->whereNull('at.transaction_payment_id')
            ->where(function ($w) {
                $w->where('at.type', 'credit')->orWhereNull('at.transfer_transaction_id');
            })
            ->pluck('m.id');
        foreach ($ids->chunk(500) as $chunk) {
            DB::table('transaction_mappings')->whereIn('id', $chunk->all())->delete();
        }
    }

    public function down(): void
    {
        // nothing to undo: the next ledger:sync rebuilds the mappings
    }
};
