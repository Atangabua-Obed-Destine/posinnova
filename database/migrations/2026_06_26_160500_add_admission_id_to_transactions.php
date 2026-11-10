<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a sale to an admission so ward dispenses roll up into the patient's
 * running bill (Phase 7). Nullable — non-hospital sales leave it null.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'admission_id')) {
                $table->unsignedBigInteger('admission_id')->nullable()->after('visit_id');
                $table->index('admission_id');
            }
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'admission_id')) {
                $table->dropIndex(['admission_id']);
                $table->dropColumn('admission_id');
            }
        });
    }
};
