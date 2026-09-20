<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A theatre booking / operative record (hospital module). Schedules a procedure
 * for a patient and captures pre-op / intra-op / post-op notes through its
 * lifecycle.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('theatre_bookings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->unsignedInteger('contact_id'); // patient
            $table->unsignedBigInteger('procedure_id');
            $table->string('procedure_name', 191); // snapshot
            $table->unsignedBigInteger('surgeon_id')->nullable();
            $table->unsignedBigInteger('anaesthetist_id')->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->text('preop_notes')->nullable();
            $table->text('intraop_notes')->nullable();
            $table->text('postop_notes')->nullable();
            $table->decimal('fee', 22, 4)->default(0);
            $table->decimal('anaesthesia_fee', 22, 4)->default(0);
            $table->boolean('fee_charged')->default(0);
            $table->string('ref_no', 50)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->index('contact_id');
            $table->index('scheduled_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('theatre_bookings');
    }
};
