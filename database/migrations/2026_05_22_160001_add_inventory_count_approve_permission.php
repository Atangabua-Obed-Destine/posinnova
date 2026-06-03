<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Phase 5 permission:
 *   inventory_count.approve  – approve/reject a session that has been submitted
 *
 * Auto-granted to anyone who currently has inventory_count.post so single-
 * operator businesses are not broken (they can self-approve). For multi-staff
 * shops the admin should remove .approve from counter roles to enforce
 * segregation of duties.
 */
return new class extends Migration
{
    public function up()
    {
        $name = 'inventory_count.approve';
        $exists = Permission::where('name', $name)->where('guard_name', 'web')->exists();
        if (! $exists) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        $post_perm = Permission::where('name', 'inventory_count.post')->where('guard_name', 'web')->first();
        $approve_perm = Permission::where('name', $name)->where('guard_name', 'web')->first();
        if ($post_perm && $approve_perm) {
            $role_ids = \DB::table('role_has_permissions')
                ->where('permission_id', $post_perm->id)
                ->pluck('role_id');

            foreach ($role_ids as $rid) {
                $already = \DB::table('role_has_permissions')
                    ->where('role_id', $rid)
                    ->where('permission_id', $approve_perm->id)
                    ->exists();
                if (! $already) {
                    \DB::table('role_has_permissions')->insert([
                        'role_id' => $rid,
                        'permission_id' => $approve_perm->id,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        Permission::where('name', 'inventory_count.approve')
            ->where('guard_name', 'web')
            ->delete();
    }
};
