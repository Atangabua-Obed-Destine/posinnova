<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single antenatal (ANC) visit within a pregnancy record.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('antenatal_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('antenatal_record_id');
            $table->unsignedSmallInteger('visit_number')->nullable(); // 1..8
            $table->date('visit_date');
            $table->unsignedBigInteger('attended_by')->nullable();
            $table->unsignedSmallInteger('gestational_age_weeks')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
            $table->decimal('fundal_height', 5, 2)->nullable();
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->string('presentation', 100)->nullable();
            $table->text('complications')->nullable();
            $table->date('next_visit_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('fee', 22, 4)->default(0);
            $table->timestamps();

            $table->foreign('antenatal_record_id')->references('id')->on('antenatal_records')->onDelete('cascade');
            $table->index('antenatal_record_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('antenatal_visits');
    }
};
