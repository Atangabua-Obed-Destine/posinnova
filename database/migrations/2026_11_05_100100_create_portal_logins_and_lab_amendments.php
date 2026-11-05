<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic (hospital module) foundations, part 2.
 *
 * patient_portal_logins   every patient portal sign-in attempt, successful or
 *                         not (the portal faces the internet).
 * lab_result_amendments   a correction to a VALIDATED lab result. The line keeps
 *                         the new value; this row keeps the old one, the reason,
 *                         who changed it and when.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('patient_portal_logins')) {
            Schema::create('patient_portal_logins', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->nullable()->index();
                $table->unsignedInteger('contact_id')->nullable()->index();
                $table->string('file_number', 50)->nullable();
                $table->string('ip', 45)->nullable();
                $table->boolean('success')->default(false);
                $table->string('reason', 50)->nullable();
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('lab_result_amendments')) {
            Schema::create('lab_result_amendments', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('business_id')->index();
                $table->unsignedInteger('lab_request_id')->index();
                $table->unsignedInteger('lab_request_line_id')->index();
                $table->string('old_value')->nullable();
                $table->string('old_flag', 30)->nullable();
                $table->string('new_value')->nullable();
                $table->string('new_flag', 30)->nullable();
                $table->text('reason');
                $table->unsignedInteger('amended_by');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('lab_result_amendments');
        Schema::dropIfExists('patient_portal_logins');
    }
};
