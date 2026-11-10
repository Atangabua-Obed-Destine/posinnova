<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A delivery record (hospital module, maternity). Captures the birth and links
 * to the auto-created baby patient contact.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('delivery_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('antenatal_record_id')->nullable();
            $table->unsignedInteger('mother_contact_id');
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->string('ref_no', 50)->nullable();
            $table->dateTime('delivery_date');
            $table->enum('delivery_type', ['normal', 'cs', 'forceps', 'vacuum', 'breech'])->default('normal');
            $table->unsignedBigInteger('attended_by')->nullable();
            $table->enum('baby_sex', ['M', 'F', 'Unknown'])->default('Unknown');
            $table->decimal('baby_weight', 8, 2)->nullable(); // kg
            $table->unsignedTinyInteger('apgar_1min')->nullable();
            $table->unsignedTinyInteger('apgar_5min')->nullable();
            $table->text('complications')->nullable();
            $table->unsignedInteger('baby_contact_id')->nullable(); // auto-created baby patient
            $table->text('notes')->nullable();
            $table->decimal('fee', 22, 4)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('mother_contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index(['business_id', 'delivery_date']);
            $table->index('mother_contact_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('delivery_records');
    }
};
