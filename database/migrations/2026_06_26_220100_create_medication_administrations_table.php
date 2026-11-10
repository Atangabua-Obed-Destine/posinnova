<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medication Administration Record (MAR) entries (hospital module, nursing).
 * Each row is a single administration of a drug to an admitted patient, usually
 * linked to a doctor's prescription line. Status covers given / refused / held /
 * missed for an honest clinical record.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('admission_id');
            $table->unsignedInteger('contact_id'); // patient
            $table->unsignedBigInteger('consultation_prescription_id')->nullable(); // source Rx line
            $table->unsignedInteger('product_id')->nullable();
            $table->string('drug_name', 191);
            $table->string('dose', 100)->nullable();
            $table->string('route', 50)->nullable(); // PO, IV, IM…
            $table->dateTime('administered_at');
            $table->enum('status', ['given', 'refused', 'held', 'missed'])->default('given');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('administered_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('admission_id')->references('id')->on('admissions')->onDelete('cascade');
            $table->index(['admission_id', 'administered_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('medication_administrations');
    }
};
