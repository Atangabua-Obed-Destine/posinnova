<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request for one or more lab tests, raised by a doctor (optionally from a
 * consultation) and processed by the lab. Each requested test is a line.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->unsignedInteger('contact_id'); // patient
            $table->unsignedBigInteger('requested_by');
            $table->unsignedInteger('location_id')->nullable(); // lab location
            $table->string('ref_no', 50)->nullable();
            $table->enum('status', ['pending', 'sample_collected', 'processing', 'resulted', 'validated'])->default('pending');
            $table->text('clinical_notes')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('resulted_at')->nullable();
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->index('visit_id');
            $table->index('contact_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_requests');
    }
};
