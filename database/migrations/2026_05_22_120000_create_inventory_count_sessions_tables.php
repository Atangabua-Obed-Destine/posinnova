<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 of Inventory Count: persistent count sessions.
 *
 * inventory_count_sessions — header row per physical count attempt
 *   status: draft | posted | cancelled
 *   reference_no: human-readable IC-YYYYMMDD-NNNN style id
 *
 * inventory_count_session_lines — one row per variation enumerated at session
 *   start. theoretical_at_start is a snapshot of vld.qty_available at that
 *   instant. counted_qty is null until the counter enters a number; an entered
 *   counted_qty of 0 is meaningfully different from null.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('inventory_count_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('location_id');
            $table->string('reference_no', 64)->unique();
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft')->index();
            $table->string('reason', 64)->nullable();
            $table->string('reason_other', 255)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('started_by');
            $table->timestamp('started_at')->useCurrent();
            $table->unsignedInteger('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->unsignedInteger('cancelled_by')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('shortage_transaction_id')->nullable();
            $table->unsignedBigInteger('surplus_transaction_id')->nullable();
            $table->decimal('shortage_value', 22, 4)->default(0);
            $table->decimal('surplus_value', 22, 4)->default(0);
            $table->unsignedInteger('lines_total')->default(0);
            $table->unsignedInteger('lines_counted')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'location_id', 'status']);
        });

        Schema::create('inventory_count_session_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('session_id');
            $table->unsignedInteger('product_id');
            $table->unsignedBigInteger('variation_id');
            $table->decimal('theoretical_at_start', 22, 4)->default(0);
            $table->decimal('counted_qty', 22, 4)->nullable();
            $table->decimal('unit_cost', 22, 4)->default(0);
            $table->unsignedInteger('counted_by')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'variation_id']);
            $table->index('session_id');
            $table->foreign('session_id')->references('id')->on('inventory_count_sessions')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_count_session_lines');
        Schema::dropIfExists('inventory_count_sessions');
    }
};
