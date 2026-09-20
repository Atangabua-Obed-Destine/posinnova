<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of theatre/clinical procedures with their fees (hospital module).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('procedures', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('name', 191);
            $table->enum('category', ['surgery', 'obstetric', 'dental', 'other'])->default('surgery');
            $table->decimal('fee', 22, 4)->default(0);
            $table->decimal('anaesthesia_fee', 22, 4)->default(0);
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
        Schema::dropIfExists('procedures');
    }
};
