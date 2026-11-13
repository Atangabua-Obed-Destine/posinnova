<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: printing a lab result that is not paid yet hands it to the patient before payment; the
 * person who chose to print it anyway is recorded (who, when, how much was due, why).
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('lab_requests', 'unpaid_print_by')) {
            Schema::table('lab_requests', function (Blueprint $t) {
                $t->unsignedInteger('unpaid_print_by')->nullable();
                $t->dateTime('unpaid_print_at')->nullable();
                $t->decimal('unpaid_print_due', 22, 4)->nullable();
                $t->string('unpaid_print_reason', 191)->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('lab_requests', 'unpaid_print_by')) {
            Schema::table('lab_requests', function (Blueprint $t) {
                $t->dropColumn(['unpaid_print_by', 'unpaid_print_at', 'unpaid_print_due', 'unpaid_print_reason']);
            });
        }
    }
};
