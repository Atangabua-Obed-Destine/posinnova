<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            $table->string('scope_type', 20)->default('all')->after('location_id');
            $table->json('scope_ids')->nullable()->after('scope_type');
            $table->string('scope_label')->nullable()->after('scope_ids');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_count_sessions', function (Blueprint $table) {
            $table->dropColumn(['scope_type', 'scope_ids', 'scope_label']);
        });
    }
};
