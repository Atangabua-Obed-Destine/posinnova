<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand transactions.adjustment_type ENUM to allow physical inventory
     * recount shortages (created by the dedicated Inventory Count screen).
     *
     * Existing values:
     *   - normal   : Stock Adjustment caused by normal business operations
     *   - abnormal : Damage / theft / expiry (loss to be recovered)
     *
     * New value:
     *   - recount_shortage : Physical count revealed missing units (no $ recovery)
     */
    public function up()
    {
        DB::statement("ALTER TABLE `transactions` CHANGE `adjustment_type` `adjustment_type` ENUM('normal','abnormal','recount_shortage') DEFAULT NULL");
    }

    public function down()
    {
        // Convert any new value back to 'abnormal' so the ENUM shrink succeeds.
        DB::statement("UPDATE `transactions` SET `adjustment_type` = 'abnormal' WHERE `adjustment_type` = 'recount_shortage'");
        DB::statement("ALTER TABLE `transactions` CHANGE `adjustment_type` `adjustment_type` ENUM('normal','abnormal') DEFAULT NULL");
    }
};
