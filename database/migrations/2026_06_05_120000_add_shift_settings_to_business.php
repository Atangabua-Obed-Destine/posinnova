<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova hospital shifts — per-business settings.
 *
 * enable_shift          master on/off. When 0 the whole shift feature is
 *                       dormant and the system behaves exactly as before.
 * shift_morning_start   morning shift opening time (default 06:00).
 * shift_morning_end     morning shift closing time (default 18:00).
 *
 * The night shift is always the contiguous complement of the morning window
 * (it starts the instant morning ends and runs until morning starts again,
 * wrapping past midnight), so it needs no columns of its own.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('business', function (Blueprint $table) {
            if (! Schema::hasColumn('business', 'enable_shift')) {
                $table->boolean('enable_shift')->default(0);
            }
            if (! Schema::hasColumn('business', 'shift_morning_start')) {
                $table->time('shift_morning_start')->default('06:00:00');
            }
            if (! Schema::hasColumn('business', 'shift_morning_end')) {
                $table->time('shift_morning_end')->default('18:00:00');
            }
        });
    }

    public function down()
    {
        Schema::table('business', function (Blueprint $table) {
            foreach (['enable_shift', 'shift_morning_start', 'shift_morning_end'] as $col) {
                if (Schema::hasColumn('business', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
