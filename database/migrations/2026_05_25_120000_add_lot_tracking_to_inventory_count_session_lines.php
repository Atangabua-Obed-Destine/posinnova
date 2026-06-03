<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory Count — Phase 7: per-batch (lot/expiry) tracking
 * ----------------------------------------------------------
 * Previously each (session, variation) was a single row. With lot/expiry
 * enabled this is insufficient: each PurchaseLine (batch) has its own
 * lot_number / exp_date / remaining qty and must be counted independently
 * — exactly like Stock Adjustment lines today.
 *
 * Changes:
 *   - Drop unique(session_id, variation_id).
 *   - Add purchase_line_id (nullable) → FK to purchase_lines.id (set null on delete).
 *   - Add lot_number / exp_date snapshot columns (denormalized for audit
 *     and so the line still reads sensibly if the PurchaseLine is later
 *     soft-deleted/edited).
 *   - Add unique(session_id, variation_id, purchase_line_id). NULLs are
 *     considered distinct in MySQL, so legacy "no-lot" rows still allow
 *     a single null per (session, variation).
 */
return new class extends Migration
{
    public function up()
    {
        // Drop old uniqueness so multiple batches per (session, variation) are allowed.
        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->dropUnique(['session_id', 'variation_id']);
        });

        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->unsignedInteger('purchase_line_id')->nullable()->after('variation_id');
            $table->string('lot_number', 191)->nullable()->after('purchase_line_id');
            $table->date('exp_date')->nullable()->after('lot_number');

            $table->index('purchase_line_id');
            // NULL is distinct in MySQL → legacy/no-lot rows remain unique per (session, variation).
            $table->unique(['session_id', 'variation_id', 'purchase_line_id'], 'icsl_session_variation_lot_unique');
        });
    }

    public function down()
    {
        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->dropUnique('icsl_session_variation_lot_unique');
            $table->dropIndex(['purchase_line_id']);
            $table->dropColumn(['purchase_line_id', 'lot_number', 'exp_date']);
        });

        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->unique(['session_id', 'variation_id']);
        });
    }
};
