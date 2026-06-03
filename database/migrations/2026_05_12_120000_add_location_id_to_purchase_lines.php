<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: per-line destination location for purchase lines.
 * NULL means "fall back to the transaction's location_id" (legacy behaviour).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_lines', 'location_id')) {
                $table->unsignedInteger('location_id')->nullable()->after('variation_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_lines', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_lines', 'location_id')) {
                $table->dropIndex(['location_id']);
                $table->dropColumn('location_id');
            }
        });
    }
};
