<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Phase 2 permissions split:
 *   inventory_count.post    – finalise/post a session (separate from create)
 *   inventory_count.cancel  – cancel a draft session
 *
 * Existing roles that already had inventory_count.create are auto-granted
 * .post and .cancel for backward compatibility (single-operator businesses).
 */
return new class extends Migration
{
    public function up()
    {
        $new_perms = [
            'inventory_count.post',
            'inventory_count.cancel',
        ];

        foreach ($new_perms as $name) {
            $exists = Permission::where('name', $name)->where('guard_name', 'web')->exists();
            if (! $exists) {
                Permission::create(['name' => $name, 'guard_name' => 'web']);
            }
        }

        // Auto-grant .post / .cancel to anyone who currently has .create
        $create_perm = Permission::where('name', 'inventory_count.create')->where('guard_name', 'web')->first();
        if ($create_perm) {
            $role_ids = \DB::table('role_has_permissions')
                ->where('permission_id', $create_perm->id)
                ->pluck('role_id');

            foreach ($new_perms as $name) {
                $perm = Permission::where('name', $name)->where('guard_name', 'web')->first();
                if (! $perm) continue;
                foreach ($role_ids as $rid) {
                    $already = \DB::table('role_has_permissions')
                        ->where('role_id', $rid)
                        ->where('permission_id', $perm->id)
                        ->exists();
                    if (! $already) {
                        \DB::table('role_has_permissions')->insert([
                            'role_id' => $rid,
                            'permission_id' => $perm->id,
                        ]);
                    }
                }
            }
        }
    }

    public function down()
    {
        Permission::whereIn('name', ['inventory_count.post', 'inventory_count.cancel'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
