<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * PosInnova clinic phase 5: insurance as money the insurer owes.
 *
 * Until now the insurer's share of a charge was booked as a payment the moment
 * the charge was made, so nobody could see what each insurer still owed. Now:
 *
 *  - clinic_insurers: each insurer, backed by its own customer account
 *    (contact), so what it owes is an ordinary customer balance;
 *  - clinic_insurance_shares: one row per insured charge, linking the
 *    patient's invoice (their part) to the insurer's invoice (its part);
 *  - clinic_claims: the batches sent to an insurer; its payment is recorded
 *    against the claim, and what it refuses is billed to the patient.
 *
 * Patients whose insurer was typed as free text get a real insurer made from
 * that name, so their cover keeps working.
 */
return new class extends Migration
{
    public function up()
    {
        // a failed earlier run can leave new, empty tables behind
        foreach (['clinic_insurance_shares', 'clinic_claims', 'clinic_insurers'] as $t) {
            if (Schema::hasTable($t) && DB::table($t)->count() == 0) {
                Schema::drop($t);
            }
        }

        Schema::create('clinic_insurers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedInteger('contact_id');
            $table->string('name', 191);
            $table->decimal('default_pct', 5, 2)->nullable();
            $table->string('phone', 60)->nullable();
            $table->string('email', 191)->nullable();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(1);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'is_active']);
            $table->index('contact_id');
        });

        Schema::create('clinic_claims', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('insurer_id');
            $table->string('ref_no', 40);
            $table->date('date_from');
            $table->date('date_to');
            $table->enum('status', ['draft', 'submitted'])->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('submitted_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'insurer_id', 'status'], 'cc_business_insurer_status_index');
        });

        Schema::create('clinic_insurance_shares', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('business_id');
            $table->unsignedBigInteger('insurer_id');
            $table->unsignedInteger('contact_id');                        // the patient
            $table->unsignedInteger('visit_id')->nullable();
            $table->unsignedInteger('patient_transaction_id');             // the patient's part
            $table->unsignedInteger('insurer_transaction_id')->nullable(); // the insurer's part (null once wholly refused)
            $table->decimal('pct', 5, 2);
            $table->decimal('full_amount', 22, 4);
            $table->decimal('amount', 22, 4);                              // insurer share as charged
            $table->text('lines')->nullable();                             // JSON: what was done, for the claim form
            $table->unsignedBigInteger('claim_id')->nullable();
            $table->decimal('rebilled_amount', 22, 4)->default(0);         // refused by the insurer, billed to the patient
            $table->unsignedInteger('rebill_transaction_id')->nullable();
            $table->string('rebill_reason', 191)->nullable();
            $table->timestamp('rebilled_at')->nullable();
            $table->unsignedInteger('rebilled_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'insurer_id', 'claim_id'], 'cis_business_insurer_claim_index');
            $table->index('patient_transaction_id', 'cis_patient_tx_index');
            $table->index('insurer_transaction_id', 'cis_insurer_tx_index');
            $table->index('contact_id', 'cis_contact_index');
        });

        if (! Schema::hasColumn('patient_details', 'insurer_id')) {
            Schema::table('patient_details', function (Blueprint $table) {
                $table->unsignedBigInteger('insurer_id')->nullable()->after('insurance_provider');
                $table->index('insurer_id');
            });
        }

        if (! Permission::where('name', 'clinic_insurance.manage')->where('guard_name', 'web')->exists()) {
            Permission::create(['name' => 'clinic_insurance.manage', 'guard_name' => 'web']);
        }
        app()['cache']->forget('spatie.permission.cache');

        // free-text insurers become real ones
        $rows = DB::table('patient_details')
            ->whereNull('insurer_id')
            ->whereNotNull('insurance_provider')
            ->where('insurance_provider', '!=', '')
            ->select('business_id', 'insurance_provider')
            ->distinct()
            ->get();
        foreach ($rows as $row) {
            $owner = DB::table('business')->where('id', $row->business_id)->value('owner_id');
            if (empty($owner)) {
                continue;
            }
            $insurer = \App\Utils\ClinicInsurance::createInsurer($row->business_id, ['name' => trim($row->insurance_provider)], $owner);
            DB::table('patient_details')
                ->where('business_id', $row->business_id)
                ->whereNull('insurer_id')
                ->where('insurance_provider', $row->insurance_provider)
                ->update(['insurer_id' => $insurer->id]);
        }
    }

    public function down()
    {
        if (Schema::hasColumn('patient_details', 'insurer_id')) {
            Schema::table('patient_details', function (Blueprint $table) {
                $table->dropIndex(['insurer_id']);
                $table->dropColumn('insurer_id');
            });
        }
        Schema::dropIfExists('clinic_insurance_shares');
        Schema::dropIfExists('clinic_claims');
        Schema::dropIfExists('clinic_insurers');
        Permission::where('name', 'clinic_insurance.manage')->delete();
        app()['cache']->forget('spatie.permission.cache');
    }
};
