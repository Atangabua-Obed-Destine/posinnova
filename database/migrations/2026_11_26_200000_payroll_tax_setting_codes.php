<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova payroll: a code on the taxes the system set up (cm_pension, cm_family, cm_accident …),
 * so the ones that depend on the type of business (CNPS family allowances, work accident) can be
 * found and set again when the type changes, whatever their title has become.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_tax_settings', function (Blueprint $t) {
            $t->string('code', 30)->nullable()->after('tax_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_tax_settings', fn (Blueprint $t) => $t->dropColumn('code'));
    }
};
