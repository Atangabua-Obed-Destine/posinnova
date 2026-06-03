<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: per-variation, per-unit selling prices.
 *
 * For a given variation (e.g. "El Castillo Wine"), the cashier may select
 * any allowed unit (Bottle / Carton-12 / Carton-24). When a row exists in
 * this table for (variation_id, unit_id) the system uses the override
 * price; otherwise it falls back to the base default_sell_price multiplied
 * by the unit conversion factor.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('variation_unit_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('variation_id');
            $table->unsignedInteger('unit_id');
            $table->decimal('price_exc_tax', 22, 4)->default(0);
            $table->decimal('price_inc_tax', 22, 4)->default(0);
            $table->timestamps();

            $table->unique(['variation_id', 'unit_id'], 'vup_variation_unit_unique');

            $table->foreign('variation_id')
                ->references('id')->on('variations')
                ->onDelete('cascade');

            $table->foreign('unit_id')
                ->references('id')->on('units')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('variation_unit_prices');
    }
};
