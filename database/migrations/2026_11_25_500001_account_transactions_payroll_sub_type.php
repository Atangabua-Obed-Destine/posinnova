<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PosInnova payroll: a salary paid from a payment account is a movement of its own kind
 * (sub_type 'payroll'), so the ledger sync leaves it to the payroll's own entry and the account
 * book neither edits nor deletes it. The column was an enum without it (a non-strict server
 * stored '' instead).
 */
return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE account_transactions MODIFY sub_type ENUM('opening_balance','fund_transfer','deposit','payroll') NULL DEFAULT NULL");
    }

    public function down()
    {
        DB::table('account_transactions')->where('sub_type', 'payroll')->update(['sub_type' => null]);
        DB::statement("ALTER TABLE account_transactions MODIFY sub_type ENUM('opening_balance','fund_transfer','deposit') NULL DEFAULT NULL");
    }
};
