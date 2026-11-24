<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes what the UltimatePOS Accounting module left in the database.
 * The module's code is gone; its books are replaced by the PosInnova ledger
 * (design shared with the paxhitestbed school ERP).
 *
 * On production the module was never used: one default chart of accounts and
 * one test sale on business 1 (January 2025). A full backup is taken before
 * this runs, so down() does not rebuild anything.
 */
return new class extends Migration
{
    private $tables = [
        'accounting_accounts_transactions',
        'accounting_acc_trans_mappings',
        'accounting_budgets',
        'accounting_accounts',
        'accounting_account_types',
    ];

    private $migrations = [
        '2022_10_31_115725_create_accounting_account_types_table',
        '2022_11_01_104108_create_accounting_accounts_table',
        '2022_11_10_135427_create_accounts_transactions_table',
        '2022_11_14_171948_create_accounting_acc_trans_mapping',
        '2022_12_02_133536_create_accounting_budgets_table',
        '2022_12_19_124743_add_accounting_settings_column_to_business_table',
        '2022_12_26_182821_AddAccountingModuleVersionToSystemTable',
        '2023_05_25_131005_add_default_map_transactions_fields',
    ];

    public function up()
    {
        Schema::disableForeignKeyConstraints();
        foreach ($this->tables as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();

        if (Schema::hasColumn('business', 'accounting_settings')) {
            Schema::table('business', function ($table) {
                $table->dropColumn('accounting_settings');
            });
        }
        if (Schema::hasColumn('business_locations', 'accounting_default_map')) {
            Schema::table('business_locations', function ($table) {
                $table->dropColumn('accounting_default_map');
            });
        }

        if (Schema::hasTable('system')) {
            DB::table('system')->where('key', 'accounting_version')->delete();
        }

        if (Schema::hasTable('permissions')) {
            $ids = DB::table('permissions')->where('name', 'like', 'accounting.%')->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
                if (Schema::hasTable('model_has_permissions')) {
                    DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
                }
                DB::table('permissions')->whereIn('id', $ids)->delete();
            }
        }

        // Superadmin packages carried an 'accounting_module' switch.
        if (Schema::hasTable('packages') && Schema::hasColumn('packages', 'custom_permissions')) {
            foreach (DB::table('packages')->whereNotNull('custom_permissions')->get(['id', 'custom_permissions']) as $p) {
                $perms = json_decode($p->custom_permissions, true);
                if (is_array($perms) && array_key_exists('accounting_module', $perms)) {
                    unset($perms['accounting_module']);
                    DB::table('packages')->where('id', $p->id)->update(['custom_permissions' => json_encode($perms)]);
                }
            }
        }

        DB::table('migrations')->whereIn('migration', $this->migrations)->delete();

        try {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // permission cache is rebuilt on the next request anyway
        }
    }

    public function down()
    {
        // Nothing to rebuild: the module is removed for good (restore the backup if ever needed).
    }
};
