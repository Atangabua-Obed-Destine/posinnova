<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'commission_amount')) {
                // PosInnova: fixed commission amount owed to the commission agent
                // (referrer/intermediary) for this specific invoice. Internal
                // record only — does not affect the customer's total.
                $table->decimal('commission_amount', 22, 4)->nullable()->after('commission_agent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'commission_amount')) {
                $table->dropColumn('commission_amount');
            }
        });
    }
};
