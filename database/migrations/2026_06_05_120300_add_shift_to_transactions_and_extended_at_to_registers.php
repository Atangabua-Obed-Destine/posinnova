<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova hospital shifts — handover support.
 *
 * transactions.shift       the shift a sale was rung up in. Stamped from the
 *                          cashier's open register at POS sale creation, so it
 *                          honours an "extended" shift rather than the wall
 *                          clock. NULL for non-shift businesses / legacy rows.
 * cash_registers.extended_at  set when the cashier chooses to extend the shift
 *                          past its end time; suppresses further prompts.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'shift')) {
                $table->string('shift', 20)->nullable()->index();
            }
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_registers', 'extended_at')) {
                $table->timestamp('extended_at')->nullable()->after('shift');
            }
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'shift')) {
                $table->dropColumn('shift');
            }
        });

        Schema::table('cash_registers', function (Blueprint $table) {
            if (Schema::hasColumn('cash_registers', 'extended_at')) {
                $table->dropColumn('extended_at');
            }
        });
    }
};
