<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bedside vital-sign observations for an admitted patient (hospital module,
 * nursing). One row per observation; charted over the admission for trends.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('nursing_vitals', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('admission_id');
            $table->unsignedInteger('contact_id'); // patient (denormalized)
            $table->dateTime('observed_at');
            $table->decimal('temperature', 5, 2)->nullable(); // °C
            $table->unsignedSmallInteger('pulse')->nullable(); // bpm
            $table->unsignedSmallInteger('respiratory_rate')->nullable(); // /min
            $table->unsignedSmallInteger('bp_systolic')->nullable();
            $table->unsignedSmallInteger('bp_diastolic')->nullable();
            $table->unsignedSmallInteger('spo2')->nullable(); // %
            $table->unsignedSmallInteger('pain_score')->nullable(); // 0-10
            $table->decimal('weight', 8, 2)->nullable(); // kg
            $table->unsignedSmallInteger('blood_sugar')->nullable(); // mg/dL
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->index(['admission_id', 'observed_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('nursing_vitals');
    }
};
