<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: a doctor can prescribe a medicine the clinic does not stock by typing its name. Such a line
 * has no product (product_id / variation_id empty), keeps the typed name in drug_name, and is "to buy at
 * another pharmacy" from the start.
 */
return new class extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('consultation_prescriptions', 'drug_name')) {
            DB::statement('ALTER TABLE consultation_prescriptions ADD drug_name VARCHAR(191) NULL AFTER variation_id');
        }
        DB::statement('ALTER TABLE consultation_prescriptions MODIFY product_id INT UNSIGNED NULL');
        DB::statement('ALTER TABLE consultation_prescriptions MODIFY variation_id INT UNSIGNED NULL');
    }

    public function down()
    {
        // a typed medicine has no product: those lines cannot go back under NOT NULL
        DB::table('consultation_prescriptions')->whereNull('variation_id')->delete();
        DB::statement('ALTER TABLE consultation_prescriptions MODIFY product_id INT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE consultation_prescriptions MODIFY variation_id INT UNSIGNED NOT NULL');
        if (Schema::hasColumn('consultation_prescriptions', 'drug_name')) {
            DB::statement('ALTER TABLE consultation_prescriptions DROP COLUMN drug_name');
        }
    }
};
