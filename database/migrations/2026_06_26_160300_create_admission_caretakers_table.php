<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Caretaker(s) physically attending an admitted patient. Distinct from the
 * patient's permanent next-of-kin; multiple allowed, one marked primary.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('admission_caretakers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('admission_id');
            $table->string('name', 191);
            $table->string('phone', 50)->nullable();
            $table->string('relationship', 100)->nullable();
            $table->boolean('is_primary')->default(0);
            $table->timestamps();

            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->index('admission_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('admission_caretakers');
    }
};
