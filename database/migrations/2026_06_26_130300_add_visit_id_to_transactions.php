<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link pharmacy sells (and later other transaction types) back to a hospital
 * visit, so a patient's pharmacy charges roll up into their visit bill.
 * Nullable — ordinary non-hospital sales leave it null and behave as before.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('transactions', 'visit_id')) {
                $table->unsignedBigInteger('visit_id')->nullable()->after('contact_id');
                $table->index('visit_id');
            }
            if (! Schema::hasColumn('transactions', 'consultation_id')) {
                $table->unsignedBigInteger('consultation_id')->nullable()->after('visit_id');
            }
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'consultation_id')) {
                $table->dropColumn('consultation_id');
            }
            if (Schema::hasColumn('transactions', 'visit_id')) {
                $table->dropIndex(['visit_id']);
                $table->dropColumn('visit_id');
            }
        });
    }
};
