<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated billing ledger for a patient encounter. Each clinical/financial
 * event (consultation fee, lab, imaging, pharmacy/ward dispense, procedure,
 * etc.) writes one row, so the admission running bill and (Phase 8) discharge
 * bill can be assembled from a single source.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('visit_charges', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('visit_id')->nullable();
            $table->unsignedBigInteger('admission_id')->nullable();
            $table->unsignedInteger('contact_id'); // patient
            $table->enum('charge_type', [
                'consultation', 'lab', 'imaging', 'pharmacy', 'ward_dispense',
                'procedure', 'registration', 'anc', 'morgue', 'deposit', 'other',
            ])->default('other');
            $table->unsignedBigInteger('reference_id')->nullable(); // e.g. transaction_id
            $table->string('reference_type', 50)->nullable();
            $table->string('description', 255);
            $table->decimal('amount', 22, 4)->default(0);
            $table->decimal('insurance_covered', 22, 4)->default(0);
            $table->decimal('patient_owes', 22, 4)->default(0);
            $table->boolean('is_paid')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
            $table->foreign('contact_id')->references('id')->on('contacts')->onDelete('cascade');
            $table->index('visit_id');
            $table->index('admission_id');
            $table->index('contact_id');
            $table->index(['business_id', 'charge_type']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('visit_charges');
    }
};
