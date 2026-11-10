<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Beds within a ward. Status tracks availability; the occupying admission is
 * resolved via the admissions table (bed_id), not stored here, to avoid drift.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('beds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('ward_id');
            $table->string('bed_number', 50);
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade');
            $table->index('ward_id');
            $table->unique(['ward_id', 'bed_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('beds');
    }
};
