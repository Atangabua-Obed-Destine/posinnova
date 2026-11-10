<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: two guards against silently duplicated sales.
 *
 * 1. transactions.idempotency_key — a token the POS generates once per
 *    checkout. If a sale is submitted twice (double-click, or a retry after a
 *    response was lost in transit) the second insert collides on the unique
 *    index and the controller returns the sale that already exists instead of
 *    creating a second one with a second stock deduction.
 *
 * 2. a unique index on (business_id, type, invoice_no) — invoice numbers are
 *    handed out by read-then-increment, which can issue the same number twice
 *    to two cashiers finalising at the same moment. The locking added in
 *    TransactionUtil/Util closes the race; this index is the backstop that
 *    makes any remaining collision fail loudly instead of silently.
 *
 * Both indexes are on nullable columns, so existing rows with NULL are
 * unaffected (MySQL treats each NULL as distinct).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'idempotency_key')) {
                $table->string('idempotency_key', 64)->nullable()->after('invoice_no');
                $table->unique(['business_id', 'idempotency_key'], 'transactions_business_idempotency_unique');
            }
        });

        // Separate statement: adding a column and indexing another in one
        // ALTER can fail on some MariaDB builds.
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['business_id', 'type', 'invoice_no'], 'transactions_business_type_invoice_unique');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_business_type_invoice_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique('transactions_business_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
