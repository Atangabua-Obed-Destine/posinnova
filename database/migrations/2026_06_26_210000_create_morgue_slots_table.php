<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storage slots (chambers/bays) in the morgue (hospital module).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('morgue_slots', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->string('slot_number', 50);
            $table->string('description', 191)->nullable();
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->index(['business_id', 'status']);
            $table->unique(['business_id', 'slot_number']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('morgue_slots');
    }
};
