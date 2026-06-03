<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('transaction_sell_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_sell_lines', 'location_id')) {
                $table->unsignedInteger('location_id')->nullable()->after('variation_id');
                $table->index('location_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transaction_sell_lines', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_sell_lines', 'location_id')) {
                $table->dropIndex(['location_id']);
                $table->dropColumn('location_id');
            }
        });
    }
};
