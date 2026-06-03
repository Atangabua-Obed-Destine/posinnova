<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6 — Recount queue.
 * Lines whose variance crosses a configured threshold are flagged at approve().
 * A flagged line stays in the "recount queue" until it appears in a follow-up
 * count session (resolved_in_session_id) or is dismissed by a supervisor.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->timestamp('flagged_at')->nullable()->after('counted_at');
            $table->decimal('flagged_diff_qty', 22, 4)->nullable()->after('flagged_at');
            $table->decimal('flagged_diff_value', 22, 4)->nullable()->after('flagged_diff_qty');
            $table->timestamp('resolved_at')->nullable()->after('flagged_diff_value');
            $table->unsignedBigInteger('resolved_in_session_id')->nullable()->after('resolved_at');
            $table->unsignedBigInteger('resolved_by')->nullable()->after('resolved_in_session_id');
            $table->string('resolution', 20)->nullable()->after('resolved_by'); // 'recounted' | 'dismissed'
            $table->text('resolution_notes')->nullable()->after('resolution');

            $table->index(['flagged_at', 'resolved_at'], 'ic_lines_flag_idx');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_session_lines', function (Blueprint $table) {
            $table->dropIndex('ic_lines_flag_idx');
            $table->dropColumn([
                'flagged_at', 'flagged_diff_qty', 'flagged_diff_value',
                'resolved_at', 'resolved_in_session_id', 'resolved_by',
                'resolution', 'resolution_notes',
            ]);
        });
    }
};
