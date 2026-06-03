<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lets the counter record qty in any allowed sub-unit (e.g. "5 card-10")
     * while still storing the canonical base-unit total in counted_qty.
     *
     *   counted_qty_input  : the number the user actually typed
     *   counted_unit_id    : the unit they typed it in
     *   counted_qty        : counted_qty_input * unit.base_unit_multiplier
     *
     * Both new columns are nullable — when the user counts in the base unit
     * (or for legacy rows) they're left null and counted_qty is the input.
     */
    public function up(): void
    {
        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->unsignedInteger('counted_unit_id')->nullable()->after('counted_qty');
            $table->decimal('counted_qty_input', 22, 4)->nullable()->after('counted_unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->dropColumn(['counted_unit_id', 'counted_qty_input']);
        });
    }
};
