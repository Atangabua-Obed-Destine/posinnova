<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // adjustment_type is an ENUM — 'recount_migration' must be a member or
        // MySQL silently stores '' and the P&L exclusion can never match.
        DB::statement("ALTER TABLE transactions MODIFY COLUMN adjustment_type ENUM('normal','abnormal','recount_shortage','recount_migration') NULL");

        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            // 'routine'  : shortages are real shrinkage — reduce Net Profit (default)
            // 'migration': opening-balance correction — excluded from Net Profit
            $table->string('count_purpose', 20)->default('routine')->after('scope_label');
        });

        // Existing counts were data-migration corrections: default them to
        // migration so their shortages stop distorting the P&L. Newly created
        // counts default to 'routine'.
        DB::table('inventory_count_sessions')
            ->where(function ($q) {
                $q->whereNotNull('shortage_transaction_id')
                    ->orWhereNotNull('surplus_transaction_id');
            })
            ->update(['count_purpose' => 'migration']);

        DB::table('transactions')
            ->where('type', 'stock_adjustment')
            ->where('adjustment_type', 'recount_shortage')
            ->update(['adjustment_type' => 'recount_migration']);
    }

    public function down(): void
    {
        DB::table('transactions')
            ->where('type', 'stock_adjustment')
            ->where('adjustment_type', 'recount_migration')
            ->update(['adjustment_type' => 'recount_shortage']);

        DB::statement("ALTER TABLE transactions MODIFY COLUMN adjustment_type ENUM('normal','abnormal','recount_shortage') NULL");

        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            $table->dropColumn('count_purpose');
        });
    }
};
