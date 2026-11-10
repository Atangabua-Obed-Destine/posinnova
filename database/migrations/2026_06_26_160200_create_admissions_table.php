<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An inpatient admission: patient assigned to a ward/bed, with a running bill
 * accruing until discharge.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedInteger('contact_id'); // patient
            $table->unsignedBigInteger('ward_id');
            $table->unsignedBigInteger('bed_id')->nullable();
            $table->unsignedBigInteger('admitted_by');
            $table->unsignedBigInteger('admitting_doctor_id')->nullable();
            $table->dateTime('admission_date');
            $table->dateTime('discharge_date')->nullable();
            $table->unsignedBigInteger('discharge_authorized_by')->nullable();
            $table->text('reason_for_admission')->nullable();
            $table->decimal('deposit_amount', 22, 4)->default(0);
            $table->enum('status', ['active', 'discharged', 'transferred', 'absconded'])->default('active');
            $table->text('discharge_notes')->nullable();
            $table->string('ref_no', 50)->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->index('ward_id');
            $table->index('bed_id');
            $table->index('contact_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admissions');
    }
};
