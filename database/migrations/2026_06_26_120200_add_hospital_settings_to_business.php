<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business hospital settings.
 *
 * hospital_file_prefix   prefix for patient file numbers (e.g. PMI -> PMI-0001).
 * hospital_file_counter  running counter for file number generation.
 * morgue_daily_fee       flat daily storage fee for the morgue.
 * consultation_fee       default OPD consultation fee.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('business', function (Blueprint $table) {
            if (! Schema::hasColumn('business', 'hospital_file_prefix')) {
                $table->string('hospital_file_prefix', 20)->default('PMI');
            }
            if (! Schema::hasColumn('business', 'hospital_file_counter')) {
                $table->unsignedInteger('hospital_file_counter')->default(0);
            }
            if (! Schema::hasColumn('business', 'morgue_daily_fee')) {
                $table->decimal('morgue_daily_fee', 22, 4)->default(0);
            }
            if (! Schema::hasColumn('business', 'consultation_fee')) {
                $table->decimal('consultation_fee', 22, 4)->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('business', function (Blueprint $table) {
            foreach (['hospital_file_prefix', 'hospital_file_counter', 'morgue_daily_fee', 'consultation_fee'] as $col) {
                if (Schema::hasColumn('business', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
