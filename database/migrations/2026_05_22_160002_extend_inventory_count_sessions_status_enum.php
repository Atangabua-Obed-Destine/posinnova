<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5: extend inventory_count_sessions.status enum to include
 * 'pending_approval' for the new submit-for-approval workflow.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE inventory_count_sessions
            MODIFY status ENUM('draft','pending_approval','posted','cancelled')
            NOT NULL DEFAULT 'draft'");
    }

    public function down(): void
    {
        // Map any pending back to draft first, then narrow the enum.
        DB::statement("UPDATE inventory_count_sessions SET status='draft' WHERE status='pending_approval'");
        DB::statement("ALTER TABLE inventory_count_sessions
            MODIFY status ENUM('draft','posted','cancelled')
            NOT NULL DEFAULT 'draft'");
    }
};
