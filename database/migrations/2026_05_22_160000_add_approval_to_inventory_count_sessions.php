<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('approved_by')->nullable()->after('posted_at');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_notes')->nullable()->after('rejected_at');
            $table->text('approval_notes')->nullable()->after('rejection_notes');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'approved_at', 'rejected_by', 'rejected_at', 'rejection_notes', 'approval_notes']);
        });
    }
};
