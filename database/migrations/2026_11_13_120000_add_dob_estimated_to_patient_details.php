<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: some patients do not know their birth date. Reception can give their age instead; the
 * birth date is then worked out from it and marked as estimated (shown as "about N years").
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('patient_details', 'dob_estimated')) {
            Schema::table('patient_details', function (Blueprint $table) {
                $table->boolean('dob_estimated')->default(0)->after('date_of_birth');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('patient_details', 'dob_estimated')) {
            Schema::table('patient_details', function (Blueprint $table) {
                $table->dropColumn('dob_estimated');
            });
        }
    }
};
