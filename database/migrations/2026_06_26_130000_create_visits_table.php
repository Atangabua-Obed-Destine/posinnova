<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A visit is one patient encounter (OPD same-day, or inpatient).
 * It is the container that ties together consultations, lab requests,
 * pharmacy dispenses and (later) admissions for a single attendance.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id'); // patient
            $table->unsignedInteger('location_id')->nullable();
            $table->enum('type', ['opd', 'inpatient'])->default('opd');
            $table->enum('status', ['open', 'admitted', 'closed', 'cancelled'])->default('open');
            $table->date('visit_date');
            $table->string('ref_no', 50)->nullable();
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'visit_date']);
            $table->index('contact_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('visits');
    }
};
