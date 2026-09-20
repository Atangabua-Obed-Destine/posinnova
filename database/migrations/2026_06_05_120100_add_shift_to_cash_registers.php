<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova hospital shifts — stamp the shift a register was opened in.
 *
 * Set from the open time when shift mode is enabled; stays NULL for every
 * register opened while the feature is off, so non-hospital businesses are
 * unaffected.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            if (! Schema::hasColumn('cash_registers', 'shift')) {
                $table->string('shift', 20)->nullable()->after('status')->index();
            }
        });
    }

    public function down()
    {
        Schema::table('cash_registers', function (Blueprint $table) {
            if (Schema::hasColumn('cash_registers', 'shift')) {
                $table->dropColumn('shift');
            }
        });
    }
};
