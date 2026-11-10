<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: one counter row per (business, ref_type).
 *
 * setAndGetReferenceCount() creates the row on first use. Without this index
 * two concurrent first-uses create two counter rows for the same type, and the
 * row-level lock added in Util can then lock different rows and still hand out
 * the same number. The index makes the second create fail so the caller falls
 * back to locking the row that won.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('reference_counts', function (Blueprint $table) {
            $table->unique(['business_id', 'ref_type'], 'reference_counts_business_ref_type_unique');
        });
    }

    public function down()
    {
        Schema::table('reference_counts', function (Blueprint $table) {
            $table->dropUnique('reference_counts_business_ref_type_unique');
        });
    }
};
