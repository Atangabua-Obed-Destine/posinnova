<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_layouts', function (Blueprint $table) {
            if (! Schema::hasColumn('invoice_layouts', 'show_line_location')) {
                $table->boolean('show_line_location')->default(0)->after('show_lot');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_layouts', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_layouts', 'show_line_location')) {
                $table->dropColumn('show_line_location');
            }
        });
    }
};
