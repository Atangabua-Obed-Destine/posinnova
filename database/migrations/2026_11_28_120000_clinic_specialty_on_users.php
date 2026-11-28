<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A doctor's specialty (clinic). Empty = a general doctor. Set by the doctor in
 * My practice or by the admin in Clinic settings; shown on the public booking page,
 * at check-in, and used as the default of a new consultation.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('users', 'clinic_specialty')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('clinic_specialty', 100)->nullable()->after('contact_number');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('users', 'clinic_specialty')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('clinic_specialty');
            });
        }
    }
};
