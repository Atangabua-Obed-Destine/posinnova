<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'walk_in_customer_name')) {
                $table->string('walk_in_customer_name', 191)->nullable()->after('contact_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'walk_in_customer_name')) {
                $table->dropColumn('walk_in_customer_name');
            }
        });
    }
};
