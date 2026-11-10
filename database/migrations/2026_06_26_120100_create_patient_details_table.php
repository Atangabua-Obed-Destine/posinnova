<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinical extension of a contact that is flagged is_patient = 1.
 * Kept in a separate 1:1 table so the contacts table is not bloated with
 * a dozen nullable medical columns. Patients remain ordinary customers.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('patient_details', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('business_id');
            $table->string('file_number', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('sex', ['M', 'F', 'Other'])->nullable();
            $table->enum('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])->nullable();
            $table->text('known_allergies')->nullable();
            $table->string('next_of_kin_name', 191)->nullable();
            $table->string('next_of_kin_phone', 50)->nullable();
            $table->string('next_of_kin_relationship', 100)->nullable();
            $table->string('insurance_provider', 191)->nullable();
            $table->string('insurance_number', 100)->nullable();
            $table->decimal('insurance_coverage_pct', 5, 2)->nullable();
            $table->date('insurance_expiry_date')->nullable();
            // Permanent link to the mother's patient record (used by maternity / baby registration).
            $table->unsignedInteger('mother_contact_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->unique(['business_id', 'file_number']);
            $table->index('contact_id');
            $table->index('mother_contact_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('patient_details');
    }
};
