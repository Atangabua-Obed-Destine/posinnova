<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A morgue intake record (hospital module). Handles both deceased hospital
 * patients (linked via contact_id) and external bodies (deceased_name only).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('morgue_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('morgue_slot_id')->nullable();
            $table->unsignedInteger('contact_id')->nullable(); // if a hospital patient
            $table->string('deceased_name', 191);
            $table->unsignedSmallInteger('deceased_age')->nullable();
            $table->enum('deceased_sex', ['M', 'F', 'Unknown'])->nullable();
            $table->dateTime('date_of_death');
            $table->text('cause_of_death')->nullable();
            $table->unsignedBigInteger('certified_by')->nullable();
            $table->enum('source', ['hospital', 'external'])->default('hospital');
            $table->string('family_contact_name', 191)->nullable();
            $table->string('family_contact_phone', 50)->nullable();
            $table->string('family_relationship', 100)->nullable();
            $table->boolean('legal_hold')->default(0);
            $table->text('legal_hold_reason')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('released_at')->nullable();
            $table->string('released_to', 191)->nullable();
            $table->string('release_authorized_by', 191)->nullable();
            $table->enum('status', ['in_storage', 'released', 'transferred'])->default('in_storage');
            $table->decimal('daily_storage_fee', 22, 4)->default(0);
            $table->decimal('preparation_fee', 22, 4)->default(0);
            $table->decimal('total_fee_charged', 22, 4)->default(0);
            $table->string('ref_no', 50)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->index('date_of_death');
        });
    }

    public function down()
    {
        Schema::dropIfExists('morgue_records');
    }
};
