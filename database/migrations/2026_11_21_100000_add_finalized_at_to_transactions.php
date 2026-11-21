<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova live sales lists: when a sale became final (made final, or a draft / quotation
 * finalized later), so the open sales lists can say "New sale" for exactly those.
 * Set by AppServiceProvider (Transaction saving); empty for sales made before.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('transactions', 'finalized_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->timestamp('finalized_at')->nullable();
                $table->index(['business_id', 'finalized_at'], 'transactions_business_finalized_at_index');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('transactions', 'finalized_at')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropIndex('transactions_business_finalized_at_index');
                $table->dropColumn('finalized_at');
            });
        }
    }
};
