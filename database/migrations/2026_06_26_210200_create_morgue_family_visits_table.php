<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log of family visits to a body in the morgue (hospital module).
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('morgue_family_visits', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('morgue_record_id');
            $table->string('visitor_name', 191);
            $table->string('visitor_phone', 50)->nullable();
            $table->string('relationship', 100)->nullable();
            $table->dateTime('visited_at');
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('morgue_record_id')->references('id')->on('morgue_records')->onDelete('cascade');
            $table->index('morgue_record_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('morgue_family_visits');
    }
};
