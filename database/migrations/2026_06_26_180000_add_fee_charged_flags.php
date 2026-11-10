<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency guards so a fee is posted to the patient's account exactly once,
 * even if a record is finalized/re-saved more than once (Phase 8 billing).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (! Schema::hasColumn('consultations', 'fee_charged')) {
                $table->boolean('fee_charged')->default(0)->after('consultation_fee');
            }
        });
        Schema::table('lab_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_requests', 'fee_charged')) {
                $table->boolean('fee_charged')->default(0)->after('status');
            }
        });
        Schema::table('imaging_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('imaging_requests', 'fee_charged')) {
                $table->boolean('fee_charged')->default(0)->after('fee');
            }
        });
    }

    public function down()
    {
        Schema::table('consultations', function (Blueprint $table) {
            if (Schema::hasColumn('consultations', 'fee_charged')) {
                $table->dropColumn('fee_charged');
            }
        });
        Schema::table('lab_requests', function (Blueprint $table) {
            if (Schema::hasColumn('lab_requests', 'fee_charged')) {
                $table->dropColumn('fee_charged');
            }
        });
        Schema::table('imaging_requests', function (Blueprint $table) {
            if (Schema::hasColumn('imaging_requests', 'fee_charged')) {
                $table->dropColumn('fee_charged');
            }
        });
    }
};
