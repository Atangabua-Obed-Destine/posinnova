<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A line on a consultation's prescription. Links to a pharmacy product/variation
 * so the pharmacist can later dispense it directly into the POS (Phase 3).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('consultation_prescriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('consultation_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('variation_id');
            $table->string('dosage', 100)->nullable();
            $table->string('frequency', 100)->nullable();
            $table->string('duration', 100)->nullable();
            $table->decimal('quantity', 22, 4)->default(1);
            $table->text('notes')->nullable();
            $table->boolean('is_dispensed')->default(0);
            $table->timestamp('dispensed_at')->nullable();
            $table->unsignedBigInteger('dispensed_by')->nullable();
            $table->timestamps();

            $table->foreign('consultation_id')->references('id')->on('consultations')->onDelete('cascade');
            $table->index('consultation_id');
            $table->index(['variation_id', 'is_dispensed']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('consultation_prescriptions');
    }
};
