<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chronological nursing/shift notes for an admitted patient (hospital module).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('nursing_notes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('admission_id');
            $table->unsignedInteger('contact_id'); // patient
            $table->enum('shift', ['morning', 'afternoon', 'night'])->nullable();
            $table->enum('note_type', ['observation', 'intervention', 'incident', 'handover', 'general'])->default('general');
            $table->text('note');
            $table->dateTime('noted_at');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->index(['admission_id', 'noted_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('nursing_notes');
    }
};
