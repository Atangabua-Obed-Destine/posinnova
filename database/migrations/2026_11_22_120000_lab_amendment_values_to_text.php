<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PosInnova: a lab result can now be several lines (findings written as points), so the old and new
 * values kept with a correction must hold them too (they were 255 characters).
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('lab_result_amendments')) {
            DB::statement('ALTER TABLE lab_result_amendments MODIFY old_value TEXT NULL, MODIFY new_value TEXT NULL');
        }
    }

    public function down()
    {
        // left as text: shortening the columns again could cut corrections already recorded
    }
};
