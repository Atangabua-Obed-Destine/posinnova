<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/** The accounting permissions (paxhitestbed's names), see App\Ledger\Permissions. */
return new class extends Migration
{
    public function up()
    {
        $now = now();
        foreach (\App\Ledger\Permissions::all() as $perm) {
            if (! Permission::where('name', $perm)->where('guard_name', 'web')->exists()) {
                Permission::insert(['name' => $perm, 'guard_name' => 'web', 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down()
    {
        Permission::whereIn('name', \App\Ledger\Permissions::all())->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
