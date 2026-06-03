<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PosInnova: speed up product/variation autocomplete lookups
     * by ensuring search columns are indexed.
     *
     * - products.sku                : missing index (was created without one)
     * - products.business_id+type   : composite, used in every POS query
     * - variation_location_details.qty_available: helps the ORDER BY
     */
    public function up(): void
    {
        if (! $this->indexExists('products', 'products_sku_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('sku', 'products_sku_index');
            });
        }

        if (! $this->indexExists('products', 'products_business_type_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index(['business_id', 'type'], 'products_business_type_index');
            });
        }

        if (Schema::hasTable('variation_location_details')
            && ! $this->indexExists('variation_location_details', 'vld_qty_available_index')) {
            Schema::table('variation_location_details', function (Blueprint $table) {
                $table->index('qty_available', 'vld_qty_available_index');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('products', 'products_sku_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_sku_index');
            });
        }

        if ($this->indexExists('products', 'products_business_type_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex('products_business_type_index');
            });
        }

        if (Schema::hasTable('variation_location_details')
            && $this->indexExists('variation_location_details', 'vld_qty_available_index')) {
            Schema::table('variation_location_details', function (Blueprint $table) {
                $table->dropIndex('vld_qty_available_index');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $index]
        );

        return ! empty($rows);
    }
};
