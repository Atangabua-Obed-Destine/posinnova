<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: payments are looked up by date (received today, payment reports) but paid_on had no
 * index, so every such lookup read all the business's payments.
 */
return new class extends Migration
{
    private function has(): bool
    {
        return ! empty(DB::select("SHOW INDEX FROM transaction_payments WHERE Key_name = 'transaction_payments_paid_on_index'"));
    }

    public function up()
    {
        if (! $this->has()) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->index('paid_on', 'transaction_payments_paid_on_index');
            });
        }
    }

    public function down()
    {
        if ($this->has()) {
            Schema::table('transaction_payments', function (Blueprint $table) {
                $table->dropIndex('transaction_payments_paid_on_index');
            });
        }
    }
};
