<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova clinic: the full consultation report can be a paid extra (Clinic settings "report fee"). The
 * invoice charged for it is remembered on the consultation, so it is charged once and printed once paid.
 * visit_charges.charge_type is an enum: it gains 'report' (a non-strict server would store '' otherwise).
 */
return new class extends Migration
{
    const TYPES = "'consultation','lab','imaging','pharmacy','ward_dispense','procedure','registration','anc','morgue','deposit','other'";

    public function up()
    {
        DB::statement('ALTER TABLE visit_charges MODIFY charge_type ENUM('.self::TYPES.",'report') NOT NULL DEFAULT 'other'");
        if (! Schema::hasColumn('consultations', 'report_fee_transaction_id')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->unsignedInteger('report_fee_transaction_id')->nullable()->index('consult_report_fee_tx_idx');
            });
        }
    }

    public function down()
    {
        DB::table('visit_charges')->where('charge_type', 'report')->update(['charge_type' => 'other']);
        DB::statement('ALTER TABLE visit_charges MODIFY charge_type ENUM('.self::TYPES.") NOT NULL DEFAULT 'other'");
        if (Schema::hasColumn('consultations', 'report_fee_transaction_id')) {
            Schema::table('consultations', function (Blueprint $table) {
                $table->dropIndex('consult_report_fee_tx_idx');
                $table->dropColumn('report_fee_transaction_id');
            });
        }
    }
};
