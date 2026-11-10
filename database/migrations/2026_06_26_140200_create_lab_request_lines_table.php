<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One requested test on a lab request, with its captured result.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('lab_request_lines', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('lab_request_id');
            $table->unsignedBigInteger('lab_test_id');
            $table->string('test_name', 191); // snapshot of name at request time
            $table->string('normal_range', 191)->nullable();
            $table->string('unit', 50)->nullable();
            $table->text('result_value')->nullable();
            $table->enum('result_flag', ['normal', 'high', 'low', 'critical'])->nullable();
            $table->text('result_notes')->nullable();
            $table->unsignedBigInteger('resulted_by')->nullable();
            $table->timestamp('resulted_at')->nullable();
            $table->decimal('fee', 22, 4)->default(0);
            $table->timestamps();

            $table->foreign('lab_request_id')->references('id')->on('lab_requests')->onDelete('cascade');
            $table->index('lab_request_id');
            $table->index('lab_test_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('lab_request_lines');
    }
};
