<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds counted_breakdown to capture mixed-unit counts (e.g. 3 card-10 +
     * 4 Tab = 34 Tab). Stored as JSON array of {qty, unit_id, label,
     * multiplier}. Null when the user counted in a single unit.
     */
    public function up(): void
    {
        if (Schema::hasTable('inventory_count_session_lines')
            && ! Schema::hasColumn('inventory_count_session_lines', 'counted_breakdown')) {
            Schema::table('inventory_count_session_lines', function (Blueprint $table) {
                $table->json('counted_breakdown')->nullable()->after('counted_qty_input');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventory_count_session_lines')
            && Schema::hasColumn('inventory_count_session_lines', 'counted_breakdown')) {
            Schema::table('inventory_count_session_lines', function (Blueprint $table) {
                $table->dropColumn('counted_breakdown');
            });
        }
    }
};
