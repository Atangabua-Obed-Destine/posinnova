<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_layouts', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_layouts', 'show_product_table_borders')) {
                $table->boolean('show_product_table_borders')->default(1)->after('show_line_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_layouts', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_layouts', 'show_product_table_borders')) {
                $table->dropColumn('show_product_table_borders');
            }
        });
    }
};
