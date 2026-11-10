<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A request for an imaging study, raised by a doctor (optionally from a
 * consultation) and reported by a radiologist.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('imaging_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->unsignedInteger('contact_id'); // patient
            $table->unsignedBigInteger('imaging_catalogue_id');
            $table->string('study_name', 191); // snapshot at request time
            $table->unsignedBigInteger('requested_by');
            $table->unsignedInteger('location_id')->nullable();
            $table->string('ref_no', 50)->nullable();
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->text('clinical_notes')->nullable();
            $table->text('report')->nullable();
            $table->string('image_path', 191)->nullable();
            $table->unsignedBigInteger('reported_by')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->decimal('fee', 22, 4)->default(0);
            $table->timestamp('requested_at')->nullable();
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
        Schema::dropIfExists('imaging_requests');
    }
};
