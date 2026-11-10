<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic (hospital module) foundations, part 1.
 *
 * business.hospital_settings  JSON of the Clinic settings page (pay before/after,
 *                             plan billing rules, section switches, portal).
 *                             Read through App\Utils\ClinicSettings, which fills
 *                             in defaults, so NULL means "all defaults".
 * patient_details.portal_*    the patient portal PIN (hashed), when it was set,
 *                             consecutive failed sign-ins and the lockout time.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('business', function (Blueprint $table) {
            if (! Schema::hasColumn('business', 'hospital_settings')) {
                $table->text('hospital_settings')->nullable();
            }
        });

        Schema::table('patient_details', function (Blueprint $table) {
            if (! Schema::hasColumn('patient_details', 'portal_pin')) {
                $table->string('portal_pin')->nullable();
            }
            if (! Schema::hasColumn('patient_details', 'portal_pin_set_at')) {
                $table->dateTime('portal_pin_set_at')->nullable();
            }
            if (! Schema::hasColumn('patient_details', 'portal_failed_attempts')) {
                $table->unsignedTinyInteger('portal_failed_attempts')->default(0);
            }
            if (! Schema::hasColumn('patient_details', 'portal_locked_until')) {
                $table->dateTime('portal_locked_until')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('patient_details', function (Blueprint $table) {
            foreach (['portal_pin', 'portal_pin_set_at', 'portal_failed_attempts', 'portal_locked_until'] as $col) {
                if (Schema::hasColumn('patient_details', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('business', function (Blueprint $table) {
            if (Schema::hasColumn('business', 'hospital_settings')) {
                $table->dropColumn('hospital_settings');
            }
        });
    }
};
