<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pregnancy record for a patient (hospital module, maternity). Holds the
 * pregnancy-level data; individual ANC visits live in antenatal_visits.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('antenatal_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id'); // mother (patient)
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->string('ref_no', 50)->nullable();
            $table->date('lmp')->nullable(); // last menstrual period
            $table->date('edd')->nullable(); // estimated due date (auto from LMP)
            $table->unsignedSmallInteger('gravida')->nullable();
            $table->unsignedSmallInteger('para')->nullable();
            $table->string('blood_group_mother', 10)->nullable();
            $table->string('blood_group_father', 10)->nullable();
            $table->enum('hiv_status', ['positive', 'negative', 'unknown'])->nullable();
            $table->enum('status', ['active', 'delivered', 'terminated'])->default('active');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->index('contact_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('antenatal_records');
    }
};
