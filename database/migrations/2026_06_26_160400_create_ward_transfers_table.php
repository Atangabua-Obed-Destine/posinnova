<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record of a patient being moved between wards/beds during an admission.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('ward_transfers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('admission_id');
            $table->unsignedBigInteger('from_ward_id')->nullable();
            $table->unsignedBigInteger('to_ward_id');
            $table->unsignedBigInteger('from_bed_id')->nullable();
            $table->unsignedBigInteger('to_bed_id')->nullable();
            $table->unsignedBigInteger('transferred_by');
            $table->dateTime('transfer_date');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->index('admission_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ward_transfers');
    }
};
