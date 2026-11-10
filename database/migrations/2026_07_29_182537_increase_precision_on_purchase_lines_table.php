<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class IncreasePrecisionOnPurchaseLinesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN purchase_price DECIMAL(22, 6) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN purchase_price_inc_tax DECIMAL(22, 6) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN pp_without_discount DECIMAL(22, 6) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN discount_percent DECIMAL(22, 6) NOT NULL DEFAULT '0'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN purchase_price DECIMAL(22, 4) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN purchase_price_inc_tax DECIMAL(22, 4) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN pp_without_discount DECIMAL(22, 4) NOT NULL DEFAULT '0'");
        DB::statement("ALTER TABLE purchase_lines MODIFY COLUMN discount_percent DECIMAL(22, 4) NOT NULL DEFAULT '0'");
    }
}
