<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Two permissions for the Inventory Count feature:
     *
     *   inventory_count.view   – list, view past counts
     *   inventory_count.create – run a new physical count session
     */
    public function up()
    {
        $perms = [
            'inventory_count.view',
            'inventory_count.create',
        ];

        foreach ($perms as $name) {
            $exists = Permission::where('name', $name)->where('guard_name', 'web')->exists();
            if (! $exists) {
                Permission::create([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
            }
        }
    }

    public function down()
    {
        Permission::whereIn('name', [
            'inventory_count.view',
            'inventory_count.create',
        ])->where('guard_name', 'web')->delete();
    }
};
