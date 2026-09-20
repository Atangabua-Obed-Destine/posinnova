<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue of laboratory tests a hospital offers, with reference ranges and
 * fees. Managed by lab staff; referenced by lab requests.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('name', 191);
            $table->string('code', 50)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('normal_range', 191)->nullable();
            $table->string('unit', 50)->nullable();
            $table->decimal('fee', 22, 4)->default(0);
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
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
        Schema::dropIfExists('lab_tests');
    }
};
