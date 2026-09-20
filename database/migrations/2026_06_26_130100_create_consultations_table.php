<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A consultation is a clinical event within a visit: a nurse (general) or a
 * doctor (specialist) records vitals, complaint, examination, diagnosis and
 * may write a prescription / lab request / referral / admission request.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('visit_id');
            $table->unsignedInteger('contact_id'); // patient (denormalized)
            $table->unsignedBigInteger('consulted_by'); // doctor/nurse user
            $table->enum('consultation_type', ['general', 'specialist'])->default('general');
            $table->string('specialty', 100)->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->string('ref_no', 50)->nullable();

            $table->text('chief_complaint')->nullable();

            // Vitals
            $table->decimal('vitals_weight', 8, 2)->nullable();
            $table->decimal('vitals_height', 8, 2)->nullable();
            $table->unsignedSmallInteger('vitals_bp_systolic')->nullable();
            $table->unsignedSmallInteger('vitals_bp_diastolic')->nullable();
            $table->decimal('vitals_temperature', 5, 2)->nullable();
            $table->unsignedSmallInteger('vitals_pulse')->nullable();
            $table->unsignedSmallInteger('vitals_spo2')->nullable();

            $table->text('examination_notes')->nullable();
            $table->text('diagnosis')->nullable();
            $table->unsignedBigInteger('referral_to')->nullable(); // department id
            $table->boolean('admission_requested')->default(0);
            $table->enum('status', ['draft', 'final'])->default('draft');
            $table->decimal('consultation_fee', 22, 4)->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('visit_id')->references('id')->on('visits')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->index('visit_id');
            $table->index('contact_id');
            $table->index('consulted_by');
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultations');
    }
};
