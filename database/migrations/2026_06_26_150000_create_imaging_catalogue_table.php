<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of imaging studies a hospital offers (X-ray, ultrasound, CT, etc.)
 * with their fees. Referenced by imaging requests.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('imaging_catalogue', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('name', 191);
            $table->string('code', 50)->nullable();
            $table->string('modality', 50)->nullable(); // X-Ray, Ultrasound, CT, MRI, Echo
            $table->decimal('fee', 22, 4)->default(0);
            $table->boolean('is_active')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->index(['business_id', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('imaging_catalogue');
    }
};
